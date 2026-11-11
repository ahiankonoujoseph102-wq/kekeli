{{--
    « Relier à GitHub », comme Lovable et Bolt.new : le code du site part dans un dépôt du client, pour l'héberger où il
    veut. Rien ne s'affiche tant que l'accès à GitHub n'est pas réglé (aucun message « bientôt »).
--}}
@if ($this->githubConfigured && $this->isOwner && ! $project->isRepository() && $project->backup_at)
    @php($user = \Illuminate\Support\Facades\Auth::user())
    <div class="flex flex-wrap items-center gap-2" data-github="{{ $project->github_status ?? 'none' }}">
        @if (! $user->github_token)
            <flux:button size="sm" variant="filled" icon="link" :href="route('github.connect', ['projet' => $project->id])" data-github-connect>{{ __('Link to GitHub') }}</flux:button>
        @elseif ($project->github_status === \App\Models\Project::GITHUB_IN_PROGRESS)
            <span class="flex items-center gap-2 text-sm text-zinc-500" wire:poll.3s><flux:icon.loading class="size-4" /> {{ __('Sending your code to GitHub…') }}</span>
        @else
            <flux:button size="sm" variant="filled" icon="arrow-up-tray" wire:click="exportToGithub" :disabled="(bool) $this->activeRun" data-github-export>
                {{ $project->github_repo ? __('Send again to GitHub') : __('Link to GitHub') }}
            </flux:button>
            @if ($project->github_repo && $project->github_status === \App\Models\Project::GITHUB_DONE)
                <a href="https://github.com/{{ $project->github_repo }}" target="_blank" rel="noopener" class="break-all text-sm text-amber-700 underline dark:text-amber-400" data-github-repo>github.com/{{ $project->github_repo }}</a>
            @endif
        @endif
    </div>
    @if ($project->github_status === \App\Models\Project::GITHUB_FAILED)
        <p class="kekeli-erreur text-sm" data-github-failed>{{ __('Your code could not be sent this time. Please try again in a moment.') }}</p>
    @endif
@endif
