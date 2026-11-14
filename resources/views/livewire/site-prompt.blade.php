<?php

use App\WebApps\NewSite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/*
 * « Que voulez-vous créer aujourd'hui ? » : une seule zone de saisie, comme Bolt.new, Lovable, Manus ou Claude Code.
 * Sans compte, le texte est gardé pendant l'inscription ; avec un compte, Kekeli commence tout de suite.
 */
new class extends Component {
    use WithFileUploads;

    public string $description = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> Files joined (Word, PDF, PowerPoint). */
    public array $files = [];

    /** Whether the title is the main heading of the page (the welcome page) or not. */
    public bool $main = false;

    public function mount(bool $main = false): void
    {
        $this->main = $main;
        $this->description = (string) session(NewSite::PENDING, '');
    }

    /**
     * Suggestions under the field, like Claude, ChatGPT and Bolt.new: one per thing Kekeli can make. A touch writes
     * the start of the request in the field and puts the cursor at its end, without sending anything.
     *
     * @return list<array{label: string, icon: string, text: string}>
     */
    public function ideas(): array
    {
        return [
            ['label' => __('Website'), 'icon' => 'globe-alt', 'text' => __('Create me a website for ')],
            ['label' => __('Application'), 'icon' => 'device-phone-mobile', 'text' => __('Create me an application for ')],
            ['label' => __('AI agent'), 'icon' => 'cpu-chip', 'text' => __('Create me an AI agent for ')],
            ['label' => __('Automation'), 'icon' => 'arrow-path', 'text' => __('Create me an automation that ')],
            ['label' => __('Presentation'), 'icon' => 'presentation-chart-bar', 'text' => __('Prepare me a presentation about ')],
            ['label' => __('Image'), 'icon' => 'photo', 'text' => __('Create me an image of ')],
            ['label' => __('Video'), 'icon' => 'film', 'text' => __('Create me a video for ')],
            // Le document en dernier (décision du porteur du projet, 14 novembre 2026).
            ['label' => __('Document'), 'icon' => 'document-text', 'text' => __('Write me a document about ')],
        ];
    }

    /** « + » → « Connecteurs » sur l'accueil : le choix vaut pour les nouvelles discussions. */
    public ?string $connectorNotice = null;

    public function toggleConnector(int $id): void
    {
        $this->connectorNotice = app(\App\Support\Connectors::class)->toggle(Auth::user(), $id) ? null : __('Free formula: :count connectors active at most. Turn one off first, or choose a paid formula.', ['count' => app(\App\Support\Connectors::class)->maxActive(Auth::user()) ?? 0]);
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function build(): void
    {
        $limits = config('kekeli.client_photos');

        $this->validate([
            'description' => ['required', 'string', 'min:2', 'max:4000'],
            'photos' => ['array', 'max:'.$limits['max_per_project']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
            'files' => ['array', 'max:3'],
            'files.*' => ['file', 'mimes:'.implode(',', \App\Models\ProjectAttachment::extensions()), 'max:'.\App\Models\ProjectAttachment::MAX_MEDIA_KILOBYTES, \App\Models\ProjectAttachment::documentSizeRule()],
        ], [
            'description.required' => __('A few words are enough.'),
        ], [
            'description' => __('description'),
            'photos.*' => __('photo'),
            'files.*' => __('file'),
        ]);

        // Pas encore de compte : le texte attend pendant l'inscription, puis Kekeli commence tout seul.
        if (! Auth::check()) {
            session()->put(NewSite::PENDING, trim($this->description));
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $project = app(NewSite::class)->create(Auth::user(), $this->description, $this->photos, $this->files);
        session()->forget(NewSite::PENDING);

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-2xl flex-col items-center gap-6" data-site-prompt>
    @if ($main)
        {{-- L'accueil, plus marquant : le symbole de Kekeli au-dessus de la grande question. --}}
        <x-app-logo-icon class="kekeli-apparition kekeli-symbole-accueil h-16 w-auto sm:h-20" data-home-logo />
        <h1 class="kekeli-titre text-balance text-center text-[2.5rem] leading-[1.1] text-zinc-900 sm:text-6xl dark:text-zinc-100">{{ __('What do you want to create today?') }}</h1>
    @else
        <h2 class="kekeli-titre text-balance text-center text-4xl leading-[1.1] text-zinc-900 sm:text-5xl dark:text-zinc-100">{{ __('What do you want to create today?') }}</h2>
    @endif

    {{-- Lueur ambrée douce autour de la zone de saisie, seulement ici (comme Lovable et Bolt.new). --}}
    <form wire:submit="build" class="kekeli-lueur w-full" data-prompt-glow>
        <x-prompt-box
            model="description"
            :photos-model="auth()->check() ? 'photos' : null"
            :photos="$photos"
            :files-model="auth()->check() ? 'files' : null"
            :files="$files"
            :placeholder="__('Describe your idea…')"
            :label="__('What do you want to create today?')"
            submit="build"
            :rows="4"
            input-attribute="data-site-prompt-input"
        >
            <x-slot:menu>
                {{-- Travailler dans un dépôt GitHub, comme Claude Code : une entrée du menu « + ». --}}
                <flux:menu.item icon="github" :href="auth()->check() ? route('projects.open-repository') : route('login')" data-github-chip>GitHub</flux:menu.item>
                @auth
                    @php($connecteurs = app(\App\Support\Connectors::class))
                    <x-connectors-menu :connectors="$connecteurs->connected(auth()->user())" :active="$connecteurs->activeIds(auth()->user())" :max="$connecteurs->maxActive(auth()->user())" />
                @endauth
            </x-slot:menu>
        </x-prompt-box>

        @if ($connectorNotice)
            <p class="kekeli-erreur mt-2 text-sm" data-connector-notice>{{ $connectorNotice }}</p>
        @endif

        @error('description')
            <p class="kekeli-erreur mt-2 text-sm" data-site-prompt-error>{{ $message }}</p>
        @enderror
        @error('photos.*')
            <p class="kekeli-erreur mt-2 text-sm">{{ $message }}</p>
        @enderror
        @error('photos')
            <p class="kekeli-erreur mt-2 text-sm">{{ $message }}</p>
        @enderror
    </form>

    {{-- Toujours une seule ligne, comme Claude : elle défile (fondu sur le bord) quand la place manque, centrée sur grand écran. Le texte est écrit dans la
         zone par le navigateur (aucun aller-retour), le curseur placé à la fin, rien n'est envoyé. --}}
    <div class="kekeli-defile -mx-4 flex w-[calc(100%+2rem)] gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:w-full sm:px-0 xl:w-max xl:overflow-visible [&::-webkit-scrollbar]:hidden"
         x-data="{ debut: false, suite: false, voir() { this.debut = $el.scrollLeft > 4; this.suite = $el.scrollLeft + $el.clientWidth < $el.scrollWidth - 4 }, ecrire(texte) { const champ = document.getElementById('description-champ'); if (! champ) return; champ.value = texte; champ.dispatchEvent(new Event('input', { bubbles: true })); champ.focus(); champ.setSelectionRange(texte.length, texte.length) } }"
         x-init="$nextTick(() => voir())" x-on:scroll.passive="voir()" x-on:resize.window="voir()" x-bind:data-debut="debut" x-bind:data-suite="suite"
         role="group" aria-label="{{ __('Suggestions') }}" data-site-prompt-ideas>
        @foreach ($this->ideas() as $index => $idea)
            <button type="button" x-on:click="ecrire($el.dataset.texte)" data-texte="{{ $idea['text'] }}" class="kekeli-idee inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-sm transition-colors" data-idea="{{ $index }}">
                <flux:icon :icon="$idea['icon']" variant="outline" class="size-4 opacity-70" />
                {{ $idea['label'] }}
            </button>
        @endforeach
    </div>
</div>
