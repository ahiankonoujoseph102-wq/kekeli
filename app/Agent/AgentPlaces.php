<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places of Kekeli: a limited number of requests are handled at the same time (all users together).
 * The next ones wait for their turn, in the order they were sent, and leave automatically, without any loss.
 *
 * Paid clients first: when requests wait, those of paid formulas pass before those of the free formula; and
 * when the free quota of the AI providers gets low, free requests may only use part of the places.
 */
class AgentPlaces
{
    /** A request given to the Runner without any news for this long no longer holds a place (lost request). */
    private const SILENCE_MINUTES = 60;

    /** A waiting request whose job has not checked in for this long is ignored (its job no longer exists). */
    private const WAITING_HEARTBEAT_MINUTES = 2;

    public function __construct(private Settings $settings, private Energy $energy, private ProviderList $providers) {}

    public function limit(): int
    {
        return max(0, $this->settings->int('max_concurrent_runs'));
    }

    /**
     * Requests given to the Runner and not finished yet.
     */
    public function occupied(): int
    {
        return AgentRun::query()
            ->whereIn('status', [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING])
            ->whereNotNull('sent_at')
            ->where('updated_at', '>=', now()->subMinutes(self::SILENCE_MINUTES))
            ->count();
    }

    /**
     * Requests waiting for a place and passing before this one: the paid requests sent before it if it is paid;
     * all the waiting paid requests, then the free ones sent before it, if it is free.
     */
    public function ahead(AgentRun $run): int
    {
        $before = fn (Builder $query) => $query->where('created_at', '<', $run->created_at)
            ->orWhere(fn (Builder $same) => $same->where('created_at', $run->created_at)->where('id', '<', $run->id));

        if ($this->isPaid($run)) {
            return $this->paid($this->waiting()->whereKeyNot($run->getKey())->where($before))->count();
        }

        return $this->paid($this->waiting()->whereKeyNot($run->getKey()))->count()
            + $this->free($this->waiting()->whereKeyNot($run->getKey())->where($before))->count();
    }

    /**
     * Whether this request may be given to the Runner now (a place is free and nobody waits before it).
     * When the free quota of the day is low, free requests only use part of the places.
     */
    public function canStart(AgentRun $run): bool
    {
        $limit = $this->limit();

        if ($limit === 0 || $run->sent_at !== null) {
            return true;
        }
        if (! $this->isPaid($run) && $this->freeQuotaLow() && $this->occupiedByFree() >= $this->freePlacesWhenLow()) {
            return false;
        }

        return $this->ahead($run) < $limit - $this->occupied();
    }

    /**
     * The free quota of the AI providers is getting low today (requests counted against the daily limits).
     */
    public function freeQuotaLow(): bool
    {
        $threshold = $this->settings->float('paid_priority_quota_percent');
        $limit = 0;
        $used = 0;

        foreach ($this->providers->entries() as $entry) {
            if (! $entry['daily_limit']) {
                continue;
            }
            $limit += $entry['daily_limit'];
            $used += (int) ProviderUsage::query()
                ->whereDate('day', $this->providers->quotaDay($entry['provider']))
                ->where(['provider' => $entry['provider'], 'model' => $entry['model']])
                ->value('requests');
        }

        return $threshold > 0 && $limit > 0 && $used * 100 >= $limit * $threshold;
    }

    /**
     * Places that free requests may use when the free quota is low (at least one).
     */
    public function freePlacesWhenLow(): int
    {
        return max(1, (int) floor($this->limit() * $this->settings->float('free_places_when_quota_low_percent') / 100));
    }

    public function isPaid(AgentRun $run): bool
    {
        $user = $run->project?->user;

        return $user !== null && ! $this->energy->isFree($user);
    }

    private function occupiedByFree(): int
    {
        return $this->free(AgentRun::query()
            ->whereIn('status', [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING])
            ->whereNotNull('sent_at')
            ->where('updated_at', '>=', now()->subMinutes(self::SILENCE_MINUTES)))->count();
    }

    /**
     * Requests of users with a paid formula.
     */
    private function paid(Builder $query): Builder
    {
        return $query->whereHas('project.user', fn (Builder $users) => $users->whereIn('plan', $this->paidPlanKeys()));
    }

    /**
     * Requests of users with the free formula (or an unknown formula, which counts as free).
     */
    private function free(Builder $query): Builder
    {
        return $query->whereHas('project.user', fn (Builder $users) => $users->whereNotIn('plan', $this->paidPlanKeys())->orWhereNull('plan'));
    }

    /**
     * @return list<string>
     */
    private function paidPlanKeys(): array
    {
        return array_values(array_diff(array_keys($this->settings->get('plans')), $this->energy->freePlanKeys()));
    }

    /**
     * Position of a request in the waiting line (1 = next one), or null if it does not wait.
     */
    public function position(AgentRun $run): ?int
    {
        if ($this->limit() === 0 || $run->status !== AgentRun::STATUS_QUEUED || $run->sent_at !== null || $this->canStart($run)) {
            return null;
        }

        return $this->ahead($run) + 1;
    }

    private function waiting(): Builder
    {
        return AgentRun::query()
            ->where('status', AgentRun::STATUS_QUEUED)
            ->whereNull('sent_at')
            // Une question ne prend aucune place : elle ne lance pas d'ordinateur.
            ->where(fn (Builder $query) => $query->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))
            ->where('updated_at', '>=', now()->subMinutes(self::WAITING_HEARTBEAT_MINUTES));
    }
}
