<?php

use App\Agent\RunStarter;
use App\Models\Project;
use App\WebApps\WebAppTemplates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    /** Chosen template of the gallery (null: an application made to measure, from the starting point). */
    #[Url(as: 'modele')]
    public ?string $template = null;

    /** « laravel », « nextjs », or « auto » (Kekeli chooses). */
    public string $stack = 'auto';

    public string $name = '';

    public string $city = '';

    public string $changes = '';

    public string $description = '';

    public function rendering($view): void
    {
        $view->title(__('New web application'));
    }

    public function mount(): void
    {
        if ($this->template && ! $this->chosen) {
            $this->template = null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function templates(): array
    {
        return app(WebAppTemplates::class)->gallery();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function chosen(): ?array
    {
        $template = $this->template ? app(WebAppTemplates::class)->find($this->template) : null;

        return $template && ($template['galerie'] ?? false) ? $template : null;
    }

    public function choose(?string $slug): void
    {
        $this->template = $slug;
        unset($this->chosen);
        $this->resetErrorBag();
    }

    /**
     * Create the web application and send the first request to Kekeli.
     */
    public function create(): void
    {
        $templates = app(WebAppTemplates::class);
        $chosen = $this->chosen;

        $this->validate([
            'stack' => ['required', Rule::in([...Project::STACKS, 'auto'])],
            'name' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'changes' => ['nullable', 'string', 'max:2000'],
            'description' => [$chosen ? 'nullable' : 'required', 'string', 'min:'.($chosen ? 0 : 20), 'max:4000'],
        ]);

        $stack = $this->stack === 'auto' ? $templates->suggestStack($this->description.' '.$this->changes) : $this->stack;
        $name = trim($this->name);

        $project = Auth::user()->projects()->make([
            'name' => $name,
            'description' => $chosen ? $chosen['nom'] : null,
        ]);
        $project->forceFill([
            'kind' => Project::KIND_WEBAPP,
            'app_stack' => $stack,
            'app_template' => $chosen ? $chosen['slug'] : 'socle',
        ])->save();

        $content = $chosen
            ? trim(__('I want the application « :template » for :name', ['template' => $chosen['nom'], 'name' => $name]).(filled($this->city) ? ', '.trim($this->city) : '').'.'.(filled($this->changes) ? "\n".trim($this->changes) : ''))
            : trim($this->description);

        app(RunStarter::class)->start($project, $content, $templates->firstInstructions($stack, $chosen, [
            'name' => $name,
            'city' => trim($this->city),
            'changes' => trim($this->changes),
        ]));

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }
}; ?>

<section class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <div>
        <flux:link :href="route('projects.index')" wire:navigate class="text-sm">← {{ __('My projects') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('New web application') }}</flux:heading>
        <flux:subheading>{{ __('A professional site with a database that opens in the browser: user accounts, saved forms and an administrator area. Kekeli builds it, tests it, and you see it working in the preview.') }}</flux:subheading>
    </div>

    {{-- Kekeli propose d'abord les modèles : ils coûtent moins d'énergie. --}}
    <div class="space-y-3" data-webapp-templates>
        <div class="flex flex-wrap items-center gap-2">
            <flux:heading size="lg">{{ __('Start from a template') }}</flux:heading>
            <flux:badge color="emerald" size="sm">{{ __('Less energy') }}</flux:badge>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->templates as $item)
                <button type="button" wire:click="choose('{{ $item['slug'] }}')" @class([
                    'rounded-xl border p-3 text-left transition',
                    'border-amber-500 bg-amber-50 ring-2 ring-amber-500 dark:bg-amber-950' => $template === $item['slug'],
                    'border-zinc-200 hover:border-amber-400 dark:border-zinc-700' => $template !== $item['slug'],
                ]) data-webapp-template="{{ $item['slug'] }}">
                    @if (is_file(public_path('images/modeles-applications/'.$item['slug'].'.png')))
                        <img src="{{ asset('images/modeles-applications/'.$item['slug'].'.png') }}" alt="" class="mb-2 aspect-[2/1] w-full rounded-lg border border-zinc-200 object-cover object-top dark:border-zinc-700" loading="lazy">
                    @endif
                    <span class="block font-medium text-zinc-900 dark:text-white">{{ $item['nom'] }}</span>
                    <span class="mt-1 block text-sm text-zinc-600 dark:text-zinc-300">{{ $item['description'] }}</span>
                </button>
            @endforeach
        </div>
        <button type="button" wire:click="choose(null)" @class([
            'w-full rounded-xl border p-3 text-left transition',
            'border-amber-500 bg-amber-50 ring-2 ring-amber-500 dark:bg-amber-950' => ! $template,
            'border-zinc-200 hover:border-amber-400 dark:border-zinc-700' => (bool) $template,
        ]) data-webapp-from-scratch>
            <span class="block font-medium text-zinc-900 dark:text-white">{{ __('Made to measure') }}</span>
            <span class="mt-1 block text-sm text-zinc-600 dark:text-zinc-300">{{ __('Describe your application: Kekeli builds it from scratch. It takes more energy: if the energy of the day is used up, the work continues the next day, where it stopped.') }}</span>
        </button>
    </div>

    <form wire:submit="create" class="space-y-5 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:input wire:model="name" :label="$this->chosen ? __('Name of your business') : __('Name of your application')" :placeholder="__('For example: Restaurant Le Palmier')" required data-webapp-name />

        @if ($this->chosen)
            <flux:input wire:model="city" :label="__('Town or district (optional)')" :placeholder="__('For example: Lomé, Adidogomé')" />
            <flux:textarea wire:model="changes" rows="3" :label="__('What you want to change (optional)')" :placeholder="__('For example: add a field « Allergies », choices of rooms « Standard » and « Confort ».')" />
        @else
            <flux:textarea wire:model="description" rows="6" :label="__('What must your application do?')" :placeholder="__('For example: parents register their children online, the school accepts or refuses each registration, sees the list of students by class and sends a message to the parents.')" required data-webapp-description />
        @endif

        <flux:radio.group wire:model="stack" :label="__('Technology')" data-webapp-stack>
            <flux:radio value="auto" :label="__('I do not know: Kekeli chooses for me')" :description="__('Kekeli suggests Laravel, the simplest to put online (also on shared hosting with cPanel), or Next.js if you speak of Node or Vercel.')" />
            <flux:radio value="laravel" :label="__('Laravel (PHP) and MySQL')" :description="__('Recommended: hosts Laravel Cloud, Hostinger, Render, shared hosting with cPanel.')" />
            <flux:radio value="nextjs" :label="__('Next.js (Node) and PostgreSQL')" :description="__('Hosts Vercel and Render, with a PostgreSQL database (for example Neon).')" />
        </flux:radio.group>

        <flux:text class="text-xs">{{ __('Kekeli builds and tests the application in its own computer, with a test database. You keep the code (GitHub or ZIP, free). Then you choose and pay for your hosting and your database yourself: Kekeli gives you a step-by-step guide.') }}</flux:text>

        <flux:button type="submit" variant="primary" icon="sparkles" class="w-full" data-webapp-create>{{ __('Create my application') }}</flux:button>
    </form>
</section>
