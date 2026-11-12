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

    private const GENERAL = ['web_search_monthly_cap', 'web_search_price_usd', 'pause_minutes', 'free_pause_minutes', 'erase_paused_after_days', 'free_daily_cap_fcfa', 'usd_to_fcfa', 'computer_vcpu', 'computer_ram_gib', 'e2b_usd_per_vcpu_second', 'e2b_usd_per_gib_second', 'image_price_usd', 'images_max_per_day', 'max_concurrent_runs', 'paid_priority_quota_percent', 'free_places_when_quota_low_percent', 'hosting_alert_usd', 'ci_fix_attempts_max', 'openai_daily_cap_usd', 'transcription_usd_per_minute'];

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
        // Les formules enregistrées avant le 12 novembre 2026 n'avaient ni période ni prix en dollars.
        $this->plans = collect($settings->get('plans'))->map(fn ($plan, $key) => [
            ...$plan,
            'price_usd' => (int) ($plan['price_usd'] ?? 0),
            'period' => \App\Agent\Energy::periodOf($plan) === 'month' ? 'month' : 'day',
            'web_searches_per_day' => (int) ($plan['web_searches_per_day'] ?? \App\Agent\WebSearchQuota::DEFAULT_PER_DAY[$key] ?? 3),
            'answer_model' => (string) ($plan['answer_model'] ?? config('kekeli.answer_models.'.$key, '')),
            'build_model' => (string) ($plan['build_model'] ?? config('kekeli.build_models.'.$key, '')),
        ])->all();
        $this->published = (bool) $settings->get('plans_published');
    }

    public function save(): void
    {
        Gate::authorize('admin');

        $this->validate([
            'general.pause_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'general.free_pause_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'general.erase_paused_after_days' => ['required', 'integer', 'min:1', 'max:365'],
            'general.usd_to_fcfa' => ['required', 'numeric', 'min:100', 'max:2000'],
            'general.free_daily_cap_fcfa' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'general.computer_vcpu' => ['required', 'numeric', 'min:1', 'max:8'],
            'general.computer_ram_gib' => ['required', 'numeric', 'min:0.5', 'max:8'],
            'general.e2b_usd_per_vcpu_second' => ['required', 'numeric', 'min:0', 'max:0.01'],
            'general.e2b_usd_per_gib_second' => ['required', 'numeric', 'min:0', 'max:0.01'],
            'general.image_price_usd' => ['required', 'numeric', 'min:0', 'max:1'],
            'general.images_max_per_day' => ['required', 'integer', 'min:0', 'max:100000'],
            'general.max_concurrent_runs' => ['required', 'integer', 'min:0', 'max:1000'],
            'general.paid_priority_quota_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'general.free_places_when_quota_low_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'general.hosting_alert_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
            'general.ci_fix_attempts_max' => ['required', 'integer', 'min:0', 'max:10'],
            'prices.*.input' => ['required', 'numeric', 'min:0', 'max:1000'],
            'prices.*.output' => ['required', 'numeric', 'min:0', 'max:1000'],
            'prices.*.cache' => ['required', 'numeric', 'min:0', 'max:1000'],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.name' => ['required', 'string', 'max:40'],
            'plans.*.price_fcfa' => ['required', 'integer', 'min:0', 'max:10000000'],
            'plans.*.energy_fcfa' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'plans.*.price_usd' => ['required', 'integer', 'min:0', 'max:100000'],
            'plans.*.period' => ['required', 'in:day,month'],
            'plans.*.web_searches_per_day' => ['required', 'integer', 'min:0', 'max:1000'],
            'plans.*.build_model' => ['nullable', 'string', 'max:160', fn ($attribute, $value, $fail) => blank($value) || \App\Agent\AnswerModels::isValid($value) ? null : $fail(__('This model cannot answer the questions.'))],
            'plans.*.answer_model' => ['nullable', 'string', 'max:160', fn ($attribute, $value, $fail) => blank($value) || \App\Agent\AnswerModels::isValid($value) ? null : $fail(__('This model cannot answer the questions.'))],
            'general.openai_daily_cap_usd' => ['required', 'numeric', 'min:0', 'max:1000'],
            'general.transcription_usd_per_minute' => ['required', 'numeric', 'min:0', 'max:1'],
            'general.web_search_monthly_cap' => ['required', 'integer', 'min:0', 'max:1000000'],
            'general.web_search_price_usd' => ['required', 'numeric', 'min:0', 'max:1'],
        ], [], [
            'general.pause_minutes' => __('minutes before the pause'),
            'general.free_pause_minutes' => __('minutes before the pause'),
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
            'price_usd' => (int) $plan['price_usd'],
            'energy_fcfa' => (float) $plan['energy_fcfa'],
            'period' => (string) $plan['period'],
            'window_hours' => 0,
            'web_searches_per_day' => (int) $plan['web_searches_per_day'],
            'answer_model' => trim((string) ($plan['answer_model'] ?? '')),
            'build_model' => trim((string) ($plan['build_model'] ?? '')),
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

        $this->plans[$key] = ['name' => trim($this->newPlan), 'price_fcfa' => 0, 'price_usd' => 0, 'energy_fcfa' => 0, 'period' => 'month', 'window_hours' => 0, 'web_searches_per_day' => 20, 'answer_model' => (string) config('kekeli.answer_models.pro', '')];
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
            <flux:input type="number" wire:model="general.free_pause_minutes" :label="__('Minutes before the pause for the free formula')" :description="__('Shorter for the free formula, its main real cost. The preview turns back on by itself when the client comes back.')" min="1" max="60" data-setting="free_pause_minutes" />
            <flux:input type="number" wire:model="general.erase_paused_after_days" :label="__('Days in pause before the computer is erased')" :description="__('The project stays saved in Kekeli and is put back in a new computer at the next request, without any loss.')" min="1" max="365" data-setting="erase_paused_after_days" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input type="number" step="1" wire:model="general.computer_vcpu" :label="__('Processors')" />
                <flux:input type="number" step="0.5" wire:model="general.computer_ram_gib" :label="__('Memory (GB)')" />
                <flux:input type="number" step="0.0000001" wire:model="general.e2b_usd_per_vcpu_second" :label="__('Dollars per processor and per second')" />
                <flux:input type="number" step="0.0000001" wire:model="general.e2b_usd_per_gib_second" :label="__('Dollars per GB and per second')" />
            </div>
            <flux:input type="number" step="1" wire:model="general.max_concurrent_runs" :label="__('Requests handled at the same time (all users)')" :description="__('The next ones wait for their turn with a clear message, without losing anything. The Hobby offer of E2B allows 10 computers switched on at the same time. 0 = no limit.')" data-setting="max_concurrent_runs" />
            <flux:input type="number" step="1" wire:model="general.paid_priority_quota_percent" :label="__('Paid clients first from this share of the free quota (%)')" :description="__('When the requests of the day to the free AI providers reach this percentage of their limits, free requests only use part of the places. 0 = never.')" data-setting="paid_priority_quota_percent" />
            <flux:input type="number" step="1" wire:model="general.free_places_when_quota_low_percent" :label="__('Places for free requests when the free quota is low (%)')" :description="__('Share of the places that free requests may still use (at least one). Paid requests always pass first in the waiting line.')" data-setting="free_places_when_quota_low_percent" />
            <flux:input type="number" step="1" wire:model="general.ci_fix_attempts_max" :label="__('Developer mode: automatic fixes when GitHub checks fail')" :description="__('Each fix is a request counted in the client\'s energy. Then Kekeli asks the client. 0 = never fix automatically.')" data-setting="ci_fix_attempts_max" />
            <flux:input type="number" step="0.1" wire:model="general.usd_to_fcfa" :label="__('1 dollar in FCFA')" />
            <flux:input type="number" step="0.0001" wire:model="general.image_price_usd" :label="__('Price of one created image (dollars)')" :description="__('Counted in the client\'s energy for each image created by Kekeli.')" data-setting="image_price_usd" />
            <flux:input type="number" step="1" wire:model="general.web_search_monthly_cap" :label="__('Internet searches per month (all users)')" :description="__('Keeps Kekeli within the free offers of Tavily (about 1 000 searches a month) and Exa. Beyond it, Kekeli answers without searching and says so politely.')" data-web-search-cap />
            <flux:input type="number" step="0.001" wire:model="general.web_search_price_usd" :label="__('Price of one Internet search (dollars)')" :description="__('Counted in the client\'s energy for each search, like the created images.')" />
            <flux:input type="number" step="1" wire:model="general.images_max_per_day" :label="__('Maximum number of images created per day (all users)')" :description="__('Keeps Kekeli within the free offer of Cloudflare (about 170 images per day, estimate). 0 = no image created.')" data-setting="images_max_per_day" />
        </div>

        <div class="flex flex-col gap-3" data-hosting>
            <flux:heading size="lg">{{ __('Hosting of Kekeli (Laravel Cloud)') }}</flux:heading>
            @php($lastCost = app(\App\Support\Settings::class)->get('hosting_last_cost'))
            <flux:text class="text-sm" data-hosting-cost>
                @if (! app(\App\Hosting\HostingCost::class)->configured())
                    {{ __('The reading key of Laravel Cloud is not placed yet: the cost of Kekeli is not read.') }}
                @elseif (is_array($lastCost))
                    {{ __('Cost of Kekeli this month: :cost $ (read on :date, Lomé time). Talkami and Lomé IA School are not counted.', ['cost' => number_format((float) $lastCost['usd'], 2, ',', ' '), 'date' => \Illuminate\Support\Carbon::parse($lastCost['read_at'])->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]) }}
                @else
                    {{ __('The cost of Kekeli will be read tonight.') }}
                @endif
            </flux:text>
            <flux:input type="number" step="1" wire:model="general.hosting_alert_usd" :label="__('Alert threshold for the cost of Kekeli (dollars per month)')" :description="__('You receive an alert at 80 % then at 100 %. Nothing is ever stopped. 0 = no alert.')" data-setting="hosting_alert_usd" />
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
            <flux:text class="text-sm">{{ __('Energy = real cost allowed per period, in FCFA. Every day: recharged at midnight (Lomé time). Every month: for each month of the subscription.') }}</flux:text>
            @foreach ($plans as $key => $plan)
                <div wire:key="plan-{{ $key }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-plan-setting="{{ $key }}">
                    <div class="mb-2 flex items-center gap-2">
                        <flux:input size="sm" wire:model="plans.{{ $key }}.name" :label="__('Name')" class="flex-1" />
                        @if (count($plans) > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removePlan('{{ $key }}')" :aria-label="__('Remove :name', ['name' => $plan['name']])" class="mt-6" />
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <flux:input size="sm" type="number" wire:model="plans.{{ $key }}.price_fcfa" :label="__('Price (FCFA per month)')" />
                        <flux:input size="sm" type="number" wire:model="plans.{{ $key }}.price_usd" :label="__('Price (dollars per month)')" />
                        <flux:input size="sm" type="number" step="0.1" wire:model="plans.{{ $key }}.energy_fcfa" :label="__('Energy (FCFA)')" />
                        <flux:select size="sm" wire:model="plans.{{ $key }}.period" :label="__('Recharge')">
                            <flux:select.option value="day">{{ __('Every day') }}</flux:select.option>
                            <flux:select.option value="month">{{ __('Every month') }}</flux:select.option>
                        </flux:select>
                        <flux:input size="sm" type="number" step="1" wire:model="plans.{{ $key }}.web_searches_per_day" :label="__('Internet searches per day')" data-plan-searches />
                    </div>
                    <flux:select size="sm" class="mt-2" wire:model="plans.{{ $key }}.answer_model" :label="__('Model that answers the questions')" data-plan-answer-model>
                        <flux:select.option value="">{{ __('Free providers only') }}</flux:select.option>
                        @foreach (\App\Agent\AnswerModels::OPENAI_MODELS as $model)
                            <flux:select.option value="openai/{{ $model }}">{{ data_get(app(\App\Support\Settings::class)->get('model_prices'), 'openai/'.$model.'.label', $model) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="plans.{{ $key }}.answer_model" />
                    <flux:select size="sm" class="mt-2" wire:model="plans.{{ $key }}.build_model" :label="__('Model that builds the sites and applications')" data-plan-build-model>
                        <flux:select.option value="">{{ __('Free providers only') }}</flux:select.option>
                        @foreach (\App\Agent\AnswerModels::OPENAI_MODELS as $model)
                            <flux:select.option value="openai/{{ $model }}">{{ data_get(app(\App\Support\Settings::class)->get('model_prices'), 'openai/'.$model.'.label', $model) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="plans.{{ $key }}.build_model" />
                </div>
            @endforeach
            <div class="flex items-start gap-2">
                <flux:input size="sm" wire:model="newPlan" :placeholder="__('Name of a new formula')" :aria-label="__('Name of a new formula')" class="flex-1" />
                <flux:button size="sm" wire:click="addPlan">{{ __('Add') }}</flux:button>
            </div>
            <flux:error name="newPlan" />
            <flux:input type="number" step="0.1" wire:model="general.openai_daily_cap_usd" :label="__('Daily ceiling of OpenAI spending (dollars)')" :description="__('Answers and dictation together. Spent today: :spent $. When the ceiling is reached, Kekeli answers with the free providers without telling the client. 0 = OpenAI never used.', ['spent' => number_format(app(\App\Agent\AnswerModels::class)->openAiSpentToday(), 4, ',', ' ')])" data-setting="openai_daily_cap_usd" />
            <flux:input type="number" step="0.0001" wire:model="general.transcription_usd_per_minute" :label="__('Price of one minute of dictation (dollars)')" :description="__('Only when the browser cannot write the dictation itself. Counted in the client\'s energy.')" data-setting="transcription_usd_per_minute" />
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
