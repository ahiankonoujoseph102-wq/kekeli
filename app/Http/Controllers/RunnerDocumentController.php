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
    /** Largest request (a video is sent in parts of 8 MB). */
    public const MAX_BYTES = 30 * 1024 * 1024;

    /** Largest video, all its parts together. */
    public const MAX_VIDEO_BYTES = 400 * 1024 * 1024;

    public function store(Request $request, Project $project, string $run): JsonResponse
    {
        $agentRun = AgentRun::query()->whereKey($run)->where('project_id', $project->id)->firstOrFail();
        abort_unless($agentRun->isDocumentWork() && $agentRun->isActive(), 409);

        // Seulement les noms produits par le moteur de rendu : jamais un chemin.
        $name = (string) $request->query('nom');
        abort_unless((bool) preg_match(DocumentVersion::FILE_NAMES, $name), 422);
        $content = $request->getContent();
        abort_if($content === '' || strlen($content) > self::MAX_BYTES, 413);

        $disk = Storage::disk(ProjectPhoto::disk());
        $path = DocumentVersion::folder($project, $agentRun->id).'/'.$name;

        // Une vidéo arrive en morceaux, dans l'ordre : chacun est gardé à part, puis le dernier les réunit (par flux,
        // sans tout charger en mémoire).
        $parts = (int) $request->query('parties', 1);
        if ($parts > 1) {
            $part = (int) $request->query('partie', 0);
            abort_unless($part >= 0 && $part < $parts && $parts <= 60, 422);
            $disk->put($path.'.partie-'.$part, $content);
            if ($part === $parts - 1) {
                $total = 0;
                for ($i = 0; $i < $parts; $i++) {
                    abort_unless($disk->exists($path.'.partie-'.$i), 409);
                    $total += $disk->size($path.'.partie-'.$i);
                }
                abort_if($total > self::MAX_VIDEO_BYTES, 413);
                $joined = tmpfile();
                for ($i = 0; $i < $parts; $i++) {
                    $stream = $disk->readStream($path.'.partie-'.$i);
                    stream_copy_to_stream($stream, $joined);
                    fclose($stream);
                    $disk->delete($path.'.partie-'.$i);
                }
                rewind($joined);
                $disk->writeStream($path, $joined);
                fclose($joined);
            }

            return response()->json(['ok' => true]);
        }

        $disk->put($path, $content);

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

    /**
     * An image created by Kekeli for a scene of a previous version of a video, read by the Runner to keep it when the
     * client asks for a change that does not concern it.
     */
    public function media(Project $project, DocumentVersion $version, string $name): StreamedResponse
    {
        abort_unless($version->project_id === $project->id && preg_match('/^media-\d{1,2}\.jpg$/', $name) && in_array($name, (array) $version->files, true), 404);

        return Storage::disk(ProjectPhoto::disk())->download($version->path($name), $name);
    }

    public function attachment(Project $project, ProjectAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);

        return Storage::disk(ProjectPhoto::disk())->download($attachment->path, $attachment->name);
    }
}
