<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectPhoto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads of a version (Word, PowerPoint, PDF) and the images of its pages, for the owner (and the administrator,
 * read only). Every version stays available.
 */
class DocumentController extends Controller
{
    public function download(Project $project, int $number, string $format): Response
    {
        $version = $this->version($project, $number);
        $name = (string) data_get($version->files, $format);
        abort_unless($name !== '' && array_key_exists($format, DocumentVersion::FORMATS), 404);

        return Storage::disk(ProjectPhoto::disk())->download($version->path($name), $version->downloadName($format));
    }

    public function page(Project $project, int $number, int $page): Response
    {
        $version = $this->version($project, $number);
        $name = (array) $version->previews;
        abort_unless($page >= 1 && $page <= count($name), 404);

        return Storage::disk(ProjectPhoto::disk())->response($version->path($name[$page - 1]), null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function version(Project $project, int $number): DocumentVersion
    {
        $user = Auth::user();
        abort_unless($project->user_id === $user->id || $user->is_admin, 404);

        return $project->documentVersions()->where('number', $number)->firstOrFail();
    }
}
