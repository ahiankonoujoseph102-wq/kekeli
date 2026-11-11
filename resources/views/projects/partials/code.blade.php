{{--
    Vue « Code » : le code du site en lecture seule (comme Bolt.new sur un téléphone), puis les moyens de l'emporter :
    fichier ZIP et GitHub, gratuits pour toutes les formules.
--}}
<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-3 sm:p-4" data-project-code>
    @if (! $project->backup_at)
        <div class="flex flex-col items-center gap-3 py-10 text-center">
            <flux:icon.code-bracket class="size-10 text-zinc-400" />
            <flux:heading size="lg">{{ __('The code will appear here') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Once Kekeli has built your site, you can read its code here and take it with you.') }}</flux:text>
        </div>
    @else
        @if ($this->isOwner && ! $project->isRepository())
            <div class="grid gap-3 sm:grid-cols-2">
                {{-- GitHub : gratuit pour toutes les formules. Un seul geste : « Connecter GitHub », on accepte, le code arrive tout seul. --}}
                {{-- GitHub : gratuit pour toutes les formules. Un seul geste : « Connecter GitHub », on accepte, le code arrive tout seul. --}}
                <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-github="{{ $project->github_status ?? 'none' }}">
                    <flux:heading size="sm">{{ __('Send to GitHub') }}</flux:heading>
                    @if (! $this->githubConfigured)
                        <flux:text class="text-sm">{{ __('Sending to GitHub will be available very soon.') }}</flux:text>
                    @elseif (! \Illuminate\Support\Facades\Auth::user()->github_token)
                        <flux:text class="text-sm">{{ __('Free. Tap the button, accept on GitHub: the code of your site arrives by itself in a private repository of your account.') }}</flux:text>
                        <flux:button size="sm" variant="primary" icon="link" :href="route('github.connect', ['projet' => $project->id])" data-github-connect>{{ __('Connect GitHub') }}</flux:button>
                    @else
                        <flux:text class="text-sm">{{ __('Connected GitHub account: :login', ['login' => \Illuminate\Support\Facades\Auth::user()->github_login]) }}</flux:text>
                        @if ($project->github_status === \App\Models\Project::GITHUB_IN_PROGRESS)
                            <div class="flex items-center gap-2 text-sm font-medium text-sky-700 dark:text-sky-300" wire:poll.3s>
                                <flux:icon.loading class="size-4" /> {{ __('Sending your code to GitHub…') }}
                            </div>
                        @else
                            @if ($project->github_repo && $project->github_status === \App\Models\Project::GITHUB_DONE)
                                <p class="text-sm">{{ __('Your code is on GitHub:') }}
                                    <a href="https://github.com/{{ $project->github_repo }}" target="_blank" rel="noopener" class="break-all font-medium text-amber-700 underline dark:text-amber-400" data-github-repo>github.com/{{ $project->github_repo }}</a>
                                </p>
                            @elseif ($project->github_status === \App\Models\Project::GITHUB_FAILED)
                                <p class="kekeli-erreur text-sm" data-github-failed>{{ __('Your code could not be sent this time. Please try again in a moment.') }}</p>
                            @endif
                            <flux:button size="sm" :variant="$project->github_repo ? 'subtle' : 'primary'" icon="arrow-up-tray" wire:click="exportToGithub" :disabled="(bool) $this->activeRun" data-github-export>
                                {{ $project->github_repo ? __('Send again') : __('Send my code to GitHub') }}
                            </flux:button>
                        @endif
                    @endif
                </div>

                {{-- ZIP : gratuit pour toutes les formules. --}}
                <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-zip>
                    <flux:heading size="sm">{{ __('Download the code (ZIP file)') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('Free, for every formula.') }}</flux:text>
                    <flux:button size="sm" variant="filled" icon="arrow-down-tray" :href="route('projects.code.zip', $project)" data-zip-download>{{ __('Download the ZIP file') }}</flux:button>
                </div>

            </div>
        @endif

        {{-- Les fichiers du site, en lecture seule. --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-code-files>
            @if (! $codeLoaded)
                <div class="p-4">
                    <flux:button size="sm" icon="folder-open" wire:click="loadCode">{{ __('Show the files') }}</flux:button>
                </div>
            @elseif ($openedFile && array_key_exists($openedFile, $this->codeFiles))
                @php($content = $this->codeFiles[$openedFile])
                <div class="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="closeFile" :aria-label="__('All the files')" />
                    <span class="min-w-0 flex-1 truncate font-mono text-xs" data-opened-file>{{ $openedFile }}</span>
                </div>
                @if (preg_match('/\.(png|jpe?g|gif|webp|ico|svgz|woff2?|ttf|otf|pdf|zip|mp4|mp3)$/i', $openedFile) || ! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0"))
                    <p class="p-4 text-sm text-zinc-500">{{ __('This file is not text (image, font or other): it cannot be shown here.') }}</p>
                @else
                    <pre class="max-h-[70svh] overflow-auto p-3 font-mono text-xs leading-relaxed text-zinc-800 dark:text-zinc-100" data-file-content>{{ \Illuminate\Support\Str::limit($content, 200000) }}</pre>
                @endif
            @elseif ($this->codeFiles === [])
                <p class="p-4 text-sm text-zinc-500">{{ __('The files cannot be shown right now. Please try again in a moment.') }}</p>
            @else
                <p class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">{{ trans_choice(':count file, read only|:count files, read only', count($this->codeFiles), ['count' => count($this->codeFiles)]) }}</p>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->codeFiles as $path => $content)
                        <li wire:key="fichier-code-{{ md5($path) }}">
                            <button type="button" wire:click="openFile(@js($path))" class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800" data-code-file>
                                <flux:icon.document-text variant="micro" class="shrink-0 text-zinc-400" />
                                <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ $path }}</span>
                                <span class="shrink-0 text-xs text-zinc-400">@php($octets = strlen($content)){{ $octets < 1024 ? __(':count bytes', ['count' => $octets]) : ($octets < 1048576 ? round($octets / 1024).' Ko' : number_format($octets / 1048576, 1, ',', ' ').' Mo') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
