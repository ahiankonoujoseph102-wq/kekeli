<?php

namespace App\Jobs;

use App\Agent\AnswerModels;
use App\Agent\FirstResponder;
use App\Agent\Markdown;
use App\Agent\RunnerEventHandler;
use App\Agent\RunReport;
use App\Memory\UserMemory;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Models\Message;
use App\Support\Connectors;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A message of the client goes to the first responder (the model, without a computer). It answers a question itself,
 * calls a tool (create or change the site: the usual work starts with the client's message), or asks one short
 * question. The requests and tokens are counted like any request (energy, providers): a question costs very little.
 *
 * When every provider is busy, the message waits and is tried again by itself, like a request in waiting.
 */
class RespondToMessage implements ShouldQueue
{
    use Queueable;

    /** Tries of the first responder when every provider is busy (one every few minutes). */
    public const ATTEMPTS = 6;

    public const RETRY_MINUTES = 5;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public AgentRun $run, public int $attempt = 1) {}

    public function handle(FirstResponder $responder, RunnerEventHandler $events): void
    {
        $run = $this->run->fresh(['project', 'message']);
        if (! $run || ! in_array($run->status, [AgentRun::STATUS_QUEUED, AgentRun::STATUS_WAITING], true)) {
            return;
        }

        // Un connecteur actif et le plafond OpenAI du jour atteint : la demande attend (« Reprise à … »), jamais un
        // modèle gratuit avec les données du client.
        if (app(Connectors::class)->activeIds($run->project->user, $run->project) !== [] && app(AnswerModels::class)->connectorCapReached($run->project->user)) {
            $run->update(['status' => AgentRun::STATUS_STOPPED, 'stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_MODEL_CAP, 'finished_at' => now(), 'resume_at' => null]);

            return;
        }

        $events->handle($run, ['type' => 'demarrage']);

        try {
            $result = $responder->decide($run);
        } catch (\Throwable $exception) {
            report($exception);
            $result = ['ok' => false];
        }

        foreach ((array) ($result['refus'] ?? []) as $refusal) {
            $events->handle($run, ['type' => 'refus', 'fournisseur' => $refusal['fournisseur'] ?? '', 'modele' => $refusal['modele'] ?? '']);
        }

        if (! ($result['ok'] ?? false)) {
            $this->wait($run->fresh());

            return;
        }

        $this->keepSummary($run, (array) ($result['resume'] ?? []));
        // Mémoire : appliquée avant la réponse ou le travail, pour qu'une création demandée dans le même message
        // profite déjà des informations que le client vient de donner.
        $this->remember($run, (array) ($result['memoire'] ?? []));
        $events->handle($run, ['type' => 'fournisseur', 'fournisseur' => $result['fournisseur'] ?? '', 'modele' => $result['modele'] ?? '']);
        $events->handle($run, ['type' => 'requete'] + array_intersect_key($result, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache', 'requetes'])));
        $this->countSearches($run, (array) ($result['recherches'] ?? []));
        $connectorsUsed = $this->journal($run, (array) ($result['actions_connecteurs'] ?? []));

        // Le client a touché « Arrêter » pendant que Kekeli réfléchissait : la réponse n'est pas affichée.
        if (! $run->fresh()?->isActive()) {
            return;
        }

        // Une action qui modifie dans un connecteur : Kekeli demande l'accord du client, comme Claude.
        if (($result['decision'] ?? null) === 'accord' && is_array($result['accord'] ?? null)) {
            $this->askApproval($run, (array) $result['accord'], $connectorsUsed);

            return;
        }

        $place = FirstResponder::placeOf($run->project);
        $tool = ($result['decision'] ?? null) === 'outil' ? FirstResponder::tool($place, $result['outil'] ?? null) : null;

        if ($tool) {
            $this->useTool($run->fresh(['project', 'message']), (string) $result['outil'], $tool, array_filter((array) ($result['arguments'] ?? []), 'is_string'));

            return;
        }

        $answer = trim((string) ($result['reponse'] ?? ''));
        if ($answer === '') {
            // Un outil inconnu ou interdit ici, sans texte : jamais exécuté, le client lit une phrase polie.
            $answer = __('Sorry, Kekeli did not understand. Can you say it again in other words?');
        }

        $this->answer($run, $answer, self::sources((array) ($result['sources'] ?? [])), self::searches((array) ($result['recherches'] ?? [])), $place, self::thinking($result['reflexion'] ?? null), $connectorsUsed);
        $this->giveTitle($run, $result['titre'] ?? null);
    }

    /**
     * The summary of the beginning of a long discussion, made by the Runner before answering: kept by the site (never
     * shown to the client), and its cost counted in the energy like the answer.
     *
     * @param  array<string, mixed>  $summary
     */
    private function keepSummary(AgentRun $run, array $summary): void
    {
        $usage = (array) ($summary['consommation'] ?? []);
        if ($usage !== []) {
            app(RunnerEventHandler::class)->handle($run, ['type' => 'requete'] + array_intersect_key($usage, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache', 'requetes'])));
        }
        $text = trim((string) ($summary['texte'] ?? ''));
        $until = (int) ($summary['jusqu_a'] ?? 0);
        if ($text !== '' && $until > (int) $run->project->summary_until_message_id) {
            $run->project->forceFill(['conversation_summary' => mb_substr($text, 0, 2000), 'summary_until_message_id' => $until])->save();
        }
    }

    /**
     * The memories proposed by the Runner for this message: applied (with the site's own lock), and the cost of the
     * extraction counted in the energy like the answer.
     *
     * @param  array<string, mixed>  $memory
     */
    private function remember(AgentRun $run, array $memory): void
    {
        $usage = (array) ($memory['consommation'] ?? []);
        if ((int) ($usage['requetes'] ?? 0) > 0) {
            app(RunnerEventHandler::class)->handle($run, ['type' => 'requete'] + array_intersect_key($usage, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache', 'requetes'])));
        }

        $operations = array_values(array_filter((array) ($memory['operations'] ?? []), 'is_array'));
        $logo = ($memory['logo'] ?? false) === true;
        if ($run->message && ($operations !== [] || $logo)) {
            app(UserMemory::class)->apply($run->project->user, $run->message, $operations, $logo);
        }
    }

    /**
     * The real title of a new discussion, a short summary written by the model with its first answer. It replaces
     * the temporary title only: never a title chosen by the client.
     */
    private function giveTitle(AgentRun $run, mixed $title): void
    {
        $titles = app(ChatTitles::class);
        $title = $titles->fromModel(is_string($title) ? $title : null);
        $project = $run->project->fresh();

        if ($title !== null && $project && $titles->isTemporary($project)) {
            $project->forceFill(['name' => $title])->save();
        }
    }

    /**
     * What Kekeli searched and the sites it read, shown folded under « A cherché sur Internet ».
     *
     * @param  array<int, mixed>  $searches
     * @return list<array{query: string, sites: list<string>}>
     */
    public static function searches(array $searches): array
    {
        return collect($searches)
            ->take(5)
            ->map(fn ($search) => [
                'query' => Str::limit(trim((string) data_get($search, 'requete', '')), 200),
                'sites' => collect((array) data_get($search, 'sites', []))
                    ->filter(fn ($site) => is_string($site) && preg_match('/^[a-z0-9.-]{3,100}$/i', $site))
                    ->unique()->take(8)->values()->all(),
            ])
            ->filter(fn ($search) => $search['query'] !== '')
            ->values()
            ->all();
    }

    /**
     * What is shown folded under « A cherché sur Internet · N sources » for an answer: the searches done, the sites
     * read and the number of sources given. Null when Kekeli did not search for this message.
     *
     * @return array{queries: list<string>, sites: list<string>, sources: int}|null
     */
    public static function searchSummary(AgentRun $run): ?array
    {
        $report = (array) $run->report;
        $sources = self::sources((array) ($report['sources'] ?? []));
        $searches = collect((array) ($report['searches'] ?? []));
        $queries = $searches->pluck('query')->filter()->values();
        $sites = $searches->pluck('sites')->flatten()->filter()->unique()->values();

        // Réponses d'avant le 12 novembre 2026 : la recherche est dans les étapes, les sites dans les sources.
        if ($queries->isEmpty()) {
            $queries = $run->steps->where('kind', 'recherche_web')->pluck('target')->filter()->unique()->values();
        }
        if ($sites->isEmpty()) {
            $sites = collect($sources)->map(fn ($source) => preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)))->filter()->unique()->values();
        }

        if ($queries->isEmpty() && ! $run->steps->contains('kind', 'recherche_web')) {
            return null;
        }

        return ['queries' => $queries->take(5)->all(), 'sites' => $sites->take(10)->all(), 'sources' => count($sources)];
    }

    /**
     * Whether an answer ends by offering to create the site (« Voulez-vous que je crée le site… ? »): the client then
     * sees a button « Oui, créer le site » under the question, like the cards of Lovable or Bolt.new.
     */
    public static function offersSite(string $answer): bool
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($answer)) ?: [];
        $last = (string) end($sentences);

        return (bool) preg_match('/\?\s*$/u', $last)
            && (bool) preg_match('/\b(?:cré\w*|fasse|fais|faire|construi\w*|réalis\w*|prépar\w*)\b[^?]*\bsite\b/iu', $last);
    }

    /**
     * The thinking of the model, shown folded above the answer: its length in seconds and a short summary in the
     * client's language. Null when the model did not really think (simple question).
     *
     * @return array{seconds: int, summary: ?string}|null
     */
    public static function thinking(mixed $thinking): ?array
    {
        $seconds = (int) data_get($thinking, 'secondes', 0);
        if (! is_array($thinking) || $seconds < 1) {
            return null;
        }
        $summary = trim(RunReport::clean((string) data_get($thinking, 'resume', '')));

        return ['seconds' => min($seconds, 600), 'summary' => $summary !== '' ? Str::limit($summary, 400) : null];
    }

    /**
     * Searches done on the Internet: counted in the quota of the day and of the month, and in the client's energy.
     *
     * @param  array<int, mixed>  $searches
     */
    private function countSearches(AgentRun $run, array $searches): void
    {
        $count = min(5, count($searches));
        if ($count === 0) {
            return;
        }

        $cost = $count * app(Settings::class)->float('web_search_price_usd');
        AgentRun::query()->whereKey($run->id)->update([
            'web_searches' => DB::raw('web_searches + '.$count),
            'web_search_cost_usd' => DB::raw('web_search_cost_usd + '.number_format($cost, 6, '.', '')),
        ]);
    }

    /**
     * The sources shown under an answer: 5 at most, only http or https addresses, with a short title.
     *
     * @param  array<int, mixed>  $sources
     * @return list<array{title: string, url: string, date: string}>
     */
    public static function sources(array $sources): array
    {
        return collect($sources)
            ->map(fn ($source) => ['title' => Str::limit(trim((string) (data_get($source, 'title') ?? data_get($source, 'titre', ''))), 120), 'url' => trim((string) data_get($source, 'url', '')), 'date' => Str::limit(trim((string) data_get($source, 'date', '')), 30, '')])
            ->filter(fn ($source) => filter_var($source['url'], FILTER_VALIDATE_URL) && in_array(parse_url($source['url'], PHP_URL_SCHEME), ['http', 'https'], true))
            ->map(fn ($source) => ['title' => $source['title'] !== '' ? $source['title'] : (string) parse_url($source['url'], PHP_URL_HOST), 'url' => $source['url'], 'date' => $source['date']])
            ->unique('url')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Kekeli answers in the discussion, without touching any site.
     */
    /**
     * @param  list<array{title: string, url: string, date: string}>  $sources
     * @param  list<array{query: string, sites: list<string>}>  $searches
     */
    private function answer(AgentRun $run, string $answer, array $sources = [], array $searches = [], string $place = FirstResponder::PLACE_SITE, ?array $thinking = null, array $connectors = []): void
    {
        // Le texte garde sa mise en forme (Markdown) : il est affiché de façon sûre par App\Agent\Markdown.
        $answer = Str::limit(Markdown::prepare($answer), 16000);
        DB::transaction(function () use ($run, $answer, $sources, $searches, $place, $thinking, $connectors) {
            $run->project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => $answer,
                'agent_run_id' => $run->id,
            ]);
            $run->project->touch();

            $run->refresh()->forceFill([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'resume_at' => null,
                'report' => ['done' => $answer, 'note' => null, 'question' => true, 'markdown' => true, 'thinking' => $thinking, 'sources' => $sources, 'searches' => $searches, 'offer' => $place === FirstResponder::PLACE_DISCUSSION && self::offersSite($answer) ? 'creer_site' : null, 'connectors' => $connectors, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
            ])->save();
        });
    }

    /**
     * The actions done with the connectors during the answer: written in the journal of the client (Réglages →
     * Connecteurs), and returned for the folded line « A utilisé Notion · 2 actions ».
     *
     * @param  array<int, mixed>  $actions
     * @return list<array{name: string, title: string, writes: bool, status: string}>
     */
    private function journal(AgentRun $run, array $actions): array
    {
        $user = $run->project->user;
        $ids = $user->connectors()->pluck('id')->all();
        $used = [];
        foreach (array_slice($actions, 0, 10) as $action) {
            $connectorId = (int) data_get($action, 'connecteur');
            if (! in_array($connectorId, $ids, true)) {
                continue;
            }
            $entry = ConnectorAction::create([
                'user_id' => $user->id,
                'user_connector_id' => $connectorId,
                'project_id' => $run->project_id,
                'agent_run_id' => $run->id,
                'connector_name' => Str::limit((string) data_get($action, 'nom', ''), 60, ''),
                'tool' => Str::limit((string) data_get($action, 'outil', ''), 128, ''),
                'writes' => (bool) data_get($action, 'ecriture', false),
                'status' => data_get($action, 'statut') === ConnectorAction::FAILED ? ConnectorAction::FAILED : ConnectorAction::DONE,
                'summary' => self::summary(data_get($action, 'resume')),
            ]);
            $used[] = ['name' => $entry->connector_name, 'title' => $entry->title(), 'writes' => $entry->writes, 'status' => $entry->status];
        }

        return $used;
    }

    /**
     * The summary of an action, shown to the client: the action and its main details, as plain bounded texts.
     *
     * @return array{action: string, description: string, details: list<array{nom: string, valeur: string}>}
     */
    public static function summary(mixed $summary): array
    {
        return [
            'action' => Str::limit(trim((string) data_get($summary, 'action', '')), 80),
            'description' => Str::limit(trim((string) data_get($summary, 'description', '')), 300),
            'details' => collect((array) data_get($summary, 'details', []))
                ->map(fn ($d) => ['nom' => Str::limit(trim((string) data_get($d, 'nom', '')), 40), 'valeur' => Str::limit(trim((string) data_get($d, 'valeur', '')), 240)])
                ->filter(fn ($d) => $d['nom'] !== '')->take(6)->values()->all(),
        ];
    }

    /**
     * An action that changes something in a connector: nothing is done yet; the client sees a clear summary with
     * « Autoriser » and « Refuser » (and « Toujours autoriser pour cet outil »).
     *
     * @param  array<string, mixed>  $approval
     * @param  list<array<string, mixed>>  $connectors
     */
    private function askApproval(AgentRun $run, array $approval, array $connectors): void
    {
        $user = $run->project->user;
        $connector = $user->connectors()->find((int) ($approval['connecteur'] ?? 0));
        if (! $connector) {
            $this->answer($run, __('Sorry, Kekeli did not understand. Can you say it again in other words?'), connectors: $connectors);

            return;
        }

        $action = ConnectorAction::create([
            'user_id' => $user->id,
            'user_connector_id' => $connector->id,
            'project_id' => $run->project_id,
            'agent_run_id' => $run->id,
            'connector_name' => $connector->name,
            'tool' => Str::limit((string) ($approval['outil'] ?? ''), 128, ''),
            'writes' => true,
            'status' => ConnectorAction::PENDING,
            'summary' => self::summary($approval['resume'] ?? []),
            'arguments' => is_array($approval['arguments'] ?? null) ? $approval['arguments'] : [],
        ]);

        $run->forceFill([
            'status' => AgentRun::STATUS_COMPLETED,
            'finished_at' => now(),
            'resume_at' => null,
            'report' => ['done' => '', 'note' => null, 'question' => true, 'approval' => $action->id, 'connectors' => $connectors, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();
        $run->project->touch();
    }

    /**
     * The model chose a tool offered in this place: the first responder is done, then the work starts as usual.
     *
     * @param  class-string  $tool
     * @param  array<string, string>  $arguments
     */
    private function useTool(AgentRun $run, string $name, string $tool, array $arguments = []): void
    {
        $run->forceFill([
            'status' => AgentRun::STATUS_COMPLETED,
            'finished_at' => now(),
            'resume_at' => null,
            'report' => ['done' => '', 'note' => null, 'question' => true, 'tool' => $name, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();

        app($tool)->handle($run->fresh(['project', 'message']), $arguments);
    }

    /**
     * Every provider is busy: the message waits and is tried again by itself; after the last try, a polite sentence.
     */
    private function wait(AgentRun $run): void
    {
        if ($this->attempt < self::ATTEMPTS) {
            $resumeAt = CarbonImmutable::now()->addMinutes(self::RETRY_MINUTES);
            $run->update(['status' => AgentRun::STATUS_WAITING, 'resume_at' => $resumeAt]);

            if ($this->attempt === 1) {
                AdminAlert::create([
                    'kind' => 'providers_exhausted',
                    'message' => __('Every AI provider has reached its limit. A request is waiting and will resume around :time (Lomé time).', [
                        'time' => $resumeAt->setTimezone(config('kekeli.display_timezone'))->format('H:i'),
                    ]),
                ]);
            }

            self::dispatch($run, $this->attempt + 1)->delay($resumeAt);

            return;
        }

        $run->forceFill([
            'status' => AgentRun::STATUS_FAILED,
            'finished_at' => now(),
            'resume_at' => null,
            'error' => 'premier répondant : aucun fournisseur disponible',
            'report' => ['done' => '', 'note' => __('Kekeli is in high demand right now and could not answer. Your message is kept: send it again in a little while.'), 'question' => true, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();
    }
}
