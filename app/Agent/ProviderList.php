<?php

namespace App\Agent;

use Carbon\CarbonImmutable;

/**
 * Ordered list of the AI providers, read from the settings (never written in the code).
 */
class ProviderList
{
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
