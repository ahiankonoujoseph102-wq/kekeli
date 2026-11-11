<?php

use App\Jobs\EraseProject;
use App\Models\Project;
use App\WebApps\NewSite;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $renamingId = null;

    public string $newName = '';

    public ?string $deletingId = null;

    /**
     * Text typed on the welcome page before creating an account: Kekeli starts on it right away.
     */
    public function mount(): void
    {
        $pending = trim((string) session()->pull(NewSite::PENDING, ''));
        if (mb_strlen($pending) >= 10) {
            $project = app(NewSite::class)->create(Auth::user(), $pending);
            $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
        }
    }

    /**
     * Get the current user's projects, most recently updated first.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Auth::user()->projects()->latest('updated_at')->get();
    }

    /**
     * Render the page with a translated title.
     */
    public function rendering($view): void
    {
        $view->title(__('My projects'));
    }

    /**
     * Open the rename dialog for one of the user's projects.
     */
    public function startRenaming(string $projectId): void
    {
        $project = $this->findProject($projectId);

        $this->resetErrorBag();
        $this->renamingId = $project->id;
        $this->newName = $project->name;

        Flux::modal('rename-project')->show();
    }

    /**
     * Save the new name of the project being renamed.
     */
    public function renameProject(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:100'],
        ]);

        $this->findProject((string) $this->renamingId)->update(['name' => trim($validated['newName'])]);

        $this->reset('renamingId', 'newName');
        unset($this->projects);

        Flux::modal('rename-project')->close();
    }

    /**
     * Open the delete confirmation for one of the user's projects.
     */
    public function confirmDeletion(string $projectId): void
    {
        $this->deletingId = $this->findProject($projectId)->id;

        Flux::modal('delete-project')->show();
    }

    /**
     * Delete the project awaiting confirmation, with its conversation.
     */
    public function deleteProject(): void
    {
        $project = $this->findProject((string) $this->deletingId);
        $project->delete();

        // Son ordinateur et sa sauvegarde sont effacés pour de bon.
        rescue(function () use ($project) {
            EraseProject::dispatch($project->id);
        }, report: true);

        $this->reset('deletingId');
        unset($this->projects);

        Flux::modal('delete-project')->close();
    }

    /**
     * Find a project among the current user's projects only.
     */
    protected function findProject(string $projectId): Project
    {
        return Auth::user()->projects()->findOrFail($projectId);
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-10">
    <div class="pt-2 sm:pt-8">
        <livewire:site-prompt />
    </div>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg" level="1">{{ __('My projects') }}</flux:heading>

    @if ($this->projects->isEmpty())
        <p class="rounded-xl border border-dashed border-zinc-300 px-6 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700" data-no-project>{{ __('Your sites will appear here.') }}</p>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($this->projects as $project)
                <li wire:key="project-{{ $project->id }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <a href="{{ route('projects.show', $project) }}" class="min-w-0 flex-1" wire:navigate>
                        <span class="block truncate font-semibold text-zinc-900 dark:text-white">{{ $project->name }}</span>
                        <span class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-project-state>
                            @if ($project->isRepository())
                                <span data-project-kind="repository">{{ __('GitHub repository') }}</span>
                            @elseif ($project->published_url)
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>{{ __('Online') }}
                            @else
                                <span class="size-1.5 rounded-full bg-zinc-400"></span>{{ __('Draft') }}
                            @endif
                            <span aria-hidden="true">·</span>
                            <span>{{ __('Updated :time', ['time' => $project->updated_at->diffForHumans(options: \Carbon\CarbonInterface::JUST_NOW)]) }}</span>
                        </span>

                    </a>

                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('Project actions')" />

                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $project->id }}')">{{ __('Rename') }}</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $project->id }}')">{{ __('Delete') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @endforeach
        </ul>
    @endif
    </div>

    <flux:modal name="rename-project" class="w-full max-w-lg">
        <form wire:submit="renameProject" class="space-y-6">
            <flux:heading size="lg">{{ __('Rename project') }}</flux:heading>

            <flux:input wire:model="newName" :label="__('Project name')" maxlength="100" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-project" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this project?') }}</flux:heading>
                <flux:subheading>{{ __('The project and its whole conversation will be permanently deleted.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteProject">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
