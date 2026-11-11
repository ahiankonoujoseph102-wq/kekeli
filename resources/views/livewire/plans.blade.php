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
    <div>
        <flux:heading size="xl" level="1">{{ __('Formulas') }}</flux:heading>
        <flux:subheading>{{ __('Kekeli works with energy that recharges. When it is used up, Kekeli rests, then starts again.') }}</flux:subheading>
    </div>

    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950" data-my-plan>
        <div class="flex items-center gap-2 font-medium text-zinc-900 dark:text-white">
            <flux:icon.sun class="size-5 text-amber-500" />
            {{ __('Your formula: :name', ['name' => $this->mine['name']]) }}
        </div>
        <flux:text class="mt-1">{{ __('Energy left: :percent %. Full again at :time (Lomé time).', ['percent' => $this->mine['percent'], 'time' => $this->mine['recharges_at']]) }}</flux:text>
        @if ($this->mine['expires_at'])
            <flux:text class="mt-1" data-plan-expires>{{ __('Active until :date.', ['date' => $this->mine['expires_at']]) }}</flux:text>
        @endif
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
                <flux:text class="mt-2">
                    {{ (int) $plan['window_hours'] === 0 ? __('Energy recharged every day at midnight (Lomé time).') : __('Energy recharged every :hours hours.', ['hours' => (int) $plan['window_hours']]) }}
                </flux:text>
                @if ((int) $plan['price_fcfa'] > 0)
                    <flux:button :href="route('payments.create', $key)" wire:navigate :variant="$this->mine['key'] === $key ? 'filled' : 'primary'" class="mt-3 w-full" data-choose-plan="{{ $key }}">
                        {{ $this->mine['key'] === $key ? __('Renew this formula') : __('Choose this formula') }}
                    </flux:button>
                @elseif ($this->mine['key'] === $key)
                    <flux:badge color="amber" class="mt-3">{{ __('Your current formula') }}</flux:badge>
                @endif
            </div>
        @endforeach
    </div>

    @unless ($this->published)
        <flux:callout icon="clock" color="zinc" data-plans-coming>
            <flux:callout.text>{{ __('Paid formulas are being prepared. They will be paid with Mixx by Yas or Flooz.') }}</flux:callout.text>
        </flux:callout>
    @endunless
</section>
