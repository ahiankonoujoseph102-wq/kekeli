<?php

namespace App\Github;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The GitHub App of Kekeli, like Claude Code: the client installs it on their account, chooses on GitHub the
 * repositories Kekeli may see (all, or only some), and accepts that Kekeli acts for them. Kekeli receives a user access
 * token (8 hours) and a refresh token (6 months), both kept encrypted and never shown nor sent to the virtual computer.
 *
 * Permissions asked by the application (the smallest set): Contents, Pull requests, Workflows, Secrets, Actions and
 * Administration (to create a private repository) in write; Checks and Metadata in read. No webhook.
 */
class GithubClient
{
    public function configured(): bool
    {
        return filled(config('services.github.app_slug')) && filled(config('services.github.client_id')) && filled(config('services.github.client_secret'));
    }

    /**
     * The GitHub page where the client installs Kekeli and chooses its repositories (or changes that choice). With
     * « Request user authorization during installation », GitHub then comes back to Kekeli with a code.
     */
    public function installUrl(string $state): string
    {
        return 'https://github.com/apps/'.rawurlencode((string) config('services.github.app_slug')).'/installations/new?'.http_build_query(['state' => $state]);
    }

    /**
     * When the application is already installed: GitHub asks only to accept, then comes back with a code.
     */
    public function authorizeUrl(string $state): string
    {
        return 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => route('github.callback'),
            'state' => $state,
        ]);
    }

    /**
     * The page where the client changes the repositories Kekeli may see.
     */
    public function repositoriesUrl(User $user): string
    {
        return $user->github_installation_id
            ? 'https://github.com/settings/installations/'.$user->github_installation_id
            : 'https://github.com/apps/'.rawurlencode((string) config('services.github.app_slug')).'/installations/new';
    }

    /**
     * Exchange the code given by GitHub for the tokens, then read the login of the account and its installation.
     *
     * @return array{token: string, refresh: ?string, expires_at: ?Carbon, login: string, installation: ?int}
     */
    public function connect(string $code): array
    {
        $tokens = $this->tokens(['code' => $code, 'redirect_uri' => route('github.callback')]);
        $login = (string) $this->api($tokens['token'])->get('/user')->throw()->json('login');

        return $tokens + ['login' => $login, 'installation' => $this->installation($tokens['token'], $login)];
    }

    /**
     * Device flow of GitHub (official): Kekeli shows a short code, the client types it on github.com/login/device.
     * Used when GitHub cannot come back to Kekeli (site not online yet, for example during the first real test).
     *
     * @return array{device_code: string, user_code: string, verification_uri: string, interval: int, expires_in: int}
     */
    public function deviceCode(): array
    {
        $response = Http::acceptJson()->timeout(20)->post('https://github.com/login/device/code', ['client_id' => config('services.github.client_id')])->throw();

        return [
            'device_code' => (string) $response->json('device_code'),
            'user_code' => (string) $response->json('user_code'),
            'verification_uri' => (string) ($response->json('verification_uri') ?: 'https://github.com/login/device'),
            'interval' => max(5, (int) $response->json('interval')),
            'expires_in' => (int) ($response->json('expires_in') ?: 900),
        ];
    }

    /**
     * One check of the device flow: the account (as connect()), « attendre », « ralentir », or an error.
     *
     * @return array<string, mixed>
     */
    public function pollDevice(string $deviceCode): array
    {
        $response = Http::acceptJson()->timeout(20)->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'device_code' => $deviceCode,
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        ])->throw();

        $error = (string) $response->json('error');
        if ($error === 'authorization_pending') {
            return ['etat' => 'attendre'];
        }
        if ($error === 'slow_down') {
            return ['etat' => 'ralentir', 'interval' => (int) $response->json('interval')];
        }
        if ($error !== '' || ! is_string($response->json('access_token'))) {
            return ['etat' => 'erreur', 'erreur' => $error ?: 'sans_jeton'];
        }

        $token = (string) $response->json('access_token');
        $expires = (int) $response->json('expires_in');
        $login = (string) $this->api($token)->get('/user')->throw()->json('login');

        return ['etat' => 'ok', 'token' => $token, 'refresh' => $response->json('refresh_token') ?: null, 'expires_at' => $expires > 0 ? now()->addSeconds($expires) : null, 'login' => $login, 'installation' => $this->installation($token, $login)];
    }

    /**
     * A valid user access token (renewed when it expires in less than 5 minutes), or null when GitHub is not linked.
     */
    public function token(User $user): ?string
    {
        $token = $user->github_token;
        if (! $token) {
            return null;
        }
        if (! $user->github_token_expires_at || $user->github_token_expires_at->isAfter(now()->addMinutes(5))) {
            return $token;
        }
        if (! $user->github_refresh_token || ! $this->configured()) {
            return null;
        }

        try {
            $fresh = $this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => $user->github_refresh_token]);
        } catch (\Throwable $exception) {
            report($exception);
            // Jeton de renouvellement refusé (accès retiré, six mois passés) : il faudra relier GitHub à nouveau.
            $user->forceFill(['github_token' => null, 'github_refresh_token' => null, 'github_token_expires_at' => null])->save();

            return null;
        }
        $user->forceFill(['github_token' => $fresh['token'], 'github_refresh_token' => $fresh['refresh'] ?? $user->github_refresh_token, 'github_token_expires_at' => $fresh['expires_at']])->save();

        return $fresh['token'];
    }

    /**
     * « Projet d'abord »: a new repository in the client's account (private by default), added to the installation of
     * Kekeli when the client chose only some repositories. Returns its full name and default branch.
     *
     * @return array{full_name: string, default_branch: string}
     */
    public function createRepository(User $user, Project $project, bool $private = true): array
    {
        $token = $this->token($user) ?? throw new RuntimeException('GitHub n\'est pas relié.');
        $api = $this->api($token);
        $payload = [
            'name' => $project->pagesName(),
            'description' => Str::limit('« '.$project->name.' », construit avec Kekeli.', 300, ''),
            'private' => $private,
            // Un premier commit est nécessaire pour écrire ensuite avec l'interface « Git Data » de GitHub.
            'auto_init' => true,
        ];

        try {
            $repo = $api->post('/user/repos', $payload)->throw()->json();
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 422) {
                throw $exception;
            }
            // Un dépôt du même nom existe déjà : jamais écrasé, un autre nom est pris.
            $payload['name'] .= '-'.Str::lower(Str::random(4));
            $repo = $api->post('/user/repos', $payload)->throw()->json();
        }

        if ($user->github_installation_id && isset($repo['id'])) {
            // Installation limitée à certains dépôts : le nouveau dépôt y est ajouté (sans effet si elle les voit tous).
            $api->put('/user/installations/'.$user->github_installation_id.'/repositories/'.$repo['id']);
        }

        return ['full_name' => (string) $repo['full_name'], 'default_branch' => (string) ($repo['default_branch'] ?? 'main')];
    }

    /**
     * Secrets of GitHub Actions: each value is encrypted with the public key of the repository (sealed box of
     * libsodium, as GitHub asks) before leaving Kekeli; GitHub never gives them back.
     *
     * @param  array<string, string>  $secrets
     * @return list<string> Names written.
     */
    public function putSecrets(string $repository, array $secrets, string $token): array
    {
        if ($secrets === []) {
            return [];
        }
        $api = $this->api($token);
        $key = $api->get('/repos/'.$repository.'/actions/secrets/public-key')->throw()->json();
        $public = base64_decode((string) ($key['key'] ?? ''), true);
        if ($public === false || strlen($public) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RuntimeException('Clé publique du dépôt illisible.');
        }

        $written = [];
        foreach ($secrets as $name => $value) {
            $api->put('/repos/'.$repository.'/actions/secrets/'.$name, [
                'encrypted_value' => self::seal($value, $public),
                'key_id' => (string) $key['key_id'],
            ])->throw();
            $written[] = $name;
        }

        return $written;
    }

    /**
     * Sealed box of libsodium, encoded in base64 (the format of the secrets of GitHub Actions).
     */
    public static function seal(string $value, string $publicKey): string
    {
        return base64_encode(sodium_crypto_box_seal($value, $publicKey));
    }

    /**
     * Starts a workflow of GitHub Actions by hand (« workflow_dispatch »).
     */
    public function dispatchWorkflow(string $repository, string $workflow, string $branch, string $token): void
    {
        $this->api($token)->post('/repos/'.$repository.'/actions/workflows/'.rawurlencode($workflow).'/dispatches', ['ref' => $branch])->throw();
    }

    /**
     * @param  array<string, string>  $grant
     * @return array{token: string, refresh: ?string, expires_at: ?Carbon}
     */
    private function tokens(array $grant): array
    {
        $response = Http::acceptJson()->timeout(20)->post('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            ...$grant,
        ])->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub n\'a pas donné de jeton d\'accès.');
        }
        $expires = (int) $response->json('expires_in');

        return ['token' => $token, 'refresh' => $response->json('refresh_token') ?: null, 'expires_at' => $expires > 0 ? now()->addSeconds($expires) : null];
    }

    /**
     * The installation of Kekeli on the client's own account (else the first one the client can use).
     */
    private function installation(string $token, string $login): ?int
    {
        $installations = collect($this->api($token)->get('/user/installations')->json('installations') ?? []);
        $own = $installations->first(fn ($i) => Str::lower((string) data_get($i, 'account.login')) === Str::lower($login)) ?? $installations->first();

        return isset($own['id']) ? (int) $own['id'] : null;
    }

    private function api(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.github.api_url'))
            ->withToken($token)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Kekeli'])
            ->acceptJson()
            ->timeout(30);
    }
}
