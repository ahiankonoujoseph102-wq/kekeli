<?php

namespace App\Support;

use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Github\GithubClient;
use App\Models\Project;
use App\Models\User;
use App\Models\UserConnector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Connectors (MCP), like Claude and ChatGPT. The catalog lives in config/kekeli.php (« connectors »): official MCP
 * servers where Kekeli registers by itself (OAuth with dynamic client registration) or that need no login, chosen
 * after the study of docs/plan.md, partie 26.16. The Runner is the MCP client; the tokens stay here, encrypted.
 * « Réglages → Connecteurs » is shown only when connectors really work (Runner configured, or GitHub App set).
 */
class Connectors
{
    public function __construct(private Settings $settings, private Energy $energy) {}

    /** Whether the connectors can work here: the Runner is reachable with its secret. */
    public function enabled(): bool
    {
        return filled(config('kekeli.runner.secret')) && filled(config('kekeli.runner.url'));
    }

    /**
     * The catalog shown in « Réglages → Connecteurs » (name, address, description, icon).
     *
     * @return array<string, array{name: string, url: string, auth: string, icon: string, description: string}>
     */
    public function catalog(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return collect((array) config('kekeli.connectors', []))
            ->filter(fn ($c) => is_array($c) && filled($c['url'] ?? null) && filled($c['name'] ?? null))
            ->map(fn ($c) => ['name' => (string) $c['name'], 'url' => (string) $c['url'], 'auth' => ($c['auth'] ?? 'oauth') === UserConnector::AUTH_NONE ? UserConnector::AUTH_NONE : UserConnector::AUTH_OAUTH, 'icon' => (string) ($c['icon'] ?? 'puzzle-piece'), 'description' => (string) ($c['description'] ?? '')])
            ->all();
    }

    /**
     * Kept for the settings tabs: GitHub (when the GitHub App is set) and the catalog.
     *
     * @return list<array{key: string, name: string}>
     */
    public function available(): array
    {
        $list = collect($this->catalog())->map(fn ($c, $key) => ['key' => (string) $key, 'name' => $c['name']])->values()->all();

        // GitHub, dès que la GitHub App de Kekeli est réglée (relier son compte, choisir ses dépôts).
        if (app(GithubClient::class)->configured()) {
            array_unshift($list, ['key' => 'github', 'name' => 'GitHub']);
        }

        return $list;
    }

    public function any(): bool
    {
        return $this->available() !== [];
    }

    /**
     * Connectors active at the same time in one discussion: 2 for the free formula (Administration → Réglages),
     * no limit for the paid formulas.
     */
    public function maxActive(User $user): ?int
    {
        return $this->energy->isFree($user) ? max(0, $this->settings->int('free_active_connectors_max')) : null;
    }

    /**
     * The connected connectors of the client, with whether each is active for this project (or for a new discussion
     * when there is no project yet).
     *
     * @return Collection<int, UserConnector>
     */
    public function connected(User $user): Collection
    {
        return $user->connectors()->whereNotNull('connected_at')->orderBy('name')->get()->filter->isConnected()->values();
    }

    /**
     * The ids of the connectors active in a project: chosen in the project, or the client's defaults; never more than
     * the limit of the formula.
     *
     * @return list<int>
     */
    public function activeIds(User $user, ?Project $project = null): array
    {
        $connected = $this->connected($user);
        $ids = $project && is_array($project->connectors)
            ? array_values(array_intersect($project->connectors, $connected->pluck('id')->all()))
            : $connected->where('active', true)->pluck('id')->all();

        $max = $this->maxActive($user);

        return array_values(array_map('intval', $max === null ? $ids : array_slice($ids, 0, $max)));
    }

    /**
     * Turn a connector on or off for a project (or as a default for new discussions). Returns false when the limit of
     * the free formula is reached.
     */
    public function toggle(User $user, int $connectorId, ?Project $project = null): bool
    {
        $connector = $this->connected($user)->firstWhere('id', $connectorId);
        if (! $connector) {
            return false;
        }

        $active = $this->activeIds($user, $project);
        $on = ! in_array($connectorId, $active, true);
        $max = $this->maxActive($user);
        if ($on && $max !== null && count($active) >= $max) {
            return false;
        }
        $active = $on ? [...$active, $connectorId] : array_values(array_diff($active, [$connectorId]));

        if ($project) {
            $project->forceFill(['connectors' => $active])->save();
        } else {
            // Sans projet (accueil) : le choix vaut pour les nouvelles discussions.
            $user->connectors()->update(['active' => false]);
            $user->connectors()->whereIn('id', $active)->update(['active' => true]);
        }

        return true;
    }

    /**
     * What the Runner receives for a request: the address, the name, a fresh access token (renewed when it is about
     * to expire) and the tools always allowed. Never the refresh token.
     *
     * @return list<array{id: int, nom: string, url: string, jeton: ?string, toujours: list<string>}>
     */
    public function forRunner(User $user, ?Project $project): array
    {
        $ids = $this->activeIds($user, $project);
        if ($ids === []) {
            return [];
        }

        return $user->connectors()->whereIn('id', $ids)->get()
            ->filter->isConnected()
            ->map(fn (UserConnector $c) => ['id' => $c->id, 'nom' => $c->name, 'url' => $c->url, 'jeton' => $this->freshToken($c), 'toujours' => $c->alwaysAllowed(), 'sans_connexion' => $c->auth === UserConnector::AUTH_NONE])
            // Un jeton qui ne se renouvelle plus : le connecteur est laissé de côté (le client le relie à nouveau).
            ->reject(fn ($c) => ! $c['sans_connexion'] && $c['jeton'] === null)
            ->map(fn ($c) => array_diff_key($c, ['sans_connexion' => true]))
            ->values()->all();
    }

    /**
     * The access token of a connector, renewed by the Runner when it expires within two minutes. Null when it cannot
     * be renewed (the client links the connector again).
     */
    public function freshToken(UserConnector $connector): ?string
    {
        if ($connector->auth === UserConnector::AUTH_NONE) {
            return null;
        }
        if ($connector->token_expires_at === null || $connector->token_expires_at->isAfter(now()->addMinutes(2))) {
            return $connector->access_token;
        }
        if (blank($connector->refresh_token)) {
            return null;
        }

        $result = app(RunnerClient::class)->connector('renouveler', ['url' => $connector->url, 'client' => $connector->client, 'renouvellement' => $connector->refresh_token, 'ressource' => $connector->resource]);
        if (! ($result['ok'] ?? false) || ! is_string($result['jeton'] ?? null)) {
            return null;
        }
        $this->saveTokens($connector, $result);

        return $connector->access_token;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    public function saveTokens(UserConnector $connector, array $tokens): void
    {
        $connector->forceFill([
            'access_token' => (string) $tokens['jeton'],
            'refresh_token' => is_string($tokens['renouvellement'] ?? null) ? $tokens['renouvellement'] : $connector->refresh_token,
            'token_expires_at' => is_numeric($tokens['expire_dans'] ?? null) ? CarbonImmutable::now()->addSeconds((int) $tokens['expire_dans']) : null,
            'connected_at' => $connector->connected_at ?? now(),
        ])->save();
    }

    /**
     * An address of an MCP server the client may add: https, a public domain name, never an internal address. The
     * Runner checks it again before any connection.
     */
    public static function safeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || ! str_contains($host, '.') || str_contains($host, ':') || $host === 'localhost' || preg_match('/\.(localhost|local|internal)$/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return strlen($url) <= 500;
    }
}
