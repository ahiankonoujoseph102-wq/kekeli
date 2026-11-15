<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Searches on the Internet left for a client: a number of questions per day according to the formula (few for the
 * free formula, more for the paid ones) — one question is ONE search, however many requests Kekeli sends — and a
 * monthly ceiling of provider requests for all clients together, set to stay under the free offers (Tavily 1 000
 * credits a month, then Exa 10 $ a month). Both are set in Administration → Réglages.
 */
class WebSearchQuota
{
    /** Searches per day when a formula does not say it (formulas saved before the 12th of November 2026). */
    public const DEFAULT_PER_DAY = ['gratuite' => 3, 'pro' => 20, 'expert' => 40, 'agence' => 80];

    /** Requests Kekeli may send to the providers for one question (same as REQUETES_MAX_PAR_QUESTION in the Runner). */
    public const REQUESTS_PER_QUESTION = 5;

    public function __construct(private Settings $settings, private Energy $energy) {}

    public function perDay(User $user): int
    {
        $plan = $this->energy->plan($user);

        return max(0, (int) ($plan['web_searches_per_day'] ?? self::DEFAULT_PER_DAY[$plan['key']] ?? self::DEFAULT_PER_DAY['gratuite']));
    }

    public function usedToday(User $user): int
    {
        $midnight = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();

        return (int) AgentRun::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->where('created_at', '>=', $midnight)
            ->sum('web_searches');
    }

    /** Requests sent to the search providers this month, for all clients (the free offers count them). */
    public function usedThisMonth(): int
    {
        return (int) AgentRun::query()->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())->sum('web_search_requests');
    }

    /** Requests to the search providers still allowed this month, for all clients together. */
    public function requestsLeftThisMonth(): int
    {
        return max(0, $this->settings->int('web_search_monthly_cap') - $this->usedThisMonth());
    }

    /**
     * Questions with a search the client may still ask today (0 when the day's limit or the month's ceiling is
     * reached).
     */
    public function remaining(User $user): int
    {
        if ($this->requestsLeftThisMonth() <= 0) {
            return 0;
        }

        return max(0, $this->perDay($user) - $this->usedToday($user));
    }

    /**
     * What the Runner receives for a message: the searches of the day left (one is enough for the whole question) and
     * the provider requests it may send for this question (5 at most, fewer when the month's ceiling is near).
     *
     * @return array{permise: bool, restantes: int, requetes_restantes: int}
     */
    public function forRunner(User $user): array
    {
        return ['permise' => true, 'restantes' => $this->remaining($user), 'requetes_restantes' => min(self::REQUESTS_PER_QUESTION, $this->requestsLeftThisMonth())];
    }
}
