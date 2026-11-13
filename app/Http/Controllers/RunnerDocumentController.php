<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files exchanged with the Runner for the documents (signed requests): the files of a new version, sent one by one
 * (Word, PowerPoint, PDF and the images of the pages), and the files joined by the client, read in the isolated computer.
 */
class RunnerDocumentController extends Controller
{
    /** Largest file of a version. */
    public const MAX_BYTES = 30 * 1024 * 1024;

    public function store(Request $request, Project $project, string $run): JsonResponse
    {
        $agentRun = AgentRun::query()->whereKey($run)->where('project_id', $project->id)->firstOrFail();
        abort_unless($agentRun->isDocumentWork() && $agentRun->isActive(), 409);

        // Seulement les noms produits par le moteur de rendu : jamais un chemin.
        $name = (string) $request->query('nom');
        abort_unless((bool) preg_match(DocumentVersion::FILE_NAMES, $name), 422);
        $content = $request->getContent();
        abort_if($content === '' || strlen($content) > self::MAX_BYTES, 413);

        Storage::disk(ProjectPhoto::disk())->put(DocumentVersion::folder($project, $agentRun->id).'/'.$name, $content);

        return response()->json(['ok' => true]);
    }

    /**
     * The image of the previous version of an image (its background, or the poster drawn by the generator), read by
     * the Runner to make the next version without creating the same image again.
     */
    public function background(Project $project, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->project_id === $project->id && filled($name = data_get($version->files, 'fond')), 404);

        return Storage::disk(ProjectPhoto::disk())->download($version->path($name), $name);
    }

    public function attachment(Project $project, ProjectAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);

        return Storage::disk(ProjectPhoto::disk())->download($attachment->path, $attachment->name);
    }
}
