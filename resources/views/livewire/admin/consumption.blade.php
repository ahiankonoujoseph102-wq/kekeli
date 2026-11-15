<?php

use App\Agent\Costs;
use App\Agent\Energy;
use App\Agent\Timings;
use App\Models\AgentRun;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $period = 'month';

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function rendering($view): void
    {
        $view->title(__('Consumption'));
    }

    private function since(): CarbonImmutable
    {
        $now = CarbonImmutable::now(config('kekeli.display_timezone'));

        return ($this->period === 'day' ? $now->startOfDay() : $now->startOfMonth())->utc();
    }

    /**
     * Totals of the period.
     *
     * @return array<string, float|int>
     */
    #[Computed]
    public function totals(): array
    {
        $row = AgentRun::query()->where('created_at', '>=', $this->since())->selectRaw(
            'COUNT(*) as runs, COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(input_tokens), 0) as input_tokens,
             COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(reasoning_tokens), 0) as reasoning_tokens,
             COALESCE(SUM(cached_tokens), 0) as cached_tokens, COALESCE(SUM(model_cost_usd), 0) as model_cost_usd,
             COALESCE(SUM(computer_seconds), 0) as computer_seconds, COALESCE(SUM(computer_cost_usd), 0) as computer_cost_usd'
        )->first();

        return array_map(fn ($value) => is_numeric($value) ? $value + 0 : 0, $row?->toArray() ?? []);
    }

    /**
     * The same requests, priced with every model of the grid (an estimation: another model would
     * not use exactly the same number of tokens).
     *
     * @return list<array{label: string, usd: float, fcfa: float, per_run_fcfa: float}>
     */
    #[Computed]
    public function comparison(): array
    {
        $costs = app(Costs::class);
        $totals = $this->totals;
        $runs = max(1, (int) ($totals['runs'] ?? 0));
        $rows = [];

        foreach (app(Settings::class)->get('model_prices') as $key => $price) {
            $price = ['input' => (float) $price['input'], 'output' => (float) $price['output'], 'cache' => (float) $price['cache']];
            $usd = $costs->tokensCost($price, (int) $totals['input_tokens'], (int) $totals['output_tokens'], (int) $totals['reasoning_tokens'], (int) $totals['cached_tokens']);
            $rows[] = [
                'label' => (string) (app(Settings::class)->get('model_prices')[$key]['label'] ?? $key),
                'usd' => $usd,
                'fcfa' => $costs->toFcfa($usd),
                'per_run_fcfa' => $costs->toFcfa($usd / $runs),
            ];
        }

        usort($rows, fn ($a, $b) => $a['usd'] <=> $b['usd']);

        return $rows;
    }

    /**
     * Users with their formula, their energy and their cost over the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function users(): Collection
    {
        $energy = app(Energy::class);
        $costs = app(Costs::class);

        return User::query()->orderBy('name')->get()->map(function (User $user) use ($energy, $costs) {
            $usd = (float) AgentRun::query()
                ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
                ->where('created_at', '>=', $this->since())
                ->selectRaw('COALESCE(SUM(model_cost_usd + computer_cost_usd + image_cost_usd + web_search_cost_usd + voice_cost_usd), 0) as total')
                ->value('total');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $energy->plan($user)['key'],
                'percent' => $energy->percentLeft($user),
                'cost_fcfa' => $costs->toFcfa($usd),
            ];
        });
    }

    /**
     * @return Collection<int, AgentRun>
     */
    #[Computed]
    public function recentRuns(): Collection
    {
        return AgentRun::query()->with('project.user', 'message')->latest()->limit(15)->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function planNames(): array
    {
        return collect(app(Settings::class)->get('plans'))->map(fn ($plan) => (string) $plan['name'])->all();
    }

    public function setPlan(int $userId, string $plan): void
    {
        Gate::authorize('admin');
        abort_unless(array_key_exists($plan, $this->planNames), 422);

        User::query()->whereKey($userId)->update(['plan' => $plan, 'energy_window_started_at' => null]);
        unset($this->users);
    }

    /**
     * Today's energy of all free users together, compared with the daily ceiling.
     *
     * @return array{used: float, cap: float}
     */
    #[Computed]
    public function freeToday(): array
    {
        return ['used' => app(Energy::class)->freeUsedTodayFcfa(), 'cap' => app(Settings::class)->float('free_daily_cap_fcfa')];
    }

    /**
     * La vitesse des dernières réponses et des derniers travaux, étape par étape, en secondes (Partie 1 du chantier
     * « vitesse ») : le site depuis la réception du message, le Runner depuis la réception de sa demande, le
     * téléphone depuis le toucher sur « Envoyer ». Seulement des nombres, jamais le texte du client.
     *
     * @return array{rows: list<array<string, mixed>>, medians: array<string, float>}
     */
    #[Computed]
    public function speeds(): array
    {
        $runs = AgentRun::query()->whereNotNull('timings')->latest('id')->limit(20)->get(['id', 'purpose', 'timings', 'created_at']);
        $rows = $runs->map(fn (AgentRun $run) => [
            'id' => $run->id,
            'kind' => $run->isQuestion() ? __('Answer') : __('Work'),
            'at' => $run->created_at,
            'server' => Timings::secondsFrom((array) $run->timings, isset($run->timings['serveur']['recu']) ? 'recu' : 'travail_cree'),
            'runner' => Timings::seconds((array) $run->timings, 'runner'),
            'browser' => Timings::seconds((array) $run->timings, 'navigateur'),
        ])->all();
        // Médianes des réponses (questions) : le chiffre qui compte pour le client, sans les cas extrêmes.
        $medians = [];
        foreach (['premier_mot', 'fin'] as $step) {
            $values = collect($rows)->where('kind', __('Answer'))->pluck("server.$step")->filter(fn ($v) => $v !== null)->sort()->values();
            if ($values->isNotEmpty()) {
                $medians[$step] = (float) $values[intdiv($values->count(), 2)];
            }
        }

        // Les médianes portent sur les 20 dernières ; seules les 10 dernières sont détaillées (lisible sur téléphone).
        return ['rows' => array_slice($rows, 0, 10), 'medians' => $medians];
    }

    public function fcfa(float $usd): string
    {
        return number_format(app(Costs::class)->toFcfa($usd), 1, ',', ' ');
    }
}; ?>

<section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Consumption') }}</flux:heading>
        <flux:subheading>{{ __('Real requests, tokens and computer time. Model costs use the paid price of each model, even while Kekeli uses a free offer: that is what the work would cost with a paid key.') }}</flux:subheading>
    </div>

    <flux:radio.group wire:model.live="period" variant="segmented" :label="__('Period')">
        <flux:radio value="day" :label="__('Today')" />
        <flux:radio value="month" :label="__('This month')" />
    </flux:radio.group>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-totals>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-xs text-zinc-500">{{ __('Requests to the agent') }}</div>
            <div class="text-xl font-medium tabular-nums">{{ $this->totals['runs'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-xs text-zinc-500">{{ __('Requests to the model') }}</div>
            <div class="text-xl font-medium tabular-nums">{{ $this->totals['requests'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-xs text-zinc-500">{{ __('Model cost') }}</div>
            <div class="text-xl font-medium tabular-nums">{{ $this->fcfa($this->totals['model_cost_usd']) }} FCFA</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-xs text-zinc-500">{{ __('Computer cost') }}</div>
            <div class="text-xl font-medium tabular-nums">{{ $this->fcfa($this->totals['computer_cost_usd']) }} FCFA</div>
        </div>
    </div>
    <flux:text class="text-xs">
        {{ __('Tokens sent: :input · received: :output · thinking: :reasoning · from the cache: :cached · computer: :minutes minutes of work.', [
            'input' => number_format($this->totals['input_tokens'], 0, ',', ' '),
            'output' => number_format($this->totals['output_tokens'], 0, ',', ' '),
            'reasoning' => number_format($this->totals['reasoning_tokens'], 0, ',', ' '),
            'cached' => number_format($this->totals['cached_tokens'], 0, ',', ' '),
            'minutes' => number_format($this->totals['computer_seconds'] / 60, 1, ',', ' '),
        ]) }}
    </flux:text>

    <flux:callout icon="fire" :color="$this->freeToday['cap'] > 0 && $this->freeToday['used'] >= $this->freeToday['cap'] ? 'red' : 'zinc'" data-free-today>
        <flux:callout.text>{{ __('Free formula today: :used FCFA used out of a ceiling of :cap FCFA for all free users together.', ['used' => number_format($this->freeToday['used'], 1, ',', ' '), 'cap' => number_format($this->freeToday['cap'], 0, ',', ' ')]) }}</flux:callout.text>
    </flux:callout>

    <div>
        <flux:heading size="lg">{{ __('The same requests with each model') }}</flux:heading>
        <flux:text class="text-xs">{{ __('Estimation: another model would not use exactly the same number of tokens.') }}</flux:text>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-sm" data-comparison>
                <thead>
                    <tr class="text-left text-zinc-500">
                        <th class="py-1 pr-3 font-normal">{{ __('Model') }}</th>
                        <th class="py-1 pr-3 text-right font-normal">{{ __('Total') }}</th>
                        <th class="py-1 text-right font-normal">{{ __('Per request') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->comparison as $row)
                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="py-1.5 pr-3">{{ $row['label'] }}</td>
                            <td class="py-1.5 pr-3 text-right whitespace-nowrap">{{ number_format($row['fcfa'], 1, ',', ' ') }} FCFA</td>
                            <td class="py-1.5 text-right whitespace-nowrap">{{ number_format($row['per_run_fcfa'], 1, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <flux:heading size="lg">{{ __('Users') }}</flux:heading>
        <div class="mt-2 flex flex-col gap-2" data-users>
            @foreach ($this->users as $user)
                <div wire:key="user-{{ $user['id'] }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $user['name'] }}</div>
                        <div class="break-all text-xs text-zinc-500">{{ $user['email'] }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('Energy left: :percent % · cost over the period: :cost FCFA', ['percent' => $user['percent'], 'cost' => number_format($user['cost_fcfa'], 1, ',', ' ')]) }}</div>
                    </div>
                    <flux:select size="sm" class="max-w-40" :aria-label="__('Formula of :name', ['name' => $user['name']])" wire:change="setPlan({{ $user['id'] }}, $event.target.value)">
                        @foreach ($this->planNames as $key => $name)
                            <flux:select.option :value="$key" :selected="$user['plan'] === $key">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Vitesse : le temps de chaque étape, comme on mesure celle de Claude ou de ChatGPT. --}}
    <div data-speeds>
        <flux:heading size="lg">{{ __('Speed of the answers') }}</flux:heading>
        <flux:text class="mt-1 text-xs">{{ __('In seconds. Site: from the message received; Runner: from its request received; phone: from the touch on « Send ».') }}</flux:text>
        @if ($this->speeds['medians'] !== [])
            <p class="mt-2 text-sm tabular-nums" data-speed-medians>
                {{ __('Median of the last answers: first word :first s, answer finished :end s.', ['first' => \App\Support\Formats::number($this->speeds['medians']['premier_mot'] ?? 0, 1), 'end' => \App\Support\Formats::number($this->speeds['medians']['fin'] ?? 0, 1)]) }}
            </p>
        @endif
        @if ($this->speeds['rows'] === [])
            <div class="kekeli-carte kekeli-vide mt-2"><p>{{ __('No measured request yet.') }}</p></div>
        @else
            <ul class="mt-2 flex flex-col gap-2" data-speed-rows>
                @foreach ($this->speeds['rows'] as $row)
                    <li wire:key="vitesse-{{ $row['id'] }}" class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700" data-speed-row>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $row['kind'] }} <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ \App\Support\Formats::short($row['at']) }}</span></p>
                        @foreach (['server' => __('Site'), 'runner' => __('Runner'), 'browser' => __('Phone')] as $family => $label)
                            @if ($row[$family] !== [])
                                <p class="mt-1 text-xs text-zinc-600 tabular-nums dark:text-zinc-300">
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $label }} :</span>
                                    {{ collect($row[$family])->map(fn ($seconds, $step) => (\App\Agent\Timings::LABELS[$step] ?? $step).' '.\App\Support\Formats::number($seconds, 1).' s')->join(' · ') }}
                                </p>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <flux:heading size="lg">{{ __('Last requests') }}</flux:heading>
        <div class="mt-2 flex flex-col gap-2" data-runs>
            @foreach ($this->recentRuns as $run)
                <div wire:key="run-{{ $run->id }}" class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <div class="break-words">{{ \Illuminate\Support\Str::limit($run->message?->content ?? '', 90) }}</div>
                    <div class="mt-1 text-xs text-zinc-500">
                        {{ \App\Support\Formats::short($run->created_at) }} · {{ $run->project?->user?->email }} ·
                        {{ __(':requests requests, :tokens tokens, :seconds s, :cost FCFA', [
                            'requests' => $run->requests,
                            'tokens' => number_format($run->input_tokens + $run->output_tokens + $run->reasoning_tokens + $run->cached_tokens, 0, ',', ' '),
                            'seconds' => $run->computer_seconds,
                            'cost' => $this->fcfa($run->model_cost_usd + $run->computer_cost_usd + $run->image_cost_usd + $run->web_search_cost_usd + $run->voice_cost_usd),
                        ]) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
