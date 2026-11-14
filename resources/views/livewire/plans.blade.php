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
            'expires_at' => $user->plan_expires_at?->isFuture() ? $user->plan_expires_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y') : null,
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
     * What the client can do with a formula, in simple words (estimates of docs/plan.md, part 25).
     *
     * @param  array<string, mixed>  $plan
     * @return array{main: string, more: ?string}
     */
    public function canDo(array $plan): array
    {
        $settings = app(Settings::class);
        $energy = (float) $plan['energy_fcfa'];

        if (Energy::periodOf($plan) !== 'month') {
            $days = max(1, (int) ceil($settings->float('free_site_estimate_fcfa') / max(1, $energy)));

            return ['main' => trans_choice('{1} About 1 showcase site per day|[2,*] About 1 showcase site every :count days', $days, ['count' => $days]), 'more' => __('then small changes every day')];
        }

        $round = fn (float $count) => $count >= 50 ? (int) (round($count / 10) * 10) : (int) floor($count);
        $sites = $round($energy / max(1, $settings->float('site_estimate_fcfa')));
        $webapps = $round($energy / max(1, $settings->float('webapp_estimate_fcfa')));

        return [
            'main' => trans_choice('{1} About :count showcase site per month|[2,*] About :count showcase sites per month', $sites, ['count' => $sites]),
            'more' => trans_choice('{1} or :count site with a database|[2,*] or :count sites with a database', $webapps, ['count' => $webapps]),
        ];
    }
}; ?>

{{--
    Page des formules, comme Lovable et Bolt.new : une carte par formule, avec le prix, ce que le client peut faire
    concrètement et le bouton. La formule actuelle est marquée sur sa carte. Prix en dollars hors de la zone FCFA.
--}}
<section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Formulas') }}</flux:heading>

    @if ($this->lastPayment?->status === Payment::STATUS_PENDING)
        <flux:callout icon="clock" color="sky" data-last-payment="pending">
            <flux:callout.text>{{ __('Your payment is being checked. Your formula will be active as soon as it is validated (usually within a few hours).') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->lastPayment?->status === Payment::STATUS_REFUSED)
        <flux:callout icon="information-circle" color="amber" data-last-payment="refused">
            <flux:callout.text>
                {{ __('Your last payment could not be validated.') }}
                @if ($this->lastPayment->refusal_reason) {{ __('Reason: :reason', ['reason' => $this->lastPayment->refusal_reason]) }} @endif
                {{ __('If you think it is a mistake, contact us:') }} <x-contact inline />
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->plans as $key => $plan)
            @php($current = $this->mine['key'] === $key)
            @php($free = (int) $plan['price_fcfa'] === 0)
            @php($can = $this->canDo($plan))
            <div wire:key="plan-{{ $key }}" @class(['kekeli-carte kekeli-apparition flex flex-col p-5', '!border-amber-400 ring-1 ring-amber-400 dark:!border-amber-500 dark:ring-amber-500' => $current]) data-plan="{{ $key }}">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $plan['name'] }}</h2>
                    @if ($current)
                        <span class="kekeli-pastille kekeli-pastille-lumiere" data-my-plan>{{ __('Your formula') }}</span>
                    @endif
                </div>

                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5 lg:flex-col lg:items-start" data-plan-price>
                    @if ($this->inDollars && ! $free && (int) ($plan['price_usd'] ?? 0) > 0)
                        <span class="whitespace-nowrap text-3xl font-semibold tracking-tight text-zinc-900 lg:text-2xl xl:text-3xl dark:text-white">{{ (int) $plan['price_usd'] }} $</span>
                    @else
                        <span class="whitespace-nowrap text-3xl font-semibold tracking-tight text-zinc-900 lg:text-2xl xl:text-3xl dark:text-white">{{ number_format((int) $plan['price_fcfa'], 0, ',', ' ') }} FCFA</span>
                    @endif
                    @unless ($free)
                        <span class="whitespace-nowrap text-sm text-zinc-500">{{ __('per month') }}</span>
                    @endunless
                </p>
                @if ($this->inDollars && ! $free && (int) ($plan['price_usd'] ?? 0) > 0)
                    <p class="text-xs text-zinc-500" data-plan-price-fcfa>{{ __('Paid :price FCFA by Mobile Money', ['price' => number_format((int) $plan['price_fcfa'], 0, ',', ' ')]) }}</p>
                @endif

                <div class="mt-4 flex-1 text-sm" data-plan-can-do>
                    <p class="text-zinc-800 dark:text-zinc-100">{{ $can['main'] }}</p>
                    @if ($can['more'])
                        <p class="mt-0.5 text-zinc-500 dark:text-zinc-400">{{ $can['more'] }}</p>
                    @endif
                </div>

                <div class="mt-5">
                    @if (! $free)
                        <flux:button :href="route('payments.create', $key)" wire:navigate :variant="$current ? 'filled' : 'primary'" class="w-full" data-choose-plan="{{ $key }}">
                            {{ $current ? __('Renew') : __('Choose :name', ['name' => $plan['name']]) }}
                        </flux:button>
                        @if ($current && $this->mine['expires_at'])
                            <p class="mt-2 text-center text-xs text-zinc-500" data-plan-expires>{{ __('Active until :date.', ['date' => $this->mine['expires_at']]) }}</p>
                        @endif
                    @elseif ($current)
                        <flux:button variant="filled" class="w-full" disabled>{{ __('Your current formula') }}</flux:button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <x-contact class="kekeli-carte p-4" :title="__('A question about the formulas or a payment? Contact us')" />
</section>
