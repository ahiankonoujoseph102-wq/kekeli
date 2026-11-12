{{--
    « Télécharger », comme Claude et ChatGPT : un seul format, un bouton direct ; plusieurs (Word et PDF, PowerPoint et
    PDF), un petit menu pour choisir. Sur un téléphone, dans la barre du haut, l'icône seule avec son libellé accessible.
--}}
@php($formats = $version->formats())
@php($compact = $compact ?? false)
@if (count($formats) === 1)
    @php($format = array_key_first($formats))
    <flux:button size="sm" variant="primary" icon="arrow-down-tray" :href="route('projects.versions.download', ['project' => $project, 'number' => $version->number, 'format' => $format])" :aria-label="__('Download')" @class(['max-sm:!px-2' => $compact]) data-download="{{ $format }}">
        <span @class(['max-sm:sr-only' => $compact])>{{ __('Download') }}</span>
    </flux:button>
@else
    <flux:dropdown position="bottom" align="end">
        <flux:button size="sm" variant="primary" icon="arrow-down-tray" icon:trailing="chevron-down" :aria-label="__('Download')" data-download-open>
            <span @class(['max-sm:sr-only' => $compact])>{{ __('Download') }}</span>
        </flux:button>
        <flux:menu class="min-w-44" data-download-menu>
            @foreach ($formats as $format => $label)
                <flux:menu.item :icon="$format === 'pdf' ? 'document' : ($format === 'pptx' ? 'presentation-chart-bar' : 'document-text')" :href="route('projects.versions.download', ['project' => $project, 'number' => $version->number, 'format' => $format])" data-download="{{ $format }}">
                    {{ $label }} <span class="text-zinc-400">(.{{ $format }})</span>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
@endif
