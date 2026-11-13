<?php

use App\Models\ConnectorAction;
use App\Support\Connectors;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

/*
 * Réglages → Connecteurs, comme Claude et ChatGPT : le catalogue court des connecteurs (MCP) que Kekeli branche tout
 * seul (docs/plan.md, partie 26.16), « Connecter » / « Déconnecter », un connecteur personnalisé (adresse d'un serveur
 * MCP distant), les outils toujours autorisés (réglables) et le journal des actions. La page n'existe que lorsque
 * les connecteurs fonctionnent vraiment.
 */
new class extends Component {
    public function mount(Connectors $connectors): void
    {
        abort_unless($connectors->any(), 404);
    }

    /** Retirer un outil des « Toujours autoriser » : Kekeli redemandera l'accord la prochaine fois. */
    public function removeAlways(int $connectorId, string $tool): void
    {
        $connector = Auth::user()->connectors()->findOrFail($connectorId);
        $connector->forceFill(['always_allowed' => array_values(array_diff($connector->alwaysAllowed(), [$tool]))])->save();
    }

    public function with(Connectors $connectors): array
    {
        $user = Auth::user();
        $mine = $user->connectors()->orderBy('name')->get();

        return [
            'catalog' => $connectors->catalog(),
            'mine' => $mine,
            'custom' => $mine->whereNull('catalog_key')->values(),
            'byUrl' => $mine->keyBy('url'),
            'max' => $connectors->maxActive($user),
            'journal' => $user->connectorActions()->latest('id')->limit(30)->get(),
            'github' => app(\App\Github\GithubClient::class)->configured(),
        ];
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Connectors')" :subheading="__('Kekeli can use your tools when you ask it: search your pages, read your tickets, create a page. Kekeli always asks before an action that changes something.')">
        <div class="flex w-full flex-col gap-8" data-connectors>
            @if (session('connector-status'))
                <p class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" data-connector-status>{{ session('connector-status') }}</p>
            @endif
            @if (session('connector-error'))
                <p class="kekeli-erreur text-sm" data-connector-error>{{ session('connector-error') }}</p>
            @endif

            <ul class="flex flex-col gap-2">
                @if ($github)
                    {{-- GitHub App de Kekeli : le client relie son compte et choisit sur GitHub les dépôts que Kekeli peut voir. --}}
                    @php($user = auth()->user())
                    <li class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" data-connector="github">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <flux:icon.github class="size-5 shrink-0" />
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="font-medium">GitHub</p>
                                <p class="text-zinc-500 dark:text-zinc-400">{{ $user->github_token ? __('Linked to :login', ['login' => $user->github_login]) : __('Kekeli works on your repositories and sends its changes in pull requests.') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1 max-sm:w-full max-sm:ps-8">
                            @if ($user->github_token)
                                <flux:button size="sm" variant="ghost" :href="app(\App\Github\GithubClient::class)->repositoriesUrl($user)" target="_blank" data-choose-repositories>{{ __('Choose the repositories') }}</flux:button>
                                <form method="POST" action="{{ route('github.disconnect') }}">@csrf<flux:button size="sm" variant="ghost" type="submit" data-github-disconnect>{{ __('Unlink') }}</flux:button></form>
                            @else
                                <flux:button size="sm" variant="primary" :href="route('github.connect', ['retour' => route('settings.connectors')])" data-github-connect>{{ __('Link') }}</flux:button>
                            @endif
                            </div>
                        </div>
                    </li>
                @endif

                @foreach ($catalog as $key => $entry)
                    @php($linked = $byUrl->get($entry['url']))
                    @php($connected = $linked?->isConnected())
                    <li wire:key="connecteur-{{ $key }}" class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" data-connector="{{ $key }}" data-connected="{{ $connected ? 'oui' : 'non' }}">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-700"><flux:icon :icon="$entry['icon']" variant="outline" class="size-5 text-zinc-600 dark:text-zinc-300" /></span>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="font-medium">{{ $entry['name'] }} @if ($connected)<span class="ms-1 text-xs font-normal text-zinc-500 dark:text-zinc-400" data-connected-label>· {{ __('Connected') }}</span>@endif</p>
                                <p class="text-zinc-500 dark:text-zinc-400">{{ __($entry['description']) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1 max-sm:w-full max-sm:ps-12">
                                @if ($connected)
                                    <form method="POST" action="{{ route('connectors.destroy', $linked) }}">@csrf @method('DELETE')<flux:button size="sm" variant="ghost" type="submit" data-connector-disconnect="{{ $key }}">{{ __('Disconnect') }}</flux:button></form>
                                @else
                                    <flux:button size="sm" variant="primary" :href="route('connectors.connect', $key)" data-connector-connect="{{ $key }}">{{ __('Connect it') }}</flux:button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach

                @foreach ($custom as $linked)
                    <li wire:key="connecteur-perso-{{ $linked->id }}" class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" data-connector-custom="{{ $linked->id }}">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-700"><flux:icon.puzzle-piece class="size-5 text-zinc-600 dark:text-zinc-300" /></span>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="font-medium">{{ $linked->name }} @if ($linked->isConnected())<span class="ms-1 text-xs font-normal text-zinc-500 dark:text-zinc-400">· {{ __('Connected') }}</span>@endif</p>
                                <p class="truncate text-zinc-500 dark:text-zinc-400">{{ $linked->url }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1 max-sm:w-full max-sm:ps-12">
                                <form method="POST" action="{{ route('connectors.destroy', $linked) }}">@csrf @method('DELETE')<flux:button size="sm" variant="ghost" type="submit" data-connector-remove="{{ $linked->id }}">{{ $linked->isConnected() ? __('Disconnect') : __('Remove') }}</flux:button></form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($max !== null)
                <p class="-mt-5 text-sm text-zinc-500 dark:text-zinc-400" data-connectors-limit>{{ trans_choice('Free formula: :count connector active at a time in a discussion.|Free formula: :count connectors active at a time in a discussion.', $max, ['count' => $max]) }} <a href="{{ route('plans') }}" class="underline underline-offset-2">{{ __('See the formulas') }}</a></p>
            @endif

            {{-- Un connecteur personnalisé : l'adresse d'un serveur MCP distant (https), comme dans Claude. --}}
            <details class="w-full rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" @if ($errors->connecteur->any()) open @endif data-custom-connector>
                <summary class="cursor-pointer font-medium">{{ __('Add a custom connector') }}</summary>
                <form method="POST" action="{{ route('connectors.store') }}" class="mt-3 flex flex-col gap-3">
                    @csrf
                    <flux:input name="name" :value="old('name')" :label="__('Name')" maxlength="60" required data-custom-name />
                    <flux:input name="url" type="url" :value="old('url')" :label="__('Address of the remote MCP server')" placeholder="https://" maxlength="500" required data-custom-url />
                    @foreach (['name', 'url'] as $field)
                        @error($field, 'connecteur')<p class="kekeli-erreur text-sm" data-custom-error>{{ $message }}</p>@enderror
                    @endforeach
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('Only add servers you trust: Kekeli will read what they send back, and it always asks before an action that changes something.') }}</p>
                    <div><flux:button type="submit" size="sm" variant="primary" data-custom-add>{{ __('Add') }}</flux:button></div>
                </form>
            </details>

            @php($allowed = $mine->filter(fn ($c) => $c->alwaysAllowed() !== []))
            @if ($allowed->isNotEmpty())
                <section class="w-full" data-always-allowed>
                    <h3 class="mb-2 text-sm font-medium">{{ __('Always allowed') }}</h3>
                    <ul class="flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($allowed as $connector)
                            @foreach ($connector->alwaysAllowed() as $tool)
                                <li class="flex items-center justify-between gap-3 px-4 py-2" wire:key="toujours-{{ $connector->id }}-{{ $tool }}">
                                    <span class="min-w-0 truncate">{{ $connector->name }} · {{ str_replace(['_', '-'], ' ', $tool) }}</span>
                                    <flux:button size="xs" variant="ghost" wire:click="removeAlways({{ $connector->id }}, @js($tool))" data-always-remove="{{ $tool }}">{{ __('Remove') }}</flux:button>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="w-full" data-connector-journal>
                <h3 class="mb-2 text-sm font-medium">{{ __('Journal of the actions') }}</h3>
                @if ($journal->isEmpty())
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No action yet. Every read and every change made with a connector will be listed here.') }}</p>
                @else
                    <ul class="flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($journal as $action)
                            <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 px-4 py-2" wire:key="action-{{ $action->id }}" data-journal-action="{{ $action->status }}">
                                <span class="min-w-0 flex-1 basis-48"><span class="font-medium">{{ $action->connector_name }}</span> · {{ $action->title() }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $action->writes ? __('Change') : __('Reading') }} ·
                                    {{ match ($action->status) { ConnectorAction::DONE => __('Done'), ConnectorAction::FAILED => __('Failed'), ConnectorAction::PENDING => __('Waiting for your answer'), ConnectorAction::ALLOWED => __('Allowed'), ConnectorAction::REFUSED => __('Refused'), default => $action->status } }} ·
                                    {{ $action->created_at->timezone(config('kekeli.display_timezone'))->format('d/m H \h i') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </x-settings.layout>
</div>
