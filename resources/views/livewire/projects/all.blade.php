<?php

use App\Livewire\Concerns\ManagesCreations;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/*
 * « Projets », comme le tableau de bord de Lovable et de Bolt.new : les sites, applications, agents et automatisations
 * du client, avec des filtres par type (seulement les types qu'il a), une recherche et un tri (récents d'abord).
 * « Renommer » et « Supprimer » dans le menu « ⋯ » de chaque projet.
 */
new class extends Component {
    use ManagesCreations;

    #[Url(as: 'type', except: 'all')]
    public string $type = 'all';

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'tri', except: 'recent')]
    public string $sort = 'recent';

    public function rendering($view): void
    {
        $view->title(__('Projects'));
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, Project::PROJECT_TYPES, true) ? $type : 'all';
    }

    protected function modalPrefix(): string
    {
        return 'projets';
    }

    #[On('projet-renomme')]
    public function forgetLists(): void
    {
        unset($this->projects, $this->types);
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function projects(): Collection
    {
        $search = trim($this->query);

        return Auth::user()->projects()->inProjects()
            ->when($this->type !== 'all', fn ($q) => $q->ofType($this->type))
            ->when($search !== '', fn ($q) => $q->whereLike('name', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'), fn ($q) => $q->orderBy('updated_at', $this->sort === 'old' ? 'asc' : 'desc'))
            ->limit(200)
            ->get();
    }

    /**
     * « Tous », then only the types the client really has.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function types(): array
    {
        $present = Auth::user()->projects()->inProjects()->get(['id', 'kind', 'document_type', 'is_application', 'is_agent', 'is_automation'])->map->typeKey()->unique()->all();

        return ['all' => __('All')] + array_intersect_key(Project::typeLabels(), array_flip($present));
    }
}; ?>

<section class="mx-auto flex w-full max-w-5xl flex-col gap-5" data-page-projects>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Projects') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your sites, applications, agents and automations.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('projects.index')" wire:navigate data-new-from-projects>{{ __('New discussion') }}</flux:button>
    </header>

    @if (count($this->types) > 1 || $query !== '')
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:input wire:model.live.debounce.300ms="query" icon="magnifying-glass" :placeholder="__('Search a project')" :aria-label="__('Search a project')" class="sm:max-w-xs" clearable data-projects-search />
            <div class="flex min-w-0 flex-1 gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="{{ __('Show') }}" data-projects-filters>
                @foreach ($this->types as $key => $label)
                    <button type="button" wire:click="setType('{{ $key }}')" aria-pressed="{{ $type === $key ? 'true' : 'false' }}" data-projects-filter="{{ $key }}"
                        @class([
                            'shrink-0 whitespace-nowrap rounded-full border px-3 py-1.5 text-sm font-medium transition',
                            'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $type === $key,
                            'border-zinc-200 text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-white' => $type !== $key,
                        ])>{{ $label }}</button>
                @endforeach
            </div>
            <flux:select wire:model.live="sort" size="sm" class="sm:w-44" :aria-label="__('Sort')" data-projects-sort>
                <flux:select.option value="recent">{{ __('Most recent first') }}</flux:select.option>
                <flux:select.option value="old">{{ __('Oldest first') }}</flux:select.option>
                <flux:select.option value="name">{{ __('Name, A to Z') }}</flux:select.option>
            </flux:select>
        </div>
    @endif

    @if ($this->projects->isEmpty())
        <div class="kekeli-carte kekeli-vide" data-projects-empty>
            <span class="kekeli-vide-icone"><flux:icon.window variant="mini" /></span>
            @if ($query !== '' || $type !== 'all')
                <p class="kekeli-vide-titre">{{ __('No project matches.') }}</p>
                <flux:button size="sm" variant="ghost" wire:click="$set('query', ''); $set('type', 'all')">{{ __('Show all the projects') }}</flux:button>
            @else
                <p class="kekeli-vide-titre">{{ __('No project yet') }}</p>
                <p>{{ __('Ask Kekeli for a site, an application, an agent or an automation: it will appear here.') }}</p>
                <flux:button size="sm" variant="primary" :href="route('projects.index')" wire:navigate>{{ __('New discussion') }}</flux:button>
            @endif
        </div>
    @else
        <ul class="grid gap-3 transition-opacity sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60" wire:target="query, type, sort, setType" data-projects-list>
            @foreach ($this->projects as $project)
                <li wire:key="projet-{{ $project->id }}" class="kekeli-carte kekeli-apparition group relative transition hover:border-zinc-300 dark:hover:border-zinc-600" data-project-card="{{ $project->typeKey() }}">
                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex items-start gap-3 rounded-xl p-4 pe-12">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <flux:icon :name="$project->typeIcon()" variant="mini" data-project-icon="{{ $project->typeIcon() }}" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $project->name }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $project->typeLabel() }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $project->updated_at->locale('fr')->diffForHumans() }}</span>
                            </span>
                            @if ($project->published_url)
                                <span class="kekeli-pastille kekeli-pastille-lumiere mt-2">{{ __('Online') }}</span>
                            @endif
                        </span>
                    </a>
                    <flux:dropdown position="bottom" align="end" class="absolute end-2 top-3">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $project->name])" data-project-actions />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $project->id }}')">{{ __('Rename') }}</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $project->id }}')">{{ __('Delete') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @endforeach
        </ul>
    @endif

    <x-creation-modals prefix="projets" />
</section>
