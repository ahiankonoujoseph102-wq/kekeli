<?php

namespace App\Jobs;

use App\Agent\FirstResponder;
use App\Agent\RunnerEventHandler;
use App\Agent\RunReport;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\Message;
use App\Support\Settings;
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

        $events->handle($run, ['type' => 'fournisseur', 'fournisseur' => $result['fournisseur'] ?? '', 'modele' => $result['modele'] ?? '']);
        $events->handle($run, ['type' => 'requete'] + array_intersect_key($result, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache', 'requetes'])));
        $this->countSearches($run, (array) ($result['recherches'] ?? []));

        $place = FirstResponder::placeOf($run->project);
        $tool = ($result['decision'] ?? null) === 'outil' ? FirstResponder::tool($place, $result['outil'] ?? null) : null;

        if ($tool) {
            $this->useTool($run->fresh(['project', 'message']), (string) $result['outil'], $tool);

            return;
        }

        $answer = trim((string) ($result['reponse'] ?? ''));
        if ($answer === '') {
            // Un outil inconnu ou interdit ici, sans texte : jamais exécuté, le client lit une phrase polie.
            $answer = __('Sorry, Kekeli did not understand. Can you say it again in other words?');
        }

        $this->answer($run, $answer, self::sources((array) ($result['sources'] ?? [])));
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
     * @return list<array{title: string, url: string}>
     */
    public static function sources(array $sources): array
    {
        return collect($sources)
            ->map(fn ($source) => ['title' => Str::limit(trim((string) (data_get($source, 'title') ?? data_get($source, 'titre', ''))), 120), 'url' => trim((string) data_get($source, 'url', ''))])
            ->filter(fn ($source) => filter_var($source['url'], FILTER_VALIDATE_URL) && in_array(parse_url($source['url'], PHP_URL_SCHEME), ['http', 'https'], true))
            ->map(fn ($source) => ['title' => $source['title'] !== '' ? $source['title'] : (string) parse_url($source['url'], PHP_URL_HOST), 'url' => $source['url']])
            ->unique('url')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Kekeli answers in the discussion, without touching any site.
     */
    /**
     * @param  list<array{title: string, url: string}>  $sources
     */
    private function answer(AgentRun $run, string $answer, array $sources = []): void
    {
        DB::transaction(function () use ($run, $answer, $sources) {
            $run->project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => Str::limit($answer, 6000),
                'agent_run_id' => $run->id,
            ]);
            $run->project->touch();

            $run->refresh()->forceFill([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'resume_at' => null,
                'report' => ['done' => Str::limit(RunReport::clean($answer), 3000), 'note' => null, 'question' => true, 'sources' => $sources, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
            ])->save();
        });
    }

    /**
     * The model chose a tool offered in this place: the first responder is done, then the work starts as usual.
     *
     * @param  class-string  $tool
     */
    private function useTool(AgentRun $run, string $name, string $tool): void
    {
        $run->forceFill([
            'status' => AgentRun::STATUS_COMPLETED,
            'finished_at' => now(),
            'resume_at' => null,
            'report' => ['done' => '', 'note' => null, 'question' => true, 'tool' => $name, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();

        app($tool)->handle($run->fresh(['project', 'message']));
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
