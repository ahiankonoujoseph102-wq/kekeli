<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks the Runner to wake the project's computer so the user can see the preview again.
 */
class WakePreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(public Project $project) {}

    public function handle(RunnerClient $runner): void
    {
        $runner->wakePreview($this->project);
    }

    public function failed(?Throwable $exception): void
    {
        $this->project->timestamps = false;
        $this->project->forceFill(['preview_waking_at' => null])->save();
    }
}
