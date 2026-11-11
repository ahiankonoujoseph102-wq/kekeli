<?php

namespace App\Http\Controllers;

use App\Agent\ProjectFiles;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download of the code of a project in a ZIP file (free, for every formula).
 */
class ProjectCodeController extends Controller
{
    public function zip(Project $project, ProjectFiles $files): BinaryFileResponse
    {
        abort_unless($project->user_id === Auth::id(), 404);

        try {
            $path = $files->zip($project);
        } catch (\RuntimeException) {
            abort(404);
        }

        return response()->download($path, $project->pagesName().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
