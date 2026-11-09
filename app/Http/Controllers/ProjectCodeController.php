<?php

namespace App\Http\Controllers;

use App\Agent\Energy;
use App\Agent\ProjectFiles;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download of the code of a project in a ZIP file (paid formulas).
 */
class ProjectCodeController extends Controller
{
    public function zip(Project $project, ProjectFiles $files, Energy $energy): BinaryFileResponse
    {
        abort_unless($project->user_id === Auth::id(), 404);
        abort_if($energy->isFree(Auth::user()), 403);

        try {
            $path = $files->zip($project);
        } catch (\RuntimeException) {
            abort(404);
        }

        return response()->download($path, $project->pagesName().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
