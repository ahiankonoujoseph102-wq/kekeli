<?php

use App\Github\GithubClient;
use App\Github\RepositoryClient;
use App\Models\Project;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    /** Address of a public repository, for example https://github.com/laravel/laravel. */
    public string $publicAddress = '';

    public function rendering($view): void
    {
        $view->title(__('Open a GitHub repository'));
    }

    #[Computed]
    public function githubConfigured(): bool
    {
        return app(GithubClient::class)->configured();
    }

    /**
     * The client's repositories (kept a few minutes, to stay fast on the phone).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function repositories(): array
    {
        $token = Auth::user()->github_token;
        if (! $token) {
            return [];
        }

        try {
            $all = Cache::remember('kekeli.depots.'.Auth::id(), now()->addMinutes(5), fn () => app(RepositoryClient::class)->repositories($token));
        } catch (RequestException) {
            return [];
        }

        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($all, fn ($repo) => $search === '' || str_contains(mb_strtolower($repo['full_name']), $search)));
    }

    public function open(string $fullName): void
    {
        $this->openRepository($fullName);
    }

    public function openPublic(): void
    {
        $this->resetErrorBag();
        $address = trim($this->publicAddress);
        if (! preg_match('#^(?:https?://)?(?:www\.)?github\.com/([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $address, $match)
            && ! preg_match('#^([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)$#', $address, $match)) {
            $this->addError('publicAddress', __('Write the address of the repository, for example https://github.com/owner/name.'));

            return;
        }

        $this->openRepository($match[1]);
    }

    /**
     * A new project for the repository: Kekeli will open it in its computer at the first request, on its own branch.
     */
    private function openRepository(string $fullName): void
    {
        $token = Auth::user()->github_token ?: null;

        try {
            $repo = app(RepositoryClient::class)->repository($fullName, $token);
        } catch (RequestException $exception) {
            $this->addError($this->publicAddress !== '' ? 'publicAddress' : 'search', $exception->response->status() === 404
                ? __('This repository cannot be found. If it is private, connect your GitHub account first.')
                : __('GitHub did not answer this time. Please try again in a moment.'));

            return;
        }

        $project = Auth::user()->projects()->create([
            'name' => Str::afterLast($repo['full_name'], '/'),
            'description' => __('GitHub repository :name', ['name' => $repo['full_name']]),
        ]);
        $project->forceFill([
            'kind' => Project::KIND_REPOSITORY,
            'repository' => $repo['full_name'],
            'repository_base_branch' => $repo['default_branch'],
            'repository_base_sha' => $repo['sha'],
            'repository_access' => $repo['can_write'] ? 'write' : 'read',
            'work_branch' => 'kekeli/'.now()->timezone(config('kekeli.display_timezone'))->format('Ymd-His'),
        ])->save();

        $this->redirect(route('projects.show', $project), navigate: true);
    }
}; ?>

<section class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <div>
        <flux:link :href="route('projects.index')" wire:navigate class="text-sm">← {{ __('My projects') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Open a GitHub repository') }}</flux:heading>
        <flux:subheading>{{ __('Developer mode: Kekeli opens an existing project (for example one started with Claude Code), reads it, works on its own branch, runs the tests and opens a pull request. It merges only if you ask.') }}</flux:subheading>
    </div>

    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-my-repositories>
        <flux:heading size="lg">{{ __('My repositories') }}</flux:heading>
        @if (! $this->githubConfigured)
            <flux:text class="mt-2 text-sm">{{ __('Sending to GitHub will be available very soon.') }}</flux:text>
        @elseif (! Auth::user()->github_token)
            <flux:text class="mt-2 text-sm">{{ __('Connect your GitHub account once to see your repositories, including private ones.') }}</flux:text>
            <flux:button variant="primary" icon="link" class="mt-3" :href="route('github.connect', ['retour' => route('projects.open-repository')])" data-github-connect>{{ __('Connect GitHub') }}</flux:button>
        @else
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search a repository')" class="mt-3" :aria-label="__('Search a repository')" />
            <flux:error name="search" />
            <ul class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->repositories as $repo)
                    <li wire:key="repo-{{ $repo['full_name'] }}" class="flex items-center justify-between gap-3 py-3" data-repository="{{ $repo['full_name'] }}">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $repo['full_name'] }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ $repo['private'] ? __('Private') : __('Public') }} · {{ __('branch :branch', ['branch' => $repo['default_branch']]) }}{{ $repo['can_write'] ? '' : ' · '.__('read only') }}
                            </p>
                        </div>
                        <flux:button size="sm" variant="primary" wire:click="open('{{ $repo['full_name'] }}')">{{ __('Open') }}</flux:button>
                    </li>
                @empty
                    <li class="py-3 text-sm text-zinc-500">{{ __('No repository found.') }}</li>
                @endforelse
            </ul>
        @endif
    </div>

    <form wire:submit="openPublic" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-public-repository>
        <flux:heading size="lg">{{ __('A public repository') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Any public repository can be opened in read only, without connecting GitHub: Kekeli can work on it in its computer, but cannot send anything to GitHub.') }}</flux:text>
        <div class="mt-3 flex items-start gap-2">
            <div class="flex-1">
                <flux:input wire:model="publicAddress" placeholder="https://github.com/owner/name" autocapitalize="none" :aria-label="__('Address of the repository')" data-public-address />
                <flux:error name="publicAddress" />
            </div>
            <flux:button type="submit" variant="primary">{{ __('Open') }}</flux:button>
        </div>
    </form>
</section>
