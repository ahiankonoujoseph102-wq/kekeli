<?php

use App\Agent\Costs;
use App\Agent\FreeLlmApi;
use App\Agent\ModelRouter;
use App\Agent\ProviderList;
use App\Agent\RunnerClient;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/*
 * Administration → Modèles (docs/plan.md, parties 26.26 et 26.38) : interrupteurs des fournisseurs, FreeLLMAPI (état du
 * processus voisin du Runner et de chacun de ses fournisseurs, sans aucune clé), familles de modèles par type de tâche,
 * part de chaque formule et plafond du jour, consommation du jour par vrai fournisseur. Réservé aux administrateurs.
 */
new class extends Component {
    /** @var array<string, bool> */
    public array $providers = [];

    /** @var array<string, string> groupe => « famille, famille » */
    public array $routes = [];

    /** @var array<string, int> formule => demandes par jour */
    public array $perPlan = [];

    public int $dailyCap = 0;

    public function mount(Settings $settings): void
    {
        Gate::authorize('admin');
        foreach (ModelRouter::PROVIDERS as $provider) {
            $this->providers[$provider] = app(ModelRouter::class)->providerEnabled($provider);
        }
        foreach (FreeLlmApi::GROUPS as $group) {
            $this->routes[$group] = implode(', ', app(FreeLlmApi::class)->models($group));
        }
        $this->perPlan = array_map('intval', (array) $settings->get('freellmapi_requests_per_day'));
        $this->dailyCap = $settings->int('freellmapi_daily_cap');
    }

    public function rendering($view): void
    {
        $view->title(__('Models'));
    }

    public function saveSwitches(Settings $settings): void
    {
        Gate::authorize('admin');
        $settings->set('model_providers', array_map(fn ($on) => (bool) $on, array_intersect_key($this->providers, array_flip(ModelRouter::PROVIDERS))));
        $this->dispatch('modeles-enregistres');
    }

    public function saveFreellmapi(Settings $settings): void
    {
        Gate::authorize('admin');
        $this->resetErrorBag();
        $routes = [];
        foreach (FreeLlmApi::GROUPS as $group) {
            $models = array_values(array_filter(array_map(fn ($m) => strtolower(trim($m)), explode(',', (string) ($this->routes[$group] ?? '')))));
            $unknown = array_diff($models, FreeLlmApi::MODELS);
            if ($unknown !== []) {
                $this->addError("routes.$group", __('Unknown model: :model', ['model' => implode(', ', $unknown)]));

                return;
            }
            $routes[$group] = $models;
        }
        $this->validate([
            'perPlan.*' => ['required', 'integer', 'min:0', 'max:100000'],
            'dailyCap' => ['required', 'integer', 'min:0', 'max:10000000'],
        ]);
        $settings->set('freellmapi_routes', $routes);
        $settings->set('freellmapi_requests_per_day', array_map('intval', $this->perPlan));
        $settings->set('freellmapi_daily_cap', $this->dailyCap);
        $this->dispatch('modeles-enregistres');
    }

    /** @return array<string, mixed>|null the state of FreeLLMAPI and of its providers, from the Runner (no key) */
    #[Computed]
    public function freellmapi(): ?array
    {
        return app(RunnerClient::class)->freellmapiState();
    }

    /** @return array{used: int, cap: int, percent: int} today's use of FreeLLMAPI against the ceiling of the day */
    #[Computed]
    public function today(): array
    {
        $used = app(FreeLlmApi::class)->globalUsedToday();
        $cap = app(Settings::class)->int('freellmapi_daily_cap');

        return ['used' => $used, 'cap' => $cap, 'percent' => $cap > 0 ? (int) min(100, round($used * 100 / $cap)) : 0];
    }

    /**
     * Today's use of FreeLLMAPI, by real provider and model (« groq/openai/gpt-oss-120b »), with its estimated cost.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function usage(): array
    {
        $costs = app(Costs::class);

        return ProviderUsage::query()
            ->where('provider', FreeLlmApi::PROVIDER)
            ->where('day', app(ProviderList::class)->quotaDay(FreeLlmApi::PROVIDER))
            ->orderByDesc('requests')
            ->get()
            ->map(fn (ProviderUsage $u) => [
                'platform' => str_contains($u->model, '/') ? strstr($u->model, '/', true) : '—',
                'model' => $u->model,
                'requests' => (int) $u->requests,
                'refusals' => (int) $u->rate_limited,
                'tokens' => (int) $u->input_tokens + (int) $u->output_tokens,
                'cost' => $costs->modelCost(FreeLlmApi::PROVIDER, $u->model, (int) $u->input_tokens, (int) $u->output_tokens, (int) $u->reasoning_tokens, (int) $u->cached_tokens),
            ])->all();
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
        <flux:text class="text-xs">{{ __('Order: OpenAI, then the free offers, then FreeLLMAPI.') }}</flux:text>
        <flux:switch wire:model="providers.openai" :label="__('OpenAI (paid key, with its daily ceiling)')" data-switch-openai />
        <flux:switch wire:model="providers.gratuits" :label="__('Free offers (Google AI Studio, OpenRouter, Cloudflare)')" data-switch-free />
        <flux:switch wire:model="providers.freellmapi" :label="__('FreeLLMAPI (Groq, LongCat, Z.ai and the other free providers it groups)')" data-switch-freellmapi />
        <flux:switch wire:model="providers.freemodel" :label="__('FreeModel (last reinforcement, one million free tokens a month)')" data-switch-freemodel />
        <p class="text-sm text-zinc-500" data-freemodel-usage>{{ __('FreeModel this month: :used tokens of :allowance.', ['used' => number_format(app(\App\Agent\FreeModel::class)->tokensUsedThisMonth(), 0, ',', ' '), 'allowance' => number_format(app(\App\Agent\FreeModel::class)->monthlyAllowance(), 0, ',', ' ')]) }}</p>
        <div><flux:button size="sm" variant="primary" wire:click="saveSwitches" data-save-switches>{{ __('Save') }}</flux:button></div>
    </section>

    {{-- FreeLLMAPI : état --}}
    <section class="flex flex-col gap-3" data-freellmapi>
        <flux:heading size="lg">{{ __('FreeLLMAPI') }}</flux:heading>
        @php($state = $this->freellmapi)
        @if ($state === null)
            <div class="kekeli-carte kekeli-vide" data-freellmapi-unreachable>
                <span class="kekeli-vide-icone"><flux:icon.signal-slash variant="mini" /></span>
                <p>{{ __('The Runner does not answer: the state of FreeLLMAPI cannot be read.') }}</p>
            </div>
        @else
            <div class="kekeli-carte flex flex-col gap-2 p-4 text-sm sm:p-5" data-freellmapi-state>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium text-zinc-900 dark:text-white">{{ __('Next to the Runner, on 127.0.0.1 only') }}</p>
                    @if ($state['pret'] ?? false)
                        <span class="kekeli-pastille kekeli-pastille-succes" data-freellmapi-ready>{{ __('Ready') }}</span>
                    @elseif ($state['demande'] ?? false)
                        <span class="kekeli-pastille" data-freellmapi-not-ready>{{ __('Not ready') }}</span>
                    @else
                        <span class="kekeli-pastille" data-freellmapi-off>{{ __('No key') }}</span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                    <dt>{{ __('Version') }}</dt><dd class="text-end tabular-nums">{{ substr((string) ($state['version'] ?? ''), 0, 7) ?: '—' }}</dd>
                    <dt>{{ __('Providers with a key') }}</dt><dd class="text-end">{{ implode(', ', (array) ($state['plateformes'] ?? [])) ?: '—' }}</dd>
                    <dt>{{ __('Backup of the quotas') }}</dt><dd class="text-end">{{ ($state['sauvegarde'] ?? false) ? __('Yes') : __('No') }}</dd>
                    @if (! empty($state['erreur']))
                        <dt>{{ __('Problem') }}</dt><dd class="text-end">{{ $state['erreur'] }}</dd>
                    @endif
                </dl>
            </div>

            @php($quotas = collect($state['quotas'] ?? [])->keyBy('nom'))
            @if (! empty($state['fournisseurs']))
                <ul class="grid gap-3 sm:grid-cols-2" data-freellmapi-providers>
                    @foreach ((array) ($state['fournisseurs'] ?? []) as $provider)
                        @php($quota = $quotas[$provider['nom'] ?? ''] ?? null)
                        <li class="kekeli-carte flex flex-col gap-2 p-4 text-sm" data-freellmapi-provider="{{ $provider['nom'] ?? '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $provider['nom'] ?? '' }}</p>
                                @if (! ($provider['actif'] ?? true))
                                    <span class="kekeli-pastille">{{ __('Switched off') }}</span>
                                @elseif (($provider['pauses'] ?? 0) > 0)
                                    <span class="kekeli-pastille">{{ __('Paused') }}</span>
                                @else
                                    <span class="kekeli-pastille kekeli-pastille-succes">{{ __('Active') }}</span>
                                @endif
                            </div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                                <dt>{{ __('Requests this month') }}</dt><dd class="text-end tabular-nums">{{ number_format((int) ($provider['requetes_mois'] ?? 0), 0, ',', ' ') }}</dd>
                                <dt>{{ __('Tokens this month') }}</dt><dd class="text-end tabular-nums">{{ number_format((int) ($provider['tokens_mois'] ?? 0), 0, ',', ' ') }}</dd>
                                @if ($quota)
                                    <dt>{{ __('Free allowance') }}</dt><dd class="text-end">{{ $quota['budget_mois'] ?: '—' }}</dd>
                                    @if ($quota['restant'] !== null && $quota['limite'] !== null)
                                        <dt>{{ __('Left now') }}</dt><dd class="text-end tabular-nums">{{ number_format((int) $quota['restant'], 0, ',', ' ') }} / {{ number_format((int) $quota['limite'], 0, ',', ' ') }} {{ $quota['unite'] === 'tokens' ? __('tokens') : $quota['unite'] }}</dd>
                                    @endif
                                @endif
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    {{-- FreeLLMAPI : réglages --}}
    <section class="kekeli-carte flex flex-col gap-4 p-4 sm:p-5" data-freellmapi-settings>
        <div>
            <flux:heading size="lg">{{ __('Models of FreeLLMAPI for each task') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Families tried in order, after OpenAI and the free offers. A family without any provider key is skipped at once.') }}</flux:text>
            <flux:text class="mt-1 text-xs">{{ __('Allowed models:') }} {{ implode(', ', FreeLlmApi::MODELS) }}</flux:text>
        </div>
        @foreach (['discussion' => __('Discussions'), 'document' => __('Documents and presentations'), 'construction' => __('Sites, applications, agents and automations')] as $group => $label)
            <div class="grid gap-2 sm:grid-cols-[14rem_1fr] sm:items-center" data-freellmapi-route="{{ $group }}">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</p>
                <flux:input size="sm" wire:model="routes.{{ $group }}" :aria-label="$label" />
            </div>
            @error("routes.$group") <flux:text class="text-xs">{{ $message }}</flux:text> @enderror
        @endforeach
        <flux:separator variant="subtle" />
        <div>
            <flux:heading size="sm">{{ __('Share of each client per day') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Answers and works served by FreeLLMAPI per client and per day (Lomé time), according to the formula. Energy is counted as usual.') }}</flux:text>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($perPlan as $plan => $value)
                <flux:input size="sm" type="number" min="0" wire:model="perPlan.{{ $plan }}" :label="ucfirst($plan)" data-freellmapi-plan="{{ $plan }}" />
            @endforeach
        </div>
        <flux:input size="sm" type="number" min="0" wire:model="dailyCap" :label="__('Ceiling of the day for all the clients (0: none)')" data-freellmapi-cap />
        <div><flux:button size="sm" variant="primary" wire:click="saveFreellmapi" data-save-freellmapi>{{ __('Save') }}</flux:button></div>
    </section>

    {{-- Consommation du jour --}}
    <section class="flex flex-col gap-3" data-model-usage>
        <div class="flex flex-wrap items-end justify-between gap-2">
            <flux:heading size="lg">{{ __('Use of the day (FreeLLMAPI)') }}</flux:heading>
            @if ($this->today['cap'] > 0)
                <flux:text class="text-xs tabular-nums" data-freellmapi-today>{{ __(':used of :cap requests (:percent %)', ['used' => number_format($this->today['used'], 0, ',', ' '), 'cap' => number_format($this->today['cap'], 0, ',', ' '), 'percent' => $this->today['percent']]) }}</flux:text>
            @endif
        </div>
        @if ($this->today['cap'] > 0 && $this->today['percent'] >= 80)
            <p class="kekeli-avis p-3 text-sm" data-freellmapi-alert>{{ __('FreeLLMAPI has used :percent % of its ceiling of the day. Add a provider key or raise the ceiling.', ['percent' => $this->today['percent']]) }}</p>
        @endif
        @if ($this->usage === [])
            <div class="kekeli-carte kekeli-vide"><p>{{ __('No request to FreeLLMAPI today.') }}</p></div>
        @else
            <ul class="flex flex-col gap-2 sm:hidden" data-usage-list>
                @foreach ($this->usage as $row)
                    <li class="kekeli-carte flex flex-col gap-1.5 p-4 text-sm" data-usage-item>
                        <p class="break-words font-medium text-zinc-900 dark:text-white">{{ $row['model'] }}</p>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                            <dt>{{ __('Requests') }}</dt><dd class="text-end tabular-nums">{{ $row['requests'] }}</dd>
                            <dt>{{ __('Refusals') }}</dt><dd class="text-end tabular-nums">{{ $row['refusals'] }}</dd>
                            <dt>{{ __('Tokens') }}</dt><dd class="text-end tabular-nums">{{ number_format($row['tokens'], 0, ',', ' ') }}</dd>
                            <dt>{{ __('Estimated cost') }}</dt><dd class="text-end tabular-nums">{{ number_format($row['cost'], 4, ',', ' ') }} $</dd>
                        </dl>
                    </li>
                @endforeach
            </ul>
            <div class="kekeli-tableau hidden sm:block">
                <table>
                    <thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Model') }}</th><th>{{ __('Requests') }}</th><th>{{ __('Refusals') }}</th><th>{{ __('Tokens') }}</th><th>{{ __('Estimated cost') }}</th></tr></thead>
                    <tbody>
                        @foreach ($this->usage as $row)
                            <tr data-usage-row><td>{{ $row['platform'] }}</td><td>{{ $row['model'] }}</td><td>{{ $row['requests'] }}</td><td>{{ $row['refusals'] }}</td><td>{{ number_format($row['tokens'], 0, ',', ' ') }}</td><td>{{ number_format($row['cost'], 4, ',', ' ') }} $</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</section>
