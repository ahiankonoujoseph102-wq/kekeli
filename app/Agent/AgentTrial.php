<?php

namespace App\Agent;

use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Free trial of an AI agent in the preview, like the « Test » panel of Dify: until the client has put the key of
 * their own model in the « Variables secrètes », the agent answers with the free model of Kekeli, through the relay
 * of the Runner (the key of Kekeli never enters the computer). A few messages per day and per client (10 at first,
 * set in Administration → Réglages), never counted in the energy.
 */
class AgentTrial
{
    public function __construct(private Settings $settings, private ProviderList $providers) {}

    public function perDay(): int
    {
        return max(0, $this->settings->int('agent_trial_messages_per_day'));
    }

    public function usedToday(User $user): int
    {
        $midnight = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();

        return DB::table('agent_trial_messages')->where('user_id', $user->id)->where('created_at', '>=', $midnight)->count();
    }

    public function remaining(User $user): int
    {
        return max(0, $this->perDay() - $this->usedToday($user));
    }

    /**
     * Whether the client of this agent has put the key of their own model: the trial is then never used.
     */
    public static function hasOwnKey(Project $project): bool
    {
        return $project->secrets()->where('name', 'MODELE_API_CLE')->exists();
    }

    /**
     * One message of the trial: allowed and counted, or refused when the day's trial is over. A continuation (another
     * call to the model for the same message of the visitor, for example by a team of agents) is not counted again,
     * but the request to the free model is still counted for its provider.
     *
     * @return array{permis: bool, restants: int}
     */
    public function take(Project $project, bool $continuation = false): array
    {
        $user = $project->user;
        if (! $project->usesClientModel() || ! $user) {
            return ['permis' => false, 'restants' => 0];
        }
        if ($continuation) {
            $this->countRequest();

            return ['permis' => true, 'restants' => $this->remaining($user)];
        }

        return DB::transaction(function () use ($project, $user) {
            // Un seul message compté à la fois pour ce client (deux onglets ouverts ne dépassent pas la limite).
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            if ($this->remaining($user) <= 0) {
                return ['permis' => false, 'restants' => 0];
            }

            DB::table('agent_trial_messages')->insert(['user_id' => $user->id, 'project_id' => $project->id, 'created_at' => now()]);
            $this->countRequest();

            return ['permis' => true, 'restants' => $this->remaining($user)];
        });
    }

    /**
     * The request to the free model, counted for its provider (Administration → Fournisseurs).
     */
    private function countRequest(): void
    {
        $model = (string) config('kekeli.agent_trial_model', 'gemini-3.5-flash-lite');
        $attributes = ['day' => $this->providers->quotaDay('gemini'), 'provider' => 'gemini', 'model' => $model];
        $usage = ProviderUsage::query()->where($attributes)->first() ?? ProviderUsage::create($attributes);
        $usage->increment('requests');
    }
}
