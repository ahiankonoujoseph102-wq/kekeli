{{--
    La carte d'un fichier dans la discussion, comme Claude : l'icône du type, le nom, les formats disponibles,
    « Ouvrir » (l'onglet du fichier, à cette version) et « Télécharger ».
--}}
@php($icon = $project->typeIcon())
<div class="flex max-w-md flex-wrap items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900" data-file-card="{{ $version->number }}">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
        <flux:icon :name="$icon" variant="mini" />
    </span>
    <button type="button" class="min-w-[9rem] flex-1 text-start" x-on:click="vue = 'apercu'; $wire.showVersion({{ $version->number }})" data-file-card-title>
        <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $version->title }}</span>
        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ implode(' · ', $version->formats()) }}@if ($version->number > 1) · {{ __('Version :number', ['number' => $version->number]) }}@endif</span>
    </button>
    <div class="flex items-center gap-1.5 max-sm:w-full max-sm:justify-end">
        <flux:button size="sm" variant="ghost" icon="eye" x-on:click="vue = 'apercu'; $wire.showVersion({{ $version->number }})" data-file-card-open>{{ __('Open') }}</flux:button>
        @include('projects.partials.document-download', ['version' => $version, 'secondary' => true])
    </div>
</div>
