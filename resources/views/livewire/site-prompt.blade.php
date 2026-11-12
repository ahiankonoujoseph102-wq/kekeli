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

    /** Whether the title is the main heading of the page (the welcome page) or not. */
    public bool $main = false;

    public function mount(bool $main = false): void
    {
        $this->main = $main;
        $this->description = (string) session(NewSite::PENDING, '');
    }

    /**
     * Ideas shown under the field: a touch fills it (the client can change the text before sending).
     *
     * @return list<array{label: string, text: string}>
     */
    public function ideas(): array
    {
        return [
            ['label' => __('my restaurant'), 'text' => __('A website for my restaurant in Lomé, with the menu, the prices in FCFA and a WhatsApp button to book a table.')],
            ['label' => __('my school'), 'text' => __('A website for my school where parents register their children online, and where the school accepts each registration and sees the list of pupils by class.')],
            ['label' => __('my shop'), 'text' => __('A website for my shop of wax fabrics, with my products, their prices and a WhatsApp button to order.')],
            ['label' => __('my hair salon'), 'text' => __('A website for my hair salon, with the services, the prices, photos and the opening hours.')],
        ];
    }

    public function useIdea(int $index): void
    {
        $this->description = $this->ideas()[$index]['text'] ?? $this->description;
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function build(): void
    {
        $limits = config('kekeli.client_photos');

        $this->validate([
            'description' => ['required', 'string', 'min:2', 'max:4000'],
            'photos' => ['array', 'max:'.$limits['max_per_project']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
        ], [
            'description.required' => __('A few words are enough.'),
        ], [
            'description' => __('description'),
            'photos.*' => __('photo'),
        ]);

        // Pas encore de compte : le texte attend pendant l'inscription, puis Kekeli commence tout seul.
        if (! Auth::check()) {
            session()->put(NewSite::PENDING, trim($this->description));
            $this->redirectRoute('register', navigate: true);

            return;
        }

        $project = app(NewSite::class)->create(Auth::user(), $this->description, $this->photos);
        session()->forget(NewSite::PENDING);

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-2xl flex-col items-center gap-6" data-site-prompt>
    @if ($main)
        <h1 class="kekeli-titre text-balance text-center text-[2.5rem] leading-[1.1] text-zinc-900 sm:text-6xl dark:text-zinc-100">{{ __('What do you want to create today?') }}</h1>
    @else
        <h2 class="kekeli-titre text-balance text-center text-4xl leading-[1.1] text-zinc-900 sm:text-5xl dark:text-zinc-100">{{ __('What do you want to create today?') }}</h2>
    @endif

    <form wire:submit="build" class="w-full">
        <x-prompt-box
            model="description"
            :photos-model="auth()->check() ? 'photos' : null"
            :photos="$photos"
            :placeholder="__('Ask Kekeli to create a website for…')"
            :label="__('What do you want to create today?')"
            submit="build"
            :rows="4"
            input-attribute="data-site-prompt-input"
        >
            <x-slot:menu>
                {{-- Travailler dans un dépôt GitHub, comme Claude Code : une entrée du menu « + ». --}}
                <flux:menu.item icon="github" :href="auth()->check() ? route('projects.open-repository') : route('login')" data-github-chip>GitHub</flux:menu.item>
            </x-slot:menu>
        </x-prompt-box>

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

    <div class="flex w-full flex-wrap justify-center gap-2" data-site-prompt-ideas>
        @foreach ($this->ideas() as $index => $idea)
            <button type="button" wire:click="useIdea({{ $index }})" class="rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:text-white">
                {{ $idea['label'] }}
            </button>
        @endforeach
    </div>
</div>
