@auth
    @php($subscriptions = app(\App\Billing\Subscriptions::class))
    @if ($subscriptions->endsSoon(auth()->user()))
        @php($plan = app(\App\Agent\Energy::class)->plan(auth()->user()))
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950" data-plan-reminder>
            <p class="font-medium text-amber-800 dark:text-amber-200">
                {{ __('Your :plan formula ends on :date (Lomé time). Renew it to keep your energy.', ['plan' => $plan['name'], 'date' => auth()->user()->plan_expires_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y \à H:i')]) }}
            </p>
            <flux:button :href="route('payments.create', $plan['key'])" variant="primary" size="sm" icon="fire" class="mt-2" wire:navigate>{{ __('Renew') }}</flux:button>
        </div>
    @endif
@endauth
