<?php

use App\Agent\Costs;
use App\Agent\ModelRouter;
use App\Agent\RunnerClient;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/*
 * Administration → Modèles (docs/plan.md, partie 26.26) : interrupteurs des fournisseurs, comptes Coarena (clés
 * masquées : seulement leur NOM), test de chaque clé, demande officielle de capacité, modèles par tâche et par mode,
 * classement du banc d'essai et consommation du jour. Réservé aux administrateurs.
 */
new class extends Component {
    /** @var array<string, bool> */
    public array $providers = [];

    public bool $multiAccounts = true;

    /** @var array<string, array<string, string>> tâche => mode => « modèle, modèle » */
    public array $routes = [];

    /** @var list<array<string, mixed>> */
    public array $checks = [];

    public ?string $capacityMessage = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('admin');
        foreach (ModelRouter::PROVIDERS as $provider) {
            $this->providers[$provider] = app(ModelRouter::class)->providerEnabled($provider);
        }
        $this->multiAccounts = (bool) $settings->get('coarena_multi_accounts');
        $saved = (array) $settings->get('model_routes');
        foreach (ModelRouter::TASKS as $task) {
            foreach (ModelRouter::MODES as $mode) {
                $this->routes[$task][$mode] = implode(', ', (array) ($saved[$task][$mode] ?? []));
            }
        }
        $this->checks = array_values((array) ($settings->get('coarena_key_checks') ?? []));
    }

    public function rendering($view): void
    {
        $view->title(__('Models'));
    }

    public function saveSwitches(Settings $settings): void
    {
        Gate::authorize('admin');
        $settings->set('model_providers', array_map(fn ($on) => (bool) $on, array_intersect_key($this->providers, array_flip(ModelRouter::PROVIDERS))));
        $settings->set('coarena_multi_accounts', $this->multiAccounts);
        $this->dispatch('modeles-enregistres');
    }

    public function toggleKey(string $name, Settings $settings): void
    {
        Gate::authorize('admin');
        abort_unless(preg_match('/^COARENA_API_KEY(_\d{1,3})?$/', $name), 404);
        $off = (array) ($settings->get('coarena_disabled_keys') ?? []);
        $off = in_array($name, $off, true) ? array_values(array_diff($off, [$name])) : [...$off, $name];
        $settings->set('coarena_disabled_keys', $off);
    }

    public function saveRoutes(Settings $settings): void
    {
        Gate::authorize('admin');
        $this->resetErrorBag();
        $routes = [];
        foreach (ModelRouter::TASKS as $task) {
            foreach (ModelRouter::MODES as $mode) {
                $models = array_values(array_filter(array_map('trim', explode(',', (string) ($this->routes[$task][$mode] ?? '')))));
                $unknown = array_diff($models, ModelRouter::COARENA_MODELS);
                if ($unknown !== []) {
                    $this->addError("routes.$task.$mode", __('Unknown model: :model', ['model' => implode(', ', $unknown)]));

                    return;
                }
                $routes[$task][$mode] = $models;
            }
        }
        $settings->set('model_routes', $routes);
        $this->dispatch('modeles-enregistres');
    }

    /** Test of one key (or all of them): only the names of the keys come back from the Runner. */
    public function testKeys(?string $name = null): void
    {
        Gate::authorize('admin');
        $results = app(RunnerClient::class)->coarenaKeys($name);
        $byName = collect($this->checks)->keyBy('nom');
        foreach ($results as $result) {
            $byName[$result['nom']] = $result;
        }
        $this->checks = $byName->sortBy(fn ($c) => (int) (preg_match('/_(\d+)$/', $c['nom'], $m) ? $m[1] : 1))->values()->all();
        app(Settings::class)->set('coarena_key_checks', $this->checks);
        unset($this->runnerState);
    }

    public function askCapacity(): void
    {
        Gate::authorize('admin');
        $answer = app(RunnerClient::class)->coarenaCapacity();
        $this->capacityMessage = ($answer['ok'] ?? false)
            ? __('Request sent to Coarena. Its answer will appear in the quota of the main account.')
            : __('Coarena did not accept the request: :reason', ['reason' => (string) ($answer['erreur'] ?? __('unknown reason'))]);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function runnerState(): ?array
    {
        return app(RunnerClient::class)->state();
    }

    /** @return array<string, array<string, mixed>> the state of each key in the Runner (account, stats), by name */
    #[Computed]
    public function keyStates(): array
    {
        return collect($this->runnerState['coarena'] ?? [])->keyBy('nom')->all();
    }

    /**
     * Today's use of Coarena, by model (UTC day of Coarena's quota), with its estimated cost.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function usage(): array
    {
        $costs = app(Costs::class);

        return ProviderUsage::query()
            ->where('provider', 'coarena')
            ->whereDate('day', CarbonImmutable::now('UTC')->toDateString())
            ->orderByDesc('requests')
            ->get()
            ->map(fn (ProviderUsage $u) => [
                'model' => ModelRouter::COARENA_LABELS[$u->model] ?? $u->model,
                'requests' => (int) $u->requests,
                'refusals' => (int) $u->rate_limited,
                'tokens' => (int) $u->input_tokens + (int) $u->output_tokens,
                'cost' => $costs->modelCost('coarena', $u->model, (int) $u->input_tokens, (int) $u->output_tokens, (int) $u->reasoning_tokens, (int) $u->cached_tokens),
            ])->all();
    }

    /**
     * The ranking of the test bench (runner/mesures/modeles-coarena.json): total mark on 70, time, cost, errors.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function ranking(): array
    {
        $file = base_path('runner/mesures/modeles-coarena.json');
        if (! is_file($file)) {
            return [];
        }
        $tests = collect((array) (json_decode((string) file_get_contents($file), true)['essais'] ?? []));

        return $tests->groupBy('modele')->map(fn ($rows, $model) => [
            'model' => ModelRouter::COARENA_LABELS[$model] ?? $model,
            'quality' => ModelRouter::BENCH_QUALITY[$model][0] ?? null,
            'kept' => ModelRouter::BENCH_QUALITY[$model][1] ?? false,
            'mark' => (int) $rows->sum('note'),
            'tasks' => $rows->count(),
            'seconds' => (int) round($rows->sum('duree')),
            'cost' => (float) $rows->sum('cout_usd'),
            'errors' => $rows->filter(fn ($r) => ! ($r['ok'] ?? false))->count(),
        ])->sortByDesc(fn ($r) => $r['quality'] ?? -1)->values()->all();
    }
}; ?>

<section class="mx-auto flex w-full max-w-4xl flex-col gap-8" data-admin-models>
    <header>
        <flux:heading size="xl" level="1">{{ __('Models') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Kekeli chooses the model for each task. Clients never see the name of a model or a provider.') }}</flux:text>
    </header>

    {{-- Interrupteurs --}}
    <section class="kekeli-carte flex flex-col gap-4 p-4 sm:p-5" data-model-switches>
        <flux:heading size="lg">{{ __('Providers') }}</flux:heading>
        <flux:switch wire:model="providers.coarena" :label="__('Coarena (many models, free of charge each day for each account)')" data-switch-coarena />
        <flux:switch wire:model="providers.openai" :label="__('OpenAI (paid key, with its daily ceiling)')" data-switch-openai />
        <flux:switch wire:model="providers.gratuits" :label="__('Free offers (Google AI Studio, OpenRouter, Cloudflare)')" data-switch-free />
        <flux:separator variant="subtle" />
        <flux:switch wire:model="multiAccounts" :label="__('Switch to another Coarena account when the quota of an account is used up')" data-switch-multi-accounts />
        <flux:text class="text-xs">{{ __('Coarena may change its limits or withdraw access at any time (its conditions, point 6). Kekeli then goes on with OpenAI and the free offers.') }}</flux:text>
        <div><flux:button size="sm" variant="primary" wire:click="saveSwitches" data-save-switches>{{ __('Save') }}</flux:button></div>
    </section>

    {{-- Comptes Coarena --}}
    <section class="flex flex-col gap-3" data-coarena-keys>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Coarena accounts') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="testKeys" wire:loading.attr="disabled" wire:target="testKeys" data-test-all-keys>{{ __('Test all the keys') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="askCapacity" data-ask-capacity>{{ __('Ask Coarena for more capacity') }}</flux:button>
            </div>
        </div>
        @if ($capacityMessage)
            <flux:text data-capacity-message>{{ $capacityMessage }}</flux:text>
        @endif
        @php($disabled = (array) (app(\App\Support\Settings::class)->get('coarena_disabled_keys') ?? []))
        @php($names = collect($this->checks)->pluck('nom')->merge(array_keys($this->keyStates))->unique()->values())
        @if ($names->isEmpty())
            <div class="kekeli-carte kekeli-vide" data-no-key>
                <span class="kekeli-vide-icone"><flux:icon.key variant="mini" /></span>
                <p>{{ __('No key tested yet. The keys are read from the environment variables COARENA_API_KEY, COARENA_API_KEY_2…') }}</p>
                <flux:button size="sm" wire:click="testKeys">{{ __('Test all the keys') }}</flux:button>
            </div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2" wire:loading.class="opacity-60" wire:target="testKeys">
                @foreach ($names as $name)
                    @php($check = collect($this->checks)->firstWhere('nom', $name) ?? [])
                    @php($state = $this->keyStates[$name] ?? [])
                    @php($stats = $state['statistiques'] ?? null)
                    <li wire:key="cle-{{ $name }}" class="kekeli-carte flex flex-col gap-2 p-4 text-sm" data-coarena-key="{{ $name }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                {{-- Seulement le nom de la variable : jamais un caractère de la clé. --}}
                                <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Account') }} {{ $check['compte'] ?? $state['compte'] ?? '—' }}</p>
                            </div>
                            @if (in_array($name, $disabled, true))
                                <span class="kekeli-pastille">{{ __('Switched off') }}</span>
                            @elseif (($check['valide'] ?? null) === false)
                                <span class="kekeli-pastille" data-key-status="invalide">{{ __('Invalid') }}</span>
                            @elseif ($state['hors_service'] ?? false)
                                <span class="kekeli-pastille" data-key-status="pause">{{ __('Paused') }}</span>
                            @elseif (($check['valide'] ?? null) === true)
                                <span class="kekeli-pastille kekeli-pastille-lumiere" data-key-status="valide">{{ __('Valid') }}</span>
                            @endif
                        </div>
                        @if ($check)
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                                <dt>{{ __('Quota of the day') }}</dt><dd class="text-end">{{ isset($check['quota']) ? number_format((float) $check['quota']['utilise_usd'], 3, ',', ' ').' / '.$check['quota']['usd_par_jour'].' $' : '—' }}</dd>
                                <dt>{{ __('Models') }}</dt><dd class="text-end">{{ count($check['modeles'] ?? []) }}</dd>
                                <dt>{{ __('Small request') }}</dt><dd class="text-end">{{ isset($check['essai']) ? (($check['essai']['ok'] ?? false) ? number_format(($check['essai']['duree_ms'] ?? 0) / 1000, 1, ',', ' ').' s' : __('Error: :type', ['type' => $check['essai']['erreur'] ?? '?'])) : '—' }}</dd>
                                <dt>{{ __('Expires on') }}</dt><dd class="text-end">{{ ! empty($check['expire_le']) ? \Carbon\CarbonImmutable::parse($check['expire_le'])->locale('fr')->isoFormat('D MMMM YYYY') : '—' }}</dd>
                                <dt>{{ __('Tested') }}</dt><dd class="text-end">{{ ! empty($check['controle_le']) ? \Carbon\CarbonImmutable::parse($check['controle_le'])->locale('fr')->diffForHumans() : '—' }}</dd>
                                @if ($stats)
                                    <dt>{{ __('Requests since the start of the Runner') }}</dt><dd class="text-end">{{ $stats['reussites'] }} / {{ $stats['demandes'] }}</dd>
                                    <dt>{{ __('Average time') }}</dt><dd class="text-end">{{ $stats['reussites'] ? number_format($stats['duree_totale_ms'] / $stats['reussites'] / 1000, 1, ',', ' ').' s' : '—' }}</dd>
                                    <dt>{{ __('Errors') }}</dt><dd class="text-end">{{ collect($stats['erreurs'])->map(fn ($n, $k) => "$k : $n")->implode(', ') ?: '0' }}</dd>
                                @endif
                            </dl>
                            @if (! empty($check['erreur']))
                                <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('Error: :type', ['type' => $check['erreur']]) }}{{ ! empty($check['detail']) ? ' · '.$check['detail'] : '' }}</p>
                            @endif
                        @endif
                        <div class="flex gap-2">
                            <flux:button size="xs" wire:click="testKeys('{{ $name }}')" data-test-key="{{ $name }}">{{ __('Test') }}</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="toggleKey('{{ $name }}')" data-toggle-key="{{ $name }}">{{ in_array($name, $disabled, true) ? __('Switch on') : __('Switch off') }}</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Modèles par tâche --}}
    <section class="kekeli-carte flex flex-col gap-4 p-4 sm:p-5" data-model-routes>
        <div>
            <flux:heading size="lg">{{ __('Models for each task') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Coarena models tried in order, before OpenAI and the free offers. Quality: for every client, free formula included. Economy: only when the credit of the client is almost used up, to finish the work.') }}</flux:text>
            <flux:text class="mt-1 text-xs">{{ __('Allowed models:') }} {{ implode(', ', ModelRouter::COARENA_MODELS) }}</flux:text>
        </div>
        @foreach (ModelRouter::taskLabels() as $task => $label)
            <div class="grid gap-2 sm:grid-cols-[10rem_1fr_1fr] sm:items-start" data-route="{{ $task }}">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</p>
                <flux:input size="sm" wire:model="routes.{{ $task }}.qualite" :aria-label="$label.' · '.__('Quality')" :placeholder="__('Quality')" />
                <flux:input size="sm" wire:model="routes.{{ $task }}.economie" :aria-label="$label.' · '.__('Economy')" :placeholder="__('Economy')" />
            </div>
            @error("routes.$task.qualite") <flux:text class="text-xs">{{ $message }}</flux:text> @enderror
            @error("routes.$task.economie") <flux:text class="text-xs">{{ $message }}</flux:text> @enderror
        @endforeach
        <div><flux:button size="sm" variant="primary" wire:click="saveRoutes" data-save-routes>{{ __('Save') }}</flux:button></div>
    </section>

    {{-- Classement du banc d'essai --}}
    <section class="flex flex-col gap-3" data-model-ranking>
        <flux:heading size="lg">{{ __('Ranking of the test bench') }}</flux:heading>
        <flux:text class="text-xs">{{ __('Seven real tasks of Kekeli, checked by the code: reasoning, site, change of a site, application, document, tools, instructions (runner/mesures/modeles-coarena.md).') }}</flux:text>
        @if ($this->ranking === [])
            <div class="kekeli-carte kekeli-vide"><p>{{ __('No test yet.') }}</p></div>
        @else
            {{-- Téléphone : une liste compacte (le tableau serait coupé à droite) ; ordinateur : le tableau. --}}
            <ul class="flex flex-col gap-2 sm:hidden" data-ranking-list>
                @foreach ($this->ranking as $row)
                    <li class="kekeli-carte flex flex-col gap-1.5 p-4 text-sm" data-ranking-item>
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 break-words font-medium text-zinc-900 dark:text-white">{{ $row['model'] }}</p>
                            <span class="kekeli-pastille shrink-0 {{ $row['kept'] ? 'kekeli-pastille-lumiere' : '' }}">{{ $row['kept'] ? __('Kept') : __('Not kept') }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                            <dt>{{ __('Quality') }}</dt><dd class="text-end">{{ $row['quality'] !== null ? str_replace('.', ',', (string) $row['quality']).' / 10' : __('Not tested') }}</dd>
                            <dt>{{ __('Checks') }}</dt><dd class="text-end">{{ $row['mark'] }} / {{ $row['tasks'] * 10 }}</dd>
                            <dt>{{ __('Time') }}</dt><dd class="text-end">{{ $row['seconds'] }} s</dd>
                            <dt>{{ __('Cost') }}</dt><dd class="text-end">{{ number_format($row['cost'], 4, ',', ' ') }} $</dd>
                        </dl>
                    </li>
                @endforeach
            </ul>
            <div class="kekeli-tableau hidden sm:block">
                <table>
                    <thead><tr><th>{{ __('Model') }}</th><th>{{ __('Quality') }}</th><th>{{ __('Checks') }}</th><th>{{ __('Time') }}</th><th>{{ __('Cost') }}</th><th>{{ __('Kept') }}</th></tr></thead>
                    <tbody>
                        @foreach ($this->ranking as $row)
                            <tr data-ranking-row><td>{{ $row['model'] }}</td><td>{{ $row['quality'] !== null ? str_replace('.', ',', (string) $row['quality']).' / 10' : __('Not tested') }}</td><td>{{ $row['mark'] }} / {{ $row['tasks'] * 10 }}</td><td>{{ $row['seconds'] }} s</td><td>{{ number_format($row['cost'], 4, ',', ' ') }} $</td><td>{{ $row['kept'] ? __('Yes') : __('No') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Consommation du jour --}}
    <section class="flex flex-col gap-3" data-model-usage>
        <flux:heading size="lg">{{ __('Use of the day (Coarena)') }}</flux:heading>
        @if ($this->usage === [])
            <div class="kekeli-carte kekeli-vide"><p>{{ __('No request to Coarena today.') }}</p></div>
        @else
            <ul class="flex flex-col gap-2 sm:hidden" data-usage-list>
                @foreach ($this->usage as $row)
                    <li class="kekeli-carte flex flex-col gap-1.5 p-4 text-sm" data-usage-item>
                        <p class="break-words font-medium text-zinc-900 dark:text-white">{{ $row['model'] }}</p>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                            <dt>{{ __('Requests') }}</dt><dd class="text-end">{{ $row['requests'] }}</dd>
                            <dt>{{ __('Refusals') }}</dt><dd class="text-end">{{ $row['refusals'] }}</dd>
                            <dt>{{ __('Tokens') }}</dt><dd class="text-end">{{ number_format($row['tokens'], 0, ',', ' ') }}</dd>
                            <dt>{{ __('Estimated cost') }}</dt><dd class="text-end">{{ number_format($row['cost'], 4, ',', ' ') }} $</dd>
                        </dl>
                    </li>
                @endforeach
            </ul>
            <div class="kekeli-tableau hidden sm:block">
                <table>
                    <thead><tr><th>{{ __('Model') }}</th><th>{{ __('Requests') }}</th><th>{{ __('Refusals') }}</th><th>{{ __('Tokens') }}</th><th>{{ __('Estimated cost') }}</th></tr></thead>
                    <tbody>
                        @foreach ($this->usage as $row)
                            <tr data-usage-row><td>{{ $row['model'] }}</td><td>{{ $row['requests'] }}</td><td>{{ $row['refusals'] }}</td><td>{{ number_format($row['tokens'], 0, ',', ' ') }}</td><td>{{ number_format($row['cost'], 4, ',', ' ') }} $</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</section>
