{{--
    Vue « Code », comme Lovable ou Bolt.new : une barre compacte (nombre de fichiers, téléchargement, GitHub quand il
    est en service), les fichiers changés par la dernière demande (ce qui remplace « Voir les modifications » dans la
    discussion), puis tous les fichiers du site en lecture seule. GitHub est caché tant qu'il ne fonctionne pas.
--}}
@php($user = \Illuminate\Support\Facades\Auth::user())
@php($changed = $this->lastChangedRun)
@php($marks = ['ajoute' => ['+', 'text-emerald-600', __('added')], 'supprime' => ['−', 'text-red-600', __('deleted')], 'renomme' => ['→', 'text-sky-600', __('renamed')], 'modifie' => ['~', 'text-amber-600', __('changed')]])
<div class="mx-auto flex w-full max-w-3xl flex-col gap-3 p-3 sm:p-4" data-project-code>
    @if ($project->backup_at && $this->isOwner && ! $project->isRepository())
        <div class="flex flex-wrap items-center gap-2" data-code-toolbar>
            <flux:button size="sm" variant="filled" icon="arrow-down-tray" :href="route('projects.code.zip', $project)" data-zip-download>{{ __('Download') }}</flux:button>

            {{-- GitHub : visible seulement quand l'envoi fonctionne vraiment. --}}
            @if ($this->githubConfigured)
                <div class="flex flex-wrap items-center gap-2" data-github="{{ $project->github_status ?? 'none' }}">
                    @if (! $user->github_token)
                        <flux:button size="sm" variant="filled" icon="link" :href="route('github.connect', ['projet' => $project->id])" data-github-connect>{{ __('Connect GitHub') }}</flux:button>
                    @elseif ($project->github_status === \App\Models\Project::GITHUB_IN_PROGRESS)
                        <span class="flex items-center gap-2 text-sm text-zinc-500" wire:poll.3s><flux:icon.loading class="size-4" /> {{ __('Sending your code to GitHub…') }}</span>
                    @else
                        <flux:button size="sm" variant="filled" icon="arrow-up-tray" wire:click="exportToGithub" :disabled="(bool) $this->activeRun" data-github-export>
                            {{ $project->github_repo ? __('Send again') : __('Send to GitHub') }}
                        </flux:button>
                        @if ($project->github_repo && $project->github_status === \App\Models\Project::GITHUB_DONE)
                            <a href="https://github.com/{{ $project->github_repo }}" target="_blank" rel="noopener" class="break-all text-sm text-amber-700 underline dark:text-amber-400" data-github-repo>github.com/{{ $project->github_repo }}</a>
                        @endif
                    @endif
                </div>
            @endif
        </div>
        @if ($project->github_status === \App\Models\Project::GITHUB_FAILED && $this->githubConfigured)
            <p class="kekeli-erreur text-sm" data-github-failed>{{ __('Your code could not be sent this time. Please try again in a moment.') }}</p>
        @endif
    @endif

    {{-- Ce que la dernière demande a changé : chaque fichier s'ouvre sur ses modifications. --}}
    @if ($changed && ! empty($changed->changes['fichiers']))
        <section class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-last-changes>
            <p class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">{{ __('Last changes') }}</p>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach (array_slice($changed->changes['fichiers'], 0, 30) as $file)
                    @php([$mark, $color, $label] = $marks[$file['etat'] ?? 'modifie'] ?? $marks['modifie'])
                    <li wire:key="changement-{{ md5($file['chemin']) }}">
                        <button type="button" wire:click="showChanges('{{ $changed->id }}')" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800" data-show-changes>
                            <span class="{{ $color }} w-3 shrink-0 text-center font-mono text-xs font-bold" title="{{ $label }}">{{ $mark }}</span>
                            <span class="min-w-0 flex-1 truncate font-mono text-xs" data-changed-path>{{ $file['chemin'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (! $project->backup_at && ! ($changed && ! empty($changed->changes['fichiers'])))
        <div class="flex flex-col items-center gap-3 py-16 text-center">
            <flux:icon.code-bracket class="size-8 text-zinc-400" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The code will appear here') }}</p>
        </div>
    @elseif ($project->backup_at)

        {{-- Les fichiers du site, en lecture seule. --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-code-files>
            @if (! $codeLoaded)
                <div class="p-3">
                    <flux:button size="sm" icon="folder-open" wire:click="loadCode">{{ __('Show the files') }}</flux:button>
                </div>
            @elseif ($openedFile && array_key_exists($openedFile, $this->codeFiles))
                @php($content = $this->codeFiles[$openedFile])
                <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="closeFile" :aria-label="__('All the files')" />
                    <span class="min-w-0 flex-1 truncate font-mono text-xs" data-opened-file>{{ $openedFile }}</span>
                </div>
                @if (preg_match('/\.(png|jpe?g|gif|webp|ico|svgz|woff2?|ttf|otf|pdf|zip|mp4|mp3)$/i', $openedFile) || ! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0"))
                    <p class="p-4 text-sm text-zinc-500">{{ __('This file cannot be shown here.') }}</p>
                @else
                    <pre class="max-h-[70svh] overflow-auto p-3 font-mono text-xs leading-relaxed text-zinc-800 dark:text-zinc-100" data-file-content>{{ \Illuminate\Support\Str::limit($content, 200000) }}</pre>
                @endif
            @elseif ($this->codeFiles === [])
                <p class="p-4 text-sm text-zinc-500">{{ __('The files cannot be shown right now. Please try again in a moment.') }}</p>
            @else
                <p class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">{{ trans_choice(':count file|:count files', count($this->codeFiles), ['count' => count($this->codeFiles)]) }}</p>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->codeFiles as $path => $content)
                        <li wire:key="fichier-code-{{ md5($path) }}">
                            <button type="button" wire:click="openFile(@js($path))" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800" data-code-file>
                                <flux:icon.document-text variant="micro" class="shrink-0 text-zinc-400" />
                                <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ $path }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
