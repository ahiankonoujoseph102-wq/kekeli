<?php

namespace App\Github;

use App\Models\Project;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Developer mode: the client's existing GitHub repository. Every call uses the client's GitHub access key, kept
 * encrypted in Kekeli and never sent to the virtual computer (a public repository can be opened without it, read only).
 * The work is sent with GitHub's « Git Data » interface: only the files that changed, on Kekeli's own branch.
 */
class RepositoryClient
{
    /** Largest repository archive downloaded for the virtual computer. */
    public const MAX_ARCHIVE_BYTES = 200 * 1024 * 1024;

    /**
     * Repositories the client can open (most recently updated first).
     *
     * @return list<array{full_name: string, private: bool, default_branch: string, can_write: bool, description: ?string}>
     */
    public function repositories(string $token): array
    {
        $items = $this->api($token)->get('/user/repos', [
            'per_page' => 100,
            'sort' => 'updated',
            'affiliation' => 'owner,collaborator,organization_member',
        ])->throw()->json();

        return collect(is_array($items) ? $items : [])
            ->filter(fn ($repo) => is_array($repo) && isset($repo['full_name']) && ! ($repo['archived'] ?? false))
            ->map(fn ($repo) => [
                'full_name' => (string) $repo['full_name'],
                'private' => (bool) ($repo['private'] ?? false),
                'default_branch' => (string) ($repo['default_branch'] ?? 'main'),
                'can_write' => (bool) data_get($repo, 'permissions.push', false),
                'description' => isset($repo['description']) ? mb_substr((string) $repo['description'], 0, 160) : null,
            ])->values()->all();
    }

    /**
     * One repository, with its default branch and the commit it points to.
     *
     * @return array{full_name: string, default_branch: string, sha: string, can_write: bool, private: bool}
     */
    public function repository(string $fullName, ?string $token): array
    {
        $repo = $this->api($token)->get('/repos/'.$fullName)->throw()->json();
        $branch = (string) ($repo['default_branch'] ?? 'main');
        $sha = (string) $this->api($token)->get('/repos/'.$fullName.'/commits/'.rawurlencode($branch))->throw()->json('sha');

        return [
            'full_name' => (string) $repo['full_name'],
            'default_branch' => $branch,
            'sha' => $sha,
            'can_write' => $token !== null && (bool) data_get($repo, 'permissions.push', false),
            'private' => (bool) ($repo['private'] ?? false),
        ];
    }

    /**
     * Archive « .tar.gz » of the repository at the given commit (for the virtual computer).
     */
    public function archive(Project $project, ?string $token): string
    {
        $response = $this->api($token)->timeout(120)->get('/repos/'.$project->repository.'/tarball/'.$project->repository_base_sha)->throw();
        $body = $response->body();

        if (strlen($body) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Le dépôt est trop gros pour être ouvert dans Kekeli.');
        }

        return $body;
    }

    /**
     * Send the project's files (from Kekeli's last backup) to Kekeli's branch: one commit with every file that differs
     * from the starting commit, and the files that were deleted. Returns the new commit, or null if nothing changed.
     *
     * @param  array<string, string>  $files  Path → content (without the files ignored by the project).
     * @return array{sha: string, changed: int, deleted: int}|null
     */
    public function push(Project $project, array $files, string $message, string $token): ?array
    {
        $repo = '/repos/'.$project->repository;
        $api = $this->api($token);

        // Fichiers du commit de départ (empreinte Git de chacun).
        $baseTree = (string) $api->get($repo.'/git/commits/'.$project->repository_base_sha)->throw()->json('tree.sha');
        $tree = $api->get($repo.'/git/trees/'.$baseTree, ['recursive' => 1])->throw()->json();
        $remote = collect($tree['tree'] ?? [])->where('type', 'blob')->keyBy('path');

        $entries = [];
        $changed = 0;
        foreach ($files as $path => $content) {
            $known = $remote->get($path);
            if ($known && $known['sha'] === self::blobSha($content)) {
                continue;
            }
            $blob = $api->post($repo.'/git/blobs', ['content' => base64_encode($content), 'encoding' => 'base64'])->throw()->json('sha');
            $entries[] = ['path' => $path, 'mode' => $known['mode'] ?? '100644', 'type' => 'blob', 'sha' => $blob];
            $changed++;
        }

        // Fichiers supprimés (seulement si GitHub a donné la liste complète des fichiers).
        $deleted = 0;
        if (! ($tree['truncated'] ?? false)) {
            foreach ($remote->keys()->diff(array_keys($files)) as $path) {
                $entries[] = ['path' => $path, 'mode' => $remote[$path]['mode'], 'type' => 'blob', 'sha' => null];
                $deleted++;
            }
        }

        $branch = $project->work_branch;
        $head = $api->get($repo.'/git/ref/heads/'.$branch);
        $headSha = $head->successful() ? (string) $head->json('object.sha') : null;

        if ($entries === [] && $headSha === null) {
            return null;
        }

        $newTree = $entries === [] ? $baseTree : (string) $api->post($repo.'/git/trees', ['base_tree' => $baseTree, 'tree' => $entries])->throw()->json('sha');

        // Rien de nouveau depuis le dernier envoi.
        if ($headSha !== null && (string) $api->get($repo.'/git/commits/'.$headSha)->throw()->json('tree.sha') === $newTree) {
            return null;
        }

        $commit = (string) $api->post($repo.'/git/commits', [
            'message' => $message,
            'tree' => $newTree,
            'parents' => [$headSha ?? $project->repository_base_sha],
        ])->throw()->json('sha');

        if ($headSha === null) {
            $api->post($repo.'/git/refs', ['ref' => 'refs/heads/'.$branch, 'sha' => $commit])->throw();
        } else {
            $api->patch($repo.'/git/refs/heads/'.$branch, ['sha' => $commit, 'force' => false])->throw();
        }

        return ['sha' => $commit, 'changed' => $changed, 'deleted' => $deleted];
    }

    /**
     * The pull request of Kekeli's branch: the open one if it exists, otherwise a new one.
     *
     * @return array{number: int, url: string}
     */
    public function pullRequest(Project $project, string $title, string $body, string $token): array
    {
        $api = $this->api($token);
        $owner = explode('/', (string) $project->repository)[0];
        $open = $api->get('/repos/'.$project->repository.'/pulls', ['head' => $owner.':'.$project->work_branch, 'state' => 'open'])->throw()->json();

        $pull = is_array($open) && isset($open[0]['number'])
            ? $open[0]
            : $api->post('/repos/'.$project->repository.'/pulls', [
                'title' => $title,
                'head' => $project->work_branch,
                'base' => $project->repository_base_branch,
                'body' => $body,
            ])->throw()->json();

        return ['number' => (int) $pull['number'], 'url' => (string) $pull['html_url']];
    }

    /**
     * State of GitHub's automatic checks on a commit: « pending », « success », « failure » or « none »,
     * with the failed checks (name, summary and the end of the log of GitHub Actions).
     *
     * @return array{state: string, failed: list<array{name: string, summary: string, log: string}>, total: int}
     */
    public function checks(Project $project, string $sha, string $token): array
    {
        $api = $this->api($token);
        $runs = collect($api->get('/repos/'.$project->repository.'/commits/'.$sha.'/check-runs', ['per_page' => 100])->throw()->json('check_runs') ?? []);

        if ($runs->isEmpty()) {
            return ['state' => Project::CI_NONE, 'failed' => [], 'total' => 0];
        }
        if ($runs->contains(fn ($run) => ($run['status'] ?? '') !== 'completed')) {
            return ['state' => Project::CI_PENDING, 'failed' => [], 'total' => $runs->count()];
        }

        $failed = $runs->filter(fn ($run) => in_array($run['conclusion'] ?? '', ['failure', 'timed_out', 'cancelled', 'action_required', 'startup_failure'], true))
            ->take(3)
            ->map(fn ($run) => [
                'name' => (string) ($run['name'] ?? 'vérification'),
                'summary' => mb_substr(trim(((string) data_get($run, 'output.title')).' '.((string) data_get($run, 'output.summary'))), 0, 1500),
                'log' => $this->logEnd($project, $run, $token),
            ])->values()->all();

        return ['state' => $failed === [] ? Project::CI_SUCCESS : Project::CI_FAILURE, 'failed' => $failed, 'total' => $runs->count()];
    }

    /**
     * Merge the pull request (only when the client asks for it).
     */
    public function merge(Project $project, string $token): void
    {
        $this->api($token)->put('/repos/'.$project->repository.'/pulls/'.$project->pr_number.'/merge', [
            'merge_method' => 'merge',
            'commit_title' => 'Fusion du travail de Kekeli ('.$project->work_branch.')',
        ])->throw();
    }

    /**
     * Git « blob » fingerprint of a file, the same as GitHub's: sha1 of « blob <size>\0<content> ».
     */
    public static function blobSha(string $content): string
    {
        return sha1('blob '.strlen($content)."\0".$content);
    }

    /**
     * Last lines of the log of a failed GitHub Actions job (empty for other checks).
     *
     * @param  array<string, mixed>  $run
     */
    private function logEnd(Project $project, array $run, string $token): string
    {
        if (! str_contains((string) data_get($run, 'app.slug', ''), 'github-actions') || ! isset($run['id'])) {
            return '';
        }

        $response = $this->api($token)->get('/repos/'.$project->repository.'/actions/jobs/'.$run['id'].'/logs');
        if (! $response->successful()) {
            return '';
        }

        $lines = collect(explode("\n", $response->body()))
            ->map(fn ($line) => preg_replace('/^\d{4}-\d\d-\d\dT[\d:.]+Z\s?/', '', rtrim($line)))
            ->filter(fn ($line) => $line !== '');

        return mb_substr($lines->slice(-80)->implode("\n"), -6000);
    }

    private function api(?string $token): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.github.api_url'))
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Kekeli'])
            ->acceptJson()
            ->timeout(30);

        return $token ? $request->withToken($token) : $request;
    }
}
