<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A photo sent by the client, shown in their project (only to the owner and to an administrator).
 */
class ProjectPhotoController extends Controller
{
    public function show(Request $request, Project $project, ProjectPhoto $photo): Response
    {
        abort_unless($project->user()->is($request->user()) || $request->user()->can('admin'), 404);
        abort_unless($photo->project_id === $project->id, 404);
        abort_unless(Storage::disk(ProjectPhoto::disk())->exists($photo->path), 404);

        return Storage::disk(ProjectPhoto::disk())->response($photo->path, $photo->name, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
