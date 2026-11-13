{{--
    « Versions », comme Lovable et Bolt.new : chaque travail réussi de Kekeli est une version, de la plus récente à la
    plus ancienne. « Voir » la montre dans l'aperçu sans rien changer ; « Restaurer » crée une NOUVELLE version avec ses
    fichiers (rien n'est effacé : une restauration s'annule en restaurant la version d'avant).
--}}
@php($versions = $this->projectVersions)
@php($current = $versions->first())
<flux:modal name="versions" class="w-full max-w-lg" data-versions-modal>
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Versions') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Each work of Kekeli creates a version. Look at an old version in the preview, or restore it: nothing is ever erased.') }}</flux:text>
        </div>
        <ol class="-mx-1 max-h-[60vh] space-y-1 overflow-y-auto px-1" data-versions-list>
            @foreach ($versions as $version)
                @php($isCurrent = $version->number === $current?->number)
                @php($isViewed = $project->viewing_version === $version->number)
                <li wire:key="version-{{ $version->number }}" @class(['rounded-xl border px-3 py-2.5 text-sm', 'border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800/60' => $isCurrent || $isViewed, 'border-zinc-200 dark:border-zinc-700' => ! ($isCurrent || $isViewed)]) data-version-item="{{ $version->number }}">
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                        <div class="min-w-0 flex-1 basis-52">
                            <p class="font-medium text-zinc-900 dark:text-white">
                                {{ __('Version :number', ['number' => $version->number]) }}
                                @if ($isCurrent)<span class="ms-1 rounded-full bg-zinc-900 px-2 py-0.5 text-[11px] font-medium text-white dark:bg-white dark:text-zinc-900" data-version-current>{{ __('Current') }}</span>@endif
                                @if ($isViewed)<span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">{{ __('In the preview') }}</span>@endif
                            </p>
                            <p class="break-words text-zinc-700 dark:text-zinc-200">
                                @if ($version->isRestore())<flux:icon.arrow-uturn-left variant="micro" class="me-0.5 inline size-3.5 text-zinc-400" />@endif{{ $version->title }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $version->created_at->timezone(config('kekeli.display_timezone'))->translatedFormat('j F Y \à H \h i') }}</p>
                        </div>
                        @if ($this->isOwner && ! $isCurrent)
                            <div class="flex shrink-0 gap-1">
                                @unless ($isViewed)
                                    <flux:button size="xs" variant="ghost" icon="eye" wire:click="viewVersion({{ $version->number }})" :disabled="(bool) $this->activeRun" data-version-view="{{ $version->number }}">{{ __('See') }}</flux:button>
                                @endunless
                                <flux:button size="xs" variant="filled" icon="arrow-uturn-left" wire:click="askRestore({{ $version->number }})" :disabled="(bool) $this->activeRun" data-version-restore="{{ $version->number }}">{{ __('Restore') }}</flux:button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</flux:modal>

<flux:modal name="restore-version" class="w-full max-w-md" data-restore-modal>
    @php($toRestore = $restoreNumber ? $versions->firstWhere('number', $restoreNumber) : null)
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Restore this version?') }}</flux:heading>
        @if ($toRestore)
            <flux:text>{{ __('The project goes back to version :number (« :title »). Kekeli creates version :next with these files: the current version stays in the list and you can come back to it at any time.', ['number' => $toRestore->number, 'title' => $toRestore->title, 'next' => ($current?->number ?? 0) + 1]) }}</flux:text>
            @if ($project->isLinkedToGithub())
                <flux:text>{{ __('On GitHub, the return is sent as a new change, nothing is erased.') }}</flux:text>
            @endif
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost" size="sm">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button variant="primary" size="sm" icon="arrow-uturn-left" wire:click="restoreVersion" data-restore-confirm>{{ __('Restore this version') }}</flux:button>
        </div>
    </div>
</flux:modal>
