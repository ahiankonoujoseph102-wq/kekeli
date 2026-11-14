<?php

namespace App\Agent;

use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * The model that answers the questions of the client (the first responder), chosen for each formula in
 * Administration → Réglages, like Claude or ChatGPT: the quality comes first, and the real cost of each answer is
 * counted in the client's energy. For the questions, the free providers stay behind it as a safety net: when the
 * chosen model fails, or when the ceiling of OpenAI spending for the day is reached, Kekeli goes on with them without
 * telling the client. A construction never falls back to them: it stops and resumes the next day.
 *
 * Only the models of OPENAI_MODELS can be chosen at OpenAI (never a very expensive one by a wrong setting); the same
 * list is checked again by the Runner (MODELES_OPENAI in runner/src/repondant.js).
 */
class AnswerModels
{
    public const OPENAI_MODELS = ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.1-sol'];

    /** Generators of images that can be chosen (the same list is checked by the Runner, GENERATEURS). */
    public const IMAGE_MODELS = ['cloudflare/flux-1-schnell', 'cloudflare/flux-2-klein-4b', 'openai/gpt-image-2.6-flare', 'openai/gpt-image-2.6-sunburst'];

    /**
     * The generator of images of the client's formula, and how its posters are checked. A generator of OpenAI when the
     * day's ceiling is reached: the free generator takes over (an image, unlike a construction, is finished by it).
     *
     * @return array{generateur: string, verification: ?array{fournisseur: string, modele: string}, secours: string}
     */
    public function imageFor(User $user): array
    {
        $fallback = (string) config('kekeli.image_fallback_model');
        $chosen = trim((string) ($this->energy->plan($user)['image_model'] ?? ''));
        $chosen = in_array($chosen, self::IMAGE_MODELS, true) ? $chosen : $fallback;
        if (str_starts_with($chosen, 'openai/') && ! $this->openAiAvailable()) {
            $chosen = $fallback;
        }
        [$provider, $model] = array_pad(explode('/', (string) config('kekeli.image_check_model'), 2), 2, '');
        $check = $provider === 'openai' && in_array($model, self::OPENAI_MODELS, true) && str_starts_with($chosen, 'openai/') ? ['fournisseur' => 'openai', 'modele' => $model] : null;

        return ['generateur' => $chosen, 'verification' => $check, 'secours' => $fallback];
    }

    public function __construct(private Settings $settings, private ProviderList $providers, private Energy $energy) {}

    /**
     * Whether a setting « provider/model » can answer the questions: a model of OpenAI in the list, or a free model
     * of the agent's providers.
     */
    public static function isValid(?string $value): bool
    {
        [$provider, $model] = array_pad(explode('/', trim((string) $value), 2), 2, '');

        return $provider === 'openai'
            ? in_array($model, self::OPENAI_MODELS, true)
            : isset(config('kekeli.providers')[$provider]) && ProviderList::isAllowed($provider, $model);
    }

    /**
     * The model chosen for the formula of the client (« provider/model »), or null to use the free list only.
     */
    public function chosenFor(User $user): ?string
    {
        $plan = $this->energy->plan($user);
        $value = trim($plan['answer_model']);

        return $value !== '' && self::isValid($value) ? $value : null;
    }

    /**
     * Dollars spent at OpenAI today (UTC day, the day of its invoices): answers and dictation together.
     */
    public function openAiSpentToday(): float
    {
        return (float) ProviderUsage::query()
            ->where('provider', 'openai')
            ->whereDate('day', CarbonImmutable::now('UTC')->toDateString())
            ->sum('cost_usd');
    }

    /**
     * Whether OpenAI can still be used today: below the ceiling set in Administration → Réglages (0 = never).
     */
    public function openAiAvailable(): bool
    {
        $ceiling = $this->settings->float('openai_daily_cap_usd');

        return $ceiling > 0 && $this->openAiSpentToday() < $ceiling;
    }

    /**
     * The list sent to the Runner for an answer: the model of the formula first (when it can still be used), then
     * the free providers of the agent, in their usual order.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    /**
     * The economic model (the answer model of the free formula), then the free providers: for the small internal
     * tasks (summary of a long discussion, small change), whatever the formula of the client.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function economic(): array
    {
        $chosen = trim((string) (config('kekeli.answer_models.gratuite') ?? ''));
        $plans = (array) $this->settings->get('plans');
        $chosen = trim((string) ($plans['gratuite']['answer_model'] ?? '')) ?: $chosen;

        return $this->withFirst(self::isValid($chosen) ? $chosen : null);
    }

    public function forRunner(User $user): array
    {
        return $this->withFirst($this->chosenFor($user));
    }

    /**
     * The models allowed when a connector of the client is active: only the paid model of the formula at OpenAI
     * (never a free offer, which may use the data). Empty when it cannot be used today: the request stops and resumes.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function connectorModelsFor(User $user): array
    {
        $chosen = $this->chosenFor($user) ?? '';
        if (! str_starts_with($chosen, 'openai/') || ! $this->openAiAvailable()) {
            return [];
        }

        return array_slice($this->withFirst($chosen), 0, 1);
    }

    /**
     * Whether a message with an active connector must wait: the paid model of the formula is at OpenAI and the day's
     * ceiling is reached (« Kekeli est très sollicité · Reprise à … », never a free model with the client's data).
     */
    public function connectorCapReached(User $user): bool
    {
        return str_starts_with($this->chosenFor($user) ?? '', 'openai/') && ! $this->openAiAvailable();
    }

    /**
     * The model that builds the sites and applications for the formula of the client (« provider/model »), chosen in
     * Administration → Réglages after the real comparison of docs/plan.md, partie 27; null for the free list only.
     */
    public function buildChosenFor(User $user): ?string
    {
        $value = trim((string) ($this->energy->plan($user)['build_model'] ?? ''));

        return $value !== '' && self::isValid($value) ? $value : null;
    }

    /**
     * The list sent to the Runner for a work in the computer: the build model of the formula first (when OpenAI can
     * still be used today), then the free providers. OpenAI goes through the relay of the Runner, never in the computer.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    /**
     * For a small change of a document, a presentation, an image or a video (a colour, a text, a price, a date): a
     * cheaper model of the formula, with the same measured quality (runner/mesures/retouches), then the free providers.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function smallEditForRunner(User $user): array
    {
        $key = $this->energy->isFree($user) ? 'gratuite' : (string) $user->plan;
        $chosen = trim((string) (config('kekeli.small_edit_models')[$key] ?? ''));
        if ($chosen === '' || ! self::isValid($chosen)) {
            return $this->buildForRunner($user);
        }
        if (str_starts_with($chosen, 'openai/')) {
            return $this->openAiAvailable() ? array_slice($this->withFirst($chosen), 0, 1) : [];
        }

        return $this->withFirst($chosen);
    }

    /**
     * The model that extracts the memories of a message: the cheapest one that does the work well (measured). The
     * free providers for the free formula; GPT-6 Luna for the paid ones (never a free offer with their data). When the
     * day's OpenAI ceiling is reached, nothing is extracted for a paid client (empty list): his memory waits.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function memoryFor(User $user): array
    {
        $key = $this->energy->isFree($user) ? 'gratuite' : (string) $user->plan;
        $chosen = trim((string) (config('kekeli.memory_models')[$key] ?? ''));
        if ($chosen === '' || ! self::isValid($chosen)) {
            return $this->providers->forRunner();
        }
        if (str_starts_with($chosen, 'openai/')) {
            return $this->openAiAvailable() ? array_slice($this->withFirst($chosen), 0, 1) : [];
        }

        return $this->withFirst($chosen);
    }

    public function buildForRunner(User $user): array
    {
        $chosen = $this->buildChosenFor($user);

        // Une construction ne passe jamais d'OpenAI à un modèle gratuit, qui ne sait pas la finir : elle s'arrête
        // et reprend quand le plafond du jour est remis à zéro (SendAgentRun, RunnerEventHandler::stopRequest).
        if ($chosen !== null && str_starts_with($chosen, 'openai/')) {
            return $this->openAiAvailable() ? array_slice($this->withFirst($chosen), 0, 1) : [];
        }

        return $this->withFirst($chosen);
    }

    /**
     * Whether a construction of this client must wait: its build model is at OpenAI and the day's ceiling of OpenAI
     * spending is reached. The simple questions go on with the free providers; a construction stops (« Reprise à … »).
     */
    public function buildCapReached(User $user): bool
    {
        $chosen = $this->buildChosenFor($user);

        return $chosen !== null && str_starts_with($chosen, 'openai/') && ! $this->openAiAvailable();
    }

    /**
     * When the constructions resume: the ceiling is counted by UTC day, shown in Lomé time (« 00 h 00 »).
     */
    public function buildResumesText(): string
    {
        return CarbonImmutable::now('UTC')->addDay()->startOfDay()->timezone(config('kekeli.display_timezone'))->format('H \h i');
    }

    /**
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    private function withFirst(?string $chosen): array
    {
        $list = $this->providers->forRunner();

        if ($chosen === null) {
            return $list;
        }

        [$provider, $model] = explode('/', $chosen, 2);
        if ($provider === 'openai' && ! $this->openAiAvailable()) {
            return $list;
        }

        $first = ['fournisseur' => $provider, 'modele' => $model, 'remise_a_zero' => CarbonImmutable::now('UTC')->addDay()->startOfDay()->toIso8601String()];

        return [$first, ...array_values(array_filter($list, fn (array $entry) => $chosen !== $entry['fournisseur'].'/'.$entry['modele']))];
    }
}
