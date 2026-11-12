<?php

namespace App\Agent;

use Carbon\CarbonImmutable;

/**
 * Ordered list of the AI providers, read from the settings (never written in the code).
 */
class ProviderList
{
    /**
     * Free models allowed when a provider also sells paid models (same list as MODELES_GRATUITS in the Runner).
     * Cloudflare: text models able to use tools, available on the free plan.
     */
    public const FREE_MODELS = [
        'workersai' => [
            '@cf/zai-org/glm-4.7-flash',
            '@cf/zai-org/glm-5.3-flash',
            '@cf/qwen/qwen3-30b-a3b-fp8',
            '@cf/openai/gpt-oss-120b',
            '@cf/openai/gpt-oss-20b',
            '@cf/meta/llama-4-scout-17b-16e-instruct',
            '@cf/moonshotai/kimi-k2.5',
        ],
    ];

    /**
     * Entries in the order of KEKELI_FOURNISSEURS. Each entry is « provider » or « provider/model ».
     *
     * @return list<array{key: string, provider: string, model: string, label: string, daily_limit: int|null, quota_timezone: string}>
     */
    public function entries(): array
    {
        $catalogue = config('kekeli.providers', []);
        $entries = [];

        foreach (explode(',', (string) config('kekeli.providers_order', '')) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            [$provider, $model] = array_pad(explode('/', $item, 2), 2, null);
            $provider = strtolower(trim($provider));

            if (! isset($catalogue[$provider])) {
                continue;
            }

            $settings = $catalogue[$provider];
            $model = trim((string) ($model ?: $settings['model']));
            $key = $provider.'/'.$model;

            if ($model === '' || isset($entries[$key])) {
                continue;
            }

            // Aucune dépense possible : un modèle OpenRouter payant n'entre jamais dans la liste.
            if (! self::isAllowed($provider, $model)) {
                continue;
            }

            $limit = $settings['daily_limit'] ?? null;

            $entries[$key] = [
                'key' => $key,
                'provider' => $provider,
                'model' => $model,
                'label' => $settings['label'],
                'daily_limit' => is_numeric($limit) ? (int) $limit : null,
                'quota_timezone' => $settings['quota_timezone'] ?? 'UTC',
            ];
        }

        return array_values($entries);
    }

    /**
     * No spending possible, even by a wrong setting (the same rule as modeleAutorise() in the Runner):
     * only free models at OpenRouter (« :free »), never the random router « openrouter/… »; only the list of free
     * models at Cloudflare; never a paid Gemini model (Pro, Argon, Ultra).
     */
    public static function isAllowed(string $provider, string $model): bool
    {
        if ($model === '') {
            return false;
        }

        if ($provider === 'openrouter') {
            return (bool) preg_match('#^[a-z0-9][\w.\-]*/[\w.\-]+:free$#i', $model) && ! str_starts_with(strtolower($model), 'openrouter/');
        }

        if (isset(self::FREE_MODELS[$provider])) {
            return in_array($model, self::FREE_MODELS[$provider], true);
        }

        // OpenAI ne sert qu'aux réponses (App\Agent\AnswerModels) : jamais dans la liste de l'agent.
        if ($provider === 'openai') {
            return false;
        }

        if ($provider === 'gemini') {
            return ! preg_match('/(^|-)(pro|argon|ultra)(-|$)/i', $model);
        }

        return true;
    }

    /**
     * List sent to the Runner with each request.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function forRunner(): array
    {
        return array_map(fn (array $entry) => [
            'fournisseur' => $entry['provider'],
            'modele' => $entry['model'],
            'remise_a_zero' => $this->nextReset($entry['provider'])->utc()->toIso8601String(),
        ], $this->entries());
    }

    /**
     * Current quota day of a provider (Gemini counts its day in Pacific time).
     */
    public function quotaDay(string $provider): string
    {
        $timezone = config("kekeli.providers.$provider.quota_timezone", 'UTC');

        return CarbonImmutable::now($timezone)->toDateString();
    }

    /**
     * Next reset of a provider's daily quota.
     */
    public function nextReset(string $provider): CarbonImmutable
    {
        $timezone = config("kekeli.providers.$provider.quota_timezone", 'UTC');

        return CarbonImmutable::now($timezone)->addDay()->startOfDay();
    }
}
