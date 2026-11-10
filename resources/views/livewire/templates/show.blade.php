<?php

use App\Templates\TemplateGallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    #[Locked]
    public string $slug = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $template = [];

    public function mount(string $slug): void
    {
        $this->template = app(TemplateGallery::class)->find($slug) ?? abort(404);
        $this->slug = $slug;
    }

    public function rendering($view): void
    {
        $view->title(__('Template « :name »', ['name' => $this->template['nom']]));
    }

    /**
     * Create a project from the template. Without an account, the visitor creates one first and comes back here.
     */
    public function useTemplate(): void
    {
        if (! Auth::check()) {
            session()->put('url.intended', route('templates.show', $this->slug));
            $this->redirect(route('register'), navigate: true);

            return;
        }

        $project = app(TemplateGallery::class)->createProject(Auth::user(), $this->slug);
        $this->redirect(route('projects.show', $project), navigate: true);
    }
}; ?>

<section class="flex flex-col gap-6 lg:flex-row lg:items-start" data-template-show="{{ $slug }}">
    <div class="flex flex-col gap-4 lg:w-1/2">
        <flux:link :href="route('templates.index')" class="text-sm">← {{ __('All templates') }}</flux:link>
        <flux:heading size="xl" level="1">{{ $template['nom'] }}</flux:heading>
        <flux:text>{{ $template['description'] }}</flux:text>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950">
            <p class="font-medium text-amber-900 dark:text-amber-100">{{ __('How it works') }}</p>
            <ol class="mt-2 list-decimal space-y-1 ps-5 text-amber-900 dark:text-amber-100">
                <li>{{ __('Tap « Use this template ».') }}</li>
                <li>{{ __('Answer a few simple questions: name of your activity, city, WhatsApp number, prices, colours and photos.') }}</li>
                <li>{{ __('Kekeli personalizes the whole site. Then ask for every change you want.') }}</li>
            </ol>
            <p class="mt-2 text-amber-900 dark:text-amber-100">{{ __('The names, numbers and texts of the template are examples: Kekeli replaces them all before your site can be published.') }}</p>
        </div>

        <flux:button variant="primary" icon="sparkles" wire:click="useTemplate" class="w-full sm:w-auto" data-use-template>{{ __('Use this template') }}</flux:button>
        @guest
            <flux:text class="text-sm">{{ __('You will create your free account first (one minute).') }}</flux:text>
        @endguest
    </div>

    {{-- Aperçu dans un cadre de téléphone : le visiteur fait défiler le vrai site du modèle. --}}
    <div class="mx-auto w-full max-w-[380px] lg:w-1/2">
        <div class="overflow-hidden rounded-[2.2rem] border-[10px] border-zinc-900 bg-zinc-900 shadow-2xl dark:border-zinc-700">
            <iframe src="{{ route('templates.preview', [$slug, 'index.html']) }}" title="{{ __('Preview of the template on a phone') }}" class="h-[640px] w-full bg-white" loading="lazy" data-template-preview></iframe>
        </div>
        <p class="mt-2 text-center text-xs text-zinc-500">{{ __('Preview on a phone: scroll inside the screen.') }}</p>
    </div>
</section>
