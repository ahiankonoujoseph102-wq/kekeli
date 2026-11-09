<?php

namespace App\Billing;

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

        return $payment;
    }

    /**
     * The administrator has found the payment in the history of the operator: the formula is active for 30 days.
     * If the same formula is still active, the new period starts at its end (no day is lost).
     */
    public function validate(Payment $payment, User $admin): void
    {
        DB::transaction(function () use ($payment, $admin) {
            $user = $payment->user;
            $now = CarbonImmutable::now();
            $current = $user->plan_expires_at ? CarbonImmutable::parse($user->plan_expires_at) : null;
            $starts = $user->plan === $payment->plan && $current?->isFuture() ? $current : $now;
            $expires = $starts->addDays($this->settings->int('plan_duration_days'));

            $payment->forceFill([
                'status' => Payment::STATUS_VALIDATED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => $now,
                'starts_at' => $starts,
                'expires_at' => $expires,
            ])->save();

            $user->forceFill(['plan' => $payment->plan, 'plan_expires_at' => $expires, 'energy_window_started_at' => null])->save();
        });
    }

    public function refuse(Payment $payment, User $admin, string $reason): void
    {
        $payment->forceFill([
            'status' => Payment::STATUS_REFUSED,
            'refusal_reason' => mb_substr(trim($reason), 0, 300) ?: null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * End of the paid period: back to the free formula. Returns the number of users concerned.
     */
    public function expireDue(): int
    {
        return User::query()
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now())
            ->update(['plan' => $this->freePlanKey(), 'plan_expires_at' => null, 'energy_window_started_at' => null]);
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
