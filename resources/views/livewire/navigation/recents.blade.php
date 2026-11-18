<?php

use App\Livewire\Concerns\ManagesCreations;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/*
 * « Récents », dans la barre de navigation, comme Claude et ChatGPT : les dernières discussions du client seulement
 * (les projets ont leur page « Projets », les fichiers leur « Bibliothèque »), du plus récent au plus ancien, avec
 * « Renommer » ou « Supprimer » dans le menu « ⋯ ».
 */
new class extends Component {
    use ManagesCreations;

    public const PAGE = 30;

    /** The page open, highlighted in the list. */
    #[Locked]
    public ?string $current = null;

    public int $limit = self::PAGE;

    public function mount(?string $current = null): void
    {
        $this->current = $current;
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    protected function modalPrefix(): string
    {
        return 'recents';
    }

    #[On('projet-renomme')]
    public function forgetLists(): void
    {
        unset($this->projects);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Auth::user()->projects()
            ->ofType('chat')
            // Les conversations de la base de connaissances restent dans leur espace, comme les projets de ChatGPT.
            ->whereNull('knowledge_space_id')
            ->latest('updated_at')
            ->limit($this->limit + 1)
            ->get();
    }
}; ?>

<div class="-mx-2 flex min-h-0 flex-1 flex-col" data-recents>
    @if ($this->projects->isNotEmpty())
        <p class="px-4 pt-3 pb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Recent') }}</p>

        <ul class="min-h-0 flex-1 overflow-y-auto px-2" data-recents-list>
            @foreach ($this->projects->take($limit) as $project)
                <li wire:key="recent-{{ $project->id }}" class="group relative" data-recent="{{ $project->typeKey() }}">
                    <a href="{{ route('projects.show', $project) }}" wire:navigate.hover
                        @class([
                            'block truncate rounded-lg py-1.5 pe-8 ps-2 text-sm transition-colors',
                            'bg-zinc-200/70 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' => $project->id === $current,
                            'text-zinc-700 hover:bg-zinc-200/50 dark:text-zinc-300 dark:hover:bg-zinc-800/60' => $project->id !== $current,
                        ])
                        @if ($project->id === $current) aria-current="page" @endif>{{ $project->name }}</a>
                    <flux:dropdown position="bottom" align="end" class="absolute end-0.5 top-1/2 -translate-y-1/2">
                        <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $project->name])"
                            class="lg:opacity-0 lg:group-hover:opacity-100 lg:focus:opacity-100" data-recent-actions />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $project->id }}')" data-recent-rename>{{ __('Rename') }}</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $project->id }}')" data-recent-delete>{{ __('Delete') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @endforeach
            @if ($this->projects->count() > $limit)
                <li><button type="button" wire:click="more" class="w-full rounded-lg px-2 py-1.5 text-start text-sm text-zinc-500 hover:bg-zinc-200/50 dark:text-zinc-400 dark:hover:bg-zinc-800/60" data-recents-more>{{ __('Show more') }}</button></li>
            @endif
        </ul>
    @endif

    <x-creation-modals prefix="recents" />
</div>
