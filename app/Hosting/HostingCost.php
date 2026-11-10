<?php

namespace App\Hosting;

use App\Models\AdminAlert;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cost of Kekeli itself on Laravel Cloud (its applications and its database), read every day with the official
 * usage API (GET /api/usage, permission « organization_usage.view » only). The administrator receives an alert
 * when the cost of the month reaches 80 % then 100 % of a threshold set in Administration → Settings.
 * It never changes anything on Laravel Cloud: it only reads.
 */
class HostingCost
{
    public function __construct(private Settings $settings) {}

    public function configured(): bool
    {
        return filled(config('kekeli.hosting.token'));
    }

    /**
     * Read the cost of the current billing period, in dollars, for the applications and databases of Kekeli.
     *
     * @return array{usd: float, applications: float, databases: float, found: list<string>}
     */
    public function read(): array
    {
        $response = Http::withToken((string) config('kekeli.hosting.token'))
            ->acceptJson()
            ->timeout(20)
            ->get(rtrim((string) config('kekeli.hosting.api_url'), '/').'/usage', ['period' => 0])
            ->throw()
            ->json('data');

        if (! is_array($response)) {
            throw new RuntimeException('Réponse inattendue de Laravel Cloud.');
        }

        $found = [];
        $applications = $this->sum($response['application_totals'] ?? [], (array) config('kekeli.hosting.applications'), $found);
        $databases = $this->sum($response['resources'] ?? [], (array) config('kekeli.hosting.databases'), $found);

        if ($found === []) {
            throw new RuntimeException('Aucune application de Kekeli trouvée dans l\'usage de Laravel Cloud.');
        }

        return ['usd' => round(($applications + $databases) / 100, 2), 'applications' => $applications / 100, 'databases' => $databases / 100, 'found' => $found];
    }

    /**
     * Read the cost, keep it for the administration and send an alert when a threshold is reached (once per month).
     */
    public function check(): ?float
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $cost = $this->read();
        } catch (\Throwable $exception) {
            $this->alertOnce('hosting_cost_unreadable', __('The cost of Kekeli on Laravel Cloud could not be read: :error', ['error' => mb_substr($exception->getMessage(), 0, 200)]));

            return null;
        }

        $this->settings->set('hosting_last_cost', ['usd' => $cost['usd'], 'read_at' => now()->toIso8601String()]);

        $threshold = $this->settings->float('hosting_alert_usd');
        if ($threshold > 0) {
            if ($cost['usd'] >= $threshold) {
                $this->alertOnce('hosting_cost_100', __('Kekeli has cost :cost $ on Laravel Cloud this month: the threshold of :threshold $ is reached. Talkami and Lomé IA School are not affected.', ['cost' => number_format($cost['usd'], 2, ',', ' '), 'threshold' => number_format($threshold, 2, ',', ' ')]));
            } elseif ($cost['usd'] >= $threshold * (int) config('kekeli.alert_threshold') / 100) {
                $this->alertOnce('hosting_cost_80', __('Kekeli has cost :cost $ on Laravel Cloud this month, more than :percent % of the threshold of :threshold $.', ['cost' => number_format($cost['usd'], 2, ',', ' '), 'percent' => (int) config('kekeli.alert_threshold'), 'threshold' => number_format($threshold, 2, ',', ' ')]));
            }
        }

        return $cost['usd'];
    }

    /**
     * Total cost, in cents, of the items whose name is one of the given names (search in the whole answer, because
     * Laravel Cloud does not document the exact shape of each item).
     *
     * @param  list<string>  $names
     * @param  list<string>  $found
     */
    private function sum(mixed $data, array $names, array &$found): float
    {
        if (! is_array($data)) {
            return 0;
        }

        $wanted = array_map(fn ($name) => strtolower(trim((string) $name)), array_filter($names));
        $name = strtolower((string) ($data['name'] ?? data_get($data, 'application.name') ?? data_get($data, 'database.name') ?? ''));

        if ($name !== '' && in_array($name, $wanted, true)) {
            foreach (['total_cost_cents', 'total_cents', 'cost_cents'] as $field) {
                if (is_numeric($data[$field] ?? null)) {
                    $found[] = $name;

                    return (float) $data[$field];
                }
            }
        }

        $total = 0.0;
        foreach ($data as $value) {
            if (is_array($value)) {
                $total += $this->sum($value, $names, $found);
            }
        }

        return $total;
    }

    private function alertOnce(string $kind, string $message): void
    {
        $already = AdminAlert::query()->where('kind', $kind)->where('created_at', '>=', now()->startOfMonth())->exists();

        if (! $already) {
            AdminAlert::create(['kind' => $kind, 'message' => $message]);
        }
    }
}
