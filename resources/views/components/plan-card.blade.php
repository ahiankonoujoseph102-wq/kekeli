@props(['card', 'current' => false, 'inDollars' => false, 'href' => null, 'expires' => null, 'compact' => false])
{{--
    Une formule, comme chez Claude, ChatGPT, Lovable, Bolt.new, Manus et Cursor : nom, prix, une phrase « pour qui », un
    bouton, puis « Tout ce que contient …, et en plus : » et les avantages. Textes : App\Billing\PlanCards (une seule
    source pour la page des formules, la carte des crédits utilisés, Réglages → Formule et le paiement).
--}}
@php
    $usd = $inDollars && ! $card['free'] && $card['price_usd'] > 0;
@endphp
<div {{ $attributes->class(['kekeli-carte kekeli-apparition flex flex-col', 'p-4 sm:p-5' => ! $compact, 'p-4' => $compact, '!border-zinc-900 ring-1 ring-zinc-900 dark:!border-zinc-200 dark:ring-zinc-200' => $current]) }} data-plan="{{ $card['key'] }}">
    <div class="flex min-h-6 flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-medium text-zinc-900 dark:text-white">{{ $card['name'] }}</h2>
        @if ($current)
            <span class="kekeli-pastille" data-my-plan>{{ __('Your formula') }}</span>
        @elseif ($card['popular'])
            <span class="kekeli-pastille" data-plan-popular>{{ __('The most popular') }}</span>
        @endif
    </div>

    <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5" data-plan-price>
        <span class="whitespace-nowrap text-3xl font-medium tabular-nums tracking-tight text-zinc-900 lg:text-2xl xl:text-3xl dark:text-white">{{ $usd ? $card['price_usd'].' $' : number_format($card['price_fcfa'], 0, ',', ' ').' FCFA' }}</span>
        @unless ($card['free'])
            <span class="whitespace-nowrap text-sm text-zinc-500">{{ __('per month') }}</span>
        @endunless
    </p>
    @if ($usd)
        <p class="text-xs text-zinc-500" data-plan-price-fcfa>{{ __('Paid :price FCFA by Mobile Money', ['price' => number_format($card['price_fcfa'], 0, ',', ' ')]) }}</p>
    @endif

    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300" data-plan-for-whom>{{ $card['for_whom'] }}</p>

    @if (isset($slot) && $slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @elseif ($href)
        <div class="mt-4">
            <flux:button :href="$href" wire:navigate :variant="$current ? 'filled' : 'primary'" class="w-full" data-choose-plan="{{ $card['key'] }}">
                {{ $current && ! $card['free'] ? __('Renew') : $card['button'] }}
            </flux:button>
            @if ($expires)
                <p class="mt-2 text-center text-xs text-zinc-500" data-plan-expires>{{ __('Active until :date.', ['date' => $expires]) }}</p>
            @endif
        </div>
    @endif

    @if ($card['features'] !== [])
        <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-700" data-plan-features>
            @if ($card['includes'])
                <p class="mb-2.5 text-sm text-zinc-500 dark:text-zinc-400" data-plan-includes>{{ $card['includes'] }}</p>
            @endif
            <ul class="flex flex-col gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                @foreach ($card['features'] as $feature)
                    <li class="flex items-start gap-2"><flux:icon.check variant="micro" class="mt-0.5 shrink-0 text-zinc-500" /><span>{{ $feature }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
