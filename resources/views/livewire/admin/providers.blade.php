<?php

use App\Agent\ProviderList;
use App\Agent\RunnerClient;
use App\Models\AdminAlert;
use App\Models\ProviderUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function rendering($view): void
    {
        $view->title(__('AI providers'));
    }

    /**
     * State of the providers as seen by the Runner (null if the Runner cannot be reached).
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function runnerState(): ?array
    {
        return app(RunnerClient::class)->state();
    }

    /**
     * One row per entry of the provider list, with today's usage.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        $list = app(ProviderList::class);
        $state = $this->runnerState;
        $timezone = config('kekeli.display_timezone');
        $rows = [];

        foreach ($list->entries() as $position => $entry) {
            $usage = ProviderUsage::query()->where([
                'day' => $list->quotaDay($entry['provider']),
                'provider' => $entry['provider'],
                'model' => $entry['model'],
            ])->first();

            $setAside = $state ? ($state['mis_de_cote'][$entry['key']] ?? null) : Cache::get('kekeli.set_aside.'.$entry['key']);
            $requests = (int) ($usage->requests ?? 0);
            $limit = $entry['daily_limit'];

            $rows[] = $entry + [
                'position' => $position + 1,
                'has_key' => $state ? in_array($entry['provider'], $state['cles_presentes'] ?? [], true) : null,
                'set_aside_until' => $setAside ? CarbonImmutable::parse($setAside)->setTimezone($timezone)->format('H:i') : null,
                'requests' => $requests,
                'rate_limited' => (int) ($usage->rate_limited ?? 0),
                'input_tokens' => (int) ($usage->input_tokens ?? 0),
                'output_tokens' => (int) (($usage->output_tokens ?? 0) + ($usage->reasoning_tokens ?? 0)),
                'percent' => $limit ? (int) floor($requests * 100 / $limit) : null,
                'reset_at' => $list->nextReset($entry['provider'])->setTimezone($timezone)->format('H:i'),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, AdminAlert>
     */
    #[Computed]
    public function alerts(): Collection
    {
        return AdminAlert::query()->unread()->latest()->latest('id')->limit(20)->get();
    }

    public function markAsRead(int $alertId): void
    {
        Gate::authorize('admin');
        AdminAlert::query()->whereKey($alertId)->update(['read_at' => now()]);
        unset($this->alerts);
    }

    public function markAllAsRead(): void
    {
        Gate::authorize('admin');
        AdminAlert::query()->unread()->update(['read_at' => now()]);
        unset($this->alerts);
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('AI providers') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Kekeli uses the providers in this order. When one reaches its limit, Kekeli automatically moves on to the next one.') }}</flux:text>
    </div>

    @if (! $this->runnerState)
        <flux:callout icon="exclamation-triangle" color="amber" :heading="__('The Runner cannot be reached')" :text="__('Keys and set-aside providers cannot be checked right now. The counters below are still up to date.')" />
    @endif

    <div class="flex flex-col gap-3">
        @forelse ($this->rows as $row)
            <div wire:key="provider-{{ $row['key'] }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-provider="{{ $row['key'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <flux:heading size="lg">{{ $row['position'] }}. {{ $row['label'] }}</flux:heading>
                        <flux:text class="break-all text-xs">{{ __('Model') }} : {{ $row['model'] }}</flux:text>
                    </div>
                    @if ($row['has_key'] === false)
                        <flux:badge color="zinc" size="sm">{{ __('Key missing, ignored') }}</flux:badge>
                    @elseif ($row['set_aside_until'])
                        <flux:badge color="red" size="sm">{{ __('Set aside until :time', ['time' => $row['set_aside_until']]) }}</flux:badge>
                    @elseif ($row['has_key'])
                        <flux:badge color="green" size="sm">{{ __('Available') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('State unknown') }}</flux:badge>
                    @endif
                </div>

                <div class="mt-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <span>{{ __('Requests today') }}</span>
                        <span class="font-medium">
                            {{ $row['requests'] }}
                            @if ($row['daily_limit']) / {{ $row['daily_limit'] }} @endif
                        </span>
                    </div>
                    @if ($row['daily_limit'])
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuenow="{{ min($row['percent'], 100) }}" aria-valuemin="0" aria-valuemax="100">
                            <div @class(['h-full rounded-full', 'bg-red-500' => $row['percent'] >= config('kekeli.alert_threshold'), 'bg-amber-500' => $row['percent'] < config('kekeli.alert_threshold')]) style="width: {{ min($row['percent'], 100) }}%"></div>
                        </div>
                        <flux:text class="mt-1 text-xs">{{ __('Estimated daily limit, to be checked in the provider’s console.') }}</flux:text>
                    @else
                        <flux:text class="mt-1 text-xs">{{ __('Daily limit not published by the provider.') }}</flux:text>
                    @endif

                    @if ($row['percent'] !== null && $row['percent'] >= config('kekeli.alert_threshold'))
                        <flux:callout class="mt-2" icon="bell-alert" color="red" :text="__('Warning: :percent % of the daily limit is used. It will soon be time to switch to a paid key.', ['percent' => $row['percent']])" />
                    @endif

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                        <dt>{{ __('Refusals for reached limit') }}</dt><dd class="text-right">{{ $row['rate_limited'] }}</dd>
                        <dt>{{ __('Tokens sent') }}</dt><dd class="text-right">{{ number_format($row['input_tokens'], 0, ',', ' ') }}</dd>
                        <dt>{{ __('Tokens received') }}</dt><dd class="text-right">{{ number_format($row['output_tokens'], 0, ',', ' ') }}</dd>
                        <dt>{{ __('Quota reset') }}</dt><dd class="text-right">{{ __(':time (Lomé time)', ['time' => $row['reset_at']]) }}</dd>
                    </dl>
                </div>
            </div>
        @empty
            <flux:callout icon="exclamation-triangle" color="red" :text="__('No provider is configured. Check the KEKELI_FOURNISSEURS setting.')" />
        @endforelse
    </div>

    <div>
        <div class="flex items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Alerts') }}</flux:heading>
            @if ($this->alerts->isNotEmpty())
                <flux:button size="sm" variant="ghost" wire:click="markAllAsRead">{{ __('Mark all as read') }}</flux:button>
            @endif
        </div>

        <div class="mt-2 flex flex-col gap-2">
            @forelse ($this->alerts as $alert)
                <div wire:key="alert-{{ $alert->id }}" class="flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950">
                    <div class="min-w-0">
                        <div class="break-words">{{ $alert->message }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ $alert->created_at->timezone(config('kekeli.display_timezone'))->translatedFormat('j F Y, H:i') }}</div>
                    </div>
                    <flux:button size="xs" variant="ghost" wire:click="markAsRead({{ $alert->id }})">{{ __('Mark as read') }}</flux:button>
                </div>
            @empty
                <div class="kekeli-vide"><span class="kekeli-vide-icone"><flux:icon.bell variant="mini" /></span><p>{{ __('No alert.') }}</p></div>
            @endforelse
        </div>
    </div>
</section>
