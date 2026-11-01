<?php

use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';

    public string $description = '';

    public ?string $renamingId = null;

    public string $newName = '';

    public ?string $deletingId = null;

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
     * Create a new project and open it.
     */
    public function createProject(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = Auth::user()->projects()->create([
            'name' => trim($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
        ]);

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
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
        $this->findProject((string) $this->deletingId)->delete();

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

<section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('My projects') }}</flux:heading>
            <flux:subheading>{{ __('Describe your project, Kekeli will build it for you.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="create-project">
            <flux:button variant="primary" icon="plus">{{ __('New project') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->projects->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
            <x-app-logo-icon class="size-10 text-amber-500" />
            <flux:heading size="lg">{{ __('No project yet') }}</flux:heading>
            <flux:text>{{ __('Create your first project to get started.') }}</flux:text>
            <flux:modal.trigger name="create-project">
                <flux:button variant="primary" icon="plus" class="mt-2">{{ __('Create my first project') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($this->projects as $project)
                <li wire:key="project-{{ $project->id }}" class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <a href="{{ route('projects.show', $project) }}" class="min-w-0 flex-1" wire:navigate>
                        <span class="block truncate font-semibold text-zinc-900 dark:text-white">{{ $project->name }}</span>
                        @if ($project->description)
                            <span class="mt-1 line-clamp-2 block text-sm text-zinc-600 dark:text-zinc-400">{{ $project->description }}</span>
                        @endif
                        <span class="mt-2 block text-xs text-zinc-500">
                            {{ __('Updated :time', ['time' => $project->updated_at->diffForHumans(options: \Carbon\CarbonInterface::JUST_NOW)]) }}
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

    <flux:modal name="create-project" class="w-full max-w-lg">
        <form wire:submit="createProject" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New project') }}</flux:heading>
                <flux:subheading>{{ __('Give your project a name. You can describe it in a few words.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Project name')" :placeholder="__('Example: Website for my restaurant')" maxlength="100" required />

            <flux:textarea wire:model="description" :label="__('Description (optional)')" :placeholder="__('Example: A modern website with the menu, the opening hours and a map.')" rows="3" maxlength="1000" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create project') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="rename-project" class="w-full max-w-lg">
        <form wire:submit="renameProject" class="space-y-6">
            <flux:heading size="lg">{{ __('Rename project') }}</flux:heading>

            <flux:input wire:model="newName" :label="__('Project name')" maxlength="100" required />

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
