<?php

use App\Models\AgentRun;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Project $project;

    public ?string $changesRunId = null;

    public function mount(Project $project): void
    {
        abort_unless($project->user()->is(Auth::user()) || Auth::user()->can('admin'), 404);
        $this->project = $project;
    }

    public function rendering($view): void
    {
        $view->title(__('Reports – :project', ['project' => $this->project->name]));
    }

    /**
     * Every report of the project, the most recent first.
     */
    #[Computed]
    public function runs()
    {
        return $this->project->agentRuns()->with('message', 'project')->whereNotNull('report')->reorder()->latest('finished_at')->latest('created_at')->get();
    }

    #[Computed]
    public function changesRun(): ?AgentRun
    {
        return $this->changesRunId ? $this->project->agentRuns()->whereKey($this->changesRunId)->first() : null;
    }

    public function showChanges(string $runId): void
    {
        $this->changesRunId = $this->project->agentRuns()->whereKey($runId)->value('id');
        unset($this->changesRun);
        Flux::modal('run-changes')->show();
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-4" data-reports>
    <div>
        <flux:link :href="route('projects.show', $project)" wire:navigate class="text-sm">← {{ $project->name }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Reports') }}</flux:heading>
        <flux:subheading>{{ __('Every report of Kekeli for this project, the most recent first.') }}</flux:subheading>
    </div>

    @forelse ($this->runs as $run)
        <article wire:key="compte-rendu-{{ $run->id }}" class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-report-history="{{ $run->id }}">
            <p class="text-xs text-zinc-500">{{ ($run->finished_at ?? $run->created_at)->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i') }} · {{ $run->status === AgentRun::STATUS_COMPLETED ? __('Finished') : __('Not finished') }}</p>
            <p class="mt-1 font-medium text-zinc-900 dark:text-white">{{ \Illuminate\Support\Str::limit((string) $run->message?->content, 300) }}</p>
            <x-run-report :run="$run" />
        </article>
    @empty
        <flux:text>{{ __('No report yet: Kekeli writes one at the end of each request.') }}</flux:text>
    @endforelse

    <x-run-changes-modal :run="$this->changesRun" />
</section>
