<?php

namespace App\Agent;

use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Le routeur de modèles de Kekeli (docs/plan.md, partie 26.26) : il reconnaît le TYPE de tâche (réponse simple, réponse
 * avec réflexion, rédaction, traduction, extraction, code, site, modification, application, agent, automatisation,
 * document) et choisit les meilleurs modèles de Coarena retenus par le banc d'essai (runner/mesures/modeles-coarena.md).
 * Une seule expérience pour tous, comme Lovable et Manus (décision du porteur du projet, 14 novembre 2026) : les mêmes
 * modèles de qualité pour chaque client, formule Gratuite comprise ; seul le crédit change selon la formule. Quand il
 * reste très peu de crédit, des modèles plus économiques finissent le travail au lieu de l'arrêter. Viennent ensuite,
 * sans rien changer, les modèles habituels (OpenAI, puis les offres gratuites) : si Coarena est épuisé, lent ou en panne,
 * le Runner passe au suivant sans message technique au client.
 *
 * Rien n'est tiré au hasard : la tâche est reconnue par des règles écrites (sans requête à un modèle, donc gratuit), et
 * la liste de chaque tâche se règle dans Administration → Modèles. Le client ne voit jamais le nom d'un modèle ni d'un
 * fournisseur, et aucune mention « Pro ».
 */
class ModelRouter
{
    /** Tâches reconnues, dans l'ordre de l'administration. */
    public const TASKS = [
        'reponse_simple', 'reponse_reflexion', 'redaction', 'traduction', 'extraction', 'code',
        'site', 'modification', 'application', 'agent', 'automatisation', 'document',
    ];

    /** « qualite » : pour tous les clients ; « economie » : seulement quand le crédit est presque épuisé. */
    public const MODES = ['qualite', 'economie'];

    /** Fournisseurs qu'un interrupteur de l'administration peut couper. */
    public const PROVIDERS = ['coarena', 'openai', 'gratuits'];

    public function __construct(private Settings $settings, private Energy $energy) {}

    /**
     * @return array<string, string>
     */
    public static function taskLabels(): array
    {
        return [
            'reponse_simple' => __('Simple answer'),
            'reponse_reflexion' => __('Answer with reasoning'),
            'redaction' => __('Writing'),
            'traduction' => __('Translation'),
            'extraction' => __('Data extraction'),
            'code' => __('Programming question'),
            'site' => __('New site'),
            'modification' => __('Change of a site'),
            'application' => __('Application'),
            'agent' => __('AI agent'),
            'automatisation' => __('Automation'),
            'document' => __('Document and presentation'),
        ];
    }

    /**
     * The type of a written answer, from the words of the message (no request to a model). The order matters: a
     * translation or an extraction is recognised before a writing; a long or « why / compare / plan » message asks
     * for reasoning; anything else is a simple answer.
     */
    public function classifyAnswer(string $message): string
    {
        $text = Str::lower(Str::ascii($message));

        return match (true) {
            (bool) preg_match('/\b(tradui[st]|traduction|translate|en anglais|en francais|en ewe|en espagnol|en allemand|in english)\b/', $text) => 'traduction',
            (bool) preg_match('/\b(extrai[st]|extraction|json|tableau (a partir|des donnees)|liste (des|les) (noms|numeros|montants|dates)|remplis? (ce|le) tableau|csv)\b/', $text) => 'extraction',
            (bool) preg_match('/\b(code|fonction|bug|erreur (php|javascript|python|sql)|script|requete sql|programm|api|regex|laravel|react|python|javascript|html|css)\b/', $text) => 'code',
            (bool) preg_match('/\b(redige|rediger|ecris|ecrire|courriel|e-mail|email|lettre|publication|post|annonce|discours|slogan|texte pour|message pour|cv\b|motivation)\b/', $text) => 'redaction',
            (bool) preg_match('/\b(pourquoi|compare|comparer|comparaison|analyse|analyser|strategie|plan d|budget|combien (il )?faut|conseille|conseil|explique|avantages|inconvenients|lequel|que choisir|rentab|etapes)\b/', $text),
            mb_strlen($message) > 400 => 'reponse_reflexion',
            default => 'reponse_simple',
        };
    }

    /**
     * The type of a work in the computer, from the project: a new site or a change, an application, an agent, an
     * automation.
     */
    public function classifyBuild(Project $project): string
    {
        return match (true) {
            $project->isAutomation() => 'automatisation',
            $project->isAgent() => 'agent',
            $project->isApplication() => 'application',
            $project->agentRuns()->where('status', 'completed')->exists() => 'modification',
            default => 'site',
        };
    }

    /**
     * The best models for every client; the economical ones only when less than « economy_below_percent » % of the
     * client's credit is left, so that the work finishes instead of stopping.
     */
    public function mode(User $user): string
    {
        $threshold = $this->settings->int('economy_below_percent');

        return $threshold > 0 && $this->energy->percentLeft($user) < $threshold ? 'economie' : 'qualite';
    }

    public function providerEnabled(string $provider): bool
    {
        $switches = (array) ($this->settings->get('model_providers') ?? []);

        return ($switches[$provider] ?? true) !== false;
    }

    /**
     * The Coarena models for a task and a mode, in order (only the models allowed by the Runner's closed list).
     *
     * @return list<string>
     */
    public function models(string $task, string $mode): array
    {
        $routes = (array) ($this->settings->get('model_routes') ?? []);
        $list = (array) ($routes[$task][$mode] ?? []);

        return array_values(array_filter(array_map('strval', $list), fn (string $model) => in_array($model, self::COARENA_MODELS, true)));
    }

    /**
     * The entries of Coarena for this client and this task, to put before the usual list (empty when Coarena is off).
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function entries(User $user, string $task, ?string $mode = null): array
    {
        if (! $this->providerEnabled('coarena') || ! in_array($task, self::TASKS, true)) {
            return [];
        }
        $reset = now('UTC')->addDay()->startOfDay()->toIso8601String();

        return array_map(fn (string $model) => ['fournisseur' => 'coarena', 'modele' => $model, 'remise_a_zero' => $reset], $this->models($task, $mode ?? $this->mode($user)));
    }

    /**
     * The list for an answer of the first responder. Decision of the project owner (15 November 2026): Coarena was too
     * slow (up to several minutes for a greeting), so it comes LAST everywhere. Discussions: GPT-6 Luna, then GPT-6.1
     * Terra at OpenAI (while the ceiling of the day allows it), then the free Gemini 3.1 Flash-Lite with a minimal
     * thinking (measured at 0.66 second before its first word), then the other free providers, then Coarena. The models
     * come from the configuration (« answer_route »), never written here.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $usual
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function answerRoute(User $user, string $task, array $usual): array
    {
        $route = (array) config('kekeli.answer_route', []);
        $reset = now('UTC')->addDay()->startOfDay()->toIso8601String();
        $first = app(AnswerModels::class)->openAiAvailable()
            ? array_map(fn (string $model) => ['fournisseur' => 'openai', 'modele' => $model, 'remise_a_zero' => $reset], array_map('strval', (array) ($route['openai'] ?? [])))
            : [];
        $free = array_values(array_filter([(array) ($route['gratuit'] ?? [])], fn (array $entry) => filled($entry['fournisseur'] ?? null) && filled($entry['modele'] ?? null)));
        $free = array_map(fn (array $entry) => $entry + ['remise_a_zero' => $reset], $free);
        $seen = array_map(fn (array $entry) => $entry['fournisseur'].'/'.$entry['modele'], [...$first, ...$free]);
        $others = array_values(array_filter($usual, fn (array $entry) => ! in_array($entry['fournisseur'].'/'.$entry['modele'], $seen, true)));

        return $this->filtered([...$first, ...$free, ...$others, ...$this->entries($user, $task, 'qualite')]);
    }

    /**
     * The final list for the Runner for a creation: the usual list first (GPT-6.1 Terra and Sol at OpenAI according to
     * the build settings, then the free offers), Coarena LAST (decision of 15 November 2026), only when all the others
     * failed; without the providers switched off in the administration.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $usual
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function route(User $user, string $task, array $usual): array
    {
        return $this->filtered([...$usual, ...$this->entries($user, $task)]);
    }

    /**
     * Without the providers switched off in the administration.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $list
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    private function filtered(array $list): array
    {
        return array_values(array_filter($list, function (array $entry) {
            return match ($entry['fournisseur']) {
                'coarena' => true,
                'openai' => $this->providerEnabled('openai'),
                default => $this->providerEnabled('gratuits'),
            };
        }));
    }

    /**
     * Settings of Coarena sent to the Runner with each request: the switch between accounts (off until Coarena confirms
     * it, docs/coarena.md) and the keys switched off. Only the NAMES of the keys.
     *
     * @return array{comptes_multiples: bool, cles_desactivees: list<string>}
     */
    public function coarenaSettings(): array
    {
        return [
            'comptes_multiples' => (bool) $this->settings->get('coarena_multi_accounts'),
            'cles_desactivees' => array_values(array_filter((array) ($this->settings->get('coarena_disabled_keys') ?? []), fn ($name) => is_string($name) && preg_match('/^COARENA_API_KEY(_\d{1,3})?$/', $name))),
        ];
    }

    /**
     * Same closed list as the Runner (runner/src/coarena.js, MODELES_COARENA): only the models that passed the test
     * bench, plus Claude, which could not be tested (unavailable at Coarena until 1 December 2026) and is in no route.
     */
    public const COARENA_MODELS = [
        'gpt-6-luna', 'gpt-6-1-terra', 'gpt-6-1-sol', 'gemini-3-8-flash', 'glm-5-3-flash', 'kimi-k3-1-pro',
        'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5',
    ];

    /**
     * Quality on 10 after reading the answers and the sites of the test bench (runner/mesures/modeles-coarena.md),
     * and whether the model was kept. For the administrator only.
     *
     * @var array<string, array{0: float, 1: bool}>
     */
    public const BENCH_QUALITY = [
        'gpt-6-1-sol' => [10, true], 'gpt-6-1-terra' => [9.5, true], 'gpt-6-luna' => [9, true], 'kimi-k3-1-pro' => [9, true],
        'gemini-3-8-flash' => [8.5, true], 'glm-5-3-flash' => [8, true], 'gemini-4-argon' => [7.5, false],
        'deepseek-v4-1-flash-1017' => [7, false], 'minimax-m3-1-flash' => [6, false],
    ];

    /** Names shown to the administrator only (never to a client). */
    public const COARENA_LABELS = [
        'gpt-6-luna' => 'GPT-6 Luna', 'gpt-6-1-terra' => 'GPT-6.1 Terra', 'gpt-6-1-sol' => 'GPT-6.1 Sol', 'gemini-3-8-flash' => 'Gemini 3.8 Flash',
        'gemini-4-argon' => 'Gemini 4 Argon', 'deepseek-v4-1-flash-1017' => 'DeepSeek V4.1 Flash', 'kimi-k3-1-pro' => 'Kimi K3.1 Pro',
        'glm-5-3-flash' => 'GLM 5.3 Flash', 'minimax-m3-1-flash' => 'MiniMax M3.1 Flash', 'claude-fable-5-2' => 'Claude Fable 5.2', 'claude-haiku-5-5' => 'Claude Haiku 5.5',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5', 'claude-opus-5-5' => 'Claude Opus 5.5',
    ];
}
