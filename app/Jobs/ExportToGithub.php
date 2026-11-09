<?php

namespace App\Jobs;

use App\Agent\ProjectFiles;
use App\Github\GithubClient;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Sends the last backup of a project to the client's GitHub account (private repository).
 */
class ExportToGithub implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Project $project) {}

    public function handle(ProjectFiles $files, GithubClient $github): void
    {
        $project = $this->project->refresh();
        $user = $project->user;

        try {
            $code = $files->read($project);
            if (! isset($code['README.md'])) {
                $code['README.md'] = $this->readme($project);
            }

            $repository = $github->export($user, $project, $code);
        } catch (RequestException $exception) {
            // Accès retiré par le client sur GitHub : il faudra reconnecter le compte.
            if ($exception->response->status() === 401) {
                $user->forceFill(['github_token' => null])->save();
                $this->fail($project, 'github_deconnecte');

                return;
            }
            $this->fail($project, 'GitHub : code '.$exception->response->status());

            return;
        } catch (Throwable $exception) {
            $this->fail($project, $exception->getMessage());

            return;
        }

        $project->forceFill([
            'github_repo' => $repository,
            'github_status' => Project::GITHUB_DONE,
            'github_error' => null,
            'github_exported_at' => now(),
        ])->save();
    }

    private function fail(Project $project, string $error): void
    {
        report(new \RuntimeException('Envoi vers GitHub du projet '.$project->id.' : '.$error));

        $project->forceFill(['github_status' => Project::GITHUB_FAILED, 'github_error' => mb_substr($error, 0, 500)])->save();
    }

    private function readme(Project $project): string
    {
        return '# '.$project->name."\n\n"
            ."Site construit avec Kekeli.\n\n"
            ."Ce dossier contient tout le code du site. Pour le voir, ouvrez le fichier `index.html` dans un navigateur.\n"
            ."Pour le mettre en ligne, vous pouvez utiliser l'hébergeur de votre choix (par exemple Cloudflare Pages,\n"
            ."GitHub Pages ou Netlify) : il suffit de lui donner ce dépôt.\n\n"
            ."Les images et leurs licences sont décrites dans le fichier `LICENCES.md`, s'il existe.\n";
    }
}
