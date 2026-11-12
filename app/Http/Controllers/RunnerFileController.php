<?php

namespace App\Http\Controllers;

use App\Agent\ProjectFiles;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One file of the last backup of a project, read by the first responder to answer a question about the code
 * (« où est la page de connexion ? »), without changing anything. Temporary signed address (10 minutes) given by the
 * site with the question; the path itself is not signed, but only the files of the backup can be read (never .env
 * nor the test database, see ProjectFiles), and only text.
 */
class RunnerFileController extends Controller
{
    public function show(Request $request, Project $project, ProjectFiles $files): JsonResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['chemin']), 403);

        $path = trim((string) $request->query('chemin', ''), '/ ');
        $content = $files->read($project)[$path] ?? null;

        if ($content === null || str_contains($content, "\0") || ! mb_check_encoding($content, 'UTF-8')) {
            return response()->json(['erreur' => 'introuvable'], 404);
        }

        return response()->json(['chemin' => $path, 'contenu' => mb_substr($content, 0, 20000)]);
    }
}
