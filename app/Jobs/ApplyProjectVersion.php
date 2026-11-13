<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks the Runner to put the files of a version in the computer of the project (« voir », « revenir », « remettre »):
 * the computer is woken up if needed, the preview follows. No model is asked: no energy of model is used.
 */
class ApplyProjectVersion implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(public Project $project, public string $action, public ?int $number) {}

    public function handle(RunnerClient $runner): void
    {
        $runner->applyVersion($this->project->fresh(), $this->action, $this->number);
    }

    public function failed(?Throwable $exception): void
    {
        $this->project->timestamps = false;
        $this->project->forceFill(['version_busy_at' => null])->save();
    }
}
