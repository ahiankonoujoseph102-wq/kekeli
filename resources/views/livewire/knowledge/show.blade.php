<?php

use App\Knowledge\KnowledgeException;
use App\Knowledge\KnowledgeSpaces;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSpace;
use App\Models\Project;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * Un espace de la base de connaissances : ses documents (importer, remplacer, supprimer, état du traitement) et ses
 * conversations (une nouvelle question, sur tous les documents ou seulement certains ; les conversations précédentes).
 */
new class extends Component {
    use WithFileUploads;

    /** Files imported at once. */
    public const UPLOADS_MAX = 5;

    #[Locked]
    public string $spaceId = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    public $replacement = null;

    public ?int $replacingId = null;

    public ?int $deletingId = null;

    public ?string $deletingConversation = null;

    public string $question = '';

    /** @var list<string> */
    public array $chosen = [];

    public function mount(string $space): void
    {
        $this->spaceId = Auth::user()->knowledgeSpaces()->findOrFail($space)->id;
    }

    public function rendering($view): void
    {
        $view->title($this->space->name);
    }

    #[Computed]
    public function space(): KnowledgeSpace
    {
        return Auth::user()->knowledgeSpaces()->findOrFail($this->spaceId);
    }

    /** @return Collection<int, KnowledgeDocument> */
    #[Computed]
    public function documents(): Collection
    {
        return $this->space->documents()->latest('id')->get();
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function conversations(): Collection
    {
        return Auth::user()->projects()->where('knowledge_space_id', $this->spaceId)->latest('updated_at')->limit(50)->get();
    }

    private function rules(): array
    {
        $maxKb = app(Settings::class)->int('knowledge_file_max_kb');

        return ['file', 'extensions:'.implode(',', KnowledgeDocument::extensions()), 'max:'.$maxKb, function (string $attribute, mixed $value, \Closure $fail) {
            if ($value instanceof \Illuminate\Http\UploadedFile && ! KnowledgeSpaces::looksValid($value)) {
                $fail(__('This file does not match its format.'));
            }
        }];
    }

    public function updatedUploads(): void
    {
        $this->validate(['uploads' => ['array', 'max:'.self::UPLOADS_MAX], 'uploads.*' => $this->rules()], [], ['uploads.*' => __('file')]);
        $messages = [];
        foreach ($this->uploads as $upload) {
            $result = app(KnowledgeSpaces::class)->import($this->space, $upload);
            if ($result === 'double') {
                $messages[] = __('« :name » is already in this space: it is not imported twice.', ['name' => $upload->getClientOriginalName()]);
            } elseif ($result === 'plein') {
                $messages[] = __('This space already has the maximum number of documents.');
                break;
            }
        }
        $this->reset('uploads');
        $this->space->touch();
        unset($this->documents);
        if ($messages !== []) {
            $this->addError('uploads', implode(' ', $messages));
        }
    }

    public function startReplacing(int $id): void
    {
        $this->replacingId = $this->space->documents()->findOrFail($id)->id;
    }

    public function updatedReplacement(): void
    {
        $this->validate(['replacement' => $this->rules()], [], ['replacement' => __('file')]);
        $document = $this->space->documents()->findOrFail((int) $this->replacingId);
        $result = app(KnowledgeSpaces::class)->replace($document, $this->replacement);
        if ($result === 'identique') {
            $this->addError('uploads', __('This file is the same as the current one: nothing to do.'));
        } elseif ($result === 'double') {
            $this->addError('uploads', __('This file is already in this space.'));
        }
        $this->reset('replacement', 'replacingId');
        unset($this->documents);
    }

    public function retry(int $id): void
    {
        $document = $this->space->documents()->findOrFail($id);
        if ($document->status === KnowledgeDocument::FAILED) {
            app(KnowledgeSpaces::class)->retry($document);
        }
        unset($this->documents);
    }

    public function confirmDeletion(int $id): void
    {
        $this->deletingId = $this->space->documents()->findOrFail($id)->id;
        Flux::modal('document-supprimer')->show();
    }

    public function delete(): void
    {
        $this->space->documents()->findOrFail((int) $this->deletingId)->deleteWithFile();
        $this->reset('deletingId');
        $this->chosen = array_values(array_diff($this->chosen, [(string) $this->deletingId]));
        unset($this->documents);
        Flux::modal('document-supprimer')->close();
    }

    public function confirmConversationDeletion(string $id): void
    {
        $this->deletingConversation = Auth::user()->projects()->where('knowledge_space_id', $this->spaceId)->findOrFail($id)->id;
        Flux::modal('conversation-supprimer')->show();
    }

    public function deleteConversation(): void
    {
        Auth::user()->projects()->where('knowledge_space_id', $this->spaceId)->findOrFail((string) $this->deletingConversation)->delete();
        $this->reset('deletingConversation');
        unset($this->conversations);
        Flux::modal('conversation-supprimer')->close();
    }

    public function ask(): void
    {
        $this->question = trim($this->question);
        $this->validate(['question' => ['required', 'string', 'max:4000']], [], ['question' => __('question')]);
        if (! $this->space->documents()->where('status', KnowledgeDocument::READY)->exists()) {
            $this->addError('question', __('Import a document and wait until it is ready, then ask your question.'));

            return;
        }
        $ids = $this->chosen === [] ? null : array_map('intval', $this->chosen);
        $project = app(KnowledgeSpaces::class)->startConversation(Auth::user(), $this->space, $this->question, $ids);
        $this->space->touch();
        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? \App\Support\Formats::number($bytes / 1048576, 1).' Mo' : \App\Support\Formats::number(max(1, (int) round($bytes / 1024))).' Ko';
    }
}; ?>

@php($busy = $this->documents->contains(fn ($document) => $document->isBusy()))
@php($ready = $this->documents->where('status', \App\Models\KnowledgeDocument::READY))
<section class="mx-auto flex w-full max-w-3xl flex-col gap-6" data-page-knowledge-space>
    <header class="flex flex-col gap-1">
        <a href="{{ route('knowledge.index') }}" wire:navigate.hover class="kekeli-lien flex w-fit items-center gap-1 text-sm" data-knowledge-back>
            <flux:icon.chevron-left variant="micro" />{{ __('Knowledge base') }}
        </a>
        <flux:heading size="xl" level="1" class="truncate">{{ $this->space->name }}</flux:heading>
        <flux:text>{{ __('Kekeli answers only from the documents of this space, and cites them.') }}</flux:text>
    </header>

    {{-- Une nouvelle conversation : la question, et les documents à utiliser (tous par défaut). --}}
    <form wire:submit="ask" class="kekeli-carte flex flex-col gap-3" data-knowledge-ask>
        <flux:heading size="lg" level="2">{{ __('Ask a question') }}</flux:heading>
        <flux:textarea wire:model="question" rows="3" :placeholder="__('Ask a question about your documents…')" :aria-label="__('Your question')" data-knowledge-question />
        @if ($ready->count() > 1)
            <fieldset class="flex flex-col gap-1" data-knowledge-choice>
                <legend class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Documents to use (all of them if none is ticked)') }}</legend>
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($ready as $document)
                        <label wire:key="choix-{{ $document->id }}" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-full border border-zinc-200 px-3 text-sm text-zinc-700 transition-colors has-[:checked]:border-zinc-900 has-[:checked]:text-zinc-900 dark:border-zinc-700 dark:text-zinc-200 dark:has-[:checked]:border-zinc-100 dark:has-[:checked]:text-white">
                            <input type="checkbox" wire:model="chosen" value="{{ $document->id }}" class="size-4 accent-zinc-900 dark:accent-zinc-100" data-knowledge-choose="{{ $document->id }}">
                            <span class="max-w-56 truncate">{{ $document->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="paper-airplane" :disabled="$ready->isEmpty()" data-knowledge-ask-button>{{ __('Ask Kekeli') }}</flux:button>
        </div>
    </form>

    {{-- Les documents, avec l'état de leur traitement ; la liste se met à jour seule tant qu'un document est en cours. --}}
    <div class="flex flex-col gap-3" x-data @if ($busy) wire:poll.3s @endif data-knowledge-documents>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg" level="2">{{ __('Documents') }}</flux:heading>
            <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full bg-zinc-900 px-4 text-sm font-medium text-white transition-colors hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white" data-knowledge-import>
                <flux:icon.arrow-up-tray variant="micro" />
                <span wire:loading.remove wire:target="uploads">{{ __('Import a document') }}</span>
                <span wire:loading wire:target="uploads">{{ __('Importing…') }}</span>
                <input type="file" multiple class="sr-only" wire:model="uploads" accept="{{ collect(\App\Models\KnowledgeDocument::extensions())->map(fn ($e) => '.'.$e)->implode(',') }}" data-knowledge-file>
            </label>
            <input type="file" class="sr-only" x-ref="remplacer" wire:model="replacement" accept="{{ collect(\App\Models\KnowledgeDocument::extensions())->map(fn ($e) => '.'.$e)->implode(',') }}" data-knowledge-replace-file tabindex="-1" aria-hidden="true">
        </div>
        <p class="text-xs text-zinc-500">{{ __('PDF, Word, PowerPoint, text, Markdown, CSV, JSON or HTML, up to :size MB each.', ['size' => intdiv(app(\App\Support\Settings::class)->int('knowledge_file_max_kb'), 1024)]) }}</p>
        @error('uploads') <p class="kekeli-erreur" data-knowledge-error>{{ $message }}</p> @enderror
        @error('uploads.*') <p class="kekeli-erreur" data-knowledge-error>{{ $message }}</p> @enderror
        @error('replacement') <p class="kekeli-erreur" data-knowledge-error>{{ $message }}</p> @enderror

        @if ($this->documents->isEmpty())
            <div class="kekeli-carte kekeli-vide" data-knowledge-documents-empty>
                <span class="kekeli-vide-icone"><flux:icon.document-text variant="mini" /></span>
                <p class="kekeli-vide-titre">{{ __('No document yet') }}</p>
                <p>{{ __('Import your price lists, procedures, courses or contracts: Kekeli reads them and answers your questions.') }}</p>
            </div>
        @else
            <ul class="kekeli-carte flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-700">
                @foreach ($this->documents as $document)
                    <li wire:key="document-{{ $document->id }}" class="flex items-start gap-3 px-4 py-3" data-knowledge-document="{{ $document->status }}">
                        <flux:icon.document-text variant="mini" class="mt-0.5 shrink-0 text-zinc-400" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $document->name }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500 tabular-nums">
                                <span>{{ $document->label() }} · {{ $this::size($document->size) }}@if ($document->isReady() && $document->pages > 0) · {{ trans_choice('{1} :count page|[2,*] :count pages', $document->pages, ['count' => $document->pages]) }}@endif</span>
                                @switch($document->status)
                                    @case(\App\Models\KnowledgeDocument::READY)
                                        <span class="kekeli-pastille kekeli-pastille-succes" data-knowledge-status="ready">{{ __('Ready') }}</span>
                                        @break
                                    @case(\App\Models\KnowledgeDocument::PROCESSING)
                                        <span class="kekeli-pastille kekeli-scintille" data-knowledge-status="processing">{{ __('Processing…') }}</span>
                                        @break
                                    @case(\App\Models\KnowledgeDocument::FAILED)
                                        <span class="kekeli-pastille" data-knowledge-status="failed">{{ __('Error') }}</span>
                                        @break
                                    @default
                                        <span class="kekeli-pastille" data-knowledge-status="pending">{{ __('Waiting') }}</span>
                                @endswitch
                            </p>
                            @if ($document->status === \App\Models\KnowledgeDocument::FAILED)
                                <p class="kekeli-erreur mt-1 text-xs" data-knowledge-failure>{{ KnowledgeException::explain($document->error) }}</p>
                            @endif
                        </div>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $document->name])" data-knowledge-document-actions />
                            <flux:menu>
                                @if ($document->status === \App\Models\KnowledgeDocument::FAILED)
                                    <flux:menu.item icon="arrow-path" wire:click="retry({{ $document->id }})" data-knowledge-retry>{{ __('Try again') }}</flux:menu.item>
                                @endif
                                <flux:menu.item icon="arrow-up-tray" x-on:click="$wire.startReplacing({{ $document->id }}).then(() => $refs.remplacer.click())" data-knowledge-replace>{{ __('Replace') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion({{ $document->id }})" data-knowledge-delete>{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($this->conversations->isNotEmpty())
        <div class="flex flex-col gap-3" data-knowledge-conversations>
            <flux:heading size="lg" level="2">{{ __('Conversations') }}</flux:heading>
            <ul class="kekeli-carte flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-700">
                @foreach ($this->conversations as $conversation)
                    <li wire:key="conversation-{{ $conversation->id }}" class="flex items-center gap-2 px-4 py-1" data-knowledge-conversation>
                        <a href="{{ route('projects.show', $conversation) }}" wire:navigate.hover class="flex min-h-11 min-w-0 flex-1 items-center gap-2 text-sm text-zinc-800 hover:text-zinc-950 dark:text-zinc-200 dark:hover:text-white">
                            <flux:icon.chat-bubble-left variant="mini" class="shrink-0 text-zinc-400" />
                            <span class="truncate">{{ $conversation->name }}</span>
                            <span class="ms-auto shrink-0 text-xs text-zinc-500">{{ \App\Support\Formats::short($conversation->updated_at) }}</span>
                        </a>
                        <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmConversationDeletion('{{ $conversation->id }}')" :aria-label="__('Delete :name', ['name' => $conversation->name])" data-knowledge-conversation-delete />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <flux:modal name="document-supprimer" class="w-full max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Delete this document?') }}</flux:heading>
            <flux:text>{{ __('The file and its index will be deleted for good. Kekeli will no longer use it in its answers.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-knowledge-delete-confirm>{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="conversation-supprimer" class="w-full max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Delete this conversation?') }}</flux:heading>
            <flux:text>{{ __('Its messages will be deleted for good. The documents stay in the space.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="deleteConversation" data-knowledge-conversation-delete-confirm>{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
