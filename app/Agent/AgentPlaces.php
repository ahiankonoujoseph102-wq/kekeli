<?php

namespace App\Agent;

use App\Billing\PlanPerks;
use App\Models\AgentRun;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places of Kekeli: a limited number of requests are handled at the same time (all users together).
 * The next ones wait for their turn, in the order they were sent, and leave automatically, without any loss.
 *
 * Priority by formula: when requests wait, the most expensive formula passes first (Agence, then Expert, then Pro,
 * then Gratuite), each level in the order the requests were sent; and when the free quota of the AI providers gets
 * low, free requests may only use part of the places.
 */
class AgentPlaces
{
    /** A request given to the Runner without any news for this long no longer holds a place (lost request). */
    private const SILENCE_MINUTES = 60;

    /** A waiting request whose job has not checked in for this long is ignored (its job no longer exists). */
    private const WAITING_HEARTBEAT_MINUTES = 2;

    public function __construct(private Settings $settings, private Energy $energy, private ProviderList $providers, private PlanPerks $perks) {}

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
     * Requests waiting for a place and passing before this one: all the waiting requests of a higher formula, then
     * those of the same formula sent before it.
     */
    public function ahead(AgentRun $run): int
    {
        $before = fn (Builder $query) => $query->where('created_at', '<', $run->created_at)
            ->orWhere(fn (Builder $same) => $same->where('created_at', $run->created_at)->where('id', '<', $run->id));
        $level = $this->levelOf($run);

        return $this->atLevels($this->waiting()->whereKeyNot($run->getKey()), fn (int $other) => $other > $level)->count()
            + $this->atLevels($this->waiting()->whereKeyNot($run->getKey())->where($before), fn (int $other) => $other === $level)->count();
    }

    /** Priority level of the formula of the owner of a request (0: free). */
    public function levelOf(AgentRun $run): int
    {
        $user = $run->project?->user;

        return $user ? $this->perks->level($user) : 0;
    }

    /**
     * Whether this request may be given to the Runner now (a place is free and nobody waits before it).
     * When the free quota of the day is low, free requests only use part of the places.
     */
    public function canStart(AgentRun $run): bool
    {
        $limit = $this->limit();

        if ($run->sent_at !== null) {
            return true;
        }
        // Projets menés en même temps : un pour Gratuite et Pro, plusieurs pour Expert et Agence (réglable).
        if ($this->waitsForOwnRun($run)) {
            return false;
        }
        if ($limit === 0) {
            return true;
        }
        if (! $this->isPaid($run) && $this->freeQuotaLow() && $this->occupiedByFree() >= $this->freePlacesWhenLow()) {
            return false;
        }

        return $this->ahead($run) < $limit - $this->occupied();
    }

    /**
     * Whether the owner already has as many requests at work as his formula allows at the same time: this one
     * waits for one of them to end (it starts by itself, nothing is lost).
     */
    public function waitsForOwnRun(AgentRun $run): bool
    {
        $user = $run->project?->user;
        if (! $user || $run->purpose === AgentRun::PURPOSE_QUESTION) {
            return false;
        }

        $atWork = AgentRun::query()
            ->whereKeyNot($run->getKey())
            ->whereIn('status', [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING])
            ->whereNotNull('sent_at')
            ->where('updated_at', '>=', now()->subMinutes(self::SILENCE_MINUTES))
            ->where(fn (Builder $query) => $query->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))
            ->whereHas('project', fn (Builder $projects) => $projects->where('user_id', $user->id))
            ->count();

        return $atWork >= $this->energy->plan($user)['parallel_runs'];
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
     * Requests of users with the free formula (or an unknown formula, or a paid formula already over).
     */
    private function free(Builder $query): Builder
    {
        return $this->atLevels($query, fn (int $level) => $level === 0);
    }

    /**
     * Requests of users whose formula has one of the kept priority levels. A paid formula whose 30 days are over
     * counts as free, like an unknown formula; an older formula (Découverte, Entreprise) as the one that replaced it.
     */
    private function atLevels(Builder $query, callable $keep): Builder
    {
        $replaced = (array) $this->settings->get('replaced_plans');
        $paid = [];
        $kept = [];
        foreach ($this->perks->levels() as $level => $keys) {
            if ($level === 0) {
                continue;
            }
            $withOlder = [...$keys, ...array_keys(array_intersect($replaced, $keys))];
            $paid = [...$paid, ...$withOlder];
            if ($keep($level)) {
                $kept = [...$kept, ...$withOlder];
            }
        }
        $keepFree = $keep(0);

        return $query->whereHas('project.user', fn (Builder $users) => $users->where(function (Builder $users) use ($paid, $kept, $keepFree) {
            if (! $kept && ! $keepFree) {
                $users->whereRaw('1 = 0');
            }
            if ($kept) {
                $users->orWhere(fn (Builder $active) => $active->whereIn('plan', $kept)
                    ->where(fn (Builder $date) => $date->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>', now())));
            }
            if ($keepFree) {
                $users->orWhere(fn (Builder $free) => $free->whereNull('plan')->orWhereNotIn('plan', $paid)
                    ->orWhere(fn (Builder $over) => $over->whereIn('plan', $paid)->where('plan_expires_at', '<=', now())));
            }
        }));
    }

    /**
     * Position of a request in the waiting line (1 = next one), or null if it does not wait.
     */
    public function position(AgentRun $run): ?int
    {
        if ($run->status !== AgentRun::STATUS_QUEUED || $run->sent_at !== null || $this->canStart($run) || ($this->limit() === 0 && ! $this->waitsForOwnRun($run))) {
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
