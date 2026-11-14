@props(['user', 'recharge' => null])
{{--
    Crédits gratuits utilisés (décision du porteur du projet, 14 novembre 2026, comme Lovable et Manus) : une seule
    expérience pour tous, seul le crédit change. Une carte sobre dit simplement que le projet reste disponible, montre ce
    que le client a déjà réalisé avec Kekeli et présente les formules avec leur crédit. Rien n'est effacé.
--}}
@php
    $settings = app(\App\Support\Settings::class);
    $published = (bool) $settings->get('plans_published');
    $paid = $published ? array_filter((array) $settings->get('plans'), fn ($plan) => (int) ($plan['price_fcfa'] ?? 0) > 0) : [];
    $plural = \App\Models\Project::typeLabels();
    $projects = $user->projects()->get(['id', 'kind', 'document_type', 'is_application', 'is_agent', 'is_automation'])->reject->isChat();
    $made = $projects->groupBy->typeKey()
        ->map(fn ($group, $type) => $group->count().' '.mb_strtolower($group->count() > 1 ? $plural[$type] : $group->first()->typeLabel()))->values();
    $round = fn (float $count) => $count >= 50 ? (int) (round($count / 10) * 10) : (int) floor($count);
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
        <ul class="grid gap-2 sm:grid-cols-3" data-credit-plans>
            @foreach ($paid as $key => $plan)
                @php($sites = $round((float) $plan['energy_fcfa'] / max(1, $settings->float('site_estimate_fcfa'))))
                <li class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-credit-plan="{{ $key }}">
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $plan['name'] }}</p>
                    <p class="text-sm text-zinc-700 dark:text-zinc-200">{{ number_format((int) $plan['price_fcfa'], 0, ',', ' ') }} FCFA <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('per month') }}</span></p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{1} About :count showcase site per month|[2,*] About :count showcase sites per month', $sites, ['count' => $sites]) }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    <div>
        <flux:button variant="primary" size="sm" :href="route('plans')" wire:navigate data-see-plans>{{ __('Choose a formula') }}</flux:button>
    </div>
</div>
