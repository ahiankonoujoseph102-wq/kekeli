<?php

namespace App\Agent;

use App\Models\Project;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Where the backups of the projects' files are kept.
 */
class ProjectBackup
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('kekeli.backups.disk'));
    }

    public function path(string $projectId): string
    {
        return config('kekeli.backups.directory').'/'.$projectId.'.tar.gz';
    }

    private function partPath(string $projectId, int $part): string
    {
        return config('kekeli.backups.directory').'/'.$projectId.'.parties/'.$part;
    }

    /**
     * Keep one part; once every part is there, put them together and replace the previous backup.
     */
    public function storePart(Project $project, int $part, int $parts, string $content): bool
    {
        $disk = $this->disk();
        $disk->put($this->partPath($project->id, $part), $content);

        foreach (range(1, $parts) as $number) {
            if (! $disk->exists($this->partPath($project->id, $number))) {
                return false;
            }
        }

        $whole = '';
        foreach (range(1, $parts) as $number) {
            $whole .= $disk->get($this->partPath($project->id, $number));
        }

        $disk->put($this->path($project->id), $whole);
        $disk->deleteDirectory(config('kekeli.backups.directory').'/'.$project->id.'.parties');

        $project->timestamps = false;
        $project->forceFill(['backup_size' => strlen($whole), 'backup_at' => now()])->save();
        $project->timestamps = true;

        return true;
    }

    public function exists(Project $project): bool
    {
        return $project->backup_at !== null && $this->disk()->exists($this->path($project->id));
    }

    public function download(Project $project): StreamedResponse
    {
        return $this->disk()->response($this->path($project->id), $project->id.'.tar.gz', [
            'Content-Type' => 'application/gzip',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Delete the backup of a deleted project.
     */
    public function delete(string $projectId): void
    {
        $this->disk()->delete($this->path($projectId));
        $this->disk()->deleteDirectory(config('kekeli.backups.directory').'/'.$projectId.'.parties');
    }
}
