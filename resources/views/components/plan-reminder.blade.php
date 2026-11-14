@auth
    @php($subscriptions = app(\App\Billing\Subscriptions::class))
    @if ($subscriptions->endsSoon(auth()->user()))
        @php($plan = app(\App\Agent\Energy::class)->plan(auth()->user()))
        <div class="mb-4 kekeli-avis p-3 text-sm" data-plan-reminder>
            <p class="font-medium text-zinc-900 dark:text-white">
                {{ __('Your :plan formula ends on :date (Lomé time). Renew it to keep your energy.', ['plan' => $plan['name'], 'date' => auth()->user()->plan_expires_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y \à H:i')]) }}
            </p>
            <flux:button :href="route('payments.create', $plan['key'])" variant="primary" size="sm" icon="fire" class="mt-2" wire:navigate>{{ __('Renew') }}</flux:button>
        </div>
    @endif
@endauth
