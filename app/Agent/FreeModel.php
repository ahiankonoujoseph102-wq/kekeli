<?php

namespace App\Agent;

use App\Models\AdminAlert;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * FreeModel (freemodel.in), the last reinforcement of Kekeli (decision of the project owner, 25 November 2026): an
 * OpenAI-compatible gateway with a free allowance of one million tokens a month. Its models come after every free
 * provider, for the constructions, the documents and the videos above all, where the free models sometimes fail. The
 * key stays in the Runner (never in the virtual computer) and the client never sees its name.
 *
 * The allowance of the month is counted here from the tokens recorded for each request (input, output, reasoning; the
 * cached tokens are counted too, as FreeModel counts every token sent); the administrator receives an alert at 80 % and
 * at 100 %, and once it is used up FreeModel leaves the lists until the first day of the next month.
 */
class FreeModel
{
    public const PROVIDER = 'freemodel';

    /** Closed list of the models Kekeli may ask (same list as MODELES_FREEMODEL in the Runner). */
    public const MODELS = ['claude-sonnet-5-5', 'gpt-6.1-sol', 'glm-5.3-flash', 'deepseek-v4.1-flash', 'qwen3.8-27b', 'gemini-3.7-flash'];

    /** Alert thresholds of the allowance of the month, in percent. */
    public const THRESHOLDS = [80, 100];

    public function __construct(private Settings $settings) {}

    public function enabled(): bool
    {
        $switches = (array) ($this->settings->get('model_providers') ?? []);

        return ($switches[self::PROVIDER] ?? true) !== false;
    }

    /**
     * Models of a group of tasks (discussion, document, construction), in the order of the settings, without any model
     * outside the closed list.
     *
     * @return list<string>
     */
    public function models(string $group): array
    {
        $routes = (array) ($this->settings->get('freemodel_routes') ?? config('kekeli.settings.freemodel_routes', []));

        return array_values(array_unique(array_filter(array_map('strval', (array) ($routes[$group] ?? [])), fn (string $model) => in_array($model, self::MODELS, true))));
    }

    /**
     * Entries for the Runner, or none when FreeModel is switched off or its allowance of the month is used up.
     *
     * @return list<array{fournisseur: string, modele: string, remise_a_zero: string}>
     */
    public function entries(string $task): array
    {
        if (! $this->enabled() || $this->tokensLeft() <= 0) {
            return [];
        }
        $reset = CarbonImmutable::now('UTC')->addMonthNoOverflow()->startOfMonth()->toIso8601String();

        return array_map(fn (string $model) => ['fournisseur' => self::PROVIDER, 'modele' => $model, 'remise_a_zero' => $reset], $this->models(FreeLlmApi::groupOf($task)));
    }

    public function monthlyAllowance(): int
    {
        return max(0, $this->settings->int('freemodel_monthly_tokens'));
    }

    /** Tokens used this month (UTC month, the month of FreeModel's allowance). */
    public function tokensUsedThisMonth(): int
    {
        $start = CarbonImmutable::now('UTC')->startOfMonth()->toDateString();

        return (int) ProviderUsage::query()
            ->where('provider', self::PROVIDER)
            ->where('day', '>=', $start)
            ->selectRaw('coalesce(sum(input_tokens + output_tokens + reasoning_tokens), 0) as total')
            ->value('total');
    }

    /** Tokens left this month; a margin of 5 % is kept so that a running work never meets the end of the allowance. */
    public function tokensLeft(): int
    {
        $allowance = $this->monthlyAllowance();

        return max(0, (int) floor($allowance * 0.95) - $this->tokensUsedThisMonth());
    }

    /** Alert of the administrator at 80 % and at 100 % of the allowance of the month (once per threshold and month). */
    public function checkAlerts(): void
    {
        $allowance = $this->monthlyAllowance();
        if ($allowance <= 0) {
            return;
        }
        $used = $this->tokensUsedThisMonth();
        $month = CarbonImmutable::now('UTC')->format('Y-m');
        foreach (self::THRESHOLDS as $percent) {
            if ($used < $allowance * $percent / 100 || ! Cache::add("kekeli:alerte-freemodel:{$month}:{$percent}", true, now()->addDays(40))) {
                continue;
            }
            AdminAlert::create([
                'kind' => 'freemodel_allowance',
                'message' => $percent >= 100
                    ? __('The FreeModel allowance of the month is used up (:used tokens of :allowance). Kekeli goes on with the other providers until the first day of the next month.', ['used' => number_format($used, 0, ',', ' '), 'allowance' => number_format($allowance, 0, ',', ' ')])
                    : __(':percent % of the FreeModel allowance of the month is used (:used tokens of :allowance).', ['percent' => $percent, 'used' => number_format($used, 0, ',', ' '), 'allowance' => number_format($allowance, 0, ',', ' ')]),
            ]);
        }
    }
}
