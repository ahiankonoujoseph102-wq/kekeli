<?php

use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/*
 * « Rechercher », dans la barre de navigation, comme Claude ou ChatGPT : cherche dans les titres et dans le contenu des
 * messages des projets du client (jamais ceux des autres), sans distinguer majuscules et minuscules.
 */
new class extends Component {
    public const MAX_RESULTS = 20;

    public string $query = '';

    /**
     * @return Collection<int, array{project: Project, excerpt: ?string}>
     */
    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->query);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
        $projects = Auth::user()->projects()
            ->where(fn ($query) => $query
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereHas('messages', fn ($messages) => $messages->whereRaw('lower(content) like ?', [$like])))
            ->latest('updated_at')
            ->limit(self::MAX_RESULTS)
            ->get();

        return $projects->map(function (Project $project) use ($like, $term) {
            $message = str_contains(mb_strtolower($project->name), mb_strtolower($term))
                ? null
                : $project->messages()->whereRaw('lower(content) like ?', [$like])->latest('id')->first();

            return ['project' => $project, 'excerpt' => $message ? $this->excerpt($message->content, $term) : null];
        });
    }

    /**
     * A few words around the term found in a message.
     */
    private function excerpt(string $content, string $term): string
    {
        // Sans marques de liste (« - », « • », « 1. ») ni retours à la ligne.
        $content = preg_replace('/(^|\R)\s*(?:[-*•]|\d+[.)])\s+/u', '$1', $content) ?? $content;
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $position = (int) mb_stripos($content, $term);

        // Commencer sur un mot entier, environ 40 caractères avant le mot cherché.
        $start = 0;
        if ($position > 40) {
            $before = mb_substr($content, 0, $position - 40);
            $space = mb_strrpos($before, ' ');
            $start = $space === false ? 0 : $space + 1;
        }

        return ($start > 0 ? '… ' : '').Str::limit(mb_substr($content, $start), 110);
    }
}; ?>

<flux:modal name="search" class="w-full max-w-lg" data-search>
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Search') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="query" icon="magnifying-glass" :placeholder="__('Sites and discussions…')" autocomplete="off" data-search-input />

        @if (mb_strlen(trim($query)) >= 2)
            <ul class="-mx-2 max-h-96 overflow-y-auto" data-search-results>
                @forelse ($this->results as $result)
                    <li wire:key="resultat-{{ $result['project']->id }}">
                        <a href="{{ route('projects.show', $result['project']) }}" wire:navigate class="flex items-start gap-2.5 rounded-lg px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" data-search-result>
                            @if ($result['project']->isChat())
                                <flux:icon.chat-bubble-left-right variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                            @else
                                <flux:icon.window variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $result['project']->name }}</span>
                                @if ($result['excerpt'])
                                    <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400" data-search-excerpt>{{ $result['excerpt'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-2 py-2 text-sm text-zinc-500 dark:text-zinc-400" data-search-none>{{ __('Nothing found for « :term ».', ['term' => trim($query)]) }}</li>
                @endforelse
            </ul>
        @endif
    </div>
</flux:modal>
