{{--
    « Variables secrètes » du projet, comme les variables d'environnement de Claude Code : les clés des services choisis
    par le client (base de données, paiements…). Chiffrées dans la base, jamais réaffichées, transmises seulement à
    l'ordinateur virtuel du projet, où l'application les lit dans son environnement.
--}}
<flux:modal name="secrets" class="w-full max-w-lg">
    <div class="space-y-5" data-secrets>
        <div>
            <flux:heading size="lg">{{ __('Secret variables') }}</flux:heading>
            <flux:subheading>{{ __('The keys of your own services (database, payments, e-mails). They are encrypted, never shown again in full, and only given to the computer of this project.') }}</flux:subheading>
        </div>

        <div class="space-y-2" data-secrets-list>
            @forelse ($this->secrets as $secret)
                <div wire:key="secret-{{ $secret->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" data-secret="{{ $secret->name }}">
                    <div class="min-w-0">
                        <code class="break-all text-sm font-medium">{{ $secret->name }}</code>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400"><span aria-hidden="true">••••••••</span> · {{ __('changed :date', ['date' => $secret->updated_at->timezone(config('kekeli.display_timezone'))->translatedFormat('j F Y')]) }}</p>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteSecret({{ $secret->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $secret->name]) }}" :aria-label="__('Delete :name', ['name' => $secret->name])" />
                </div>
            @empty
                <flux:text class="text-sm">{{ __('No secret variable yet. The preview works without any: it uses a test database.') }}</flux:text>
            @endforelse
        </div>

        <form wire:submit="saveSecret" class="space-y-3" data-secret-form>
            <flux:input wire:model="secretName" :label="__('Name')" :placeholder="__('for example DATABASE_URL')" autocomplete="off" autocapitalize="characters" spellcheck="false" />
            <flux:input wire:model="secretValue" type="password" viewable :label="__('Value')" autocomplete="new-password" spellcheck="false" />
            <flux:text class="text-xs">{{ __('A name that already exists is replaced. Kekeli never writes these values in your code.') }}</flux:text>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</flux:modal>
