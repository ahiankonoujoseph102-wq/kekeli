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
                <li wire:key="connecteur-{{ $connector['key'] }}" class="rounded-xl border border-zinc-200 px-4 py-3 text-sm dark:border-zinc-700">{{ $connector['name'] }}</li>
            @endforeach
        </ul>
    </x-settings.layout>
</div>
