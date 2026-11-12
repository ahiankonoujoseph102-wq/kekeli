<?php

namespace App\Jobs;

use App\Agent\ProjectBackup;
use App\Agent\RunnerClient;
use App\Models\ProjectPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * A deleted project: its computers (running or paused), its backup and the client's photos are erased for good.
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
        Storage::disk(ProjectPhoto::disk())->deleteDirectory(config('kekeli.client_photos.directory').'/'.$this->projectId);
        // Documents et présentations (toutes les versions) et fichiers joints par le client.
        Storage::disk(ProjectPhoto::disk())->deleteDirectory('documents/'.$this->projectId);
        Storage::disk(ProjectPhoto::disk())->deleteDirectory('pieces-jointes/'.$this->projectId);
        $runner->erase($this->projectId);
    }
}
