<?php

namespace App\WebApps;

use App\Models\Project;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ZIP file of a Laravel web application, ready for shared hosting (cPanel, Hostinger): the application with its
 * libraries (vendor), without any secret (.env) nor test database. Built by the Runner, sent in signed parts.
 */
class HostingPackage
{
    public const BUILDING = 'building';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public function disk(): Filesystem
    {
        return Storage::disk(config('kekeli.backups.disk'));
    }

    public function path(string $projectId): string
    {
        return 'paquets/'.$projectId.'.zip';
    }

    private function partPath(string $projectId, int $part): string
    {
        return 'paquets/'.$projectId.'.parties/'.$part;
    }

    /**
     * Keep one part; once every part is there, put them together and replace the previous file.
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
        $disk->deleteDirectory('paquets/'.$project->id.'.parties');
        $project->forceFill(['package_size' => strlen($whole), 'package_at' => now()])->save();

        return true;
    }

    public function exists(Project $project): bool
    {
        return $project->package_at !== null && $this->disk()->exists($this->path($project->id));
    }

    public function download(Project $project): StreamedResponse
    {
        return $this->disk()->download($this->path($project->id), Str::slug($project->name).'-hebergement.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function delete(string $projectId): void
    {
        $this->disk()->delete($this->path($projectId));
        $this->disk()->deleteDirectory('paquets/'.$projectId.'.parties');
    }
}
