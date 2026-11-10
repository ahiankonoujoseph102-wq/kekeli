<?php

namespace App\Jobs;

use App\Agent\ProjectFiles;
use App\Github\RepositoryClient;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Developer mode: sends Kekeli's work (the last backup of the project) to Kekeli's branch of the client's
 * repository, opens the pull request if needed, then watches GitHub's automatic checks.
 * Started by the client (« Send to GitHub »), or by Kekeli after fixing a failed check of that pull request.
 */
class PushRepository implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Project $project) {}

    public function handle(ProjectFiles $files, RepositoryClient $github): void
    {
        $project = $this->project->refresh();
        $token = (string) $project->user->github_token;

        if (! $project->canWriteRepository() || $token === '') {
            $this->fail($project, __('Kekeli cannot write in this repository. Connect a GitHub account that can write in it.'));

            return;
        }

        try {
            $commits = (array) data_get($project->repository_state, 'commits', []);
            $pushed = $github->push($project, $files->read($project), $this->commitMessage($commits), $token);

            if ($pushed === null) {
                $project->forceFill(['push_status' => Project::PUSH_NOTHING, 'push_error' => null])->save();

                return;
            }

            $pull = $github->pullRequest($project, $this->title($project, $commits), $this->body($project, $commits), $token);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 401) {
                $project->user->forceFill(['github_token' => null])->save();
            }
            $this->fail($project, 'GitHub : code '.$exception->response->status().' '.mb_substr((string) $exception->response->json('message'), 0, 200));

            return;
        } catch (Throwable $exception) {
            $this->fail($project, $exception->getMessage());

            return;
        }

        $first = $project->pr_number === null;
        $project->forceFill([
            'push_status' => Project::PUSH_DONE,
            'push_error' => null,
            'pushed_at' => now(),
            'pushed_sha' => $pushed['sha'],
            'pr_number' => $pull['number'],
            'pr_url' => $pull['url'],
            'pr_status' => 'open',
            'ci_status' => Project::CI_PENDING,
            'ci_summary' => null,
        ])->save();

        $project->messages()->create([
            'role' => Message::ROLE_ASSISTANT,
            'content' => ($first
                ? __('Your work is on GitHub: pull request :url (branch :branch, :count file(s) changed).', ['url' => $pull['url'], 'branch' => $project->work_branch, 'count' => $pushed['changed'] + $pushed['deleted']])
                : __('The corrections are on GitHub, in the same pull request: :url.', ['url' => $pull['url']]))
                .' '.__('Kekeli now watches the automatic checks of GitHub and tells you the result here.'),
        ]);

        WatchRepositoryChecks::dispatch($project, $pushed['sha'])->delay(now()->addSeconds(WatchRepositoryChecks::WAIT_SECONDS));
    }

    private function fail(Project $project, string $error): void
    {
        report(new \RuntimeException('Envoi vers GitHub du projet '.$project->id.' : '.$error));
        $project->forceFill(['push_status' => Project::PUSH_FAILED, 'push_error' => mb_substr($error, 0, 500)])->save();
    }

    /**
     * @param  list<string>  $commits
     */
    private function commitMessage(array $commits): string
    {
        $commits = array_values(array_filter($commits, fn ($c) => $c !== 'Travail de Kekeli'));
        if ($commits === []) {
            return 'Travail de Kekeli';
        }

        return $commits[0].(count($commits) > 1 ? "\n\n".collect(array_slice($commits, 1))->map(fn ($c) => '- '.$c)->implode("\n") : '');
    }

    /**
     * @param  list<string>  $commits
     */
    private function title(Project $project, array $commits): string
    {
        $first = collect($commits)->last(fn ($c) => $c !== 'Travail de Kekeli');

        return mb_substr($first ?: 'Travail de Kekeli sur '.$project->work_branch, 0, 120);
    }

    /**
     * @param  list<string>  $commits
     */
    private function body(Project $project, array $commits): string
    {
        $files = collect((array) data_get($project->repository_state, 'fichiers', []))->take(30)->map(fn ($f) => '- '.($f['chemin'] ?? '').' ('.($f['etat'] ?? 'modifie').')');
        $request = $project->messages()->where('role', Message::ROLE_USER)->latest('id')->value('content');

        return implode("\n", array_filter([
            '## Demande',
            mb_substr((string) $request, 0, 1500),
            '',
            '## Commits',
            collect($commits)->reverse()->map(fn ($c) => '- '.$c)->implode("\n") ?: '- Travail de Kekeli',
            $files->isNotEmpty() ? "\n## Fichiers\n".$files->implode("\n") : null,
            '',
            '---',
            '_Travail réalisé par [Kekeli](https://kekeli.dev), l\'agent IA qui code pour vous, à la demande du propriétaire du dépôt._',
        ], fn ($line) => $line !== null));
    }
}
