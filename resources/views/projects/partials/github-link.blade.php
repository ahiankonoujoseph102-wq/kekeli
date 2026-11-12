{{--
    « Relier à GitHub », comme Claude Code, Lovable et Bolt.new (« projet d'abord ») : Kekeli crée un dépôt privé dans
    le compte du client à partir du projet, puis y envoie ses changements après chaque travail réussi : pull request
    (par défaut) ou branche principale, au choix. Rien ne s'affiche tant que la GitHub App n'est pas réglée.
--}}
@if ($this->githubConfigured && $this->isOwner && ! $project->isRepository() && $project->backup_at)
    @php($user = \Illuminate\Support\Facades\Auth::user())
    <div class="flex flex-col gap-2" data-github="{{ $project->github_status ?? 'none' }}">
        @if (! $user->github_token)
            <flux:button size="sm" variant="filled" icon="link" :href="route('github.connect', ['projet' => $project->id])" data-github-connect>{{ __('Link to GitHub') }}</flux:button>
        @elseif ($project->github_status === \App\Models\Project::GITHUB_IN_PROGRESS)
            <span class="flex items-center gap-2 text-sm text-zinc-500" wire:poll.3s><flux:icon.loading class="size-4" /> {{ __('Sending your code to GitHub…') }}</span>
        @elseif (! $project->repository)
            <flux:button size="sm" variant="filled" icon="arrow-up-tray" wire:click="exportToGithub" :disabled="(bool) $this->activeRun" data-github-export>{{ __('Create a private repository') }}</flux:button>
        @else
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm" data-github-pill>
                <a href="https://github.com/{{ $project->repository }}" target="_blank" rel="noopener noreferrer" class="break-all text-zinc-800 underline decoration-zinc-300 underline-offset-2 dark:text-zinc-100 dark:decoration-zinc-600" data-github-repo>github.com/{{ $project->repository }}</a>
                @if ($project->pr_url)
                    <a href="{{ $project->pr_url }}" target="_blank" rel="noopener noreferrer" class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 dark:text-zinc-300 dark:decoration-zinc-600" data-github-pr>{{ __('Pull request :number', ['number' => $project->pr_number]) }}</a>
                @endif
            </div>
            {{-- Comment Kekeli envoie son travail : pull request (conseillé, comme Claude Code) ou branche principale. --}}
            <flux:radio.group wire:change="setPushMode($event.target.value)" :label="__('After each work, Kekeli sends the changes')" class="text-sm" data-push-mode="{{ $project->github_push_mode }}">
                <flux:radio value="pull_request" :label="__('In a pull request (advised)')" :checked="$project->github_push_mode !== 'direct'" />
                <flux:radio value="direct" :label="__('Straight on the main branch')" :checked="$project->github_push_mode === 'direct'" />
            </flux:radio.group>
            <flux:button size="sm" variant="ghost" icon="arrow-up-tray" wire:click="exportToGithub" :disabled="(bool) $this->activeRun" data-github-export>{{ __('Send now') }}</flux:button>
        @endif
    </div>
    @if ($project->github_status === \App\Models\Project::GITHUB_FAILED)
        <p class="kekeli-erreur text-sm" data-github-failed>{{ __('Your code could not be sent this time. Please try again in a moment.') }}</p>
    @endif
@endif
