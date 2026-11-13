<?php

use App\Jobs\EraseProject;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * « Récents », dans la barre de navigation : les sites et les discussions du client, du plus récent au plus ancien,
 * avec des filtres simples « Tous / Sites / Discussions », et « Renommer » ou « Supprimer » dans le menu « ⋯ ».
 */
new class extends Component {
    public const PAGE = 30;

    /** The project open on the page, highlighted in the list. */
    #[Locked]
    public ?string $current = null;

    /** « all », or one type of Project::TYPES (« site » counts the GitHub repositories too). */
    public string $filter = 'all';

    public int $limit = self::PAGE;

    public ?string $renamingId = null;

    public string $newName = '';

    public ?string $deletingId = null;

    public function mount(?string $current = null): void
    {
        $this->current = $current;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter === 'all' || array_key_exists($filter, Project::TYPES) ? $filter : 'all';
        $this->limit = self::PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    /**
     * Whether the client has at least one site or discussion (otherwise the section is not shown at all).
     */
    #[Computed]
    public function hasAny(): bool
    {
        return Auth::user()->projects()->exists();
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Auth::user()->projects()
            ->when($this->filter !== 'all', fn ($query) => $query->ofType($this->filter))
            ->latest('updated_at')
            ->limit($this->limit + 1)
            ->get();
    }

    /**
     * The filters: « Tous », then only the types the client really has (a type without project has no filter).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function filters(): array
    {
        $labels = ['site' => __('Sites'), 'application' => __('Applications'), 'agent' => __('Agents'), 'automation' => __('Automations'), 'document' => __('Documents'), 'presentation' => __('Presentations'), 'image' => __('Images'), 'chat' => __('Discussions')];
        $present = Auth::user()->projects()->get(['id', 'kind', 'document_type', 'is_application', 'is_agent', 'is_automation'])->map->typeKey()->unique()->all();

        return ['all' => __('All')] + array_intersect_key($labels, array_flip($present));
    }

    public function startRenaming(string $projectId): void
    {
        $project = $this->findProject($projectId);
        $this->resetErrorBag();
        $this->renamingId = $project->id;
        $this->newName = $project->name;

        Flux::modal('rename-recent')->show();
    }

    public function renameProject(): void
    {
        $validated = $this->validate(['newName' => ['required', 'string', 'max:100']]);

        $this->findProject((string) $this->renamingId)->update(['name' => trim($validated['newName'])]);
        $this->reset('renamingId', 'newName');
        unset($this->projects);

        Flux::modal('rename-recent')->close();
        // La page ouverte peut afficher ce nom : elle se met à jour.
        $this->dispatch('projet-renomme');
    }

    public function confirmDeletion(string $projectId): void
    {
        $this->deletingId = $this->findProject($projectId)->id;

        Flux::modal('delete-recent')->show();
    }

    /**
     * Delete the project awaiting confirmation, with its conversation; its computer and backup are erased for good.
     */
    public function deleteProject(): void
    {
        $project = $this->findProject((string) $this->deletingId);
        $project->delete();

        rescue(function () use ($project) {
            EraseProject::dispatch($project->id);
        }, report: true);

        $this->reset('deletingId');
        unset($this->projects, $this->hasAny);
        Flux::modal('delete-recent')->close();

        if ($project->id === $this->current) {
            $this->redirectRoute('projects.index', navigate: true);
        }
    }

    /**
     * Find a project among the current user's projects only.
     */
    protected function findProject(string $projectId): Project
    {
        return Auth::user()->projects()->findOrFail($projectId);
    }
}; ?>

<div class="-mx-2 flex min-h-0 flex-1 flex-col" data-recents>
    @if ($this->hasAny)
        <div class="flex items-center justify-between px-2 pb-1.5">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Recent') }}</p>
        </div>
        {{-- Les filtres défilent sur un téléphone quand ils ne tiennent pas, sans barre de défilement visible. --}}
        <div class="flex gap-1 overflow-x-auto px-2 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="{{ __('Show') }}" data-recents-filters>
            @foreach ($this->filters as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" data-recents-filter="{{ $key }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    @class([
                        'shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium transition',
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $filter === $key,
                        'text-zinc-600 hover:bg-zinc-200/70 dark:text-zinc-300 dark:hover:bg-zinc-800' => $filter !== $key,
                    ])>{{ $label }}</button>
            @endforeach
        </div>

        <ul class="min-h-0 flex-1 overflow-y-auto px-2" data-recents-list>
            @forelse ($this->projects->take($limit) as $project)
                <li wire:key="recent-{{ $project->id }}" class="group relative" data-recent="{{ $project->typeKey() }}">
                    <a href="{{ route('projects.show', $project) }}" wire:navigate
                        @class([
                            'flex items-center gap-2 rounded-lg py-1.5 pe-8 ps-2 text-sm transition',
                            'bg-zinc-200/70 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' => $project->id === $current,
                            'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-800/60' => $project->id !== $current,
                        ])
                        @if ($project->id === $current) aria-current="page" @endif>
                        {{-- Une icône par type : site, application, agent, automatisation, document, présentation, image, discussion. --}}
                        <flux:icon :name="$project->typeIcon()" variant="micro" class="shrink-0 text-zinc-400" data-recent-icon="{{ $project->typeIcon() }}" />
                        <span class="truncate">{{ $project->name }}</span>
                    </a>
                    <flux:dropdown position="bottom" align="end" class="absolute end-0.5 top-1/2 -translate-y-1/2">
                        <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $project->name])"
                            class="lg:opacity-0 lg:group-hover:opacity-100 lg:focus:opacity-100" data-recent-actions />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $project->id }}')" data-recent-rename>{{ __('Rename') }}</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $project->id }}')" data-recent-delete>{{ __('Delete') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @empty
                <li class="px-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-400" data-recents-none>{{ __('Nothing here yet.') }}</li>
            @endforelse
            @if ($this->projects->count() > $limit)
                <li><button type="button" wire:click="more" class="w-full rounded-lg px-2 py-1.5 text-start text-sm text-zinc-500 hover:bg-zinc-200/50 dark:text-zinc-400 dark:hover:bg-zinc-800/60" data-recents-more>{{ __('Show more') }}</button></li>
            @endif
        </ul>
    @endif

    <flux:modal name="rename-recent" class="w-full max-w-md">
        <form wire:submit="renameProject" class="space-y-5">
            <flux:heading size="lg">{{ __('Rename') }}</flux:heading>
            <flux:input wire:model="newName" :label="__('Name')" maxlength="100" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-recent" class="w-full max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Delete this project?') }}</flux:heading>
                <flux:subheading>{{ __('The project and its whole conversation will be permanently deleted.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="deleteProject" data-confirm-delete>{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
