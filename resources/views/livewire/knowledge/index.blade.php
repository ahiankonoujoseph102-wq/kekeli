<?php

use App\Knowledge\KnowledgeSpaces;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSpace;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/*
 * « Base de connaissances » (18 novembre 2026) : les espaces du client (Entreprise, Formation, Comptabilité…), chacun
 * avec ses propres documents et ses propres conversations, comme les projets de Claude ou de ChatGPT.
 */
new class extends Component {
    public string $name = '';

    public ?string $renamingId = null;

    public string $newName = '';

    public ?string $deletingId = null;

    public function rendering($view): void
    {
        $view->title(__('Knowledge base'));
    }

    /** @return Collection<int, KnowledgeSpace> */
    #[Computed]
    public function spaces(): Collection
    {
        return Auth::user()->knowledgeSpaces()
            ->withCount(['documents', 'conversations', 'documents as ready_count' => fn ($q) => $q->where('status', KnowledgeDocument::READY)])
            ->latest('updated_at')
            ->get();
    }

    public function suggest(string $name): void
    {
        $this->name = $name;
        $this->create();
    }

    public function create(): void
    {
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:80']], [], ['name' => __('name')]);
        if (Auth::user()->knowledgeSpaces()->count() >= app(Settings::class)->int('knowledge_spaces_max')) {
            $this->addError('name', __('You have reached the number of spaces allowed. Delete a space you no longer use.'));

            return;
        }
        $space = Auth::user()->knowledgeSpaces()->create(['name' => $this->name]);
        $this->reset('name');
        $this->redirectRoute('knowledge.show', ['space' => $space], navigate: true);
    }

    public function startRenaming(string $id): void
    {
        $space = Auth::user()->knowledgeSpaces()->findOrFail($id);
        $this->renamingId = $space->id;
        $this->newName = $space->name;
        Flux::modal('espace-renommer')->show();
    }

    public function rename(): void
    {
        $this->newName = trim($this->newName);
        $this->validate(['newName' => ['required', 'string', 'max:80']], [], ['newName' => __('name')]);
        Auth::user()->knowledgeSpaces()->findOrFail((string) $this->renamingId)->update(['name' => $this->newName]);
        $this->reset('renamingId', 'newName');
        unset($this->spaces);
        Flux::modal('espace-renommer')->close();
    }

    public function confirmDeletion(string $id): void
    {
        $this->deletingId = Auth::user()->knowledgeSpaces()->findOrFail($id)->id;
        Flux::modal('espace-supprimer')->show();
    }

    public function delete(): void
    {
        app(KnowledgeSpaces::class)->deleteSpace(Auth::user()->knowledgeSpaces()->findOrFail((string) $this->deletingId));
        $this->reset('deletingId');
        unset($this->spaces);
        Flux::modal('espace-supprimer')->close();
    }
}; ?>

<section class="mx-auto flex w-full max-w-5xl flex-col gap-5" data-page-knowledge>
    <header class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Knowledge base') }}</flux:heading>
        <flux:text>{{ __('Import your documents, then ask Kekeli questions: it answers from their content and cites the document and the page.') }}</flux:text>
    </header>

    <form wire:submit="create" class="kekeli-carte flex flex-col gap-3" data-knowledge-create>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
            <flux:input wire:model="name" :label="__('New space')" :placeholder="__('For example: Company, Training…')" maxlength="80" class="flex-1" data-knowledge-name />
            <flux:button type="submit" variant="primary" icon="plus" class="sm:mt-6" data-knowledge-create-button>{{ __('Create the space') }}</flux:button>
        </div>
        @if ($this->spaces->isEmpty())
            <div class="kekeli-defile -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-knowledge-suggestions>
                @foreach (\App\Models\KnowledgeSpace::SUGGESTIONS as $suggestion)
                    <button type="button" wire:click="suggest(@js(__($suggestion)))" class="kekeli-idee min-h-10 shrink-0 rounded-full border border-zinc-200 px-3 text-sm text-zinc-700 transition-colors hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-200" data-knowledge-suggestion>{{ __($suggestion) }}</button>
                @endforeach
            </div>
        @endif
    </form>

    @if ($this->spaces->isEmpty())
        <div class="kekeli-carte kekeli-vide" data-knowledge-empty>
            <span class="kekeli-vide-icone"><flux:icon.book-open variant="mini" /></span>
            <p class="kekeli-vide-titre">{{ __('No space yet') }}</p>
            <p>{{ __('Create a space, for example « Company », then import your price lists, procedures or courses.') }}</p>
        </div>
    @else
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-knowledge-spaces>
            @foreach ($this->spaces as $space)
                <li wire:key="espace-{{ $space->id }}" class="kekeli-carte kekeli-apparition relative flex flex-col gap-2 transition-colors hover:border-zinc-300 dark:hover:border-zinc-600" data-knowledge-space>
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ route('knowledge.show', $space) }}" wire:navigate.hover class="min-w-0 flex-1 after:absolute after:inset-0" data-knowledge-space-link>
                            <span class="flex items-center gap-2">
                                <flux:icon.book-open variant="mini" class="shrink-0 text-zinc-400" />
                                <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $space->name }}</span>
                            </span>
                        </a>
                        <flux:dropdown position="bottom" align="end" class="relative z-10">
                            <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $space->name])" data-knowledge-space-actions />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $space->id }}')">{{ __('Rename') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $space->id }}')">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                    <p class="text-sm text-zinc-500 tabular-nums">
                        {{ trans_choice('{0} No document|{1} :count document|[2,*] :count documents', $space->documents_count, ['count' => $space->documents_count]) }}
                        · {{ trans_choice('{0} no conversation|{1} :count conversation|[2,*] :count conversations', $space->conversations_count, ['count' => $space->conversations_count]) }}
                    </p>
                    <p class="text-xs text-zinc-500">{{ __('Updated on :date', ['date' => \App\Support\Formats::date($space->updated_at)]) }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="espace-renommer" class="w-full max-w-md">
        <form wire:submit="rename" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Rename the space') }}</flux:heading>
            <flux:input wire:model="newName" :label="__('Name')" maxlength="80" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="espace-supprimer" class="w-full max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Delete this space?') }}</flux:heading>
            <flux:text>{{ __('Its documents, their index and its conversations will be deleted for good.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-knowledge-delete-confirm>{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
