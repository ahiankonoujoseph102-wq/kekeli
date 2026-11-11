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
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'photos' => ['array', 'max:'.$limits['max_per_project']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
        ], [
            'description.required' => __('A few words are enough: what will your site be for?'),
            'description.min' => __('A few more words, so that Kekeli understands what your site is for.'),
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

<div class="mx-auto flex w-full max-w-2xl flex-col items-center gap-5" data-site-prompt>
    @if ($main)
        <h1 class="text-balance text-center text-3xl font-semibold leading-tight tracking-tight text-zinc-900 sm:text-4xl dark:text-white">{{ __('What do you want to create today?') }}</h1>
    @else
        <h2 class="text-balance text-center text-2xl font-semibold leading-tight tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ __('What do you want to create today?') }}</h2>
    @endif

    <form wire:submit="build" class="w-full">
        <div class="rounded-2xl border border-zinc-300 bg-white shadow-sm transition focus-within:border-amber-400 focus-within:ring-4 focus-within:ring-amber-400/20 dark:border-zinc-600 dark:bg-zinc-800">
            <label for="site-prompt" class="sr-only">{{ __('What do you want to create today?') }}</label>
            <textarea
                id="site-prompt"
                wire:model="description"
                rows="4"
                maxlength="4000"
                placeholder="{{ __('Ask Kekeli to create a website for…') }}"
                class="block min-h-28 w-full resize-none border-0 bg-transparent px-4 pt-4 text-base text-zinc-900 placeholder-zinc-400 outline-none focus:ring-0 dark:text-white"
                x-data
                x-on:keydown.enter="if ($event.ctrlKey || $event.metaKey) { $event.preventDefault(); $wire.build() }"
                data-site-prompt-input
            ></textarea>

            @if ($photos)
                <ul class="flex flex-wrap gap-2 px-4 pt-2" data-site-prompt-photos>
                    @foreach ($photos as $index => $photo)
                        <li wire:key="photo-{{ $index }}" class="relative">
                            @if (method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-14 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700">
                            @else
                                <span class="flex size-14 items-center justify-center rounded-lg border border-zinc-200 dark:border-zinc-700"><flux:icon.photo class="size-5 text-zinc-400" /></span>
                            @endif
                            <button type="button" wire:click="removePhoto({{ $index }})" class="absolute -right-1.5 -top-1.5 flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900" aria-label="{{ __('Remove this photo') }}">
                                <flux:icon.x-mark variant="micro" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex items-center justify-between gap-2 px-3 pb-3 pt-2">
                @auth
                    <label class="flex size-10 cursor-pointer items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" title="{{ __('Add photos') }}" data-site-prompt-attach>
                        <flux:icon.photo class="size-5" />
                        <span class="sr-only">{{ __('Add photos') }}</span>
                        <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                    </label>
                @else
                    <span></span>
                @endauth

                <div class="flex items-center gap-3">
                    <span wire:loading wire:target="photos" class="text-xs text-zinc-500">{{ __('Adding the photos…') }}</span>
                    <button
                        type="submit"
                        class="flex size-10 items-center justify-center rounded-full bg-amber-500 text-white shadow-sm transition hover:bg-amber-600 disabled:opacity-60"
                        wire:loading.attr="disabled"
                        wire:target="build,photos"
                        aria-label="{{ __('Send to Kekeli') }}"
                        data-site-prompt-send
                    >
                        <flux:icon.arrow-up class="size-5" wire:loading.remove wire:target="build" />
                        <flux:icon.loading class="size-5" wire:loading wire:target="build" />
                    </button>
                </div>
            </div>
        </div>

        @error('description')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400" data-site-prompt-error>{{ $message }}</p>
        @enderror
        @error('photos.*')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('photos')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </form>

    <div class="flex w-full flex-wrap justify-center gap-2" data-site-prompt-ideas>
        @foreach ($this->ideas() as $index => $idea)
            <button type="button" wire:click="useIdea({{ $index }})" class="rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-700 transition hover:border-amber-400 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:text-white">
                {{ $idea['label'] }}
            </button>
        @endforeach
    </div>
</div>
