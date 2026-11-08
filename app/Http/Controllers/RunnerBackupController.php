<?php

namespace App\Http\Controllers;

use App\Agent\ProjectBackup;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backups of the project's files, sent by the Runner after each request (in parts of 4 MB),
 * and fetched by the Runner to put them back in a new computer.
 */
class RunnerBackupController extends Controller
{
    public function store(Request $request, Project $project, ProjectBackup $backups): JsonResponse
    {
        $limits = config('kekeli.backups');

        $data = $request->validate([
            'partie' => ['required', 'integer', 'min:1', 'max:'.$limits['max_parts']],
            'parties' => ['required', 'integer', 'min:1', 'max:'.$limits['max_parts'], 'gte:partie'],
        ]);

        $content = $request->getContent();
        abort_if(strlen($content) > $limits['part_size'], 413);

        $complete = $backups->storePart($project, (int) $data['partie'], (int) $data['parties'], $content);

        return response()->json(['ok' => true, 'complete' => $complete]);
    }

    public function show(Project $project, ProjectBackup $backups): Response
    {
        abort_unless($backups->exists($project), 404);

        return $backups->download($project);
    }
}
