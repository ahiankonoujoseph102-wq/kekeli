<?php

namespace App\Agent;

use App\Jobs\SendAgentRun;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Message;
use App\Models\ProviderUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records the events sent by the Runner while the agent works.
 */
class RunnerEventHandler
{
    public function __construct(private ProviderList $providers) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(AgentRun $run, array $event): void
    {
        match ($event['type'] ?? null) {
            'demarrage' => $this->started($run),
            'fournisseur' => $run->update([
                'provider' => $this->text($event, 'fournisseur', 30),
                'model' => $this->text($event, 'modele', 120),
            ]),
            'etape' => $this->step($run, $event),
            'requete' => $this->request($event),
            'refus' => $this->increment($event, ['rate_limited' => 1]),
            'mis_de_cote' => $this->setAside($event),
            'terminee' => $this->completed($run, $event),
            'en_attente' => $this->waiting($run, $event),
            'erreur' => $this->failed($run, $event),
            default => null,
        };
    }

    private function started(AgentRun $run): void
    {
        $run->update([
            'status' => AgentRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
            'resume_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function step(AgentRun $run, array $event): void
    {
        $kind = $this->text($event, 'genre', 30);

        $run->steps()->create([
            'kind' => array_key_exists($kind, AgentStep::LABELS) ? $kind : 'outil',
            'target' => $this->text($event, 'cible', 300),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function request(array $event): void
    {
        $this->increment($event, [
            'requests' => 1,
            'input_tokens' => (int) ($event['tokens_entree'] ?? 0),
            'output_tokens' => (int) ($event['tokens_sortie'] ?? 0),
            'reasoning_tokens' => (int) ($event['tokens_reflexion'] ?? 0),
            'cached_tokens' => (int) ($event['tokens_cache'] ?? 0),
        ]);
    }

    /**
     * Add to the counters of a provider for its current quota day.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, int>  $amounts
     */
    private function increment(array $event, array $amounts): void
    {
        $provider = $this->text($event, 'fournisseur', 30);
        $model = $this->text($event, 'modele', 120);

        if ($provider === '' || $model === '') {
            return;
        }

        $attributes = ['day' => $this->providers->quotaDay($provider), 'provider' => $provider, 'model' => $model];

        DB::transaction(function () use ($attributes, $amounts) {
            $usage = ProviderUsage::query()->where($attributes)->lockForUpdate()->first()
                ?? ProviderUsage::create($attributes);

            foreach ($amounts as $column => $amount) {
                $usage->{$column} = $usage->{$column} + max(0, $amount);
            }

            $usage->save();
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function setAside(array $event): void
    {
        $until = $this->date($event, 'jusqu_a');
        $key = $this->text($event, 'fournisseur', 30).'/'.$this->text($event, 'modele', 120);

        if ($until && $until->isFuture()) {
            Cache::put('kekeli.set_aside.'.$key, $until->toIso8601String(), $until);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function completed(AgentRun $run, array $event): void
    {
        $answer = trim((string) ($event['reponse'] ?? ''));

        DB::transaction(function () use ($run, $answer) {
            $run->project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => Str::limit($answer !== '' ? $answer : __('Done.'), 20000),
            ]);

            $run->update([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'resume_at' => null,
            ]);

            $run->project->touch();
        });
    }

    /**
     * Every provider is exhausted: the request waits and resumes by itself.
     *
     * @param  array<string, mixed>  $event
     */
    private function waiting(AgentRun $run, array $event): void
    {
        $resumeAt = $this->date($event, 'reprise_vers');

        if (! $resumeAt || $resumeAt->isPast()) {
            $resumeAt = CarbonImmutable::now()->addMinutes(5);
        }

        $run->update([
            'status' => AgentRun::STATUS_WAITING,
            'resume_at' => $resumeAt,
        ]);

        AdminAlert::create([
            'kind' => 'providers_exhausted',
            'message' => __('Every AI provider has reached its limit. A request is waiting and will resume around :time (Lomé time).', [
                'time' => $resumeAt->setTimezone(config('kekeli.display_timezone'))->format('H:i'),
            ]),
        ]);

        SendAgentRun::dispatch($run, true)->delay($resumeAt->addSeconds(30));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function failed(AgentRun $run, array $event): void
    {
        $reason = $this->text($event, 'raison', 40);
        $detail = $this->text($event, 'detail', 1000);

        $run->update([
            'status' => AgentRun::STATUS_FAILED,
            'error' => trim($reason.' : '.$detail, ' :'),
            'finished_at' => now(),
            'resume_at' => null,
        ]);

        AdminAlert::create([
            'kind' => 'run_failed',
            'message' => __('A request to the agent failed (:reason): :detail', ['reason' => $reason, 'detail' => $detail]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function text(array $event, string $key, int $limit): string
    {
        return Str::limit(trim((string) ($event[$key] ?? '')), $limit, '…');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function date(array $event, string $key): ?CarbonImmutable
    {
        try {
            return isset($event[$key]) ? CarbonImmutable::parse((string) $event[$key]) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
