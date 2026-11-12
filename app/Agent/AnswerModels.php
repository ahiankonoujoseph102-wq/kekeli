<?php

namespace App\Agent;

use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * The model that answers the questions of the client (the first responder), chosen for each formula in
 * Administration → Réglages, like Claude or ChatGPT: the quality comes first, and the real cost of each answer is
 * counted in the client's energy. The free providers stay behind it as a safety net: when the chosen model fails, or
 * when the ceiling of OpenAI spending for the day is reached, Kekeli goes on with them without telling the client.
 *
 * Only the models of OPENAI_MODELS can be chosen at OpenAI (never a very expensive one by a wrong setting); the same
 * list is checked again by the Runner (MODELES_OPENAI in runner/src/repondant.js).
 */
class AnswerModels
{
    public const OPENAI_MODELS = ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.1-sol'];

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
    public function forRunner(User $user): array
    {
        return $this->withFirst($this->chosenFor($user));
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
    public function buildForRunner(User $user): array
    {
        return $this->withFirst($this->buildChosenFor($user));
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
