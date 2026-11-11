<?php

use App\Jobs\BuildHostingPackage;
use App\Models\Project;
use App\WebApps\DeployGuides;
use App\WebApps\HostingPackage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    public Project $project;

    #[Url(as: 'hebergeur')]
    public ?string $host = null;

    /** Encryption key of the Laravel application, made for this page (never stored by Kekeli). */
    public string $appKey = '';

    public function mount(Project $project): void
    {
        abort_unless($project->user_id === Auth::id() && $project->isWebApp(), 404);
        $this->project = $project;
        $this->appKey = 'base64:'.base64_encode(random_bytes(32));
        if (! $this->guide) {
            $this->host = array_key_first($this->guides);
        }
    }

    public function rendering($view): void
    {
        $view->title(__('Put it online'));
    }

    #[Computed]
    public function guides(): array
    {
        return app(DeployGuides::class)->for($this->project->app_stack);
    }

    #[Computed]
    public function guide(): ?array
    {
        return $this->host ? ($this->guides[$this->host] ?? null) : null;
    }

    public function chooseHost(string $host): void
    {
        if (isset($this->guides[$host])) {
            $this->host = $host;
            unset($this->guide);
        }
    }

    /**
     * Ask the Runner for the ZIP file for shared hosting (Laravel applications).
     */
    public function buildPackage(): void
    {
        $this->project->refresh();

        if ($this->project->app_stack !== 'laravel' || $this->project->package_status === HostingPackage::BUILDING) {
            return;
        }
        if (! $this->project->backup_at) {
            $this->addError('package', __('Ask Kekeli for a first version of your site before preparing the ZIP file.'));

            return;
        }

        $this->project->forceFill(['package_status' => HostingPackage::BUILDING])->save();
        BuildHostingPackage::dispatch($this->project);
    }

    /**
     * Turn a guide text into safe HTML: `code` and **bold**.
     */
    public function format(string $text): string
    {
        $html = e($text);
        $html = preg_replace('/`([^`]+)`/', '<code class="break-all rounded bg-zinc-100 px-1 py-0.5 text-[0.85em] dark:bg-zinc-800">$1</code>', $html);

        return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
    }
}; ?>

<section class="mx-auto flex w-full max-w-2xl flex-col gap-5" @if ($project->package_status === HostingPackage::BUILDING) wire:poll.5s @endif>
    <div>
        <flux:link :href="route('projects.show', $project)" wire:navigate class="text-sm">← {{ $project->name }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Put it online') }}</flux:heading>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Host') }}" data-hosts>
        @foreach ($this->guides as $key => $item)
            <button type="button" wire:click="chooseHost('{{ $key }}')" @class([
                'rounded-full border px-3 py-1.5 text-sm font-medium',
                'border-amber-500 bg-amber-500 text-white' => $host === $key,
                'border-zinc-300 bg-white text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200' => $host !== $key,
            ]) data-host="{{ $key }}">{{ $item['nom'] }}</button>
        @endforeach
    </nav>

    @if ($this->guide)
        @php($guide = $this->guide)
        <div class="space-y-1">
            <flux:heading size="lg">{{ $guide['nom'] }}</flux:heading>
            <flux:text>{{ $guide['pour'] }}</flux:text>
            <flux:text class="text-sm"><strong>{{ __('Cost:') }}</strong> {{ $guide['cout'] }}</flux:text>
        </div>

        @if ($guide['code'] === 'paquet')
            <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-package="{{ $project->package_status ?? 'none' }}">
                <p class="font-medium">{{ __('ZIP file ready for shared hosting') }}</p>
                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('Your site with all its libraries, without any password: you put it on your hosting with the file manager, without a terminal.') }}</p>
                @if ($project->package_status === HostingPackage::BUILDING)
                    <p class="mt-2 flex items-center gap-2 font-medium text-sky-700 dark:text-sky-300"><flux:icon.loading class="size-4" /> {{ __('Kekeli is preparing the ZIP file (one or two minutes)…') }}</p>
                @else
                    @if ($project->package_status === HostingPackage::READY && $project->package_at)
                        <flux:button size="sm" variant="primary" icon="arrow-down-tray" class="mt-2" :href="route('projects.hosting-package', $project)" data-package-download>
                            {{ __('Download (:size MB)', ['size' => number_format(($project->package_size ?? 0) / 1048576, 1, ',', ' ')]) }}
                        </flux:button>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Prepared on :date. If you change your site with Kekeli, prepare it again.', ['date' => $project->package_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]) }}</p>
                    @endif
                    @if ($project->package_status === HostingPackage::FAILED)
                        <p class="kekeli-erreur mt-2" data-package-failed>{{ __('The ZIP file could not be prepared this time. Please try again in a moment.') }}</p>
                    @endif
                    <flux:button size="sm" :variant="$project->package_status === HostingPackage::READY ? 'subtle' : 'filled'" icon="archive-box" class="mt-2" wire:click="buildPackage" data-package-build>
                        {{ $project->package_status === HostingPackage::READY ? __('Prepare it again') : __('Prepare the ZIP file') }}
                    </flux:button>
                    <flux:error name="package" />
                @endif
            </div>
        @endif

        @if ($project->app_stack === 'laravel' && in_array($host, ['hostinger', 'cpanel', 'render'], true))
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950" x-data="{ copie: false }" data-app-key>
                <p class="font-medium">{{ __('Your encryption key APP_KEY') }}</p>
                <p class="mt-1 text-zinc-700 dark:text-zinc-200">{{ __('Made just for you on this page and never kept by Kekeli. Copy it into your hosting settings, and do not share it.') }}</p>
                <code class="mt-2 block break-all rounded bg-white px-2 py-1 text-xs dark:bg-zinc-900">{{ $appKey }}</code>
                <flux:button size="sm" variant="filled" icon="clipboard" class="mt-2" x-on:click="navigator.clipboard?.writeText(@js($appKey)); copie = true; setTimeout(() => copie = false, 2500)">
                    <span x-show="! copie">{{ __('Copy the key') }}</span><span x-show="copie" x-cloak>{{ __('Copied') }}</span>
                </flux:button>
            </div>
        @endif

        <div x-data="{ fait: (() => { try { return JSON.parse(localStorage.getItem(@js('guide-'.$project->id.'-'.$host)) || '[]') } catch { return [] } })(), basculer(i) { this.fait = this.fait.includes(i) ? this.fait.filter((x) => x !== i) : [...this.fait, i]; try { localStorage.setItem(@js('guide-'.$project->id.'-'.$host), JSON.stringify(this.fait)) } catch {} } }" wire:key="etapes-{{ $host }}">
            <flux:heading size="lg">{{ __('Steps') }}</flux:heading>
            <ol class="mt-2 space-y-3" data-guide-steps>
                @foreach ($guide['etapes'] as $index => [$titre, $texte])
                    <li class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" :class="fait.includes({{ $index }}) ? 'opacity-60' : ''">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" class="mt-1 size-5 shrink-0 accent-amber-500" :checked="fait.includes({{ $index }})" x-on:change="basculer({{ $index }})">
                            <span class="min-w-0">
                                <span class="block font-medium text-zinc-900 dark:text-white">{{ $index + 1 }}. {{ $titre }}</span>
                                <span class="mt-1 block text-sm text-zinc-700 dark:text-zinc-200">{!! $this->format($texte) !!}</span>
                            </span>
                        </label>
                    </li>
                @endforeach
            </ol>
        </div>

        <div>
            <flux:heading size="lg">{{ __('Settings to fill in') }}</flux:heading>
            <dl class="mt-2 space-y-2 text-sm" data-guide-variables>
                @foreach ($guide['variables'] as [$nom, $explication])
                    <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900">
                        <dt class="break-all font-mono text-xs font-semibold">{{ $nom }}</dt>
                        <dd class="mt-0.5 text-zinc-700 dark:text-zinc-200">{!! $this->format($explication) !!}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div>
            <flux:heading size="lg">{{ __('Files prepared by Kekeli') }}</flux:heading>
            <ul class="mt-2 space-y-1 text-sm" data-guide-files>
                @foreach ($guide['fichiers'] as [$fichier, $role])
                    <li><code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ $fichier }}</code> : {{ $role }}</li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-guide-security>
            <p class="flex items-center gap-2 font-medium"><flux:icon.shield-check class="size-5 text-emerald-600" /> {{ __('Security') }}</p>
            <ul class="mt-1 list-disc space-y-1 ps-5 text-zinc-700 dark:text-zinc-200">
                <li>{{ __('Never a password or a key in the code or on GitHub: only in the settings of the host or in the « .env » file of the server.') }}</li>
                <li>{{ __('Before sending a screenshot to anyone, hide the passwords and the keys.') }}</li>
                <li>{{ __('You are responsible for the personal data of your users: publish a privacy policy, and keep your hosting up to date.') }}</li>
            </ul>
        </div>
    @endif
</section>
