<flux:menu class="w-[260px]">
    <flux:menu.radio.group>
        <div class="p-0 text-sm font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-zinc-200 text-zinc-900 dark:bg-zinc-700 dark:text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                </span>

                <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                </div>
            </div>
        </div>
    </flux:menu.radio.group>

    <flux:menu.separator />

    {{-- L'énergie : le détail est ici ; ailleurs, seulement un petit indicateur. --}}
    @php($energie = app(\App\Agent\Energy::class))
    @php($utilisateur = auth()->user())
    <a href="{{ route('plans') }}" wire:navigate class="block rounded-md px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700" data-account-energy>
        <span class="flex items-center justify-between gap-2 text-sm">
            <span class="flex items-center gap-1.5 font-medium text-zinc-800 dark:text-zinc-100"><flux:icon.fire variant="micro" class="text-amber-500" /> {{ __('Energy') }}</span>
            <span class="text-zinc-600 dark:text-zinc-300">{{ $energie->percentLeft($utilisateur) }} %</span>
        </span>
        <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-600">
            <span class="block h-full rounded-full bg-amber-500" style="width: {{ $energie->percentLeft($utilisateur) }}%"></span>
        </span>
        <span class="mt-1.5 block text-xs text-zinc-500 dark:text-zinc-400">
            @if ($energie->plan($utilisateur)['period'] === 'month')
                {{ __('Formula :plan · full again on :date', ['plan' => $energie->plan($utilisateur)['name'], 'date' => $energie->rechargeText($utilisateur)]) }}
            @else
                {{ __('Formula :plan · full again at :time', ['plan' => $energie->plan($utilisateur)['name'], 'time' => $energie->rechargeText($utilisateur)]) }}
            @endif
        </span>
        {{-- Les recherches sur Internet restantes : seulement ici, à côté de l'énergie, jamais dans une réponse. --}}
        @php($recherches = app(\App\Agent\WebSearchQuota::class)->remaining($utilisateur))
        <span class="mt-2 flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400" data-account-searches="{{ $recherches }}">
            <span class="flex items-center gap-1.5 whitespace-nowrap"><flux:icon.globe-alt variant="micro" class="text-zinc-400" /> {{ __('Searches of the day') }}</span>
            <span class="whitespace-nowrap">{{ trans_choice('{0} none left|{1} :count left|[2,*] :count left', $recherches, ['count' => $recherches]) }}</span>
        </span>
    </a>

    <flux:menu.separator />

    <flux:menu.radio.group>
        <flux:menu.item :href="route('plans')" icon="fire" wire:navigate>{{ __('Formulas') }}</flux:menu.item>
        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
        {{-- Comme « Aide » chez Claude et ChatGPT : écrire à une personne de Kekeli (App\Support\Contact). --}}
        @if (\App\Support\Contact::email() !== '')
            <flux:menu.item :href="'mailto:'.\App\Support\Contact::email()" icon="envelope" data-account-contact>{{ __('Contact us') }}</flux:menu.item>
        @endif
    </flux:menu.radio.group>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
            {{ __('Log Out') }}
        </flux:menu.item>
    </form>
</flux:menu>
