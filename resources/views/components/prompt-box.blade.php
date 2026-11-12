{{--
    La zone de saisie de Kekeli, la même partout (accueil, « Nouveau », page du projet), comme Claude, ChatGPT ou
    Lovable : le texte, les photos jointes, UN seul bouton « + » à gauche qui ouvre un petit menu (« Ajouter une image »,
    puis ce que la page ajoute, par exemple « GitHub ») et le bouton rond couleur encre avec une flèche. Le menu est
    prévu pour accueillir plus tard d'autres entrées ; il ne montre que ce qui fonctionne déjà.
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
    // Pendant que Kekeli répond ou travaille : le nom de l'action qui l'arrête ; le bouton d'envoi devient « Arrêter ».
    'stop' => null,
    // Le micro (dictée vocale), comme ChatGPT et Claude : présent partout où l'on écrit à Kekeli.
    'dictation' => true,
])
@php($dictee = [
    'champ' => $model.'-champ',
    'adresse' => route('dictation.store'),
    'jeton' => csrf_token(),
    'langue' => ['fr' => 'fr-FR', 'en' => 'en-US'][substr(app()->getLocale(), 0, 2)] ?? 'fr-FR',
    'messages' => [
        'refuse' => __('Kekeli cannot use the microphone. To dictate, allow the microphone for this site in your browser settings (the icon to the left of the address), then touch the microphone again.'),
        'aucunMicro' => __('No microphone was found on this device.'),
        'rien' => __('Kekeli did not hear anything. Touch the microphone and speak close to your phone.'),
        'indisponible' => __('Dictation is not available in this browser. You can write your message.'),
        'echec' => __('Kekeli could not understand the recording. Try again, or write your message.'),
    ],
])

<div x-data {{ $attributes->class('rounded-3xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgba(28,26,23,0.04),0_8px_24px_-12px_rgba(28,26,23,0.12)] transition duration-200 focus-within:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:focus-within:border-zinc-500') }} data-prompt-box>
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
            @if ($photosModel || isset($menu))
                <flux:dropdown position="top" align="start">
                    <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" aria-label="{{ __('Add') }}" title="{{ __('Add') }}" @disabled($disabled) data-prompt-plus>
                        <flux:icon.plus class="size-5" />
                    </button>
                    <flux:menu class="min-w-48" data-prompt-menu>
                        @if ($photosModel)
                            <flux:menu.item icon="photo" x-on:click="$refs.photos.click()" data-prompt-add-image>{{ __('Add an image') }}</flux:menu.item>
                        @endif
                        {{ $menu ?? '' }}
                    </flux:menu>
                </flux:dropdown>
            @endif
            @if ($photosModel)
                {{-- Le choix des photos, ouvert depuis « + » : caché, sans bouton à côté de la zone. --}}
                <span class="hidden" data-prompt-attach>
                    <input type="file" x-ref="photos" wire:model="{{ $photosModel }}" multiple accept="image/jpeg,image/png,image/webp" @disabled($disabled) aria-label="{{ __('Add photos') }}">
                </span>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if ($dictation && ! $stop && auth()->check())
                {{-- Dictée vocale : le texte s'écrit dans la zone, le client le corrige puis l'envoie. --}}
                <div class="flex items-center" x-data="kekeliDictee(@js($dictee))" data-prompt-dictation>
                    <span x-show="transcription" x-cloak class="me-1 text-xs text-zinc-500" data-dictation-busy>{{ __('Writing the dictation…') }}</span>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        x-bind:class="ecoute && '!bg-amber-50 !text-amber-600 dark:!bg-amber-950 dark:!text-amber-400'"
                        x-on:click="basculer()"
                        x-bind:disabled="transcription"
                        x-bind:aria-pressed="ecoute ? 'true' : 'false'"
                        x-bind:aria-label="ecoute ? @js(__('Stop the dictation')) : @js(__('Voice dictation'))"
                        x-bind:title="ecoute ? @js(__('Stop the dictation')) : @js(__('Voice dictation'))"
                        aria-label="{{ __('Voice dictation') }}"
                        title="{{ __('Voice dictation') }}"
                        @disabled($disabled)
                        data-prompt-mic
                    >
                        <flux:icon.microphone class="size-5" x-show="! ecoute" />
                        <span x-show="ecoute" x-cloak class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75 motion-reduce:animate-none"></span><span class="relative inline-flex size-3 rounded-full bg-amber-500"></span></span>
                    </button>
                    <template x-teleport="body">
                        <div x-show="message !== ''" x-cloak x-transition.opacity class="fixed inset-x-4 bottom-28 z-50 mx-auto max-w-md rounded-2xl border border-zinc-200 bg-white p-3 text-sm text-zinc-700 shadow-lg dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" role="alert" data-dictation-message>
                            <div class="flex items-start gap-2">
                                <flux:icon.microphone variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                                <p class="min-w-0 flex-1" x-text="message"></p>
                                <button type="button" class="shrink-0 rounded-full p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-white" x-on:click="message = ''" aria-label="{{ __('Close') }}"><flux:icon.x-mark variant="micro" /></button>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
            @if ($photosModel)
                <span wire:loading wire:target="{{ $photosModel }}" class="text-xs text-zinc-500">{{ __('Adding the photos…') }}</span>
            @endif
            @if ($stop)
                {{-- Comme Claude, ChatGPT ou Lovable : pendant la réponse ou le travail, le bouton d'envoi devient « Arrêter ». --}}
                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-white transition duration-200 hover:bg-zinc-700 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                    wire:click="{{ $stop }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $stop }}"
                    aria-label="{{ __('Stop') }}"
                    title="{{ __('Stop') }}"
                    data-prompt-stop
                >
                    <span class="block size-3 rounded-[3px] bg-current"></span>
                </button>
            @else
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
            @endif
        </div>
    </div>
</div>
