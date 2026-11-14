<?php

use App\Memory\UserMemory;
use App\Models\Memory;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

/*
 * Réglages → Mémoire, comme Claude et ChatGPT : ce que Kekeli a retenu du client (liste lisible), « Modifier » et
 * « Supprimer » pour chaque souvenir, le logo enregistré, « Tout effacer » avec confirmation, et l'interrupteur qui
 * active ou désactive la mémoire. Les souvenirs sont chiffrés, propres au client, et effacés avec son compte.
 */
new class extends Component {
    public bool $enabled = true;

    public ?int $editing = null;

    public string $draft = '';

    public function mount(): void
    {
        $this->enabled = (bool) Auth::user()->memory_enabled;
    }

    public function updatedEnabled(): void
    {
        Auth::user()->forceFill(['memory_enabled' => $this->enabled])->save();
    }

    public function edit(int $id): void
    {
        $memory = Auth::user()->memories()->findOrFail($id);
        $this->editing = $memory->id;
        $this->draft = $memory->content;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'draft');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $memory = Auth::user()->memories()->findOrFail((int) $this->editing);
        $this->validate(['draft' => ['required', 'string', 'min:3', 'max:'.Memory::MAX_LENGTH]]);
        $text = UserMemory::clean($this->draft);

        // Écrit par le client lui-même : une information sensible est permise, jamais un secret.
        if (UserMemory::refusal($text, explicit: true) === 'secret') {
            $this->addError('draft', __('Kekeli never keeps a password, a key, a code or a card or bank account number.'));

            return;
        }

        $memory->update(['content' => $text]);
        $this->cancel();
    }

    public function delete(int $id): void
    {
        Auth::user()->memories()->whereKey($id)->delete();
        if ($this->editing === $id) {
            $this->cancel();
        }
    }

    public function deleteLogo(UserMemory $memory): void
    {
        $memory->forgetLogo(Auth::user());
    }

    public function forgetAll(UserMemory $memory): void
    {
        $memory->forgetAll(Auth::user());
        $this->cancel();
        $this->modal('forget-all')->close();
    }

    public function with(): array
    {
        $user = Auth::user();

        return [
            'memories' => $user->memories()->get(),
            'logo' => filled($user->memory_logo_path),
        ];
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Memory')" :subheading="__('Kekeli remembers useful information you give it (your business, your numbers, your colors, your preferences), so you never have to give it again. Never a password, a key or a bank number.')">
        <div class="flex w-full flex-col gap-6" data-memory-settings>
            <flux:switch wire:model.live="enabled" :label="__('Use the memory')" :description="__('When it is off, Kekeli neither remembers nor uses your memories.')" data-memory-switch />

            @if ($logo)
                {{-- Sur un téléphone, le logo en haut, puis le texte et le bouton dessous ; sur un ordinateur, sur une ligne. --}}
                <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 px-4 py-3 sm:flex-row sm:items-center dark:border-zinc-700" data-memory-logo>
                    <img src="{{ route('memory.logo') }}?v={{ crc32((string) auth()->user()->memory_logo_path) }}" alt="{{ __('Your logo') }}" width="64" height="64" class="size-16 shrink-0 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-3 gap-y-2">
                        <div class="min-w-0 flex-1 basis-48 text-sm">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Your logo') }}</p>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Laid as it is on your posters, never redrawn.') }}</p>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteLogo" class="shrink-0" data-delete-logo>{{ __('Delete') }}</flux:button>
                    </div>
                </div>
            @endif

            @if ($memories->isEmpty())
                <div class="kekeli-vide rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600" data-memory-empty>
                    <span class="kekeli-vide-icone"><flux:icon.bookmark variant="mini" /></span>
                    <p>{{ __('Kekeli has not remembered anything yet. Say for example « Remember that my shop is called… ».') }}</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700" data-memory-list>
                    @foreach ($memories as $memory)
                        <li wire:key="souvenir-{{ $memory->id }}" class="px-4 py-3 text-sm" data-memory="{{ $memory->id }}">
                            @if ($editing === $memory->id)
                                <form wire:submit="save" class="flex flex-col gap-2">
                                    <flux:textarea wire:model="draft" rows="2" maxlength="{{ \App\Models\Memory::MAX_LENGTH }}" :label="__('Memory')" label:sr-only data-memory-draft />
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                                        <flux:button size="sm" variant="primary" type="submit" data-memory-save>{{ __('Save') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-start gap-3">
                                    <p class="min-w-0 flex-1 break-words leading-relaxed text-zinc-800 dark:text-zinc-100">{{ $memory->content }}</p>
                                    <div class="-my-1 flex shrink-0 gap-0.5">
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $memory->id }})" :aria-label="__('Edit')" :tooltip="__('Edit')" data-memory-edit />
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $memory->id }})" :aria-label="__('Delete')" :tooltip="__('Delete')" data-memory-delete />
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($memories->isNotEmpty() || $logo)
                <div>
                    <flux:modal.trigger name="forget-all">
                        <flux:button variant="ghost" size="sm" icon="trash" data-forget-all>{{ __('Erase everything') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('In a discussion, you can also say « Remember that… », « Forget… » or « What do you know about me? ». Your memories are encrypted, only for you, and deleted with your account.') }}</p>
        </div>

        <flux:modal name="forget-all" class="w-full max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Erase all the memory?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Kekeli will forget everything it remembered about you, and your saved logo. This cannot be undone.') }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="forgetAll" data-confirm-forget-all>{{ __('Erase everything') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-settings.layout>
</div>
