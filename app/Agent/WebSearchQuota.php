<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Searches on the Internet left for a client: a number per day according to the formula (few for the free formula,
 * more for the paid ones) and a monthly ceiling for all clients together, set to stay under the free quotas of the
 * search providers (Tavily about 1 000 searches a month, then Exa). Both are set in Administration → Réglages.
 */
class WebSearchQuota
{
    /** Searches per day when a formula does not say it (formulas saved before the 12th of November 2026). */
    public const DEFAULT_PER_DAY = ['gratuite' => 3, 'pro' => 20, 'expert' => 40, 'agence' => 80];

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

    public function usedThisMonth(): int
    {
        return (int) AgentRun::query()->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())->sum('web_searches');
    }

    /**
     * Searches the client may still do now (0 when the day's or the month's limit is reached).
     */
    public function remaining(User $user): int
    {
        $monthly = max(0, $this->settings->int('web_search_monthly_cap') - $this->usedThisMonth());

        return max(0, min($this->perDay($user) - $this->usedToday($user), $monthly));
    }
}
