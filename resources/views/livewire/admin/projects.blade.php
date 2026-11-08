<?php

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function rendering($view): void
    {
        $view->title(__('Projects'));
    }

    /**
     * Every project, most recently changed first.
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        return Project::query()->with('user')->latest('updated_at')->paginate(30);
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-4">
    <div>
        <flux:heading size="xl" level="1">{{ __('Projects') }}</flux:heading>
        <flux:subheading>{{ __('Every project of every user. Open one to see its conversation and its preview (read only).') }}</flux:subheading>
    </div>

    <div class="flex flex-col gap-2">
        @forelse ($this->projects as $project)
            <a wire:key="admin-project-{{ $project->id }}" href="{{ route('projects.show', $project) }}" wire:navigate
                class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $project->name }}</span>
                    @if ($project->hasLivePreview())
                        <flux:badge size="sm" color="emerald">{{ __('Preview on') }}</flux:badge>
                    @elseif ($project->preview_url)
                        <flux:badge size="sm" color="zinc">{{ __('Paused') }}</flux:badge>
                    @endif
                </div>
                <span class="break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $project->user->email }}</span>
                <span class="text-xs text-zinc-500">
                    {{ __('Last change: :time', ['time' => $project->updated_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]) }}
                    @if ($project->backup_at)
                        · {{ __('Saved :time', ['time' => $project->backup_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]) }}
                    @endif
                </span>
            </a>
        @empty
            <flux:text>{{ __('No project yet.') }}</flux:text>
        @endforelse
    </div>

    {{ $this->projects->links() }}
</section>
