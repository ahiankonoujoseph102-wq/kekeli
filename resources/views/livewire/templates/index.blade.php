<?php

use App\Templates\TemplateGallery;
use App\WebApps\WebAppTemplates;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public function rendering($view): void
    {
        $view->title(__('Site templates'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function templates(): array
    {
        return app(TemplateGallery::class)->all();
    }

    /**
     * Web applications (sites with a database) of the gallery.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function webApps(): array
    {
        return app(WebAppTemplates::class)->gallery();
    }
}; ?>

<section class="flex flex-col gap-6" data-templates>
    <div class="text-center">
        <flux:heading size="xl" level="1">{{ __('Site templates') }}</flux:heading>
        <flux:subheading class="mx-auto max-w-2xl">{{ __('Professional sites designed for Africa. Choose a template, answer a few simple questions, and Kekeli personalizes the whole site for you. Free for every formula.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->templates as $slug => $template)
            <a wire:key="template-{{ $slug }}" href="{{ route('templates.show', $slug) }}" class="group overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-800" data-template="{{ $slug }}">
                <img src="{{ route('templates.preview', [$slug, 'images/accueil.jpg']) }}" alt="" class="aspect-video w-full object-cover" loading="lazy">
                <div class="p-4">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ $template['nom'] }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $template['description'] }}</p>
                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-amber-700 dark:text-amber-400">{{ __('See the template') }} →</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-4 text-center" id="applications-web">
        <flux:heading size="xl" level="2">{{ __('Web application templates') }}</flux:heading>
        <flux:subheading class="mx-auto max-w-2xl">{{ __('Sites with a database: user accounts, saved forms and an administrator area. Kekeli personalizes the template, tests it and shows it working; you keep the code and choose your host. Free for every formula.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-webapp-gallery>
        @foreach ($this->webApps as $app)
            <a wire:key="webapp-{{ $app['slug'] }}" href="{{ route('projects.new-webapp', ['modele' => $app['slug']]) }}" class="group overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-800" data-webapp-template="{{ $app['slug'] }}">
                @if (is_file(public_path('images/modeles-applications/'.$app['slug'].'.png')))
                    <img src="{{ asset('images/modeles-applications/'.$app['slug'].'.png') }}" alt="" class="aspect-video w-full object-cover object-top" loading="lazy">
                @endif
                <div class="p-4">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ $app['nom'] }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $app['description'] }}</p>
                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-amber-700 dark:text-amber-400">{{ __('Use this application') }} →</span>
                </div>
            </a>
        @endforeach
    </div>
</section>
