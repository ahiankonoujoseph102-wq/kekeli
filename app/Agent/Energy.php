<?php

namespace App\Agent;

use App\Billing\Subscriptions;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\Dictation;
use App\Models\Payment;
use App\Models\User;
use App\Support\Formats;
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
     * Sums already read while a page is shown (chantier « clics instantanés », 15 novembre 2026): the account menu and
     * the navigation ask for the energy about 15 times per page, each time with the same queries. Only during the
     * display of a page (a GET request of the site); never in a job, a command or a request that changes something,
     * where the energy must always be read again.
     *
     * @var array<string, mixed>
     */
    private array $read = [];

    private function remembered(string $key, \Closure $compute): mixed
    {
        if (app()->runningInConsole() || ! request()->isMethod('GET')) {
            return $compute();
        }

        return array_key_exists($key, $this->read) ? $this->read[$key] : ($this->read[$key] = $compute());
    }

    /**
     * @return array{key: string, name: string, price_fcfa: int, price_usd: int, energy_fcfa: float, period: string, window_hours: int, web_searches_per_day: ?int, answer_model: string, build_model: string, image_model: string, carry_over: bool, early_access: bool, whatsapp_support: bool, parallel_runs: int, simple_chats_per_day: int}
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
            // Générateur des images et des affiches (partie 31 de docs/plan.md).
            'image_model' => (string) ($plan['image_model'] ?? config('kekeli.image_models.'.$key, '')),
            // Avantages des formules payantes (partie 26.28 de docs/plan.md) ; formules enregistrées avant le 14 novembre
            // 2026 : valeurs de départ de config/kekeli.php, sinon rien pour la gratuite.
            'carry_over' => (bool) ($plan['carry_over'] ?? config('kekeli.settings.plans.'.$key.'.carry_over', false)),
            'early_access' => (bool) ($plan['early_access'] ?? config('kekeli.settings.plans.'.$key.'.early_access', false)),
            'whatsapp_support' => (bool) ($plan['whatsapp_support'] ?? config('kekeli.settings.plans.'.$key.'.whatsapp_support', false)),
            'parallel_runs' => max(1, (int) ($plan['parallel_runs'] ?? config('kekeli.settings.plans.'.$key.'.parallel_runs', 1))),
            // Discussions simples permises par jour (15 novembre 2026) ; formules enregistrées avant : valeur de départ.
            'simple_chats_per_day' => max(0, (int) ($plan['simple_chats_per_day'] ?? config('kekeli.settings.plans.'.$key.'.simple_chats_per_day', self::DEFAULT_SIMPLE_CHATS))),
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
    public function monthStart(User $user): CarbonImmutable
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

        // Comme Claude Code : une heure courte pour la recharge du jour (« 00 h 00 »), une date pour celle du mois.
        return $this->plan($user)['period'] === 'month' ? Formats::date($at) : $at->format('H \h i');
    }

    /**
     * Energy used in the current period, in FCFA of real cost (model and computer).
     */
    public function usedFcfa(User $user): float
    {
        $start = $this->windowStart($user);

        // Le bonus de bienvenue s'utilise avant l'énergie de la période (15 novembre 2026).
        return $start ? max(0.0, $this->usedBetween($user, $start) - $this->bonusAbsorbed($user, $start)) : 0.0;
    }

    /**
     * Welcome bonus given once to the account (FCFA), 0 when there is none. It is never recharged.
     */
    public function bonusFcfa(User $user): float
    {
        return $user->welcome_bonus_at ? max(0.0, (float) $user->welcome_bonus_fcfa) : 0.0;
    }

    /**
     * Welcome bonus still left (FCFA): all the energy used since it was given comes out of it first.
     */
    public function bonusLeftFcfa(User $user): float
    {
        $bonus = $this->bonusFcfa($user);

        return $bonus > 0 ? round(max(0.0, $bonus - $this->usedBetween($user, CarbonImmutable::parse($user->welcome_bonus_at))), 2) : 0.0;
    }

    /** Welcome bonus left, from 0 to 100 % (shown in the account menu while some is left). */
    public function bonusPercentLeft(User $user): int
    {
        $bonus = $this->bonusFcfa($user);

        return $bonus > 0 ? (int) ceil(100 * $this->bonusLeftFcfa($user) / $bonus) : 0;
    }

    /**
     * The part of the energy used between two moments that the welcome bonus paid for: the bonus pays for the first
     * energy used after it was given, whatever the period.
     */
    private function bonusAbsorbed(User $user, CarbonImmutable $start, ?CarbonImmutable $end = null): float
    {
        $bonus = $this->bonusFcfa($user);
        if ($bonus <= 0) {
            return 0.0;
        }

        $given = CarbonImmutable::parse($user->welcome_bonus_at);
        $from = $start->max($given);
        if ($end && $end->lessThanOrEqualTo($from)) {
            return 0.0;
        }
        $before = $from->greaterThan($given) ? $this->usedBetween($user, $given, $from) : 0.0;

        return min($this->usedBetween($user, $from, $end), max(0.0, $bonus - $before));
    }

    /**
     * Energy used beyond the energy of the previous period (a creation is never cut: it may go past the energy left,
     * within the overdraft allowed for one request). It is taken from the energy of the current period.
     */
    public function debtFcfa(User $user): float
    {
        $start = $this->windowStart($user);

        return $start ? $this->overdraftBefore($user, $start, self::DEBT_DEPTH) : 0.0;
    }

    /** Periods looked back at most to know what the previous one still owed. */
    private const DEBT_DEPTH = 2;

    private function overdraftBefore(User $user, CarbonImmutable $start, int $depth): float
    {
        $plan = $this->plan($user);
        if ($plan['period'] === 'day' || ($plan['period'] !== 'month' && $plan['window_hours'] === 0)) {
            $previous = $start->subDay();
        } elseif ($plan['period'] === 'month' && $plan['price_fcfa'] > 0 && $this->paidDuring($user, $plan['key'], $start->subDays($this->monthDays()), $start)) {
            $previous = $start->subDays($this->monthDays());
        } else {
            return 0.0;
        }

        $used = $this->usedBetween($user, $previous, $start) - $this->bonusAbsorbed($user, $previous, $start);
        $limit = $plan['energy_fcfa'] - ($depth > 0 ? $this->overdraftBefore($user, $previous, $depth - 1) : 0.0);

        return round(max(0.0, $used - $limit), 2);
    }

    /**
     * Energy the client may still use now (FCFA, never below 0), welcome bonus included.
     */
    public function leftFcfa(User $user): float
    {
        return round(max(0.0, $this->limitFcfa($user) - $this->usedFcfa($user)) + $this->bonusLeftFcfa($user), 2);
    }

    /**
     * Energy used between two moments (the end is open when null), in FCFA of real cost.
     */
    public function usedBetween(User $user, CarbonImmutable $start, ?CarbonImmutable $end = null): float
    {
        return $this->remembered("used:{$user->id}:{$start->getTimestamp()}:".($end?->getTimestamp() ?? ''), fn () => $this->sumUsedBetween($user, $start, $end));
    }

    private function sumUsedBetween(User $user, CarbonImmutable $start, ?CarbonImmutable $end): float
    {
        // Une discussion simple (réponse sans aucun outil) ne retire rien à l'énergie du client (15 novembre 2026) :
        // son coût reste enregistré et compte dans le plafond global des gratuits (freeUsedTodayFcfa).
        $usd = (float) AgentRun::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->where('simple_chat', false)
            ->where('created_at', '>=', $start)
            ->when($end, fn ($query) => $query->where('created_at', '<', $end))
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd + image_cost_usd + web_search_cost_usd + voice_cost_usd), 0) as total')
            ->value('total');
        // Les dictées écrites par OpenAI (quand le navigateur ne sait pas le faire) comptent aussi.
        $usd += (float) Dictation::query()->where('user_id', $user->id)->where('created_at', '>=', $start)
            ->when($end, fn ($query) => $query->where('created_at', '<', $end))->sum('cost_usd');

        return $this->costs->toFcfa($usd);
    }

    /**
     * Energy of the period: that of the formula, plus the energy carried over from the previous month.
     */
    public function limitFcfa(User $user): float
    {
        return $this->plan($user)['energy_fcfa'] + $this->carriedOverFcfa($user) - $this->debtFcfa($user);
    }

    /**
     * Energy not used during the previous month of a paid subscription, added to the current month (Pro, Expert,
     * Agence; never the free formula): at most one month of energy, so at most twice the usual energy. Only when the
     * same formula was paid for during the whole previous month (a client who just left the free formula gets nothing).
     */
    public function carriedOverFcfa(User $user): float
    {
        $plan = $this->plan($user);

        if (! $plan['carry_over'] || $plan['period'] !== 'month' || $plan['price_fcfa'] === 0 || ! $user->plan_expires_at) {
            return 0.0;
        }

        return $this->carryBefore($user, $plan, $this->monthStart($user), self::CARRY_DEPTH);
    }

    /** Months looked back at most to know what the previous month had (its own carried over energy included). */
    private const CARRY_DEPTH = 2;

    /**
     * @param  array{key: string, energy_fcfa: float}  $plan
     */
    private function carryBefore(User $user, array $plan, CarbonImmutable $start, int $depth): float
    {
        $previous = $start->subDays($this->monthDays());

        if (! $this->paidDuring($user, $plan['key'], $previous, $start)) {
            return 0.0;
        }
        $available = $plan['energy_fcfa'] + ($depth > 0 ? $this->carryBefore($user, $plan, $previous, $depth - 1) : 0.0);

        return round(max(0.0, min($plan['energy_fcfa'], $available - $this->usedBetween($user, $previous, $start))), 2);
    }

    /**
     * Whether validated payments of this formula cover the whole period (an hour of tolerance).
     */
    private function paidDuring(User $user, string $planKey, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->remembered("paid:{$user->id}:{$planKey}:{$from->getTimestamp()}:{$to->getTimestamp()}", fn () => $this->wasPaidDuring($user, $planKey, $from, $to));
    }

    private function wasPaidDuring(User $user, string $planKey, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        $subscriptions = app(Subscriptions::class);
        $covered = $user->payments()
            ->where('status', Payment::STATUS_VALIDATED)
            ->where('starts_at', '<', $to)
            ->where('expires_at', '>', $from)
            ->get(['plan', 'starts_at', 'expires_at'])
            ->filter(fn (Payment $payment) => $subscriptions->currentKey((string) $payment->plan) === $planKey)
            ->sum(fn (Payment $payment) => max(0, CarbonImmutable::parse($payment->starts_at)->max($from)->diffInSeconds(CarbonImmutable::parse($payment->expires_at)->min($to))));

        return $covered >= $from->diffInSeconds($to) - 3600;
    }

    /**
     * Energy left, from 0 to 100 %.
     */
    public function percentLeft(User $user): int
    {
        $limit = $this->plan($user)['energy_fcfa'] + $this->carriedOverFcfa($user);

        if ($limit <= 0) {
            return 0;
        }

        // Un dépassement de la période précédente réduit l'énergie de celle-ci (limitFcfa).
        return (int) max(0, min(100, round(100 * ($this->limitFcfa($user) - $this->usedFcfa($user)) / $limit)));
    }

    /**
     * No energy left (welcome bonus included): the NEXT request is refused, never a request already started.
     */
    public function isExhausted(User $user): bool
    {
        return $this->bonusLeftFcfa($user) <= 0 && $this->usedFcfa($user) >= $this->limitFcfa($user);
    }

    /**
     * Energy used, counting also the computer of a request that is still running (its time is only
     * recorded when the request ends).
     */
    public function usedDuringRun(User $user, AgentRun $run): float
    {
        return $this->usedFcfa($user) + $this->runningComputerFcfa($run);
    }

    private function runningComputerFcfa(AgentRun $run): float
    {
        $running = $run->started_at ? (int) max(0, $run->started_at->diffInSeconds(now())) : 0;

        return $this->costs->toFcfa($this->costs->computerCost($running));
    }

    /** Overdraft allowed for one request, in FCFA (Administration → Réglages). */
    public function overdraftMaxFcfa(): float
    {
        return max(0.0, $this->settings->float('overdraft_max_fcfa'));
    }

    /**
     * Energy a running request has used beyond what was left (welcome bonus included), in FCFA (0 when it is still
     * within the energy left).
     */
    public function overdraftDuringRun(User $user, AgentRun $run): float
    {
        $over = $this->usedFcfa($user) + $this->runningComputerFcfa($run) - $this->limitFcfa($user);

        return round(max(0.0, $over), 2) * ($this->bonusLeftFcfa($user) > 0 ? 0 : 1);
    }

    /**
     * Why a running request must finish now, or null when it may go on. A creation or a change is never cut when
     * the energy runs out (15 November 2026): it goes on, and the overdraft is taken from the next energy. Only past
     * the overdraft allowed for one request (« garde_fou »), or far past the daily ceiling of the free formula, does
     * Kekeli finish the current step cleanly and write its report.
     */
    public function stopReason(User $user, AgentRun $run): ?string
    {
        if ($this->overdraftDuringRun($user, $run) > $this->overdraftMaxFcfa()) {
            return AgentRun::STOP_GUARD;
        }

        $cap = $this->settings->float('free_daily_cap_fcfa');

        return $this->isFree($user) && $cap > 0 && $this->freeUsedTodayFcfa() >= $cap + $this->overdraftMaxFcfa() ? AgentRun::STOP_GUARD : null;
    }

    /** Discussions simples par jour d'une formule ajoutée dans l'administration sans réglage. */
    public const DEFAULT_SIMPLE_CHATS = 100;

    /**
     * Simple discussions of the client today (Lomé day): answers written without any tool, which do not use energy.
     */
    public function simpleChatsToday(User $user): int
    {
        return AgentRun::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->where('simple_chat', true)
            ->where('created_at', '>=', CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc())
            ->count();
    }

    /**
     * Simple discussions left today for the client (the safeguard against abuse, set for each formula).
     */
    public function simpleChatsLeft(User $user): int
    {
        return max(0, $this->plan($user)['simple_chats_per_day'] - $this->simpleChatsToday($user));
    }

    /**
     * When the simple discussions come back: midnight, Lomé time (« 00 h 00 »).
     */
    public function simpleChatsResetText(): string
    {
        return CarbonImmutable::now(config('kekeli.display_timezone'))->addDay()->startOfDay()->format('H \h i');
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
            ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd + image_cost_usd + web_search_cost_usd + voice_cost_usd), 0) as total')
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
