<?php

use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<string, int|float> */
    public array $general = [];

    /** @var array<string, array<string, mixed>> */
    public array $prices = [];

    /** @var array<string, array<string, mixed>> */
    public array $plans = [];

    public bool $published = false;

    public string $newPlan = '';

    private const GENERAL = ['pause_minutes', 'erase_paused_after_days', 'free_daily_cap_fcfa', 'usd_to_fcfa', 'computer_vcpu', 'computer_ram_gib', 'e2b_usd_per_vcpu_second', 'e2b_usd_per_gib_second', 'image_price_usd'];

    public function mount(): void
    {
        Gate::authorize('admin');
        $this->load();
    }

    public function rendering($view): void
    {
        $view->title(__('Settings (administration)'));
    }

    private function load(): void
    {
        $settings = app(Settings::class);

        foreach (self::GENERAL as $key) {
            $this->general[$key] = $settings->get($key);
        }

        // Liste numérotée : les noms des modèles contiennent des points, que Livewire lirait comme des séparateurs.
        $this->prices = collect($settings->get('model_prices'))->map(fn ($price, $key) => ['key' => $key, ...$price])->values()->all();
        $this->plans = $settings->get('plans');
        $this->published = (bool) $settings->get('plans_published');
    }

    public function save(): void
    {
        Gate::authorize('admin');

        $this->validate([
            'general.pause_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'general.erase_paused_after_days' => ['required', 'integer', 'min:1', 'max:365'],
            'general.usd_to_fcfa' => ['required', 'numeric', 'min:100', 'max:2000'],
            'general.free_daily_cap_fcfa' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'general.computer_vcpu' => ['required', 'numeric', 'min:1', 'max:8'],
            'general.computer_ram_gib' => ['required', 'numeric', 'min:0.5', 'max:8'],
            'general.e2b_usd_per_vcpu_second' => ['required', 'numeric', 'min:0', 'max:0.01'],
            'general.e2b_usd_per_gib_second' => ['required', 'numeric', 'min:0', 'max:0.01'],
            'general.image_price_usd' => ['required', 'numeric', 'min:0', 'max:1'],
            'prices.*.input' => ['required', 'numeric', 'min:0', 'max:1000'],
            'prices.*.output' => ['required', 'numeric', 'min:0', 'max:1000'],
            'prices.*.cache' => ['required', 'numeric', 'min:0', 'max:1000'],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.name' => ['required', 'string', 'max:40'],
            'plans.*.price_fcfa' => ['required', 'integer', 'min:0', 'max:10000000'],
            'plans.*.energy_fcfa' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'plans.*.window_hours' => ['required', 'integer', 'min:0', 'max:744'],
        ], [], [
            'general.pause_minutes' => __('minutes before the pause'),
            'general.erase_paused_after_days' => __('days before erasing'),
            'general.usd_to_fcfa' => __('exchange rate'),
        ]);

        $settings = app(Settings::class);

        foreach (self::GENERAL as $key) {
            $settings->set($key, $this->general[$key] + 0);
        }

        $settings->set('model_prices', collect($this->prices)->mapWithKeys(fn ($price) => [(string) $price['key'] => [
            'label' => (string) $price['label'],
            'input' => (float) $price['input'],
            'output' => (float) $price['output'],
            'cache' => (float) $price['cache'],
        ]])->all());

        $settings->set('plans', collect($this->plans)->map(fn ($plan) => [
            'name' => trim((string) $plan['name']),
            'price_fcfa' => (int) $plan['price_fcfa'],
            'energy_fcfa' => (float) $plan['energy_fcfa'],
            'window_hours' => (int) $plan['window_hours'],
        ])->all());

        $settings->set('plans_published', $this->published);

        $this->dispatch('settings-saved');
    }

    public function addPlan(): void
    {
        $this->validate(['newPlan' => ['required', 'string', 'max:40']], [], ['newPlan' => __('name of the formula')]);

        $key = Str::slug($this->newPlan);
        if ($key === '' || isset($this->plans[$key])) {
            $this->addError('newPlan', __('This formula already exists.'));

            return;
        }

        $this->plans[$key] = ['name' => trim($this->newPlan), 'price_fcfa' => 0, 'energy_fcfa' => 0, 'window_hours' => 5];
        $this->reset('newPlan');
    }

    public function removePlan(string $key): void
    {
        if (count($this->plans) > 1) {
            unset($this->plans[$key]);
        }
    }

    public function restoreDefaults(): void
    {
        Gate::authorize('admin');

        foreach ([...self::GENERAL, 'model_prices', 'plans', 'plans_published'] as $key) {
            app(Settings::class)->reset($key);
        }

        $this->load();
        $this->dispatch('settings-saved');
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Settings (administration)') }}</flux:heading>
        <flux:subheading>{{ __('Change Kekeli\'s rules without touching the code. Prices are estimations until they are measured.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Computers') }}</flux:heading>
            <flux:input type="number" wire:model="general.pause_minutes" :label="__('Minutes without a request before the computer pauses')" :description="__('A running computer is paid by the second; a paused computer is not.')" min="5" max="60" data-setting="pause_minutes" />
            <flux:input type="number" wire:model="general.erase_paused_after_days" :label="__('Days in pause before the computer is erased')" :description="__('The project stays saved in Kekeli and is put back in a new computer at the next request, without any loss.')" min="1" max="365" data-setting="erase_paused_after_days" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input type="number" step="1" wire:model="general.computer_vcpu" :label="__('Processors')" />
                <flux:input type="number" step="0.5" wire:model="general.computer_ram_gib" :label="__('Memory (GB)')" />
                <flux:input type="number" step="0.0000001" wire:model="general.e2b_usd_per_vcpu_second" :label="__('Dollars per processor and per second')" />
                <flux:input type="number" step="0.0000001" wire:model="general.e2b_usd_per_gib_second" :label="__('Dollars per GB and per second')" />
            </div>
            <flux:input type="number" step="0.1" wire:model="general.usd_to_fcfa" :label="__('1 dollar in FCFA')" />
            <flux:input type="number" step="0.0001" wire:model="general.image_price_usd" :label="__('Price of one created image (dollars)')" :description="__('Counted in the client\'s energy for each image created by Kekeli.')" data-setting="image_price_usd" />
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Price of the models (dollars per million tokens)') }}</flux:heading>
            @foreach ($prices as $index => $price)
                <div wire:key="price-{{ $price['key'] }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="mb-2 text-sm font-medium">{{ $price['label'] }} <span class="text-xs text-zinc-500">({{ $price['key'] }})</span></div>
                    <div class="grid grid-cols-3 gap-2">
                        <flux:input size="sm" type="number" step="0.001" wire:model="prices.{{ $index }}.input" :label="__('Input')" />
                        <flux:input size="sm" type="number" step="0.001" wire:model="prices.{{ $index }}.output" :label="__('Output')" />
                        <flux:input size="sm" type="number" step="0.001" wire:model="prices.{{ $index }}.cache" :label="__('Cache')" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Formulas') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Energy = real cost allowed per period, in FCFA. A period of 0 hours means every day (midnight, Lomé time).') }}</flux:text>
            @foreach ($plans as $key => $plan)
                <div wire:key="plan-{{ $key }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-plan-setting="{{ $key }}">
                    <div class="mb-2 flex items-center gap-2">
                        <flux:input size="sm" wire:model="plans.{{ $key }}.name" :label="__('Name')" class="flex-1" />
                        @if (count($plans) > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removePlan('{{ $key }}')" :aria-label="__('Remove :name', ['name' => $plan['name']])" class="mt-6" />
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <flux:input size="sm" type="number" wire:model="plans.{{ $key }}.price_fcfa" :label="__('Price (FCFA per month)')" />
                        <flux:input size="sm" type="number" step="0.1" wire:model="plans.{{ $key }}.energy_fcfa" :label="__('Energy (FCFA)')" />
                        <flux:input size="sm" type="number" wire:model="plans.{{ $key }}.window_hours" :label="__('Period (hours)')" />
                    </div>
                </div>
            @endforeach
            <div class="flex items-start gap-2">
                <flux:input size="sm" wire:model="newPlan" :placeholder="__('Name of a new formula')" :aria-label="__('Name of a new formula')" class="flex-1" />
                <flux:button size="sm" wire:click="addPlan">{{ __('Add') }}</flux:button>
            </div>
            <flux:error name="newPlan" />
            <flux:input type="number" step="1" wire:model="general.free_daily_cap_fcfa" :label="__('Daily ceiling for all free users together (FCFA)')" :description="__('When it is reached, free users wait until midnight (Lomé time) and you receive an alert. 0 = no ceiling.')" data-setting="free_daily_cap_fcfa" />
            <flux:checkbox wire:model="published" :label="__('Publish the formulas (only after the project owner has approved the prices)')" data-setting="plans_published" />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button wire:click="restoreDefaults" variant="ghost">{{ __('Restore the default values') }}</flux:button>
            <x-action-message on="settings-saved">{{ __('Saved.') }}</x-action-message>
        </div>
    </form>
</section>
