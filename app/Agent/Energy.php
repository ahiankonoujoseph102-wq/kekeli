<?php

namespace App\Agent;

use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * « L'énergie qui se recharge » : each formula gives an amount of energy (in FCFA of real cost)
 * per period. The free formula recharges every day at midnight, Lomé time; a paid formula
 * recharges a number of hours after the first request of the period.
 */
class Energy
{
    public function __construct(private Settings $settings, private Costs $costs) {}

    /**
     * @return array{key: string, name: string, price_fcfa: int, energy_fcfa: float, window_hours: int}
     */
    public function plan(User $user): array
    {
        $plans = $this->settings->get('plans');
        $key = isset($plans[$user->plan]) ? $user->plan : array_key_first($plans);
        $plan = $plans[$key];

        return [
            'key' => $key,
            'name' => (string) $plan['name'],
            'price_fcfa' => (int) $plan['price_fcfa'],
            'energy_fcfa' => (float) $plan['energy_fcfa'],
            'window_hours' => (int) $plan['window_hours'],
        ];
    }

    public function isFree(User $user): bool
    {
        return $this->plan($user)['price_fcfa'] === 0;
    }

    /**
     * Start of the current period, or null if a paid period has not started yet.
     */
    public function windowStart(User $user): ?CarbonImmutable
    {
        $hours = $this->plan($user)['window_hours'];

        if ($hours === 0) {
            return CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();
        }

        $start = $user->energy_window_started_at ? CarbonImmutable::parse($user->energy_window_started_at) : null;

        return $start && $start->addHours($hours)->isFuture() ? $start : null;
    }

    /**
     * When the energy is full again.
     */
    public function rechargesAt(User $user): CarbonImmutable
    {
        $hours = $this->plan($user)['window_hours'];

        if ($hours === 0) {
            return CarbonImmutable::now(config('kekeli.display_timezone'))->addDay()->startOfDay()->utc();
        }

        return ($this->windowStart($user) ?? CarbonImmutable::now())->addHours($hours);
    }

    /**
     * Energy used in the current period, in FCFA of real cost (model and computer).
     */
    public function usedFcfa(User $user): float
    {
        $start = $this->windowStart($user);

        if (! $start) {
            return 0.0;
        }

        $usd = (float) AgentRun::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->where('created_at', '>=', $start)
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd), 0) as total')
            ->value('total');

        return $this->costs->toFcfa($usd);
    }

    /**
     * Energy left, from 0 to 100 %.
     */
    public function percentLeft(User $user): int
    {
        $limit = $this->plan($user)['energy_fcfa'];

        if ($limit <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round(100 - 100 * $this->usedFcfa($user) / $limit)));
    }

    public function isExhausted(User $user): bool
    {
        return $this->usedFcfa($user) >= $this->plan($user)['energy_fcfa'];
    }

    /**
     * Keys of the free formulas.
     *
     * @return list<string>
     */
    public function freePlanKeys(): array
    {
        return collect($this->settings->get('plans'))->filter(fn ($plan) => (int) $plan['price_fcfa'] === 0)->keys()->all();
    }

    /**
     * Energy used today by all the users of free formulas together, in FCFA.
     */
    public function freeUsedTodayFcfa(): float
    {
        $start = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();
        $freeKeys = $this->freePlanKeys();
        $defaultIsFree = in_array(array_key_first($this->settings->get('plans')), $freeKeys, true);

        $usd = (float) AgentRun::query()
            ->where('created_at', '>=', $start)
            ->whereHas('project.user', fn ($query) => $query->where(fn ($users) => $users
                ->whereIn('plan', $freeKeys)
                ->when($defaultIsFree, fn ($unknown) => $unknown->orWhereNotIn('plan', array_keys($this->settings->get('plans'))))))
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd), 0) as total')
            ->value('total');

        return $this->costs->toFcfa($usd);
    }

    /**
     * The daily ceiling for all free users together is reached: free users wait until midnight (Lomé time).
     * The administrator receives one alert per day.
     */
    public function freeCapReached(): bool
    {
        $cap = $this->settings->float('free_daily_cap_fcfa');
        $reached = $cap > 0 && $this->freeUsedTodayFcfa() >= $cap;

        if ($reached) {
            $today = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();

            if (! AdminAlert::query()->where('kind', 'free_cap')->where('created_at', '>=', $today)->exists()) {
                AdminAlert::create([
                    'kind' => 'free_cap',
                    'message' => __('The daily ceiling of the free formula (:cap FCFA) is reached: free users wait until midnight (Lomé time).', ['cap' => number_format($cap, 0, ',', ' ')]),
                ]);
            }
        }

        return $reached;
    }

    /**
     * A paid period starts with the first request after the previous one has ended.
     */
    public function startWindowIfNeeded(User $user): void
    {
        if ($this->plan($user)['window_hours'] > 0 && ! $this->windowStart($user)) {
            $user->forceFill(['energy_window_started_at' => now()])->save();
        }
    }
}
