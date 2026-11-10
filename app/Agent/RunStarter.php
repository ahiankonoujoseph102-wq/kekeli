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
        $owner = $project->user;

        if (($this->energy->isFree($owner) && $this->energy->freeCapReached()) || $this->energy->isExhausted($owner)) {
            return null;
        }

        if ($project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            return null;
        }

        $run = DB::transaction(function () use ($project, $content, $instructions, $purpose, $owner) {
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $content]);
            $project->touch();
            $this->energy->startWindowIfNeeded($owner);

            return $project->agentRuns()->create(['message_id' => $message->id, 'instructions' => $instructions, 'purpose' => $purpose]);
        });

        rescue(fn () => SendAgentRun::dispatch($run), report: true);

        return $run;
    }
}
