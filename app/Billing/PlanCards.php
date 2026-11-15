<?php

namespace App\Billing;

use App\Agent\Energy;
use App\Support\Settings;

/**
 * The cards of the formulas, like Claude, ChatGPT, Lovable, Bolt.new, Manus and Cursor: the name, the price, one
 * sentence « for whom », a button, then « Tout ce que contient …, et en plus : » with the advantages. One source for
 * the page of the formulas, the card « crédits gratuits utilisés », Réglages → Formule and the payment. Every number
 * comes from the settings (energy, versions kept): the client only reads true figures.
 */
class PlanCards
{
    public function __construct(private Settings $settings, private PlanPerks $perks) {}

    /**
     * The cards of the formulas shown to the clients (the paid ones only once their prices are published).
     *
     * @return array<string, array{key: string, name: string, price_fcfa: int, price_usd: int, free: bool, popular: bool, for_whom: string, button: string, includes: ?string, features: list<string>}>
     */
    public function all(): array
    {
        $plans = (array) $this->settings->get('plans');
        if (! $this->settings->get('plans_published')) {
            $plans = array_filter($plans, fn ($plan) => (int) $plan['price_fcfa'] === 0);
        }

        $cards = [];
        $previous = null;
        foreach ($this->ordered($plans) as $key => $plan) {
            $cards[$key] = $this->card((string) $key, $plan, $previous);
            $previous = $cards[$key];
        }

        return $cards;
    }

    /**
     * @return array{key: string, name: string, price_fcfa: int, price_usd: int, free: bool, popular: bool, for_whom: string, button: string, includes: ?string, features: list<string>}|null
     */
    public function for(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The formulas from the cheapest to the most expensive.
     *
     * @param  array<string, array<string, mixed>>  $plans
     * @return array<string, array<string, mixed>>
     */
    private function ordered(array $plans): array
    {
        uasort($plans, fn ($a, $b) => (int) $a['price_fcfa'] <=> (int) $b['price_fcfa']);

        return $plans;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>|null  $previous
     * @return array{key: string, name: string, price_fcfa: int, price_usd: int, free: bool, popular: bool, for_whom: string, button: string, includes: ?string, features: list<string>}
     */
    private function card(string $key, array $plan, ?array $previous): array
    {
        $name = (string) $plan['name'];
        $free = (int) $plan['price_fcfa'] === 0;
        $freeKey = app(Subscriptions::class)->freePlanKey();

        [$forWhom, $features] = match ($key) {
            'gratuite' => [__('Discover what Kekeli can create for you.'), [
                __('Websites, applications, AI agents and automations'),
                __('Documents, presentations, images and videos'),
                __('Putting your websites online'),
                __('Memory, connectors and sending to GitHub'),
                $this->freeEnergyLine($plan),
                $this->chatsLine($plan),
            ]],
            'pro' => [__('To create every week, without stopping on the way.'), [
                $this->perks->ratio('pro', $freeKey) >= 2
                    ? __(':count times more energy than the Gratuite formula', ['count' => $this->perks->ratioText('pro', $freeKey)])
                    : __('The energy of the whole month, to use whenever you want'),
                $this->chatsLine($plan),
                __('Energy not used carried over to the next month'),
                __('Priority when Kekeli is very busy'),
                __(':count versions kept per project', ['count' => number_format($this->settings->int('versions_kept_paid'), 0, ',', ' ')]),
                __('Receipt sent by e-mail with every payment'),
            ]],
            'expert' => [__('When Kekeli becomes your everyday work tool.'), [
                __(':count times more energy than Pro', ['count' => $this->perks->ratioText('expert', 'pro')]),
                $this->chatsLine($plan),
                __('Several projects worked on at the same time'),
                __('Early access to the new functions of Kekeli'),
            ]],
            'agence' => [__('For agencies, freelancers and schools who create for their clients.'), [
                __(':count times more energy than Pro', ['count' => $this->perks->ratioText('agence', 'pro')]),
                $this->chatsLine($plan),
                __('Highest priority'),
                __('Direct help on WhatsApp'),
            ]],
            // Une formule ajoutée dans l'administration : seulement ce qui est sûr.
            default => [$free ? __('Discover what Kekeli can create for you.') : __('More energy to create with Kekeli.'), []],
        };

        return [
            'key' => $key,
            'name' => $name,
            'price_fcfa' => (int) $plan['price_fcfa'],
            'price_usd' => (int) ($plan['price_usd'] ?? 0),
            'free' => $free,
            'popular' => $key === 'pro',
            'for_whom' => $forWhom,
            'button' => $free ? __('Start for free') : __('Go to :name', ['name' => $name]),
            'includes' => $previous ? __('Everything in :name, and also:', ['name' => $previous['name']]) : null,
            // Chaque ligne commence par une majuscule (« Près de 3 fois plus d'énergie que Pro »).
            'features' => array_map(fn (string $line) => mb_strtoupper(mb_substr($line, 0, 1)).mb_substr($line, 1), $features),
        ];
    }

    /**
     * The energy of the free formula, as the client reads it: « enough for a complete showcase site every day » only
     * when it is true (the estimate of a site, free_site_estimate_fcfa, fits in the energy of a day).
     *
     * @param  array<string, mixed>  $plan
     */
    private function freeEnergyLine(array $plan): string
    {
        $fits = Energy::periodOf($plan) === 'day' && $this->settings->float('free_site_estimate_fcfa') > 0
            && $this->settings->float('free_site_estimate_fcfa') <= (float) ($plan['energy_fcfa'] ?? 0);

        return $fits ? __('Enough energy every day for a complete showcase website') : __('Free energy, recharged every day');
    }

    /**
     * The simple discussions of the day (answers without any creation), which do not use the energy.
     *
     * @param  array<string, mixed>  $plan
     */
    private function chatsLine(array $plan): string
    {
        $count = (int) ($plan['simple_chats_per_day'] ?? Energy::DEFAULT_SIMPLE_CHATS);

        return __('Up to :count discussions per day, without using your energy', ['count' => number_format($count, 0, ',', ' ')]);
    }
}
