<?php

namespace App\Agent;

use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Le routeur de modèles de Kekeli (docs/plan.md, parties 26.26, 26.35 et 26.37) : il reconnaît le TYPE de tâche (réponse
 * simple, réponse avec réflexion, rédaction, traduction, extraction, code, site, modification, application, agent,
 * automatisation, document), sans requête à un modèle, et range les fournisseurs dans l'ordre décidé par le porteur du
 * projet : OpenAI d'abord (tant que le plafond du jour le permet), puis les offres gratuites (Gemini, OpenRouter, Workers
 * AI), puis FreeLLMAPI (Groq, LongCat, Z.ai…) en dernier, à la place de Coarena (supprimé le 15 novembre 2026). Une seule
 * expérience pour tous : les mêmes modèles de qualité pour chaque client, seul le crédit change selon la formule.
 *
 * Les interrupteurs des fournisseurs se règlent dans Administration → Modèles. Le client ne voit jamais le nom d'un
 * modèle ni d'un fournisseur.
 */
class ModelRouter
{
    /** Tâches reconnues, dans l'ordre de l'administration. */
    public const TASKS = [
        'reponse_simple', 'reponse_reflexion', 'redaction', 'traduction', 'extraction', 'code',
        'site', 'modification', 'application', 'agent', 'automatisation', 'document',
    ];

    /** Fournisseurs qu'un interrupteur de l'administration peut couper. */
    public const PROVIDERS = ['openai', 'gratuits', 'freellmapi'];

    public function __construct(private Settings $settings) {}

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

    public function providerEnabled(string $provider): bool
    {
        $switches = (array) ($this->settings->get('model_providers') ?? []);

        return ($switches[$provider] ?? true) !== false;
    }

    /**
     * The list for an answer of the first responder (decision of the project owner, 15 November 2026): GPT-6.1 Terra,
     * then GPT-6 Luna at OpenAI (while the ceiling of the day allows it), then the free Gemini 3.1 Flash-Lite with a
     * minimal thinking (measured at 0.66 second before its first word), then the other free providers, then FreeLLMAPI.
     * The models come from the configuration (« answer_route » and « freellmapi_routes »), never written here.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $usual
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function answerRoute(User $user, string $task, array $usual): array
    {
        $route = (array) config('kekeli.answer_route', []);
        $reset = now('UTC')->addDay()->startOfDay()->toIso8601String();
        $first = app(AnswerModels::class)->openAiAvailable()
            ? array_map(fn (string $model) => ['fournisseur' => 'openai', 'modele' => $model, 'remise_a_zero' => $reset] + (filled($route['openai_reflexion'] ?? null) ? ['reflexion' => (string) $route['openai_reflexion']] : []), array_map('strval', (array) ($route['openai'] ?? [])))
            : [];
        $free = array_values(array_filter([(array) ($route['gratuit'] ?? [])], fn (array $entry) => filled($entry['fournisseur'] ?? null) && filled($entry['modele'] ?? null)));
        $free = array_map(fn (array $entry) => $entry + ['remise_a_zero' => $reset], $free);
        $seen = array_map(fn (array $entry) => $entry['fournisseur'].'/'.$entry['modele'], [...$first, ...$free]);
        $others = array_values(array_filter($usual, fn (array $entry) => ! in_array($entry['fournisseur'].'/'.$entry['modele'], $seen, true)));

        // FreeLLMAPI (Groq, LongCat, Z.ai…) : une capacité gratuite de plus, à la place de Coarena (docs/plan.md, partie 26.38).
        return $this->filtered([...$first, ...$free, ...$others, ...app(FreeLlmApi::class)->entries($user, $task)]);
    }

    /**
     * The final list for the Runner for a creation: the usual list first (GPT-6.1 Terra and Sol at OpenAI according to
     * the build settings, then the free offers), FreeLLMAPI last; without the providers switched off in the
     * administration.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $usual
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function route(User $user, string $task, array $usual): array
    {
        return $this->filtered([...$usual, ...app(FreeLlmApi::class)->entries($user, $task)]);
    }

    /**
     * Without the providers switched off in the administration.
     *
     * @param  list<array{fournisseur: string, modele: string, remise_a_zero: string}>  $list
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    private function filtered(array $list): array
    {
        // Décision du porteur du projet (15 novembre 2026) : le crédit d'OpenAI est épuisé ; OpenAI passe EN DERNIER,
        // après tous les fournisseurs gratuits (Gemini, OpenRouter, Cloudflare Workers AI, FreeLLMAPI), partout.
        $list = [...array_filter($list, fn (array $entry) => $entry['fournisseur'] !== 'openai'), ...array_filter($list, fn (array $entry) => $entry['fournisseur'] === 'openai')];

        return array_values(array_filter($list, function (array $entry) {
            return match ($entry['fournisseur']) {
                'freellmapi' => $this->providerEnabled('freellmapi'),
                'openai' => $this->providerEnabled('openai'),
                default => $this->providerEnabled('gratuits'),
            };
        }));
    }
}
