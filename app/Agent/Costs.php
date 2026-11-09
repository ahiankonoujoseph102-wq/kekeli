<?php

namespace App\Agent;

use App\Support\Settings;

/**
 * Cost of the agent's work, from the price grid set in the administration.
 * The cost of a model is the price of its paid offer, even while Kekeli uses a free offer:
 * that is what the work would cost with a paid key.
 */
class Costs
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array{label: string, input: float, output: float, cache: float}|null
     */
    public function price(string $provider, string $model): ?array
    {
        $price = $this->settings->get('model_prices')[$provider.'/'.$model] ?? null;

        return is_array($price) ? [
            'label' => (string) ($price['label'] ?? $model),
            'input' => (float) ($price['input'] ?? 0),
            'output' => (float) ($price['output'] ?? 0),
            'cache' => (float) ($price['cache'] ?? 0),
        ] : null;
    }

    /**
     * Cost in dollars of tokens with a given price (dollars per million tokens).
     *
     * @param  array{input: float, output: float, cache: float}  $price
     */
    public function tokensCost(array $price, int $input, int $output, int $reasoning, int $cached): float
    {
        return ($input * $price['input'] + ($output + $reasoning) * $price['output'] + $cached * $price['cache']) / 1_000_000;
    }

    /**
     * Cost in dollars of the model's requests; zero when the model has no price in the grid.
     */
    public function modelCost(string $provider, string $model, int $input, int $output, int $reasoning, int $cached): float
    {
        $price = $this->price($provider, $model);

        return $price ? $this->tokensCost($price, $input, $output, $reasoning, $cached) : 0.0;
    }

    /**
     * Price in dollars of one second of a running computer (nothing is charged while it is paused).
     */
    public function computerCostPerSecond(): float
    {
        return $this->settings->float('computer_vcpu') * $this->settings->float('e2b_usd_per_vcpu_second')
            + $this->settings->float('computer_ram_gib') * $this->settings->float('e2b_usd_per_gib_second');
    }

    public function computerCost(int $seconds): float
    {
        return $seconds * $this->computerCostPerSecond();
    }

    public function toFcfa(float $usd): float
    {
        return $usd * $this->settings->float('usd_to_fcfa');
    }
}
