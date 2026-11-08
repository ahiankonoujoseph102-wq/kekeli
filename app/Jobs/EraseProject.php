<?php

namespace App\Jobs;

use App\Agent\ProjectBackup;
use App\Agent\RunnerClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A deleted project: its computers (running or paused) and its backup are erased for good.
 */
class EraseProject implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 1800];

    public function __construct(public string $projectId) {}

    public function handle(RunnerClient $runner, ProjectBackup $backups): void
    {
        $backups->delete($this->projectId);
        $runner->erase($this->projectId);
    }
}
