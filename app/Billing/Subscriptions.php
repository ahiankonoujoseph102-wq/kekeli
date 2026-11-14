<?php

namespace App\Billing;

use App\Mail\Emails;
use App\Models\AdminAlert;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Paid formulas bought by Mobile Money: validation or refusal of a payment by the administrator, a 30-day period,
 * a reminder before the end, then the return to the free formula.
 */
class Subscriptions
{
    public function __construct(private Settings $settings) {}

    /**
     * Key of the free formula (the first formula with a price of 0).
     */
    public function freePlanKey(): string
    {
        $plans = $this->settings->get('plans');

        return (string) (collect($plans)->search(fn ($plan) => (int) $plan['price_fcfa'] === 0) ?: array_key_first($plans));
    }

    /**
     * Paid formulas that can be bought.
     *
     * @return array<string, array<string, mixed>>
     */
    public function paidPlans(): array
    {
        return array_filter($this->settings->get('plans'), fn ($plan) => (int) $plan['price_fcfa'] > 0);
    }

    /**
     * Countries where Kekeli can be paid by Mobile Money, with the holder of the numbers and the operators.
     *
     * @return array<string, array{holder: string, methods: list<array{name: string, number: string}>}>
     */
    public function paymentCountries(): array
    {
        return (array) $this->settings->get('payment_countries');
    }

    /**
     * The client sends the screenshot of the payment: the administrator receives an alert.
     */
    public function submit(User $user, string $plan, string $country, string $screenshotPath): Payment
    {
        $payment = $user->payments()->create([
            'plan' => $plan,
            'amount_fcfa' => (int) $this->paidPlans()[$plan]['price_fcfa'],
            'country' => $country,
            'screenshot_path' => $screenshotPath,
        ]);

        AdminAlert::create([
            'kind' => 'payment_pending',
            'message' => __('A payment is waiting for your check: :plan formula, :amount FCFA (:email).', [
                'plan' => $this->paidPlans()[$plan]['name'],
                'amount' => number_format($payment->amount_fcfa, 0, ',', ' '),
                'email' => $user->email,
            ]),
        ]);

        app(Emails::class)->paymentToCheck($payment);

        return $payment;
    }

    /**
     * The administrator has found the payment in the history of the operator: the formula is active for 30 days.
     * Nothing already paid is lost:
     * - if the same formula is still active, the new period starts at its end;
     * - if another paid formula is still active, its remaining days are converted into days of the new one, at the
     *   value paid (for example 10 days of Pro at 7 500 FCFA = 3,75 days of Expert at 20 000 FCFA);
     * - a payment sent for an older formula (Découverte, Entreprise) counts for the formula that replaced it, for a
     *   number of days in proportion to the amount paid.
     */
    public function validate(Payment $payment, User $admin, ?string $method = null, ?string $reference = null): void
    {
        DB::transaction(function () use ($payment, $admin, $method, $reference) {
            $user = $payment->user;
            $plans = $this->settings->get('plans');
            $planKey = $this->currentKey((string) $payment->plan);
            $price = max(1, (int) ($plans[$planKey]['price_fcfa'] ?? $payment->amount_fcfa));
            $now = CarbonImmutable::now();
            $days = $this->settings->int('plan_duration_days');
            $paidSeconds = (int) round($days * 86400 * max(1, (int) $payment->amount_fcfa) / $price);

            $userKey = $this->currentKey((string) $user->plan);
            $current = $user->plan_expires_at ? CarbonImmutable::parse($user->plan_expires_at) : null;
            $starts = $now;
            if ($current?->isFuture() && $userKey === $planKey) {
                $starts = $current;
            } elseif ($current?->isFuture() && isset($plans[$userKey]) && (int) $plans[$userKey]['price_fcfa'] > 0) {
                // Les jours restants d'une autre formule payante sont gardés, convertis à la valeur payée.
                $remaining = $now->diffInSeconds($current) * (int) $plans[$userKey]['price_fcfa'] / $price;
                $starts = $now->addSeconds((int) round($remaining));
            }
            $expires = $starts->addSeconds($paidSeconds);

            $payment->forceFill([
                'plan' => $planKey,
                'status' => Payment::STATUS_VALIDATED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => $now,
                'starts_at' => $starts,
                'expires_at' => $expires,
                // Le moyen de paiement (« Mixx by Yas », « Flooz »…) et la référence de la transaction, s'il y en a une.
                'method' => mb_substr(trim((string) $method), 0, 60) ?: ($payment->method ?: $this->firstMethod($payment->country)),
                'reference' => mb_substr(trim((string) $reference), 0, 80) ?: $payment->reference,
            ])->save();

            $user->forceFill(['plan' => $planKey, 'plan_expires_at' => $expires, 'energy_window_started_at' => null, 'plan_reminded_at' => null])->save();
        });

        // Le reçu, mis en page puis envoyé au client par e-mail (en pièce jointe).
        app(Receipts::class)->issueFor($payment->fresh());
    }

    /** The first operator set for the country of the payment, or « Mobile Money ». */
    private function firstMethod(string $country): string
    {
        $methods = (array) data_get($this->paymentCountries(), $country.'.methods', []);

        return (string) (data_get($methods, '0.name') ?: 'Mobile Money');
    }

    /**
     * Key of a formula today: an older formula (Découverte, Entreprise) gives the key of the formula that replaced it.
     */
    public function currentKey(string $key): string
    {
        $plans = $this->settings->get('plans');
        $replaced = (array) $this->settings->get('replaced_plans');

        return ! isset($plans[$key]) && isset($replaced[$key]) ? (string) $replaced[$key] : $key;
    }

    /**
     * Move the subscribers of the older formulas (Découverte, Entreprise) to the formula that replaced them, without
     * losing anything they paid: the days left are kept, and multiplied by the old price divided by the new one when
     * the new formula is cheaper (Entreprise 25 000 FCFA → Expert 20 000 FCFA: 10 days left become 12,5 days).
     * A payment still waiting for the check is moved too (its days are counted from the amount paid when it is
     * validated). Returns the number of subscribers moved.
     *
     * @param  array<string, int>  $oldPrices  price in FCFA of each older formula
     */
    public function moveToNewPlans(array $oldPrices): int
    {
        $plans = $this->settings->get('plans');
        $moved = 0;

        foreach ((array) $this->settings->get('replaced_plans') as $old => $new) {
            if (isset($plans[$old]) || ! isset($plans[$new])) {
                continue;
            }
            $ratio = max(1.0, (float) ($oldPrices[$old] ?? 0) / max(1, (int) $plans[$new]['price_fcfa']));

            User::query()->where('plan', $old)->each(function (User $user) use ($new, $ratio, &$moved) {
                $expires = $user->plan_expires_at ? CarbonImmutable::parse($user->plan_expires_at) : null;
                if ($expires?->isFuture()) {
                    $now = CarbonImmutable::now();
                    $user->forceFill(['plan' => $new, 'plan_expires_at' => $now->addSeconds((int) round($now->diffInSeconds($expires) * $ratio)), 'energy_window_started_at' => null])->save();
                    $moved++;
                } else {
                    $user->forceFill(['plan' => $this->freePlanKey(), 'plan_expires_at' => null, 'energy_window_started_at' => null])->save();
                }
            });

            Payment::query()->where('plan', $old)->where('status', Payment::STATUS_PENDING)->update(['plan' => $new]);
        }

        return $moved;
    }

    public function refuse(Payment $payment, User $admin, string $reason): void
    {
        $payment->forceFill([
            'status' => Payment::STATUS_REFUSED,
            'refusal_reason' => mb_substr(trim($reason), 0, 300) ?: null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        app(Emails::class)->paymentRefused($payment);
    }

    /**
     * End of the paid period: back to the free formula. Returns the number of users concerned.
     */
    public function expireDue(): int
    {
        $plans = (array) $this->settings->get('plans');
        $count = 0;
        User::query()->whereNotNull('plan_expires_at')->where('plan_expires_at', '<=', now())->each(function (User $user) use ($plans, &$count) {
            $name = (string) ($plans[$this->currentKey((string) $user->plan)]['name'] ?? $user->plan);
            $user->forceFill(['plan' => $this->freePlanKey(), 'plan_expires_at' => null, 'energy_window_started_at' => null, 'plan_reminded_at' => null])->save();
            app(Emails::class)->planEnded($user, $name);
            $count++;
        });

        return $count;
    }

    /**
     * The day before the end of a paid formula: one e-mail, once per period. Returns the number of e-mails sent.
     */
    public function remindEnding(): int
    {
        $plans = (array) $this->settings->get('plans');
        $count = 0;
        User::query()->whereNotNull('plan_expires_at')->whereNull('plan_reminded_at')
            ->where('plan_expires_at', '>', now())->where('plan_expires_at', '<=', now()->addDay())
            ->each(function (User $user) use ($plans, &$count) {
                app(Emails::class)->planEnding($user, (string) ($plans[$this->currentKey((string) $user->plan)]['name'] ?? $user->plan));
                $user->forceFill(['plan_reminded_at' => now()])->save();
                $count++;
            });

        return $count;
    }

    /**
     * Whether to remind the user that the formula ends soon (a few days before the end).
     */
    public function endsSoon(User $user): bool
    {
        $expires = $user->plan_expires_at ? CarbonImmutable::parse($user->plan_expires_at) : null;

        return $expires !== null && $expires->isFuture() && $expires->lte(now()->addDays($this->settings->int('plan_reminder_days')));
    }
}
