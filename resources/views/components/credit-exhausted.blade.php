@props(['user', 'recharge' => null])
{{--
    Crédits gratuits utilisés (décision du porteur du projet, 14 novembre 2026, comme Lovable et Manus) : une seule
    expérience pour tous, seul le crédit change. Une carte sobre dit simplement que le projet reste disponible, montre ce
    que le client a déjà réalisé avec Kekeli et présente les formules avec leur crédit. Rien n'est effacé.
--}}
@php
    // Les mêmes cartes que la page des formules (App\Billing\PlanCards), seulement les formules payantes publiées.
    $paid = array_filter(app(\App\Billing\PlanCards::class)->all(), fn ($card) => ! $card['free']);
    $inDollars = ($pays = $user->countryDetails()) !== null && ! str_contains((string) ($pays['currency'] ?? ''), 'FCFA');
    $plural = \App\Models\Project::typeLabels();
    $projects = $user->projects()->get(['id', 'kind', 'document_type', 'is_application', 'is_agent', 'is_automation'])->reject->isChat();
    $made = $projects->groupBy->typeKey()
        ->map(fn ($group, $type) => $group->count().' '.mb_strtolower($group->count() > 1 ? $plural[$type] : $group->first()->typeLabel()))->values();
@endphp
<div class="kekeli-carte kekeli-apparition flex flex-col gap-4 p-4 sm:p-5" data-credit-exhausted>
    <div class="flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400"><flux:icon.fire variant="mini" /></span>
        <div class="min-w-0">
            <p class="font-medium text-zinc-900 dark:text-white">{{ __('You have used all your free credits.') }}</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Your project is still available. To go on creating with Kekeli, choose a subscription formula.') }}</p>
            @if ($recharge)
                {{-- Transparent : le crédit gratuit du jour revient à minuit, heure de Lomé. --}}
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-free-credit-back>{{ __('Your free credit comes back at :time.', ['time' => $recharge]) }}</p>
            @endif
            @if ($made->isNotEmpty())
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400" data-already-made>{{ __('Already made with Kekeli: :list.', ['list' => $made->join(', ', ' '.__('and').' ')]) }}</p>
            @endif
        </div>
    </div>

    @if ($paid !== [])
        {{-- Une rangée qui défile sur téléphone (fondu sur le bord), trois colonnes sur ordinateur. --}}
        <div class="kekeli-defile -mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-3 sm:px-0 [&::-webkit-scrollbar]:hidden"
             x-data="{ debut: false, suite: false, voir() { this.debut = $el.scrollLeft > 4; this.suite = $el.scrollLeft + $el.clientWidth < $el.scrollWidth - 4 } }"
             x-init="$nextTick(() => voir())" x-on:scroll.passive="voir()" x-on:resize.window="voir()" x-bind:data-debut="debut" x-bind:data-suite="suite" data-credit-plans>
            @foreach ($paid as $key => $card)
                <x-plan-card :card="$card" :in-dollars="$inDollars" :href="route('payments.create', $key)" compact class="w-64 shrink-0 snap-start sm:w-auto" data-credit-plan="{{ $key }}" />
            @endforeach
        </div>
    @endif

    <div>
        <flux:button variant="primary" size="sm" :href="route('plans')" wire:navigate data-see-plans>{{ __('Choose a formula') }}</flux:button>
    </div>
</div>
