<?php

use App\Agent\Energy;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

/*
 * Réglages → Formule : la formule actuelle, l'énergie qui reste, le lien vers la page des formules, et « Mes reçus »
 * (chaque paiement validé, téléchargeable en PDF).
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
            'recharge' => $energy->rechargeText($user),
            'receipts' => \App\Models\Receipt::query()->where('user_id', $user->id)->latest('id')->get(),
            'expires' => $plan['period'] === 'month' && $user->plan_expires_at ? $user->plan_expires_at->timezone(config('kekeli.display_timezone')) : null,
        ];
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Formula')">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-settings-plan="{{ $plan['name'] }}">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your formula') }}</p>
            <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan['name'] }}</p>

            <div class="mt-4 flex items-center justify-between text-sm">
                <span class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-200"><flux:icon.sun variant="micro" class="text-amber-500" /> {{ __('Energy') }}</span>
                <span class="text-zinc-600 dark:text-zinc-300">{{ $percent }} %</span>
            </div>
            <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <span class="block h-full rounded-full bg-amber-500" style="width: {{ $percent }}%"></span>
            </span>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $plan['period'] === 'month' ? __('Full again on :date (Lomé time).', ['date' => $recharge]) : __('Full again at :time (Lomé time).', ['time' => $recharge]) }}
            </p>
            @if ($expires)
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" data-plan-expires>{{ __('Active until :date.', ['date' => $expires->format('d/m/Y')]) }}</p>
            @endif

            <flux:button class="mt-5" variant="primary" :href="route('plans')" wire:navigate icon-trailing="arrow-right" data-see-plans>{{ __('See the formulas') }}</flux:button>
        </div>

        {{-- Mes reçus : un par paiement validé (et les reçus d'annulation), téléchargeables. --}}
        <section id="mes-recus" class="mt-8 w-full" data-my-receipts>
            <flux:heading size="lg">{{ __('My receipts') }}</flux:heading>
            @if ($receipts->isEmpty())
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400" data-no-receipt>{{ __('Your receipts will appear here after each validated payment.') }}</p>
            @else
                <ul class="mt-3 flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
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
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('receipts.show', $receipt)" data-download-receipt>{{ __('Download') }}</flux:button>
                            @else
                                <span class="text-xs text-zinc-500">{{ __('Being prepared…') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </x-settings.layout>
</div>
