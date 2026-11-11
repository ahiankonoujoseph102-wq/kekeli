<?php

use App\Agent\Energy;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public function rendering($view): void
    {
        $view->title(__('Formulas'));
    }

    /**
     * Formulas shown to the users. Paid formulas appear only once their prices are approved and published.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function plans(): array
    {
        $settings = app(Settings::class);
        $plans = $settings->get('plans');

        return $settings->get('plans_published') ? $plans : array_filter($plans, fn ($plan) => (int) $plan['price_fcfa'] === 0);
    }

    #[Computed]
    public function published(): bool
    {
        return (bool) app(Settings::class)->get('plans_published');
    }

    /**
     * Last payment sent by the user (being checked, validated or refused).
     */
    #[Computed]
    public function lastPayment(): ?Payment
    {
        return Auth::user()->payments()->first();
    }

    /**
     * @return array{key: string, name: string, expires_at: ?string, percent: int, recharges_at: string}
     */
    #[Computed]
    public function mine(): array
    {
        $energy = app(Energy::class);
        $user = Auth::user();

        return [
            'key' => $energy->plan($user)['key'],
            'name' => $energy->plan($user)['name'],
            'expires_at' => $user->plan_expires_at?->isFuture() ? $user->plan_expires_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y') : null,
            'percent' => $energy->percentLeft($user),
            'recharges_at' => $energy->rechargesAt($user)->timezone(config('kekeli.display_timezone'))->format('H:i'),
        ];
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-4">
    {{-- Comme les pages de prix de Lovable ou Bolt.new : un titre, les formules, et la formule actuelle marquée sur sa carte. --}}
    <div>
        <flux:heading size="xl" level="1">{{ __('Formulas') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Energy recharged every day at midnight (Lomé time).') }}</flux:text>
    </div>

    @if ($this->lastPayment?->status === Payment::STATUS_PENDING)
        <flux:callout icon="clock" color="sky" data-last-payment="pending">
            <flux:callout.text>{{ __('Your payment is being checked. Your formula will be active as soon as it is validated (usually within a few hours).') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->lastPayment?->status === Payment::STATUS_REFUSED)
        <flux:callout icon="information-circle" color="amber" data-last-payment="refused">
            <flux:callout.text>
                {{ __('Your last payment could not be validated.') }}
                @if ($this->lastPayment->refusal_reason) {{ __('Reason: :reason', ['reason' => $this->lastPayment->refusal_reason]) }} @endif
                {{ __('Write to us on WhatsApp if you think it is a mistake.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($this->plans as $key => $plan)
            <div wire:key="plan-{{ $key }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-plan="{{ $key }}">
                <flux:heading size="lg">{{ $plan['name'] }}</flux:heading>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ (int) $plan['price_fcfa'] === 0 ? '0 FCFA' : __(':price FCFA per month', ['price' => number_format((int) $plan['price_fcfa'], 0, ',', ' ')]) }}
                </div>
                @if ((int) $plan['window_hours'] !== 0)
                    <flux:text class="mt-2">{{ __('Energy recharged every :hours hours.', ['hours' => (int) $plan['window_hours']]) }}</flux:text>
                @endif
                @if ((int) $plan['price_fcfa'] > 0)
                    <flux:button :href="route('payments.create', $key)" wire:navigate :variant="$this->mine['key'] === $key ? 'filled' : 'primary'" class="mt-3 w-full" data-choose-plan="{{ $key }}">
                        {{ $this->mine['key'] === $key ? __('Renew this formula') : __('Choose this formula') }}
                    </flux:button>
                @endif
                @if ($this->mine['key'] === $key)
                    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-zinc-500" data-my-plan>
                        <flux:badge color="amber" size="sm">{{ __('Your current formula') }}</flux:badge>
                        @if ($this->mine['expires_at'])
                            <span data-plan-expires>{{ __('Active until :date.', ['date' => $this->mine['expires_at']]) }}</span>
                        @endif
                    </p>
                @endif
            </div>
        @endforeach
    </div>

</section>
