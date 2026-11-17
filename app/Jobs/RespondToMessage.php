<?php

namespace App\Jobs;

use App\Agent\AnswerModels;
use App\Agent\Energy;
use App\Agent\Failures;
use App\Agent\FirstResponder;
use App\Agent\Markdown;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\RunReport;
use App\Agent\Timings;
use App\Billing\PlanPerks;
use App\Memory\UserMemory;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Models\Message;
use App\Support\Connectors;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use App\WebApps\SiteNeeds;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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

    /** Minutes before a new try for the free formula; the paid formulas try sooner (PlanPerks::retryMinutes). */
    public const RETRY_MINUTES = 5;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * Secondes avant que la file d'attente réponde à la place de la page : d'habitude, la page ouvre tout de suite la
     * réponse mot par mot (AnswerStreamController) ; la file d'attente ne sert qu'en secours (page fermée, réseau coupé).
     */
    public const STREAM_FALLBACK_SECONDS = 15;

    /** Réponse mot par mot : reçoit chaque morceau de texte et chaque état, seulement quand la page attend la réponse. */
    public ?\Closure $onEvent = null;

    public function __construct(public AgentRun $run, public int $attempt = 1) {}

    /**
     * Une seule réponse par message : la page (en direct) ou la file d'attente (en secours), jamais les deux.
     */
    public function handle(FirstResponder $responder, RunnerEventHandler $events): void
    {
        $lock = Cache::lock('kekeli:reponse:'.$this->run->id, $this->timeout + 30);
        if (! $lock->get()) {
            return;
        }
        try {
            $this->respond($responder, $events);
        } finally {
            $lock->release();
        }
    }

    /**
     * Envoie un événement à la page qui attend la réponse (rien si la réponse se fait dans la file d'attente).
     *
     * @param  array<string, mixed>  $event
     */
    private function emit(array $event): void
    {
        if ($this->onEvent) {
            ($this->onEvent)($event);
        }
    }

    private function respond(FirstResponder $responder, RunnerEventHandler $events): void
    {
        $run = $this->run->fresh(['project', 'message']);
        if (! $run || ! in_array($run->status, [AgentRun::STATUS_QUEUED, AgentRun::STATUS_WAITING], true)) {
            return;
        }
        Timings::mark($run, 'tache_recue');

        // Un connecteur actif et le plafond OpenAI du jour atteint : la demande attend (« Reprise à … »), jamais un
        // modèle gratuit avec les données du client.
        if (app(Connectors::class)->activeIds($run->project->user, $run->project) !== [] && app(AnswerModels::class)->connectorCapReached($run->project->user)) {
            $run->update(['status' => AgentRun::STATUS_STOPPED, 'stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_MODEL_CAP, 'finished_at' => now(), 'resume_at' => null]);

            return;
        }

        $events->handle($run, ['type' => 'demarrage']);
        Timings::mark($run, 'tache_demarree');

        // Discussions simples du jour toutes utilisées : une réponse écrite est remplacée par une phrase courte (le
        // message part quand même au modèle, qui peut lancer une création avec l'énergie) ; rien n'est écrit en direct.
        $chatsUsedUp = app(Energy::class)->simpleChatsLeft($run->project->user) <= 0;

        try {
            Timings::mark($run, 'appel_runner');
            $forward = $this->onEvent ? function (array $event) use ($run, $chatsUsedUp) {
                if ($chatsUsedUp && in_array($event['type'] ?? null, ['texte', 'efface'], true)) {
                    return;
                }
                if (($event['type'] ?? null) === 'texte') {
                    Timings::mark($run, 'premier_mot');
                }
                $this->emit($event);
            } : null;
            $result = $responder->decide($run, $forward);
            Timings::mark($run, 'premier_mot');
            Timings::runner($run, (array) ($result['chronos'] ?? []));
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
        $this->countRace($run, (array) ($result['course'] ?? []));
        $this->countSearches($run, (array) ($result['recherches'] ?? []), (int) ($result['requetes_recherche'] ?? 0));
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
        // Une fonction en avant-première n'est jamais lancée pour une formule qui n'y a pas encore droit.
        if ($tool && ! app(PlanPerks::class)->canUseTool($run->project->user, (string) $result['outil'])) {
            $tool = null;
        }

        // Avant une création, si l'énergie restante ne suffit visiblement pas (moins de la moitié de l'estimation), Kekeli
        // le dit en une phrase et propose de commencer quand même ou de choisir une formule (15 novembre 2026).
        if ($tool && ($warning = $this->energyWarning($run, $place, (string) $result['outil'])) !== null) {
            $run->forceFill(['simple_chat' => true])->save();
            $this->answer($run, $warning, [], [], $place, null, $connectorsUsed, ['tool' => (string) $result['outil'], 'arguments' => array_filter((array) ($result['arguments'] ?? []), 'is_string'), 'reason' => 'energie']);
            Timings::mark($run, 'fin');

            return;
        }

        if ($tool) {
            // Un travail commence : la page l'affiche tout de suite (« Kekeli prépare le travail… »).
            $this->emit(['type' => 'outil']);
            $this->useTool($run->fresh(['project', 'message']), (string) $result['outil'], $tool, array_filter((array) ($result['arguments'] ?? []), 'is_string'));
            Timings::mark($run, 'fin');

            return;
        }

        $answer = trim((string) ($result['reponse'] ?? ''));
        if ($answer === '') {
            // Un outil inconnu ou interdit ici, sans texte : jamais exécuté. Comme Claude Code, le client lit ce qui se passe
            // et ce qu'il peut faire : une création d'un autre type se demande dans une nouvelle discussion ; une fonction
            // en avant-première arrive bientôt dans sa formule ; sinon, une reformulation.
            $wanted = (string) ($result['outil'] ?? '');
            $answer = match (true) {
                ($result['decision'] ?? null) === 'outil' && FirstResponder::tool($place, $wanted) === null && FirstResponder::tool(FirstResponder::PLACE_DISCUSSION, $wanted) !== null => __('Kekeli cannot do this from inside this project: each creation has its own space. Touch « New discussion » and ask it there: Kekeli will take care of it.'),
                ($result['decision'] ?? null) === 'outil' && FirstResponder::tool($place, $wanted) !== null => __('This function is not yet open to your formula: it is in early access for the Expert and Agency formulas. Kekeli can answer in writing in the meantime, or you can see the formulas.'),
                default => __('Kekeli did not understand your request this time. Can you say it again in one sentence, with what you want to get?'),
            };
        }

        // Discussion simple (15 novembre 2026) : aucune recherche, aucun connecteur, aucune création. Elle ne retire
        // rien à l'énergie ; une question de confirmation avant une création en est une aussi.
        $proposal = self::proposal($result['proposition'] ?? null, $place);
        $suggestion = self::suggestion($result['proposition'] ?? null, $place);
        $simple = self::searches((array) ($result['recherches'] ?? [])) === [] && $connectorsUsed === [] && $run->connector_action_id === null;
        $limited = false;
        if ($simple && $chatsUsedUp) {
            // Au-delà du nombre du jour, la discussion compte de nouveau dans l'énergie (garde-fou contre les abus) ;
            // une question de confirmation reste affichée, pour que la création demandée puisse partir.
            $simple = false;
            if ($proposal === null) {
                $limited = true;
                $answer = __('You have used all your discussions for today. They come back at :time. You can still create with your energy.', ['time' => app(Energy::class)->simpleChatsResetText()]);
            }
        }
        if ($simple) {
            $run->forceFill(['simple_chat' => true])->save();
        }

        $questions = $limited ? null : self::questions($result['questions'] ?? null, $place);
        $this->answer($run, $answer, self::sources((array) ($result['sources'] ?? [])), self::searches((array) ($result['recherches'] ?? [])), $place, $limited ? null : self::thinking($result['reflexion'] ?? null), $connectorsUsed, $proposal, $limited, $questions, $suggestion);
        // Démarrage anticipé : pendant les questions avant un site vitrine, son ordinateur s'allume déjà.
        if (($questions['tool'] ?? null) === 'creer_site' && ! app(SiteNeeds::class)->detect((string) $run->message?->content)['database']) {
            rescue(fn () => app(RunnerClient::class)->prepare($run->project), report: false);
        }
        $this->giveTitle($run, $result['titre'] ?? null);
        Timings::mark($run, 'fin');
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
     * The race between providers (chantier « premier mot »): the cancelled request is counted too, like a request of
     * its provider (Administration → Consommation, the OpenAI ceiling of the day, the ceiling of the free accounts),
     * with the input tokens of the same instructions and nothing written (an estimate); the race is noted on the
     * request (timings) for the measure of the speed.
     *
     * @param  array<string, mixed>  $race
     */
    private function countRace(AgentRun $run, array $race): void
    {
        if ($race === []) {
            return;
        }
        $timings = (array) ($run->fresh()?->timings ?? []);
        $timings['course'] = [
            'lancee' => ($race['lancee'] ?? false) === true,
            'gagnant' => (string) data_get($race, 'gagnant.fournisseur', ''),
            'modele' => (string) data_get($race, 'gagnant.modele', ''),
            'premier_contenu_ms' => is_numeric($race['premier_contenu_ms'] ?? null) ? (int) $race['premier_contenu_ms'] : null,
        ];
        AgentRun::query()->whereKey($run->id)->update(['timings' => json_encode($timings)]);

        $loser = (array) ($race['perdant'] ?? []);
        $input = max(0, (int) ($race['tokens_entree_estimes'] ?? 0));
        if (($loser['fournisseur'] ?? '') === '' || $input === 0) {
            return;
        }
        $cached = min($input, max(0, (int) ($race['tokens_cache_estimes'] ?? 0)));
        app(RunnerEventHandler::class)->handle($run, ['type' => 'requete', 'fournisseur' => (string) $loser['fournisseur'], 'modele' => (string) ($loser['modele'] ?? ''), 'tokens_entree' => $input - $cached, 'tokens_cache' => $cached, 'tokens_sortie' => 0, 'requetes' => 1]);
    }

    /**
     * The memories proposed by the Runner for this message: applied (with the site's own lock), and the cost of the
     * extraction counted in the energy like the answer.
     *
     * @param  array<string, mixed>  $memory
     */
    private function remember(AgentRun $run, array $memory): void
    {
        // Chaque essai d'extraction est compté (un fournisseur occupé ou une réponse illisible : le suivant essaie).
        $usages = is_array($memory['consommations'] ?? null) ? $memory['consommations'] : [(array) ($memory['consommation'] ?? [])];
        foreach (array_slice($usages, 0, 4) as $usage) {
            if (is_array($usage) && (int) ($usage['requetes'] ?? 0) > 0) {
                app(RunnerEventHandler::class)->handle($run, ['type' => 'requete'] + array_intersect_key($usage, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache', 'requetes'])));
            }
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
        // Chaque requête envoyée (le nom exact, la question, la variante en anglais) est montrée, comme chez Claude.
        return collect($searches)
            ->flatMap(fn ($search) => collect((array) data_get($search, 'requetes', []))->filter(fn ($query) => is_string($query) && trim($query) !== '')
                ->whenEmpty(fn ($queries) => $queries->push((string) data_get($search, 'requete', '')))
                ->map(fn ($query) => ['requete' => $query, 'sites' => data_get($search, 'sites', [])]))
            ->unique('requete')
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

    /** The creations that start at once, confirmed with a button when the request was vague, repeated or garbled. */
    public const CONFIRMED_TOOLS = ['creer_site', 'creer_image', 'creer_document', 'creer_presentation', 'creer_video'];

    /**
     * The questions asked before a creation, like Claude (at most 3, each with 2 to 4 short choices to touch), checked
     * again by the site; null when there are none.
     *
     * @return array{tool: string, items: list<array{question: string, choices: list<string>}>}|null
     */
    public static function questions(mixed $questions, string $place): ?array
    {
        $tool = is_array($questions) ? (string) ($questions['outil'] ?? '') : '';
        if ($place !== FirstResponder::PLACE_DISCUSSION || ! FirstResponder::tool($place, $tool)) {
            return null;
        }
        $items = collect((array) ($questions['liste'] ?? []))
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'question' => Str::limit(trim((string) ($item['question'] ?? '')), 120, ''),
                'choices' => collect((array) ($item['choix'] ?? []))->filter(fn ($choice) => is_string($choice))->map(fn ($choice) => Str::limit(trim($choice), 40, ''))->filter()->unique()->take(5)->values()->all(),
            ])
            ->filter(fn ($item) => mb_strlen($item['question']) >= 5 && count($item['choices']) >= 2)
            ->take(3)
            ->values()
            ->all();

        return $items === [] ? null : ['tool' => $tool, 'items' => $items];
    }

    /**
     * The sentence said before a creation when the energy left is visibly too small (less than half of what such a
     * creation usually costs, Administration → Réglages, « creation_estimates_fcfa »), or null to start at once.
     */
    private function energyWarning(AgentRun $run, string $place, string $tool): ?string
    {
        $estimate = (float) data_get(app(Settings::class)->get('creation_estimates_fcfa'), $tool, 0);
        if ($place !== FirstResponder::PLACE_DISCUSSION || ! in_array($tool, self::CONFIRMED_TOOLS, true) || $estimate <= 0) {
            return null;
        }
        $energy = app(Energy::class);
        $user = $run->project->user;
        $left = $energy->leftFcfa($user);
        if ($left >= $estimate / 2) {
            return null;
        }

        return __('You have about :percent % of your energy left: it may not be enough to finish this creation. Kekeli can start anyway, and the overrun will be taken from your next energy.', ['percent' => max(1, (int) round(100 * $left / $estimate))]);
    }

    /**
     * The creation the Runner holds back because the request was vague, repeated or garbled (chantier « vitesse »,
     * partie 5): Kekeli asks one short question, with « Oui, créer le site » (or the image, the document…). Only a
     * tool of the discussion, with its allowed arguments as short texts.
     *
     * @return array{tool: string, arguments: array<string, string>}|null
     */
    public static function proposal(mixed $proposal, string $place): ?array
    {
        $tool = is_array($proposal) ? (string) ($proposal['outil'] ?? '') : '';
        if (is_array($proposal) && ($proposal['raison'] ?? null) === 'proposition') {
            return null;
        }
        if ($place !== FirstResponder::PLACE_DISCUSSION || ! in_array($tool, self::CONFIRMED_TOOLS, true) || ! FirstResponder::tool($place, $tool)) {
            return null;
        }
        $arguments = collect((array) ($proposal['arguments'] ?? []))
            ->filter(fn ($value, $key) => is_string($key) && preg_match('/^[a-z_]{1,30}$/', $key) && is_scalar($value))
            ->map(fn ($value) => Str::limit((string) $value, 2000, ''))
            ->take(12)
            ->all();

        return ['tool' => $tool, 'arguments' => $arguments];
    }

    /**
     * The creation Kekeli offers in one sentence at the end of an answer to a question, like Claude (« Voulez-vous que
     * je crée cet agent pour votre boutique ? »): the client sees a button that answers « Oui, créer l'agent », sent as
     * a normal message. Only a creation tool of the discussion.
     */
    public static function suggestion(mixed $proposal, string $place): ?string
    {
        $tool = is_array($proposal) && ($proposal['raison'] ?? null) === 'proposition' ? (string) ($proposal['outil'] ?? '') : '';

        return $place === FirstResponder::PLACE_DISCUSSION && str_starts_with($tool, 'creer_') && FirstResponder::tool($place, $tool) ? $tool : null;
    }

    /** The words of the suggestion button, also sent as the client's answer. */
    public static function suggestionLabel(string $tool): string
    {
        return match ($tool) {
            'creer_application' => __('Yes, create the application'),
            'creer_agent' => __('Yes, create the agent'),
            'creer_automatisation' => __('Yes, create the automation'),
            'creer_image' => __('Yes, create the image'),
            'creer_document' => __('Yes, create the document'),
            'creer_presentation' => __('Yes, create the presentation'),
            'creer_video' => __('Yes, create the video'),
            default => __('Yes, create the site'),
        };
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
     * Searches done on the Internet for a question: ONE search of the day for the client, however many requests Kekeli
     * sent to the providers (exact name, variants, reading the best pages). The requests count in the monthly ceiling
     * and in the client's energy (price of one provider credit each).
     *
     * @param  array<int, mixed>  $searches
     */
    private function countSearches(AgentRun $run, array $searches, int $requests = 0): void
    {
        if ($searches === []) {
            return;
        }

        $requests = max(count($searches), min(10, $requests));
        $cost = $requests * app(Settings::class)->float('web_search_price_usd');
        AgentRun::query()->whereKey($run->id)->update([
            'web_searches' => DB::raw('web_searches + 1'),
            'web_search_requests' => DB::raw('web_search_requests + '.$requests),
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
    private function answer(AgentRun $run, string $answer, array $sources = [], array $searches = [], string $place = FirstResponder::PLACE_SITE, ?array $thinking = null, array $connectors = [], ?array $proposal = null, bool $chatLimit = false, ?array $questions = null, ?string $suggestion = null): void
    {
        // Le texte garde sa mise en forme (Markdown) : il est affiché de façon sûre par App\Agent\Markdown.
        $answer = Str::limit(Markdown::prepare($answer), 16000);
        DB::transaction(function () use ($run, $answer, $sources, $searches, $place, $thinking, $connectors, $proposal, $chatLimit, $questions, $suggestion) {
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
                'report' => ['done' => $answer, 'note' => null, 'question' => true, 'markdown' => true, 'thinking' => $thinking, 'sources' => $sources, 'searches' => $searches, 'chat_limit' => $chatLimit, 'offer' => $chatLimit ? null : ($proposal['tool'] ?? ($suggestion === null && $place === FirstResponder::PLACE_DISCUSSION && self::offersSite($answer) ? 'creer_site' : null)), 'suggestion' => $chatLimit ? null : $suggestion, 'offer_arguments' => $proposal['arguments'] ?? [], 'offer_reason' => $proposal['reason'] ?? null, 'questions' => $questions, 'connectors' => $connectors, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
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
            $this->answer($run, __('Kekeli did not understand your request this time. Can you say it again in one sentence, with what you want to get?'), connectors: $connectors);

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
        $this->emit(['type' => 'attente']);
        if ($this->attempt < self::ATTEMPTS) {
            // Priorité : Agence essaie de nouveau la première, puis Expert, Pro et Gratuite.
            $resumeAt = CarbonImmutable::now()->addMinutes(app(PlanPerks::class)->retryMinutes($run->project->user));
            $run->update(['status' => AgentRun::STATUS_WAITING, 'resume_at' => $resumeAt]);

            if ($this->attempt === 1) {
                AdminAlert::create([
                    'kind' => 'providers_exhausted',
                    'message' => __('Every AI provider has reached its limit. A request is waiting and will resume around :time (Lomé time).', [
                        'time' => $resumeAt->setTimezone(config('kekeli.display_timezone'))->format('H \h i'),
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
            'report' => ['done' => '', 'note' => Failures::busy(), 'question' => true, 'retry' => true, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();
    }
}
