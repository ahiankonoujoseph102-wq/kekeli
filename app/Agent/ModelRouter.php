<?php

namespace App\Agent;

use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Le routeur de modèles de Kekeli (docs/plan.md, partie 26.26) : il reconnaît le TYPE de tâche (réponse simple, réponse
 * avec réflexion, rédaction, traduction, extraction, code, site, modification, application, agent, automatisation,
 * document) et choisit les modèles de Coarena retenus par le banc d'essai (runner/mesures/modeles-coarena.md), selon le
 * mode du client : « Standard » (formule Gratuite, et par défaut) ou « Pro » (formules payantes qui l'ont choisi).
 * Viennent ensuite, sans rien changer, les modèles habituels (OpenAI, puis les offres gratuites) : si Coarena est épuisé,
 * lent ou en panne, le Runner passe au suivant sans message technique au client.
 *
 * Rien n'est tiré au hasard : la tâche est reconnue par des règles écrites (sans requête à un modèle, donc gratuit), et
 * la liste de chaque tâche se règle dans Administration → Modèles. Le client ne voit jamais le nom d'un modèle ni d'un
 * fournisseur.
 */
class ModelRouter
{
    /** Tâches reconnues, dans l'ordre de l'administration. */
    public const TASKS = [
        'reponse_simple', 'reponse_reflexion', 'redaction', 'traduction', 'extraction', 'code',
        'site', 'modification', 'application', 'agent', 'automatisation', 'document',
    ];

    public const MODES = ['standard', 'pro'];

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
     * Standard for the free formula; for a paid formula, Pro when the client chose it (« + » → « Mode Pro »).
     */
    public function mode(User $user): string
    {
        return ! $this->energy->isFree($user) && $user->model_mode === 'pro' ? 'pro' : 'standard';
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
    public function entries(User $user, string $task): array
    {
        if (! $this->providerEnabled('coarena') || ! in_array($task, self::TASKS, true)) {
            return [];
        }
        $reset = now('UTC')->addDay()->startOfDay()->toIso8601String();

        return array_map(fn (string $model) => ['fournisseur' => 'coarena', 'modele' => $model, 'remise_a_zero' => $reset], $this->models($task, $this->mode($user)));
    }

    /**
     * The final list for the Runner: Coarena for this task first, then the usual list (OpenAI, free offers), without
     * the providers switched off in the administration.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $usual
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function route(User $user, string $task, array $usual): array
    {
        $list = [...$this->entries($user, $task), ...$usual];

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

    /** Same closed list as the Runner (runner/src/coarena.js, MODELES_COARENA). */
    public const COARENA_MODELS = [
        'gpt-6-luna', 'gpt-6-1-terra', 'gpt-6-1-sol', 'gemini-3-8-flash', 'gemini-4-argon', 'deepseek-v4-1-flash-1017',
        'kimi-k3-1-pro', 'glm-5-3-flash', 'minimax-m3-1-flash', 'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5',
    ];

    /** Names shown to the administrator only (never to a client). */
    public const COARENA_LABELS = [
        'gpt-6-luna' => 'GPT-6 Luna', 'gpt-6-1-terra' => 'GPT-6.1 Terra', 'gpt-6-1-sol' => 'GPT-6.1 Sol', 'gemini-3-8-flash' => 'Gemini 3.8 Flash',
        'gemini-4-argon' => 'Gemini 4 Argon', 'deepseek-v4-1-flash-1017' => 'DeepSeek V4.1 Flash', 'kimi-k3-1-pro' => 'Kimi K3.1 Pro',
        'glm-5-3-flash' => 'GLM 5.3 Flash', 'minimax-m3-1-flash' => 'MiniMax M3.1 Flash', 'claude-haiku-5-5' => 'Claude Haiku 5.5',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5', 'claude-opus-5-5' => 'Claude Opus 5.5',
    ];
}
