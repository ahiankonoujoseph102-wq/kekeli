<?php

namespace App\Github;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sending the code of a client's project to the client's own GitHub account (every formula).
 * Kekeli uses a GitHub « OAuth App »: the client connects their account once, Kekeli receives an access key
 * (kept encrypted, never shown), creates a private repository and puts the files of the last backup in it.
 * Once the code is there, the client hosts it wherever they want, at their own cost.
 */
class GithubClient
{
    private const API = 'https://api.github.com';

    public function configured(): bool
    {
        return filled(config('services.github.client_id')) && filled(config('services.github.client_secret'));
    }

    /**
     * Address of the GitHub page where the client allows Kekeli to create repositories in their account.
     */
    public function authorizeUrl(string $state): string
    {
        return 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => route('github.callback'),
            // « repo » : créer des dépôts privés et y écrire ; rien d'autre.
            'scope' => 'repo',
            'state' => $state,
            'allow_signup' => 'true',
        ]);
    }

    /**
     * Exchange the code given by GitHub for an access key, then read the login of the account.
     *
     * @return array{token: string, login: string}
     */
    public function connect(string $code): array
    {
        $response = Http::acceptJson()->timeout(20)->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => route('github.callback'),
        ])->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub n\'a pas donné de clé d\'accès.');
        }

        $login = $this->api($token)->get('/user')->throw()->json('login');

        return ['token' => $token, 'login' => (string) $login];
    }

    /**
     * Put the files in the client's repository (created the first time) in one commit, and return its full name
     * (« login/name »). The repository then contains exactly the files of the project, nothing more.
     *
     * @param  array<string, string>  $files
     */
    public function export(User $user, Project $project, array $files): string
    {
        $token = (string) $user->github_token;
        $api = $this->api($token);
        $repository = $project->github_repo;

        if (! $repository || $api->get('/repos/'.$repository)->status() === 404) {
            $repository = $this->createRepository($api, $user, $project);
        }

        $branch = (string) ($api->get('/repos/'.$repository)->throw()->json('default_branch') ?: 'main');
        $head = $api->get('/repos/'.$repository.'/git/ref/heads/'.$branch)->throw()->json('object.sha');

        $tree = [];
        foreach ($files as $path => $content) {
            $blob = $api->post('/repos/'.$repository.'/git/blobs', ['content' => base64_encode($content), 'encoding' => 'base64'])->throw()->json('sha');
            $tree[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blob];
        }

        $treeSha = $api->post('/repos/'.$repository.'/git/trees', ['tree' => $tree])->throw()->json('sha');
        $commit = $api->post('/repos/'.$repository.'/git/commits', [
            'message' => 'Code envoyé par Kekeli le '.now()->timezone(config('kekeli.display_timezone'))->format('d/m/Y à H:i'),
            'tree' => $treeSha,
            'parents' => $head ? [$head] : [],
        ])->throw()->json('sha');
        $api->patch('/repos/'.$repository.'/git/refs/heads/'.$branch, ['sha' => $commit])->throw();

        return $repository;
    }

    private function createRepository(PendingRequest $api, User $user, Project $project): string
    {
        $payload = [
            'name' => $project->pagesName(),
            'description' => 'Site « '.mb_substr($project->name, 0, 80).' », construit avec Kekeli.',
            'private' => true,
            // Un premier fichier est nécessaire pour pouvoir écrire dans le dépôt avec l'interface de GitHub.
            'auto_init' => true,
        ];

        try {
            return (string) $api->post('/user/repos', $payload)->throw()->json('full_name');
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 422) {
                throw $exception;
            }

            // Un dépôt du même nom existe déjà dans le compte du client : on ne l'écrase jamais.
            $payload['name'] .= '-'.strtolower(substr(bin2hex(random_bytes(3)), 0, 4));

            return (string) $api->post('/user/repos', $payload)->throw()->json('full_name');
        }
    }

    private function api(string $token): PendingRequest
    {
        return Http::baseUrl(self::API)
            ->withToken($token)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Kekeli'])
            ->acceptJson()
            ->timeout(30);
    }
}
