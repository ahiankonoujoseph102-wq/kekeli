<?php

namespace App\Agent;

use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Starts a request to the agent on behalf of the project's owner, outside of the project page (for example when
 * Kekeli fixes a failed automatic check of GitHub). The work is counted in the owner's energy, as usual.
 */
class RunStarter
{
    public function __construct(private Energy $energy) {}

    /**
     * Returns the request, or null when it cannot start (energy used up, or another request still running).
     */
    public function start(Project $project, string $content, ?string $instructions = null, ?string $purpose = null): ?AgentRun
    {
        if (! $this->canStart($project)) {
            return null;
        }

        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $content]);

        return $this->startWith($project, $message, $instructions, $purpose);
    }

    /**
     * Starts the work on a message already written by the client (for example after the first responder chose a tool).
     */
    public function startWith(Project $project, Message $message, ?string $instructions = null, ?string $purpose = null): ?AgentRun
    {
        if (! $this->canStart($project)) {
            return null;
        }

        $run = DB::transaction(function () use ($project, $message, $instructions, $purpose) {
            $project->touch();
            $this->energy->startWindowIfNeeded($project->user);

            return $project->agentRuns()->create(['message_id' => $message->id, 'instructions' => $instructions, 'purpose' => $purpose]);
        });

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run);
        }, report: true);

        return $run;
    }

    /**
     * Energy left and no other request running.
     */
    private function canStart(Project $project): bool
    {
        $owner = $project->user;

        if (($this->energy->isFree($owner) && $this->energy->freeCapReached()) || $this->energy->isExhausted($owner)) {
            return false;
        }

        return ! $project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists();
    }
}
