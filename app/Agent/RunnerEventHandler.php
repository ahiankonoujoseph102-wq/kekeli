<?php

namespace App\Agent;

use App\Jobs\SendAgentRun;
use App\Models\AdminAlert;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Message;
use App\Models\Project;
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
    public function __construct(private ProviderList $providers, private Costs $costs) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(AgentRun $run, array $event): void
    {
        match ($event['type'] ?? null) {
            'demarrage' => $this->started($run),
            'apercu' => $this->preview($run->project, $event),
            'permission' => $this->permissionAsked($run, $event),
            'permission_reglee' => $this->permissionSettled($run, $event),
            'fournisseur' => $run->update([
                'provider' => $this->text($event, 'fournisseur', 30),
                'model' => $this->text($event, 'modele', 120),
            ]),
            'etape' => $this->step($run, $event),
            'requete' => $this->request($run, $event),
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
     * Remember the link of the project's live preview and when the virtual computer will be deleted.
     *
     * @param  array<string, mixed>  $event
     */
    public function preview(Project $project, array $event): void
    {
        $url = trim((string) ($event['url'] ?? ''));

        if (! $this->isPreviewUrl($url)) {
            return;
        }

        $expiresAt = $this->date($event, 'expire_a') ?? CarbonImmutable::now()->addMinutes(30);

        $this->quietly($project, [
            'preview_url' => $url,
            'preview_expires_at' => $expiresAt,
            'preview_waking_at' => null,
        ]);
    }

    /**
     * Events that concern a project without a request to the agent (turning the preview back on).
     *
     * @param  array<string, mixed>  $event
     */
    public function handleProject(Project $project, array $event): void
    {
        match ($event['type'] ?? null) {
            'apercu' => $this->preview($project, $event),
            'apercu_impossible' => $this->quietly($project, ['preview_waking_at' => null]),
            default => null,
        };
    }

    /**
     * Change a project without changing its « modified » date.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function quietly(Project $project, array $attributes): void
    {
        $project->timestamps = false;
        $project->forceFill($attributes)->save();
        $project->timestamps = true;
    }

    /**
     * The agent asks the user before a risky action.
     *
     * @param  array<string, mixed>  $event
     */
    private function permissionAsked(AgentRun $run, array $event): void
    {
        $requestId = $this->text($event, 'id', 100);

        if ($requestId === '') {
            return;
        }

        $run->permissions()->firstOrCreate(['request_id' => $requestId], [
            'tool' => $this->text($event, 'outil', 40),
            'target' => $this->text($event, 'cible', 300),
        ]);
    }

    /**
     * The permission was answered (or refused automatically after the waiting time).
     *
     * @param  array<string, mixed>  $event
     */
    private function permissionSettled(AgentRun $run, array $event): void
    {
        $permission = $run->permissions()->where('request_id', $this->text($event, 'id', 100))->first();

        if (! $permission || ! $permission->isPending()) {
            return;
        }

        $permission->update([
            'status' => match ($event['decision'] ?? null) {
                'autoriser' => AgentPermission::STATUS_ALLOWED,
                'refuser' => AgentPermission::STATUS_REFUSED,
                default => AgentPermission::STATUS_EXPIRED,
            },
            'decided_at' => now(),
        ]);
    }

    /**
     * Only HTTPS links to the virtual computers are accepted.
     */
    private function isPreviewUrl(string $url): bool
    {
        if (strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach (config('kekeli.preview_domains', []) as $domain) {
            if (str_ends_with($host, '.'.strtolower($domain))) {
                return true;
            }
        }

        return false;
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
    private function request(AgentRun $run, array $event): void
    {
        $input = max(0, (int) ($event['tokens_entree'] ?? 0));
        $output = max(0, (int) ($event['tokens_sortie'] ?? 0));
        $reasoning = max(0, (int) ($event['tokens_reflexion'] ?? 0));
        $cached = max(0, (int) ($event['tokens_cache'] ?? 0));
        $cost = $this->costs->modelCost($this->text($event, 'fournisseur', 30), $this->text($event, 'modele', 120), $input, $output, $reasoning, $cached);

        // Consommation de la demande, pour l'énergie de l'utilisateur et la mesure des coûts.
        AgentRun::query()->whereKey($run->id)->update([
            'requests' => DB::raw('requests + 1'),
            'input_tokens' => DB::raw('input_tokens + '.$input),
            'output_tokens' => DB::raw('output_tokens + '.$output),
            'reasoning_tokens' => DB::raw('reasoning_tokens + '.$reasoning),
            'cached_tokens' => DB::raw('cached_tokens + '.$cached),
            'model_cost_usd' => DB::raw('model_cost_usd + '.number_format($cost, 6, '.', '')),
        ]);

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
                ...$this->computerTime($run),
            ]);

            $run->project->touch();
        });
    }

    /**
     * Time the computer worked for this request, and its cost (the computer is billed while it runs).
     *
     * @return array{computer_seconds: int, computer_cost_usd: string}
     */
    private function computerTime(AgentRun $run): array
    {
        $seconds = $run->started_at ? (int) max(0, $run->started_at->diffInSeconds(now())) : 0;

        return [
            'computer_seconds' => $seconds,
            'computer_cost_usd' => number_format($this->costs->computerCost($seconds), 6, '.', ''),
        ];
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
            ...$this->computerTime($run),
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
