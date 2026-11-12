<flux:modal name="allowed-sites" class="w-full max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Allowed sites') }}</flux:heading>
            <flux:subheading>{{ __('Kekeli\'s computer can only reach these sites. Changes apply from the next request.') }}</flux:subheading>
        </div>

        <div class="space-y-2" data-user-sites>
            <flux:heading size="sm">{{ __('Sites you added') }}</flux:heading>
            @forelse ($this->sites->whereNull('removed_at') as $site)
                <div wire:key="site-{{ $site->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 dark:border-zinc-700">
                    <code class="break-all text-sm">{{ $site->domain }}</code>
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeSite({{ $site->id }})" :aria-label="__('Remove :site', ['site' => $site->domain])" />
                </div>
            @empty
                <flux:text class="text-sm">{{ __('No site added yet.') }}</flux:text>
            @endforelse
        </div>

        <form wire:submit="addSite" class="flex items-start gap-2">
            <div class="flex-1">
                <flux:input wire:model="newSite" :placeholder="__('for example api.my-site.com')" :aria-label="__('Site to allow')" />
                <flux:error name="newSite" />
            </div>
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </form>

        <details class="text-sm">
            <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">{{ __('Always allowed: free sources and services Kekeli needs') }}</summary>
            <div class="mt-2 flex flex-wrap gap-1">
                {{-- Seulement les sources libres : jamais les adresses internes du moteur de Kekeli. --}}
                @foreach ((array) config('kekeli.default_sites.free') as $domain)
                    <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-800">{{ $domain }}</code>
                @endforeach
            </div>
        </details>

        @if ($this->sites->isNotEmpty())
            <details class="text-sm" data-sites-history>
                <summary class="cursor-pointer select-none text-zinc-600 dark:text-zinc-300">{{ __('History of the changes') }}</summary>
                <ul class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                    @foreach ($this->sites as $site)
                        <li wire:key="history-add-{{ $site->id }}">{{ __(':date: :site added', ['date' => $site->created_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i'), 'site' => $site->domain]) }}</li>
                        @if ($site->removed_at)
                            <li wire:key="history-remove-{{ $site->id }}">{{ __(':date: :site removed', ['date' => $site->removed_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i'), 'site' => $site->domain]) }}</li>
                        @endif
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</flux:modal>
