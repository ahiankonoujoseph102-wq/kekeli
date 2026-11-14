<?php

namespace App\Billing;

use App\Agent\Energy;
use App\Models\User;
use App\Support\Settings;

/**
 * One experience for everybody (decision of the 14th of November 2026): every tool is in the free formula; the paid
 * formulas only add capacity and advantages, like Claude, ChatGPT, Lovable, Bolt.new, Manus and Cursor:
 * - the energy not used in a month carried over to the next one (Energy::carriedOverFcfa);
 * - priority when Kekeli is very busy: the most expensive formula first (Agence, Expert, Pro, then Gratuite);
 * - early access to new functions (Expert and Agence), opened by the administrator;
 * - direct help on WhatsApp (Agence).
 */
class PlanPerks
{
    /** Minutes before a message tries again when every AI provider is busy, from the highest priority to the lowest. */
    private const RETRY_MINUTES = [2, 3, 4, 5];

    /**
     * Functions that the administrator can open first to the formulas with early access: tools of the first
     * responder in a discussion (Administration → Réglages).
     */
    public const EARLY_ACCESS_TOOLS = [
        'creer_site' => 'Websites',
        'creer_application' => 'Applications',
        'creer_agent' => 'AI agents',
        'creer_automatisation' => 'Automations',
        'creer_document' => 'Documents',
        'creer_presentation' => 'Presentations',
        'creer_image' => 'Images and posters',
        'creer_video' => 'Videos',
    ];

    public function __construct(private Settings $settings, private Energy $energy) {}

    /**
     * Priority of the formula of the client: 0 for the free formula, then 1, 2, 3… by price (the most expensive
     * formula has the highest priority).
     */
    public function level(User $user): int
    {
        return $this->levelOfPrice($this->energy->plan($user)['price_fcfa']);
    }

    public function levelOfPrice(int $price): int
    {
        if ($price <= 0) {
            return 0;
        }

        return collect($this->settings->get('plans'))->map(fn ($plan) => (int) $plan['price_fcfa'])->filter(fn ($p) => $p > 0)
            ->unique()->sort()->values()->search($price) + 1;
    }

    /**
     * Keys of the formulas of each priority level, from 0 (free) upwards.
     *
     * @return array<int, list<string>>
     */
    public function levels(): array
    {
        $levels = [];
        foreach ($this->settings->get('plans') as $key => $plan) {
            $levels[$this->levelOfPrice((int) $plan['price_fcfa'])][] = (string) $key;
        }
        ksort($levels);

        return $levels;
    }

    /**
     * Minutes before a message waiting for a busy provider tries again: the highest formulas try first.
     */
    public function retryMinutes(User $user): int
    {
        $top = max(array_keys($this->levels()));
        $steps = count(self::RETRY_MINUTES) - 1;
        // La formule la plus haute essaie toutes les 2 minutes, la gratuite toutes les 5 minutes.
        $rank = $top === 0 ? $steps : (int) round($steps * ($top - $this->level($user)) / $top);

        return self::RETRY_MINUTES[max(0, min($steps, $rank))];
    }

    public function hasEarlyAccess(User $user): bool
    {
        return $this->energy->plan($user)['early_access'];
    }

    public function hasWhatsappSupport(User $user): bool
    {
        return $this->energy->plan($user)['whatsapp_support'];
    }

    /**
     * Tools in early access, not offered yet to this client.
     *
     * @return list<string>
     */
    public function reservedTools(User $user): array
    {
        if ($this->hasEarlyAccess($user)) {
            return [];
        }

        return array_values(array_intersect(array_keys(self::EARLY_ACCESS_TOOLS), (array) $this->settings->get('early_access_tools')));
    }

    public function canUseTool(User $user, string $tool): bool
    {
        return ! in_array($tool, $this->reservedTools($user), true);
    }

    /**
     * How many times more energy a formula gives than another one, per month (the free formula: 30 days of its
     * daily energy). Read from the settings, never written by hand.
     */
    public function ratio(string $planKey, string $comparedTo): float
    {
        $monthly = fn (string $key) => $this->monthlyEnergy((array) ($this->settings->get('plans')[$key] ?? []));
        $base = $monthly($comparedTo);

        return $base > 0 ? $monthly($planKey) / $base : 0.0;
    }

    /**
     * The ratio as the client reads it, always true: « 7 » when it is whole, « 4 » for 4.4 (never more than the
     * truth), « près de 3 » for 2.75.
     */
    public function ratioText(string $planKey, string $comparedTo): string
    {
        $ratio = $this->ratio($planKey, $comparedTo);
        $whole = (int) floor($ratio + 0.001);

        if ($ratio - $whole < 0.5) {
            return (string) max(1, $whole);
        }

        return __('nearly :count', ['count' => $whole + 1]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function monthlyEnergy(array $plan): float
    {
        $energy = (float) ($plan['energy_fcfa'] ?? 0);

        return Energy::periodOf($plan) === 'month' ? $energy : $energy * max(1, $this->settings->int('plan_duration_days'));
    }
}
