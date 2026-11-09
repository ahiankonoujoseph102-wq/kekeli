<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Erases the computer of a project paused for a long time. The project and its backup stay in
 * Kekeli: at the next request, a new computer receives the backup, without any loss.
 */
class ErasePausedComputer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Project $project) {}

    public function handle(RunnerClient $runner): void
    {
        $project = $this->project->refresh();

        // Le projet a pu être repris entre-temps : on n'efface jamais un ordinateur en service.
        if ($project->hasLivePreview() || $project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            return;
        }

        $runner->erase($project->id);

        $project->timestamps = false;
        $project->forceFill(['computer_erased_at' => now()])->save();
    }
}
