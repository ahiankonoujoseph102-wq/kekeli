{{--
    La zone de saisie de Kekeli, la même partout (accueil, « Mes projets », page du projet), comme Bolt.new, Lovable
    ou Claude Code : le texte, les photos jointes, un bouton photo, des outils à gauche (par exemple « GitHub »),
    un petit indicateur à droite (par exemple l'énergie) et le bouton rond couleur encre avec une flèche.
--}}
@props([
    'model',
    'photosModel' => null,
    'photos' => [],
    'placeholder',
    'submit',
    'label' => null,
    'disabled' => false,
    'rows' => 3,
    'removePhoto' => 'removePhoto',
    'inputAttribute' => 'data-prompt-input',
])

<div {{ $attributes->class('rounded-3xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgba(28,26,23,0.04),0_8px_24px_-12px_rgba(28,26,23,0.12)] transition duration-200 focus-within:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:focus-within:border-zinc-500') }} data-prompt-box>
    <label for="{{ $model }}-champ" class="sr-only">{{ $label ?? $placeholder }}</label>
    <textarea
        id="{{ $model }}-champ"
        wire:model="{{ $model }}"
        rows="{{ $rows }}"
        maxlength="4000"
        placeholder="{{ $placeholder }}"
        @disabled($disabled)
        class="block w-full resize-none border-0 bg-transparent px-5 pt-4 text-base text-zinc-900 placeholder-zinc-400 outline-none focus:ring-0 disabled:opacity-60 dark:text-white"
        x-data
        x-on:keydown.enter="if ($event.ctrlKey || $event.metaKey) { $event.preventDefault(); $wire.{{ $submit }}() }"
        {{ $inputAttribute }}
    ></textarea>

    @if ($photos)
        <ul class="flex flex-wrap gap-2 px-4 pt-2" data-prompt-photos>
            @foreach ($photos as $index => $photo)
                <li wire:key="photo-jointe-{{ $index }}" class="relative">
                    @if (method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-14 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700">
                    @else
                        <span class="flex size-14 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-700"><flux:icon.photo class="size-5 text-zinc-400" /></span>
                    @endif
                    <button type="button" wire:click="{{ $removePhoto }}({{ $index }})" class="absolute -right-1.5 -top-1.5 flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900" aria-label="{{ __('Remove this photo') }}">
                        <flux:icon.x-mark variant="micro" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex items-center justify-between gap-2 px-3 pb-3 pt-1.5">
        <div class="flex min-w-0 items-center gap-1">
            @if ($photosModel)
                <label class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" title="{{ __('Add photos') }}" data-prompt-attach>
                    <flux:icon.photo class="size-5" />
                    <span class="sr-only">{{ __('Add photos') }}</span>
                    <input type="file" wire:model="{{ $photosModel }}" multiple accept="image/jpeg,image/png,image/webp" class="hidden" @disabled($disabled)>
                </label>
            @endif
            {{ $tools ?? '' }}
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($photosModel)
                <span wire:loading wire:target="{{ $photosModel }}" class="text-xs text-zinc-500">{{ __('Adding the photos…') }}</span>
            @endif
            {{ $aside ?? '' }}
            <button
                type="submit"
                class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-white transition duration-200 hover:bg-zinc-700 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                wire:loading.attr="disabled"
                wire:target="{{ $submit }}{{ $photosModel ? ','.$photosModel : '' }}"
                @disabled($disabled)
                aria-label="{{ __('Send to Kekeli') }}"
                data-prompt-send
            >
                <flux:icon.arrow-up class="size-5" wire:loading.remove wire:target="{{ $submit }}" />
                <flux:icon.loading class="size-5" wire:loading wire:target="{{ $submit }}" />
            </button>
        </div>
    </div>
</div>
