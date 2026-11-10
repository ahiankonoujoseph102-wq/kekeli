<?php

namespace App\Http\Controllers;

use App\Github\RepositoryClient;
use App\Models\Project;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer mode: the archive of the client's repository, fetched by the Runner (signed request) to open it in the
 * virtual computer. The client's GitHub access key stays here: it is never sent to the Runner nor to the computer.
 */
class RunnerRepositoryController extends Controller
{
    public function archive(Project $project, RepositoryClient $github): Response
    {
        abort_unless($project->isRepository() && $project->repository_base_sha, 404);

        try {
            $archive = $github->archive($project, $project->user->github_token ?: null);
        } catch (RequestException $exception) {
            abort($exception->response->status() === 404 ? 404 : 502);
        }

        return response($archive, 200, ['Content-Type' => 'application/gzip', 'Cache-Control' => 'no-store']);
    }
}
