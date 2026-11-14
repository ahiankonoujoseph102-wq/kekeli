@props(['prefix'])
{{-- Les fenêtres « Renommer » et « Supprimer » d'une liste de créations (App\Livewire\Concerns\ManagesCreations). --}}
<flux:modal :name="$prefix.'-rename'" class="w-full max-w-md">
    <form wire:submit="renameProject" class="space-y-5">
        <flux:heading size="lg">{{ __('Rename') }}</flux:heading>
        <flux:input wire:model="newName" :label="__('Name')" maxlength="100" />
        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal :name="$prefix.'-delete'" class="w-full max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Delete this project?') }}</flux:heading>
            <flux:subheading>{{ __('The project and its whole conversation will be permanently deleted.') }}</flux:subheading>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button variant="danger" wire:click="deleteProject" data-confirm-delete>{{ __('Delete') }}</flux:button>
        </div>
    </div>
</flux:modal>
