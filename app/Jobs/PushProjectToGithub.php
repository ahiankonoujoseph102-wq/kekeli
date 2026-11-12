<?php

namespace App\Jobs;

use App\Agent\ProjectFiles;
use App\Github\GithubClient;
use App\Github\GithubFiles;
use App\Github\RepositoryClient;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * « Projet d'abord », like Claude Code: the first time, Kekeli creates the client's repository (private by default)
 * from the project; then, after each successful work, it sends the changes: on its own branch with a pull request (the
 * default), or straight on the main branch when the client chose it in the project. Commit messages are clear and in
 * French; .env files, test databases and keys are never sent. For an automation, the Secret variables become the
 * encrypted secrets of the repository.
 */
class PushProjectToGithub implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Project $project, public ?AgentRun $run = null) {}

    public function handle(ProjectFiles $projectFiles, GithubClient $github, RepositoryClient $repositories): void
    {
        $project = $this->project->refresh();
        $user = $project->user;
        $token = $github->token($user);

        if (! $token || $project->isRepository() || ! $project->backup_at) {
            $project->forceFill(['github_status' => $token ? null : Project::GITHUB_FAILED, 'github_error' => $token ? null : 'github_deconnecte'])->save();

            return;
        }

        try {
            if (! $project->repository) {
                $created = $github->createRepository($user, $project);
                $project->forceFill(['repository' => $created['full_name'], 'github_repo' => $created['full_name'], 'repository_base_branch' => $created['default_branch']])->save();
            }

            $clean = GithubFiles::clean($projectFiles->read($project));
            $direct = $project->github_push_mode === Project::PUSH_DIRECT;
            $base = (string) ($project->repository_base_branch ?: 'main');
            // Une branche de Kekeli par pull request ouverte, comme Claude Code ; une nouvelle après la fusion.
            if (! $direct && (! $project->work_branch || $project->pr_status !== 'open')) {
                $project->forceFill(['work_branch' => 'kekeli/'.now()->timezone(config('kekeli.display_timezone'))->format('Y-m-d-His'), 'pr_number' => null, 'pr_url' => null, 'pr_status' => null])->save();
            }
            $branch = $direct ? $base : (string) $project->work_branch;

            $pushed = $repositories->commitFiles((string) $project->repository, $branch, $base, $clean['files'], $this->commitMessage($project, $clean['skipped']), $token);

            $pull = null;
            if ($pushed && ! $direct) {
                $pull = $repositories->pullRequest($project, $this->title($project), $this->body($project), $token);
            }

            // Automatisation : les Variables secrètes deviennent les secrets chiffrés du dépôt (jamais dans le code).
            $secrets = $project->isAutomation() ? $github->putSecrets((string) $project->repository, $project->secrets()->get()->mapWithKeys(fn ($s) => [$s->name => $s->value])->all(), $token) : [];
        } catch (RequestException $exception) {
            $this->fail($project, 'GitHub : code '.$exception->response->status().' '.Str::limit((string) $exception->response->json('message'), 200));

            return;
        } catch (Throwable $exception) {
            $this->fail($project, $exception->getMessage());

            return;
        }

        $project->forceFill(array_filter([
            'github_status' => Project::GITHUB_DONE,
            'github_error' => null,
            'github_exported_at' => now(),
            'pushed_at' => $pushed ? now() : null,
            'pushed_sha' => $pushed['sha'] ?? null,
            'pr_number' => $pull['number'] ?? null,
            'pr_url' => $pull['url'] ?? null,
            'pr_status' => $pull ? 'open' : null,
        ], fn ($value) => $value !== null) + ['github_error' => null])->save();

        if ($pushed) {
            $project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => $pull
                    ? __('Your changes are on GitHub, in a pull request: :url', ['url' => $pull['url']])
                    : __('Your changes are on GitHub, on the branch :branch of :repository.', ['branch' => $branch, 'repository' => $project->repository]),
            ]);
        } elseif ($secrets !== []) {
            $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => __('Your Secret variables are now encrypted secrets of your GitHub repository.')]);
        }
    }

    private function fail(Project $project, string $error): void
    {
        report(new \RuntimeException('Envoi vers GitHub du projet '.$project->id.' : '.$error));
        $project->forceFill(['github_status' => Project::GITHUB_FAILED, 'github_error' => Str::limit($error, 500)])->save();
    }

    /**
     * A clear commit message in French: what the client asked, then what was left out on purpose.
     *
     * @param  list<string>  $skipped
     */
    public function commitMessage(Project $project, array $skipped = []): string
    {
        $request = $this->request($project);
        $title = $request !== '' ? 'Kekeli : '.Str::limit(Str::lcfirst($request), 60) : 'Kekeli : mise à jour du projet';

        return implode("\n\n", array_filter([
            $title,
            $request !== '' && mb_strlen($request) > 60 ? 'Demande du client : « '.Str::limit($request, 600).' »' : null,
            $skipped !== [] ? 'Fichiers gardés hors du dépôt (secrets ou base d\'essai) : '.implode(', ', array_slice($skipped, 0, 10)).'.' : null,
        ]));
    }

    private function title(Project $project): string
    {
        $request = $this->request($project);

        return $request !== '' ? 'Kekeli : '.Str::limit(Str::lcfirst($request), 80) : 'Kekeli : mise à jour de « '.Str::limit($project->name, 60).' »';
    }

    private function body(Project $project): string
    {
        return implode("\n", [
            '## Demande',
            Str::limit($this->request($project) ?: $project->name, 1500),
            '',
            '---',
            '_Travail réalisé par Kekeli, l\'agent IA qui code pour vous, à la demande du propriétaire du dépôt._',
        ]);
    }

    /** The request of the client for this work (one line), never the internal instructions of Kekeli. */
    private function request(Project $project): string
    {
        $before = $this->run?->message_id;
        $messages = $project->messages()->where('role', Message::ROLE_USER)->when($before, fn ($q) => $q->where('id', '<=', $before))->latest('id')->limit(10)->pluck('content');
        // « Construire ce plan » : la vraie demande est le message écrit avant.
        $line = (string) $messages->map(fn ($m) => trim((string) preg_replace('/\s+/u', ' ', (string) $m)))
            ->first(fn ($m) => $m !== '' && ! in_array($m, [__('Build this plan'), __('Run now'), 'oui', 'Oui'], true));

        return $line;
    }
}
