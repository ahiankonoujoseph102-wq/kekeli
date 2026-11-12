<?php

namespace App\Agent;

use App\Billing\Subscriptions;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\Dictation;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * « L'énergie qui se recharge » : each formula gives an amount of energy (in FCFA of real cost) per period. The free
 * formula recharges every day at midnight, Lomé time (« day »); a paid formula gives its energy for each month of the
 * subscription, that is each block of 30 days counted back from its end (« month »), like the monthly credits of
 * Lovable and Bolt.new. The older « hours » period recharges a number of hours after the first request.
 */
class Energy
{
    public function __construct(private Settings $settings, private Costs $costs) {}

    /**
     * @return array{key: string, name: string, price_fcfa: int, price_usd: int, energy_fcfa: float, period: string, window_hours: int, web_searches_per_day: ?int, answer_model: string, build_model: string}
     */
    public function plan(User $user): array
    {
        $plans = $this->settings->get('plans');
        // Une ancienne formule (Découverte, Entreprise) compte comme celle qui la remplace, même avant la migration.
        $replaced = (array) $this->settings->get('replaced_plans');
        $current = isset($replaced[$user->plan]) && ! isset($plans[$user->plan]) ? $replaced[$user->plan] : $user->plan;
        $key = isset($plans[$current]) ? $current : array_key_first($plans);

        // Une formule payée dont les 30 jours sont finis ne compte plus, même avant le passage de la tâche de nuit.
        if ($user->plan_expires_at && $user->plan_expires_at->isPast()) {
            $key = app(Subscriptions::class)->freePlanKey();
        }
        $plan = $plans[$key];

        return [
            'key' => $key,
            'name' => (string) $plan['name'],
            'price_fcfa' => (int) $plan['price_fcfa'],
            'price_usd' => (int) ($plan['price_usd'] ?? 0),
            'energy_fcfa' => (float) $plan['energy_fcfa'],
            'period' => self::periodOf($plan),
            'web_searches_per_day' => isset($plan['web_searches_per_day']) ? (int) $plan['web_searches_per_day'] : null,
            'window_hours' => (int) ($plan['window_hours'] ?? 0),
            // Modèle qui répond aux questions (« fournisseur/modèle ») ; formules enregistrées avant le 12 novembre 2026 : valeur par défaut.
            'answer_model' => (string) ($plan['answer_model'] ?? config('kekeli.answer_models.'.$key, '')),
            // Modèle qui construit les sites et les applications (partie 27 de docs/plan.md).
            'build_model' => (string) ($plan['build_model'] ?? config('kekeli.build_models.'.$key, '')),
        ];
    }

    /**
     * Period of a formula: « day », « month » or « hours » (formulas saved before the 12th of November 2026 only had
     * « window_hours »: 0 meant every day).
     *
     * @param  array<string, mixed>  $plan
     */
    public static function periodOf(array $plan): string
    {
        $period = (string) ($plan['period'] ?? '');

        return in_array($period, ['day', 'month', 'hours'], true) ? $period : ((int) ($plan['window_hours'] ?? 0) > 0 ? 'hours' : 'day');
    }

    /**
     * Minutes without a request before the computer pauses: shorter for the free formula (its main real cost).
     */
    public function pauseMinutes(User $user): int
    {
        $free = $this->settings->int('free_pause_minutes');

        return $this->isFree($user) && $free > 0 ? $free : $this->settings->int('pause_minutes');
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
        $plan = $this->plan($user);

        if ($plan['period'] === 'month') {
            return $this->monthStart($user);
        }

        $hours = $plan['window_hours'];

        if ($plan['period'] === 'day' || $hours === 0) {
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
        $plan = $this->plan($user);

        if ($plan['period'] === 'month') {
            return $this->monthStart($user)->addDays($this->monthDays());
        }

        $hours = $plan['window_hours'];

        if ($plan['period'] === 'day' || $hours === 0) {
            return CarbonImmutable::now(config('kekeli.display_timezone'))->addDay()->startOfDay()->utc();
        }

        return ($this->windowStart($user) ?? CarbonImmutable::now())->addHours($hours);
    }

    /**
     * Start of the current month of a paid subscription: the blocks of 30 days are counted back from its end, so a
     * renewal paid in advance gives a new full month when the current one ends. Without an end date (formula given
     * by the administrator), the calendar month, Lomé time.
     */
    private function monthStart(User $user): CarbonImmutable
    {
        $expires = $user->plan_expires_at ? CarbonImmutable::parse($user->plan_expires_at) : null;

        if (! $expires || $expires->isPast()) {
            return CarbonImmutable::now(config('kekeli.display_timezone'))->startOfMonth()->utc();
        }

        $seconds = $this->monthDays() * 86400;
        $blocks = max(1, (int) ceil(CarbonImmutable::now()->diffInSeconds($expires) / $seconds));

        return $expires->subSeconds($blocks * $seconds);
    }

    private function monthDays(): int
    {
        return max(1, $this->settings->int('plan_duration_days'));
    }

    /**
     * When the energy is full again, as the client reads it: the hour for a daily formula, the date otherwise.
     */
    public function rechargeText(User $user): string
    {
        $at = $this->rechargesAt($user)->timezone(config('kekeli.display_timezone'));

        if ($this->plan($user)['period'] === 'month') {
            return $at->format('d/m/Y \à H:i');
        }

        // « à minuit » se lit mieux que « à 00:00 ».
        return $at->format('H:i') === '00:00' ? __('midnight') : $at->format('H:i');
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
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd + image_cost_usd + web_search_cost_usd), 0) as total')
            ->value('total');
        // Les dictées écrites par OpenAI (quand le navigateur ne sait pas le faire) comptent aussi.
        $usd += (float) Dictation::query()->where('user_id', $user->id)->where('created_at', '>=', $start)->sum('cost_usd');

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
     * Energy used, counting also the computer of a request that is still running (its time is only
     * recorded when the request ends).
     */
    public function usedDuringRun(User $user, AgentRun $run): float
    {
        $running = $run->started_at ? (int) max(0, $run->started_at->diffInSeconds(now())) : 0;

        return $this->usedFcfa($user) + $this->costs->toFcfa($this->costs->computerCost($running));
    }

    /**
     * Why a running request must stop now (« energie » when the user's energy, or the daily ceiling of the
     * free formula, is used up), or null when it may go on.
     */
    public function stopReason(User $user, AgentRun $run): ?string
    {
        if ($this->usedDuringRun($user, $run) >= $this->plan($user)['energy_fcfa']) {
            return 'energie';
        }

        return $this->isFree($user) && $this->freeCapReached() ? 'plafond_gratuit' : null;
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
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd + image_cost_usd + web_search_cost_usd), 0) as total')
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
        if ($this->plan($user)['period'] === 'hours' && ! $this->windowStart($user)) {
            $user->forceFill(['energy_window_started_at' => now()])->save();
        }
    }
}
