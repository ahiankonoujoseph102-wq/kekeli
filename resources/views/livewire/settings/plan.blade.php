<?php

use App\Agent\Energy;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

/*
 * Réglages → Formule : la formule actuelle, l'énergie qui reste (avec l'énergie reportée du mois dernier), ce que
 * contient la formule (même carte que la page des formules), l'assistance WhatsApp de la formule Agence, et « Mes
 * reçus » (chaque paiement validé, téléchargeable en PDF).
 */
new class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        $energy = app(Energy::class);
        $plan = $energy->plan($user);

        return [
            'plan' => $plan,
            'percent' => $energy->percentLeft($user),
            // Énergie reportée, en pourcentage de l'énergie habituelle (le client ne voit jamais de FCFA d'énergie).
            'carried' => $plan['energy_fcfa'] > 0 ? (int) round(100 * $energy->carriedOverFcfa($user) / $plan['energy_fcfa']) : 0,
            'card' => app(\App\Billing\PlanCards::class)->for($plan['key']),
            'support' => app(\App\Billing\PlanPerks::class)->hasWhatsappSupport($user) ? \App\Support\Contact::supportLink() : '',
            'recharge' => $energy->rechargeText($user),
            'receipts' => \App\Models\Receipt::query()->where('user_id', $user->id)->latest('id')->get(),
            'expires' => $plan['period'] === 'month' && $user->plan_expires_at ? $user->plan_expires_at->timezone(config('kekeli.display_timezone')) : null,
        ];
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Formula')">
        <div class="kekeli-carte p-4 sm:p-5" data-settings-plan="{{ $plan['name'] }}">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your formula') }}</p>
            <p class="mt-1 text-xl font-medium text-zinc-900 dark:text-zinc-100">{{ $plan['name'] }}</p>

            <div class="mt-4 flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-200"><flux:icon.fire variant="micro" class="text-amber-500" /> {{ __('Energy') }}</span>
                <span class="text-zinc-600 dark:text-zinc-300">{{ $percent }} %</span>
            </div>
            <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <span class="block h-full rounded-full bg-amber-500" style="width: {{ $percent }}%"></span>
            </span>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $plan['period'] === 'month' ? __('Full again on :date (Lomé time).', ['date' => $recharge]) : __('Full again at :time (Lomé time).', ['time' => $recharge]) }}
            </p>
            @if ($carried > 0)
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-energy-carried>{{ __('Energy carried over from last month: +:percent %', ['percent' => $carried]) }}</p>
            @endif
            @if ($expires)
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-plan-expires>{{ __('Active until :date.', ['date' => \App\Support\Formats::date($expires)]) }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                <flux:button variant="primary" :href="route('plans')" wire:navigate icon-trailing="arrow-right" data-see-plans>{{ __('See the formulas') }}</flux:button>
                @if ($support !== '')
                    {{-- Formule Agence : l'aide directe sur WhatsApp, vers le numéro principal. --}}
                    <flux:button :href="$support" target="_blank" rel="noopener" icon="chat-bubble-oval-left-ellipsis" data-whatsapp-support>{{ __('WhatsApp help') }}</flux:button>
                @endif
            </div>
        </div>

        @if ($card)
            {{-- Ce que contient la formule : la même carte que sur la page des formules, sans bouton. --}}
            <x-plan-card :card="$card" class="mt-4 w-full" data-settings-plan-card />
        @endif

        {{-- Mes reçus : un par paiement validé (et les reçus d'annulation), téléchargeables. --}}
        <section id="mes-recus" class="mt-8 w-full" data-my-receipts>
            <flux:heading size="lg">{{ __('My receipts') }}</flux:heading>
            @if ($receipts->isEmpty())
                <div class="kekeli-vide mt-3 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600" data-no-receipt><span class="kekeli-vide-icone"><flux:icon.document-text variant="mini" /></span><p>{{ __('Your receipts will appear here after each validated payment.') }}</p></div>
            @else
                <ul class="kekeli-carte mt-3 flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($receipts as $receipt)
                        <li wire:key="recu-{{ $receipt->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-sm" data-receipt="{{ $receipt->number }}">
                            <flux:icon.document-text variant="mini" class="shrink-0 text-zinc-400" />
                            <div class="min-w-0 flex-1 basis-40">
                                <p class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $receipt->number }}</p>
                                <p class="text-zinc-500 dark:text-zinc-400">
                                    {{ $receipt->isCancellation() ? __('Cancellation of :number', ['number' => $receipt->cancelledNumber()]) : __(':plan formula', ['plan' => $receipt->plan_name]) }}
                                    · {{ \App\Mail\Emails::date($receipt->paid_at) }} · {{ $receipt->amountText() }}
                                </p>
                            </div>
                            @if ($receipt->pdf_path)
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('receipts.show', $receipt)" data-download-receipt><span class="max-sm:sr-only">{{ __('Download') }}</span></flux:button>
                            @else
                                <span class="kekeli-scintille text-xs">{{ __('Being prepared…') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </x-settings.layout>
</div>
