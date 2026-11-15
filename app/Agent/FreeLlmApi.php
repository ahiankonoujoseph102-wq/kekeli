<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * FreeLLMAPI, une source de capacité gratuite de plus (décision du porteur du projet du 15 novembre 2026, docs/plan.md,
 * partie 26.37) : un processus voisin du Runner, qui regroupe des offres gratuites (Groq, LongCat, Z.ai…). Il prend la
 * place de Coarena dans les listes : après OpenAI, Gemini, OpenRouter et Workers AI, qui ne changent pas.
 *
 * FreeLLMAPI ne connaît qu'un seul utilisateur, le Runner. Les clients sont gérés ici, comme pour les autres
 * fournisseurs : énergie (inchangée), plus une part de demandes FreeLLMAPI par jour selon la formule et un plafond global
 * du jour, pour qu'aucun client ne prenne toute la capacité gratuite. Le Runner ajoute une seule demande à la fois par
 * client. Le client ne voit jamais le nom des fournisseurs.
 */
class FreeLlmApi
{
    public const PROVIDER = 'freellmapi';

    /** Les familles de modèles de chaque type de tâche, réglées dans Administration → Modèles (« freellmapi_routes »). */
    public const GROUPS = ['discussion', 'document', 'construction'];

    /** Même liste fermée que le Runner (runner/src/freellmapi.js, MODELES_FREELLMAPI) : jamais « auto », jamais payant. */
    public const MODELS = ['gpt-oss-120b', 'gpt-oss-20b', 'qwen3.8-27b', 'glm-4.7-flash', 'glm-4.5-flash', 'longcat-2.5', 'longcat-flash-chat', 'longcat-flash-lite'];

    public function __construct(private Settings $settings) {}

    /** The group of a task of the router (ModelRouter::TASKS). */
    public static function groupOf(string $task): string
    {
        return match ($task) {
            'document' => 'document',
            'site', 'modification', 'application', 'agent', 'automatisation' => 'construction',
            default => 'discussion',
        };
    }

    /**
     * The FreeLLMAPI families of a group, in order (only those of the closed list).
     *
     * @return list<string>
     */
    public function models(string $group): array
    {
        $routes = (array) ($this->settings->get('freellmapi_routes') ?? []);

        return array_values(array_unique(array_filter(array_map('strval', (array) ($routes[$group] ?? [])), fn (string $model) => in_array($model, self::MODELS, true))));
    }

    /**
     * The FreeLLMAPI entries for this client and this task, to put at the end of the list; none when FreeLLMAPI is
     * switched off, when the client has used its share of the day or when the global ceiling of the day is reached.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function entries(User $user, string $task): array
    {
        if (! $this->enabled() || $this->userLeft($user) <= 0 || $this->globalLeft() <= 0) {
            return [];
        }
        $reset = now('UTC')->addDay()->startOfDay()->toIso8601String();

        return array_map(fn (string $model) => ['fournisseur' => self::PROVIDER, 'modele' => $model, 'remise_a_zero' => $reset], $this->models(self::groupOf($task)));
    }

    public function enabled(): bool
    {
        $switches = (array) ($this->settings->get('model_providers') ?? []);

        return ($switches[self::PROVIDER] ?? true) !== false;
    }

    /** Requests a client may still send through FreeLLMAPI today (Lomé day), according to its formula. */
    public function userLeft(User $user): int
    {
        $perPlan = (array) ($this->settings->get('freellmapi_requests_per_day') ?? []);
        $allowed = (int) ($perPlan[$user->plan ?: 'gratuite'] ?? $perPlan['gratuite'] ?? 0);

        return max(0, $allowed - $this->userUsedToday($user));
    }

    /** Answers and works of this client served by FreeLLMAPI today (Lomé day). */
    public function userUsedToday(User $user): int
    {
        $start = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();

        return AgentRun::query()
            ->where('provider', self::PROVIDER)
            ->where('created_at', '>=', $start)
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->count();
    }

    /** Requests still allowed today for all the clients together (« freellmapi_daily_cap », 0 = no ceiling). */
    public function globalLeft(): int
    {
        $cap = $this->settings->int('freellmapi_daily_cap');

        return $cap <= 0 ? PHP_INT_MAX : max(0, $cap - $this->globalUsedToday());
    }

    public function globalUsedToday(): int
    {
        return (int) ProviderUsage::query()
            ->where('provider', self::PROVIDER)
            ->where('day', app(ProviderList::class)->quotaDay(self::PROVIDER))
            ->sum('requests');
    }

    /**
     * An opaque identifier of the client for the Runner (one request at a time per client): never its number, its
     * name or its e-mail.
     */
    public static function clientId(User $user): string
    {
        return substr(hash_hmac('sha256', 'client:'.$user->id, (string) config('kekeli.runner.secret')), 0, 24);
    }

    /**
     * Token of the backup of FreeLLMAPI's database (same calculation as the Runner: runner/src/freellmapi.js,
     * jetonSauvegarde). The backup is encrypted with a key known only to the Runner: the site keeps a file it cannot read.
     */
    public static function backupToken(): ?string
    {
        $secret = (string) config('kekeli.runner.secret');

        return $secret === '' ? null : hash_hmac('sha256', 'freellmapi:sauvegarde', $secret);
    }
}
