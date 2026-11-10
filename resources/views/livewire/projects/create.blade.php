<?php

use App\Agent\RunStarter;
use App\Models\Project;
use App\Support\Availability;
use App\WebApps\SiteNeeds;
use App\WebApps\WebAppTemplates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    /** Template of the gallery « Sites with a database » chosen by the client (null: Kekeli builds from the description). */
    #[Url(as: 'modele')]
    public ?string $template = null;

    public string $name = '';

    public string $description = '';

    /** « auto » (Kekeli understands from the description), « showcase » or « database ». */
    public string $kind = 'auto';

    /** Technology of a site with a database: « auto » (Kekeli chooses), « laravel » or « nextjs ». */
    public string $stack = 'auto';

    public string $city = '';

    public string $changes = '';

    public function rendering($view): void
    {
        $view->title(__('New site'));
    }

    public function mount(): void
    {
        if ($this->template && ! $this->chosen) {
            $this->template = null;
        }
    }

    /**
     * Template of the gallery « Sites with a database », when the client came from the gallery.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function chosen(): ?array
    {
        $template = $this->template ? app(WebAppTemplates::class)->find($this->template) : null;

        return $template && ($template['galerie'] ?? false) ? $template : null;
    }

    /**
     * What Kekeli understood from the description.
     *
     * @return array{database: bool, reasons: list<string>}
     */
    #[Computed]
    public function detection(): array
    {
        return app(SiteNeeds::class)->detect($this->description);
    }

    /**
     * Whether the site will have a database (the client's choice wins over what Kekeli understood).
     */
    #[Computed]
    public function needsDatabase(): bool
    {
        return $this->chosen !== null || match ($this->kind) {
            'database' => true,
            'showcase' => false,
            default => $this->detection['database'],
        };
    }

    public function forgetTemplate(): void
    {
        $this->template = null;
        unset($this->chosen, $this->needsDatabase);
    }

    /**
     * Create the site and give the description to Kekeli right away (like Lovable): the client sees it being built.
     */
    public function create(): void
    {
        $chosen = $this->chosen;

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => [$chosen ? 'nullable' : 'required', 'string', 'min:'.($chosen ? 0 : 10), 'max:4000'],
            'kind' => ['required', Rule::in(['auto', 'showcase', 'database'])],
            'stack' => ['required', Rule::in([...Project::STACKS, 'auto'])],
            'city' => ['nullable', 'string', 'max:100'],
            'changes' => ['nullable', 'string', 'max:2000'],
        ]);

        $name = trim($this->name);
        $templates = app(WebAppTemplates::class);
        $instructions = null;

        if ($this->needsDatabase) {
            $stack = $this->stack === 'auto' ? $templates->suggestStack($this->description.' '.$this->changes) : $this->stack;
            $project = Auth::user()->projects()->make(['name' => $name, 'description' => $chosen ? $chosen['nom'] : null]);
            $project->forceFill([
                'kind' => Project::KIND_WEBAPP,
                'app_stack' => $stack,
                'app_template' => $chosen ? $chosen['slug'] : 'socle',
            ])->save();

            $content = $chosen
                ? trim(__('I want the site « :template » for :name', ['template' => $chosen['nom'], 'name' => $name]).(filled($this->city) ? ', '.trim($this->city) : '').'.'.(filled($this->changes) ? "\n".trim($this->changes) : ''))
                : trim($this->description);
            $instructions = $templates->firstInstructions($stack, $chosen, ['name' => $name, 'city' => trim($this->city), 'changes' => trim($this->changes)]);
        } else {
            $project = Auth::user()->projects()->create(['name' => $name, 'description' => null]);
            $content = trim($this->description);
            $instructions = $templates->showcaseInstructions($name);
        }

        // Kekeli commence tout de suite, sauf s'il manque le pays, si le pays n'est pas encore ouvert ou si
        // l'énergie est épuisée : la description attend alors dans la page du projet, rien n'est perdu.
        $user = Auth::user();
        $started = $user->countryDetails() && ! app(Availability::class)->comingSoon($user)
            && app(RunStarter::class)->start($project, $content, $instructions);

        if (! $started) {
            session()->flash('brouillon.'.$project->id, $content);
        }

        $this->redirectRoute('projects.show', ['project' => $project], navigate: true);
    }
}; ?>

<section class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <div>
        <flux:link :href="route('projects.index')" wire:navigate class="text-sm">← {{ __('My projects') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('New site') }}</flux:heading>
        <flux:subheading>{{ __('Describe your site in your own words. Kekeli understands by itself whether it needs a database, user accounts or an administrator area, builds it, tests it, and you watch it being built.') }}</flux:subheading>
    </div>

    @if ($this->chosen)
        <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950" data-chosen-template="{{ $this->chosen['slug'] }}">
            @if (is_file(public_path('images/modeles-applications/'.$this->chosen['slug'].'.png')))
                <img src="{{ asset('images/modeles-applications/'.$this->chosen['slug'].'.png') }}" alt="" class="h-16 w-24 shrink-0 rounded-lg border border-zinc-200 object-cover object-top dark:border-zinc-700">
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('Template chosen') }} · {{ __('Site with a database') }}</p>
                <p class="font-medium text-zinc-900 dark:text-white">{{ $this->chosen['nom'] }}</p>
                <button type="button" wire:click="forgetTemplate" class="mt-1 text-sm underline">{{ __('Describe my own site instead') }}</button>
            </div>
        </div>
    @else
        <a href="{{ route('templates.index') }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-amber-400 dark:border-zinc-700" data-link-templates>
            <flux:icon.squares-2x2 class="size-6 shrink-0 text-amber-600" />
            <span class="min-w-0 flex-1">
                <span class="block font-medium text-zinc-900 dark:text-white">{{ __('Start from a template') }} <flux:badge color="emerald" size="sm" class="ms-1">{{ __('Less energy') }}</flux:badge></span>
                <span class="block text-sm text-zinc-600 dark:text-zinc-300">{{ __('Showcase sites and sites with a database, ready to personalize.') }}</span>
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endif

    <form wire:submit="create" class="space-y-5 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-new-site-form>
        <flux:input wire:model="name" :label="$this->chosen ? __('Name of your business') : __('Name of your site')" :placeholder="__('For example: Restaurant Le Palmier')" maxlength="100" required data-site-name />

        @if ($this->chosen)
            <flux:input wire:model="city" :label="__('Town or district (optional)')" :placeholder="__('For example: Lomé, Adidogomé')" />
            <flux:textarea wire:model="changes" rows="3" :label="__('What you want to change (optional)')" :placeholder="__('For example: add a field « Allergies », choices of rooms « Standard » and « Confort ».')" />
        @else
            <flux:textarea wire:model.live.debounce.600ms="description" rows="6" :label="__('Describe your site')" :placeholder="__('For example: a site for my restaurant with the menu and a WhatsApp button. Or: parents register their children online, the school accepts or refuses each registration and sees the list of students by class.')" required data-site-description />

            @if (mb_strlen(trim($description)) >= 10)
                <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900" data-understood="{{ $this->detection['database'] ? 'database' : 'showcase' }}">
                    <p><span class="font-medium">{{ __('Kekeli understood:') }}</span> {{ app(\App\WebApps\SiteNeeds::class)->summary($this->detection) }}</p>
                </div>
            @endif

            <flux:radio.group wire:model.live="kind" :label="__('Kind of site')" data-site-kind>
                <flux:radio value="auto" :label="__('Kekeli decides from my description (recommended)')" />
                <flux:radio value="showcase" :label="__('Showcase site')" :description="__('Pages that present your activity: photos, prices, contact, WhatsApp button. Published online in one click, free.')" />
                <flux:radio value="database" :label="__('Site with a database')" :description="__('User accounts, saved forms, administrator area, tests. You keep the code and choose your host: Kekeli guides you step by step.')" />
            </flux:radio.group>
        @endif

        @if ($this->needsDatabase)
            <details class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-site-stack>
                <summary class="cursor-pointer font-medium">{{ __('Technology (optional)') }}</summary>
                <flux:radio.group wire:model="stack" class="mt-3">
                    <flux:radio value="auto" :label="__('I do not know: Kekeli chooses for me')" :description="__('Kekeli suggests Laravel, the simplest to put online (also on shared hosting with cPanel), or Next.js if you speak of Node or Vercel.')" />
                    <flux:radio value="laravel" :label="__('Laravel (PHP) and MySQL')" :description="__('Recommended: hosts Laravel Cloud, Hostinger, Render, shared hosting with cPanel.')" />
                    <flux:radio value="nextjs" :label="__('Next.js (Node) and PostgreSQL')" :description="__('Hosts Vercel and Render, with a PostgreSQL database (for example Neon).')" />
                </flux:radio.group>
            </details>
            @unless ($this->chosen)
                <flux:text class="text-xs">{{ __('A site with a database built from your description takes more energy: if the energy of the day is used up, the work continues the next day, where it stopped.') }}</flux:text>
            @endunless
        @endif

        <flux:button type="submit" variant="primary" icon="sparkles" class="w-full" data-create-site>{{ __('Create my site') }}</flux:button>
    </form>
</section>
