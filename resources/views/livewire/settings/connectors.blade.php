<?php

use App\Support\Connectors;
use Livewire\Volt\Component;

/*
 * Réglages → Connecteurs : prévu pour les outils du client (Google Drive, Gmail, WhatsApp…). La page n'existe que
 * lorsqu'au moins un connecteur fonctionne vraiment ; aujourd'hui, aucun : elle répond « page introuvable ».
 */
new class extends Component {
    /** @var list<array{key: string, name: string}> */
    public array $connectors = [];

    public function mount(Connectors $connectors): void
    {
        abort_unless($connectors->any(), 404);
        $this->connectors = $connectors->available();
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Connectors')">
        <ul class="flex flex-col gap-2" data-connectors>
            @foreach ($connectors as $connector)
                <li wire:key="connecteur-{{ $connector['key'] }}" class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700" data-connector="{{ $connector['key'] }}">
                    @if ($connector['key'] === 'github')
                        {{-- GitHub App de Kekeli : le client relie son compte et choisit sur GitHub les dépôts que Kekeli peut voir. --}}
                        @php($user = \Illuminate\Support\Facades\Auth::user())
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
                    @else
                        {{ $connector['name'] }}
                    @endif
                </li>
            @endforeach
        </ul>
    </x-settings.layout>
</div>
