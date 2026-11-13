{{--
    Aperçu d'un document ou d'une présentation, comme Claude et ChatGPT : les vraies pages (ou diapositives) du fichier,
    l'une sous l'autre, en papier blanc même en mode sombre ; la liste des versions (les anciennes restent consultables
    et téléchargeables) ; « Télécharger ». Pas d'éditeur : tout se modifie en écrivant à Kekeli.
--}}
@php($version = $this->shownVersion)
@php($presentation = $project->isPresentation())
@php($image = $project->isImage())
@php($video = $project->isVideo())
<div class="flex min-h-0 flex-1 flex-col gap-2" data-document-preview="{{ $video ? 'video' : ($image ? 'image' : ($presentation ? 'presentation' : 'document')) }}">
    @if ($version)
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 px-1">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" data-version-title>{{ $version->title }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    @if ($video)
                        <span data-video-format>{{ \App\Models\DocumentVersion::videoFormatLabel(data_get($version->structure, 'format'), (float) data_get($version->structure, 'duree', $version->pages)) }}</span>
                    @elseif ($image)
                        <span data-image-format>{{ \App\Models\DocumentVersion::imageFormatLabel(data_get($version->structure, 'format')) }}</span>
                    @else
                    {{ $presentation ? trans_choice('{1} :count slide|[2,*] :count slides', $version->pages, ['count' => $version->pages]) : trans_choice('{1} :count page|[2,*] :count pages', $version->pages, ['count' => $version->pages]) }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                @if ($this->versions->count() > 1)
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon="clock" icon:trailing="chevron-down" data-versions-open>{{ __('Version :number', ['number' => $version->number]) }}</flux:button>
                        <flux:menu class="w-72" data-versions>
                            @foreach ($this->versions->reverse() as $item)
                                <flux:menu.item wire:click="showVersion({{ $item->number }})" data-version="{{ $item->number }}" @class(['font-semibold' => $item->number === $version->number])>
                                    <span class="flex min-w-0 flex-col">
                                        <span>{{ __('Version :number', ['number' => $item->number]) }} · {{ $item->created_at->timezone(config('kekeli.display_timezone'))->translatedFormat('j F à H \h i') }}</span>
                                        @if (filled($item->request))
                                            <span class="truncate text-xs text-zinc-500">« {{ \Illuminate\Support\Str::limit($item->request, 60) }} »</span>
                                        @endif
                                    </span>
                                </flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endif
                @include('projects.partials.document-download', ['version' => $version])
            </div>
        </div>
        @if ($version->number !== $this->versions->last()?->number)
            <p class="shrink-0 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" data-older-version>
                {{ __('You are looking at an older version.') }}
                <button type="button" class="underline underline-offset-2" wire:click="showVersion({{ $this->versions->last()->number }})">{{ __('See the last one') }}</button>
            </p>
        @endif
        @if ($video)
            {{-- Le lecteur, comme sur un réseau social : la vidéo à sa taille, sur fond sombre ; les médias et leur licence en dessous. --}}
            @php($vertical = data_get($version->structure, 'format', 'vertical') === 'vertical')
            <div class="min-h-0 flex-1 overflow-y-auto rounded-xl bg-zinc-100 p-3 sm:p-5 dark:bg-zinc-900" data-video-panel>
                <div @class(['mx-auto flex flex-col gap-3', 'max-w-[22rem]' => $vertical, 'max-w-md' => data_get($version->structure, 'format') === 'carre', 'max-w-3xl' => data_get($version->structure, 'format') === 'paysage'])>
                    <video wire:key="video-{{ $version->id }}" controls playsinline preload="metadata"
                        @if (count((array) $version->previews)) poster="{{ route('projects.versions.page', ['project' => $project, 'number' => $version->number, 'page' => 1]) }}" @endif
                        class="block h-auto w-full rounded-lg bg-black shadow-[0_1px_3px_rgba(28,26,23,0.18)]" data-video-player>
                        <source src="{{ route('projects.versions.video', ['project' => $project, 'number' => $version->number]) }}" type="video/mp4">
                    </video>
                    @if (data_get($version->structure, 'voix_ia'))
                        <p class="text-xs text-zinc-500 dark:text-zinc-400" data-ai-voice>{{ __('Voice-over created by artificial intelligence.') }}</p>
                    @endif
                    @if ($version->videoMedia() !== [])
                        <details class="group rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-800" data-video-media>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-zinc-600 select-none dark:text-zinc-300 [&::-webkit-details-marker]:hidden">
                                <span>{{ __('Media used and their license') }}</span>
                                <flux:icon.chevron-down variant="micro" class="transition group-open:rotate-180" />
                            </summary>
                            <ul class="mt-2 space-y-1.5 text-zinc-600 dark:text-zinc-300">
                                @foreach ($version->videoMedia() as $media)
                                    <li data-video-media-item>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ ($media['type'] ?? '') === 'musique' ? __('Music') : (isset($media['scene']) ? __('Scene :number', ['number' => $media['scene']]) : __('Media')) }}</span>
                                        · {{ $media['titre'] ?? $media['nom'] ?? $media['source'] ?? '' }}@if (($media['source'] ?? null) && (($media['titre'] ?? null) || ($media['nom'] ?? null))) · {{ $media['source'] }}@endif
                                        · {{ $media['licence'] ?? '' }}
                                        @if (filled($media['page'] ?? null) && str_starts_with((string) $media['page'], 'https://'))
                                            · <a href="{{ $media['page'] }}" target="_blank" rel="noopener noreferrer nofollow" class="underline underline-offset-2">{{ __('Source') }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        @else
        <div class="min-h-0 flex-1 overflow-y-auto rounded-xl bg-zinc-100 p-3 sm:p-5 dark:bg-zinc-900" data-pages>
            <div @class(['mx-auto flex flex-col gap-4', 'max-w-2xl' => ! $presentation && ! $image, 'max-w-4xl' => $presentation, 'max-w-xl' => $image])>
                @foreach ((array) $version->previews as $index => $preview)
                    <figure class="overflow-hidden rounded-sm bg-white shadow-[0_1px_3px_rgba(28,26,23,0.18)] ring-1 ring-black/5" data-page="{{ $index + 1 }}">
                        <img src="{{ route('projects.versions.page', ['project' => $project, 'number' => $version->number, 'page' => $index + 1]) }}" alt="{{ $image ? $version->title : ($presentation ? __('Slide :number', ['number' => $index + 1]) : __('Page :number', ['number' => $index + 1])) }}" loading="lazy" class="block h-auto w-full" @if ($image) @elseif ($presentation) width="960" height="540" @else width="794" height="1123" @endif>
                    </figure>
                @endforeach
            </div>
        </div>
        @endif
    @elseif ($this->activeRun)
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-zinc-200 p-6 text-center dark:border-zinc-700" data-document-waiting>
            <flux:icon.loading class="size-7 text-amber-500" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $video ? __('Kekeli is making your video…') : ($image ? __('Kekeli is preparing your image…') : ($presentation ? __('Kekeli is preparing your presentation…') : __('Kekeli is preparing your document…'))) }}</p>
        </div>
    @else
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-zinc-200 p-6 text-center dark:border-zinc-700" data-document-empty>
            <x-app-logo-icon class="size-10 text-amber-500" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $video ? __('The video will appear here') : ($image ? __('The image will appear here') : ($presentation ? __('The slides will appear here') : __('The pages will appear here'))) }}</p>
        </div>
    @endif
</div>
