<?php

use App\Agent\Energy;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public function rendering($view): void
    {
        $view->title(__('Formulas'));
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
     * @return array{key: string, name: string, expires_at: ?string}
     */
    #[Computed]
    public function mine(): array
    {
        $energy = app(Energy::class);
        $user = Auth::user();

        return [
            'key' => $energy->plan($user)['key'],
            'name' => $energy->plan($user)['name'],
            'expires_at' => $user->plan_expires_at?->isFuture() ? \App\Support\Formats::date($user->plan_expires_at) : null,
        ];
    }

    /**
     * Prices in dollars for the clients outside the FCFA zone (the payment stays in FCFA, by Mobile Money).
     */
    #[Computed]
    public function inDollars(): bool
    {
        $country = Auth::user()->countryDetails();

        return $country !== null && ! str_contains((string) ($country['currency'] ?? ''), 'FCFA');
    }

    /**
     * The cards of the formulas (App\Billing\PlanCards: the same texts everywhere).
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function cards(): array
    {
        return app(\App\Billing\PlanCards::class)->all();
    }
}; ?>

{{--
    Page des formules, comme Claude, ChatGPT, Lovable, Bolt.new, Manus et Cursor : une carte par formule (nom, prix,
    « pour qui », bouton, puis « Tout ce que contient …, et en plus : »). La formule actuelle est marquée sur sa carte ;
    Pro porte « Le plus populaire ». Prix en dollars hors de la zone FCFA.
--}}
<section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Formulas') }}</flux:heading>

    @if ($this->lastPayment?->status === Payment::STATUS_PENDING)
        <flux:callout icon="clock" color="sky" data-last-payment="pending">
            <flux:callout.text>{{ __('Your payment is being checked. Your formula will be active as soon as it is validated (usually within a few hours).') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->lastPayment?->status === Payment::STATUS_REFUSED)
        <flux:callout icon="information-circle" color="red" data-last-payment="refused">
            <flux:callout.text>
                {{ __('Your last payment could not be validated.') }}
                @if ($this->lastPayment->refusal_reason) {{ __('Reason: :reason', ['reason' => $this->lastPayment->refusal_reason]) }} @endif
                {{ __('If you think it is a mistake, contact us:') }} <x-contact inline />
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid items-start gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->cards as $key => $card)
            <x-plan-card wire:key="plan-{{ $key }}" :card="$card" :current="$this->mine['key'] === $key" :in-dollars="$this->inDollars"
                :href="$card['free'] ? route('projects.index') : route('payments.create', $key)"
                :expires="$this->mine['key'] === $key ? $this->mine['expires_at'] : null" />
        @endforeach
    </div>

    <x-contact class="kekeli-carte p-4 sm:p-5" :title="__('A question about the formulas or a payment? Contact us')" />
</section>
