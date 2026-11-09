<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Places of Kekeli: a limited number of requests are handled at the same time (all users together).
 * The next ones wait for their turn, in the order they were sent, and leave automatically, without any loss.
 */
class AgentPlaces
{
    /** A request given to the Runner without any news for this long no longer holds a place (lost request). */
    private const SILENCE_MINUTES = 60;

    /** A waiting request whose job has not checked in for this long is ignored (its job no longer exists). */
    private const WAITING_HEARTBEAT_MINUTES = 2;

    public function __construct(private Settings $settings) {}

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
     * Requests waiting for a place and sent before this one.
     */
    public function ahead(AgentRun $run): int
    {
        return $this->waiting()
            ->whereKeyNot($run->getKey())
            ->where(fn (Builder $query) => $query->where('created_at', '<', $run->created_at)
                ->orWhere(fn (Builder $same) => $same->where('created_at', $run->created_at)->where('id', '<', $run->id)))
            ->count();
    }

    /**
     * Whether this request may be given to the Runner now (a place is free and nobody waits before it).
     */
    public function canStart(AgentRun $run): bool
    {
        $limit = $this->limit();

        return $limit === 0 || $run->sent_at !== null || $this->ahead($run) < $limit - $this->occupied();
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
            ->where('updated_at', '>=', now()->subMinutes(self::WAITING_HEARTBEAT_MINUTES));
    }
}
