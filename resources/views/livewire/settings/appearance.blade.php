<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

/*
 * Réglages → Apparence : « Système » (par défaut), « Clair » ou « Sombre ». Le choix est gardé pour le compte et
 * appliqué tout de suite, sans recharger la page.
 */
new class extends Component {
    public string $appearance = 'system';

    public function mount(): void
    {
        $this->appearance = Auth::user()->appearance ?: 'system';
    }

    public function updatedAppearance(): void
    {
        $this->validate(['appearance' => ['required', Rule::in(User::APPEARANCES)]]);

        Auth::user()->forceFill(['appearance' => $this->appearance])->save();
        $this->js('window.Flux && window.Flux.applyAppearance('.json_encode($this->appearance).')');
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Kekeli follows the setting of your phone or computer, unless you choose otherwise.')">
        <flux:radio.group variant="segmented" wire:model.live="appearance" data-appearance="{{ $appearance }}">
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</div>
