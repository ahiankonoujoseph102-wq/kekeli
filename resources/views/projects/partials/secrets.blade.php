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

        @if ($project->usesClientModel())
            {{-- Choix guidé du fournisseur de SON modèle : l'adresse et un modèle conseillé se remplissent seuls. --}}
            @php($modelProviders = (array) config('kekeli.client_model_providers'))
            <form wire:submit="connectModel" class="space-y-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" x-data="{ choix: $wire.entangle('modelProvider'), liste: @js($modelProviders) }" data-model-form>
                <flux:heading size="sm">{{ __('Connect your model') }}</flux:heading>
                <flux:select wire:model.live="modelProvider" :label="__('Your provider')" data-model-provider>
                    @foreach ($modelProviders as $key => $provider)
                        <flux:select.option value="{{ $key }}">{{ $provider['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <p class="text-xs text-zinc-500 dark:text-zinc-400" data-model-address>{{ __('Address filled in by Kekeli:') }} <code class="break-all" x-text="liste[choix]?.url">{{ $modelProviders[$modelProvider]['url'] ?? '' }}</code> · {{ __('key created on') }} <span x-text="liste[choix]?.keys_page">{{ $modelProviders[$modelProvider]['keys_page'] ?? '' }}</span></p>
                <flux:input wire:model="modelName" :label="__('Model (optional)')" x-bind:placeholder="liste[choix]?.model" :placeholder="$modelProviders[$modelProvider]['model'] ?? ''" autocomplete="off" spellcheck="false" />
                <flux:input wire:model="modelKey" type="password" viewable :label="__('Your key')" autocomplete="new-password" spellcheck="false" />
                <p class="text-xs text-amber-700 dark:text-amber-400" x-show="liste[choix]?.free_data" x-text="liste[choix]?.free_data" data-model-free-data>{{ $modelProviders[$modelProvider]['free_data'] ?? '' }}</p>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Connect the model') }}</flux:button>
                </div>
            </form>
        @endif

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
