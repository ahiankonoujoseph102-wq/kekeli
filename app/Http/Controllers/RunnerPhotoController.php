<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Photos sent by the client, fetched by the Runner (signed request) to copy them into the project's computer.
 */
class RunnerPhotoController extends Controller
{
    public function show(Project $project, ProjectPhoto $photo): Response
    {
        abort_unless($photo->project_id === $project->id, 404);
        abort_unless(Storage::disk(ProjectPhoto::disk())->exists($photo->path), 404);

        return Storage::disk(ProjectPhoto::disk())->download($photo->path, $photo->name);
    }

    /**
     * The logo saved in the memory of the project's owner, laid as it is on a poster (only while his memory is on).
     */
    public function memoryLogo(Project $project): Response
    {
        $user = $project->user;
        abort_unless($user->memory_enabled && filled($user->memory_logo_path) && Storage::disk(ProjectPhoto::disk())->exists($user->memory_logo_path), 404);

        return Storage::disk(ProjectPhoto::disk())->download($user->memory_logo_path, 'logo.'.pathinfo($user->memory_logo_path, PATHINFO_EXTENSION));
    }
}
