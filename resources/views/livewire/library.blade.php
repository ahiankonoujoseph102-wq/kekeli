<?php

use App\Livewire\Concerns\ManagesCreations;
use App\Models\DocumentVersion;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/*
 * « Bibliothèque », comme la bibliothèque d'images de ChatGPT et les fichiers de Manus : les documents, présentations,
 * images et vidéos du client, en grille avec l'aperçu de la première page, des filtres par type et le téléchargement
 * direct du fichier principal (les autres formats dans « ⋯ »).
 */
new class extends Component {
    use ManagesCreations;

    #[Url(as: 'type', except: 'all')]
    public string $type = 'all';

    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** Main format of each type, downloaded in one touch. */
    public const MAIN_FORMATS = ['docx', 'pptx', 'png', 'mp4', 'pdf'];

    public function rendering($view): void
    {
        $view->title(__('Library'));
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, Project::LIBRARY_TYPES, true) ? $type : 'all';
    }

    protected function modalPrefix(): string
    {
        return 'bibliotheque';
    }

    #[On('projet-renomme')]
    public function forgetLists(): void
    {
        unset($this->items, $this->types);
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function items(): Collection
    {
        $search = trim($this->query);

        return Auth::user()->projects()->inLibrary()
            ->with('latestDocumentVersion')
            ->when($this->type !== 'all', fn ($q) => $q->ofType($this->type))
            ->when($search !== '', fn ($q) => $q->whereLike('name', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function types(): array
    {
        $present = Auth::user()->projects()->inLibrary()->get(['id', 'kind', 'document_type'])->map->typeKey()->unique()->all();

        return ['all' => __('All')] + array_intersect_key(Project::typeLabels(), array_flip($present));
    }

    public static function mainFormat(DocumentVersion $version): ?string
    {
        $formats = array_keys($version->formats());

        return collect(self::MAIN_FORMATS)->first(fn ($f) => in_array($f, $formats, true));
    }
}; ?>

<section class="mx-auto flex w-full max-w-5xl flex-col gap-5" data-page-library>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Library') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your documents, presentations, images and videos.') }}</flux:text>
        </div>
    </header>

    @if (count($this->types) > 1 || $query !== '')
        {{-- La recherche, puis les filtres en onglets soulignés (audit unslop, passe 7). --}}
        <div class="flex flex-col gap-3">
            <flux:input wire:model.live.debounce.300ms="query" icon="magnifying-glass" :placeholder="__('Search a file')" :aria-label="__('Search a file')" class="sm:max-w-xs" clearable data-library-search />
            <div class="kekeli-defile -mx-1 flex min-w-0 gap-1 overflow-x-auto border-b border-zinc-200 px-1 dark:border-zinc-700 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" x-data="{ debut: false, suite: false, voir() { this.debut = $el.scrollLeft > 4; this.suite = $el.scrollLeft + $el.clientWidth < $el.scrollWidth - 4 } }" x-init="$nextTick(() => voir())" x-on:scroll.passive="voir()" x-on:resize.window="voir()" x-bind:data-debut="debut" x-bind:data-suite="suite" role="group" aria-label="{{ __('Show') }}" data-library-filters>
                @foreach ($this->types as $key => $label)
                    <button type="button" wire:click="setType('{{ $key }}')" aria-pressed="{{ $type === $key ? 'true' : 'false' }}" data-library-filter="{{ $key }}"
                        @class([
                            '-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                            'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-white' => $type === $key,
                            'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $type !== $key,
                        ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->items->isEmpty())
        <div class="kekeli-carte kekeli-vide" data-library-empty>
            <span class="kekeli-vide-icone"><flux:icon.photo variant="mini" /></span>
            @if ($query !== '' || $type !== 'all')
                <p class="kekeli-vide-titre">{{ __('No file matches.') }}</p>
                <flux:button size="sm" variant="ghost" wire:click="$set('query', ''); $set('type', 'all')">{{ __('Show all the files') }}</flux:button>
            @else
                <p class="kekeli-vide-titre">{{ __('Your library is empty') }}</p>
                <p>{{ __('Ask Kekeli for a document, a presentation, a poster or a video: it will appear here.') }}</p>
                <flux:button size="sm" variant="primary" :href="route('projects.index')" wire:navigate>{{ __('New discussion') }}</flux:button>
            @endif
        </div>
    @else
        <ul class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 lg:grid-cols-4" wire:loading.class="opacity-60" wire:target="query, type, setType" data-library-list>
            @foreach ($this->items as $item)
                @php($version = $item->latestDocumentVersion)
                @php($main = $version ? $this::mainFormat($version) : null)
                <li wire:key="fichier-{{ $item->id }}" class="kekeli-carte kekeli-apparition relative flex flex-col overflow-hidden transition hover:border-zinc-300 dark:hover:border-zinc-600" data-library-item="{{ $item->typeKey() }}">
                    <a href="{{ route('projects.show', $item) }}" wire:navigate class="block" aria-label="{{ __('Open :name', ['name' => $item->name]) }}">
                        {{-- L'aperçu reste sur papier blanc, même en mode sombre, comme dans le projet. --}}
                        <span class="relative block aspect-[4/5] overflow-hidden border-b border-zinc-200 bg-white dark:border-zinc-700">
                            @if ($version && count((array) $version->previews) > 0)
                                <img src="{{ route('projects.versions.page', [$item, $version->number, 1]) }}" alt="" loading="lazy" decoding="async" @class(['size-full', 'object-contain' => $item->isPresentation(), 'object-cover object-top' => ! $item->isPresentation()]) data-library-preview>
                            @else
                                <span class="flex size-full items-center justify-center text-zinc-400"><flux:icon :name="$item->typeIcon()" class="size-8" /></span>
                            @endif
                            @if ($item->isVideo())
                                <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                    <span class="flex size-10 items-center justify-center rounded-full bg-zinc-900/70 text-white"><flux:icon.play variant="mini" /></span>
                                </span>
                            @endif
                        </span>
                    </a>
                    <div class="flex flex-col gap-1 p-3 pb-2">
                        <a href="{{ route('projects.show', $item) }}" wire:navigate class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $item->name }}</a>
                        {{-- Sur un téléphone, le nom prend toute la largeur ; le type et les boutons passent dessous. --}}
                        <div class="-me-1.5 flex items-center gap-1">
                            <span class="flex min-w-0 flex-1 items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <flux:icon :name="$item->typeIcon()" class="size-3.5 shrink-0" data-library-icon="{{ $item->typeIcon() }}" />
                                <span class="truncate">{{ $item->typeLabel() }}<span class="max-sm:hidden"> · {{ $item->updated_at->locale('fr')->diffForHumans(short: true) }}</span></span>
                            </span>
                            @if ($main)
                                <flux:button variant="ghost" size="xs" icon="arrow-down-tray" :href="route('projects.versions.download', [$item, $version->number, $main])" :aria-label="__('Download :name', ['name' => $item->name])" data-library-download="{{ $main }}" />
                            @endif
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $item->name])" data-library-actions />
                                <flux:menu>
                                    @foreach ($version?->formats() ?? [] as $format => $label)
                                        @continue($format === $main)
                                        <flux:menu.item icon="arrow-down-tray" :href="route('projects.versions.download', [$item, $version->number, $format])">{{ __('Download in :format', ['format' => $label]) }}</flux:menu.item>
                                    @endforeach
                                    <flux:menu.item icon="pencil-square" wire:click="startRenaming('{{ $item->id }}')">{{ __('Rename') }}</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletion('{{ $item->id }}')">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-creation-modals prefix="bibliotheque" />
</section>
