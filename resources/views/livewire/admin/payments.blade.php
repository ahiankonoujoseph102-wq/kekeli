<?php

use App\Billing\Subscriptions;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<int, string> Reason of a refusal, by payment. */
    public array $reasons = [];

    /** @var list<array{code: string, holder: string, methods: list<array{name: string, number: string}>}> */
    public array $countries = [];

    public string $whatsapp = '';

    public string $newCountry = '';

    public function mount(): void
    {
        Gate::authorize('admin');
        $this->load();
    }

    public function rendering($view): void
    {
        $view->title(__('Payments (administration)'));
    }

    private function load(): void
    {
        $this->countries = collect(app(Subscriptions::class)->paymentCountries())
            ->map(fn ($place, $code) => ['code' => (string) $code, 'holder' => (string) $place['holder'], 'methods' => array_values($place['methods'])])
            ->values()->all();
        $this->whatsapp = (string) app(Settings::class)->get('payment_whatsapp');
    }

    #[Computed]
    public function pending()
    {
        return Payment::query()->with('user')->where('status', Payment::STATUS_PENDING)->oldest('id')->get();
    }

    #[Computed]
    public function history()
    {
        return Payment::query()->with('user')->where('status', '!=', Payment::STATUS_PENDING)->latest('reviewed_at')->latest('id')->limit(50)->get();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function plans(): array
    {
        return app(Settings::class)->get('plans');
    }

    public function validatePayment(int $id): void
    {
        Gate::authorize('admin');
        $payment = Payment::query()->where('status', Payment::STATUS_PENDING)->findOrFail($id);
        app(Subscriptions::class)->validate($payment, Auth::user());
        unset($this->pending, $this->history);
    }

    public function refusePayment(int $id): void
    {
        Gate::authorize('admin');
        $payment = Payment::query()->where('status', Payment::STATUS_PENDING)->findOrFail($id);
        app(Subscriptions::class)->refuse($payment, Auth::user(), (string) ($this->reasons[$id] ?? ''));
        unset($this->reasons[$id], $this->pending, $this->history);
    }

    public function addMethod(int $index): void
    {
        $this->countries[$index]['methods'][] = ['name' => '', 'number' => ''];
    }

    public function removeMethod(int $index, int $method): void
    {
        unset($this->countries[$index]['methods'][$method]);
        $this->countries[$index]['methods'] = array_values($this->countries[$index]['methods']);
    }

    public function addCountry(): void
    {
        $this->validate(['newCountry' => ['required', 'string', 'in:'.implode(',', array_keys(config('kekeli.countries')))]], [], ['newCountry' => __('country')]);

        if (collect($this->countries)->contains('code', $this->newCountry)) {
            $this->addError('newCountry', __('This country is already in the list.'));

            return;
        }

        $this->countries[] = ['code' => $this->newCountry, 'holder' => '', 'methods' => [['name' => '', 'number' => '']]];
        $this->reset('newCountry');
    }

    public function removeCountry(int $index): void
    {
        unset($this->countries[$index]);
        $this->countries = array_values($this->countries);
    }

    public function saveNumbers(): void
    {
        Gate::authorize('admin');

        $this->validate([
            'countries.*.holder' => ['required', 'string', 'max:80'],
            'countries.*.methods' => ['required', 'array', 'min:1'],
            'countries.*.methods.*.name' => ['required', 'string', 'max:40'],
            'countries.*.methods.*.number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ]{6,}$/'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ]{6,}$/'],
        ], [], [
            'countries.*.holder' => __('holder'),
            'countries.*.methods.*.name' => __('operator'),
            'countries.*.methods.*.number' => __('number'),
            'whatsapp' => __('WhatsApp number'),
        ]);

        $settings = app(Settings::class);
        $settings->set('payment_countries', collect($this->countries)->mapWithKeys(fn ($place) => [$place['code'] => [
            'holder' => trim($place['holder']),
            'methods' => collect($place['methods'])->map(fn ($method) => ['name' => trim($method['name']), 'number' => trim($method['number'])])->values()->all(),
        ]])->all());
        $settings->set('payment_whatsapp', trim($this->whatsapp));

        $this->load();
        $this->dispatch('numbers-saved');
    }
}; ?>

<section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Payments (administration)') }}</flux:heading>
        <flux:subheading>{{ __('Check each screenshot in the history of your Mobile Money account, then validate or refuse. A validated formula is active for 30 days.') }}</flux:subheading>
    </div>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Waiting for your check (:count)', ['count' => $this->pending->count()]) }}</flux:heading>
        @forelse ($this->pending as $payment)
            <div wire:key="pending-{{ $payment->id }}" class="rounded-xl border border-amber-300 p-3 dark:border-amber-700" data-pending-payment="{{ $payment->id }}">
                <div class="text-sm">
                    <span class="font-semibold">{{ $this->plans[$payment->plan]['name'] ?? $payment->plan }}</span>
                    · {{ number_format($payment->amount_fcfa, 0, ',', ' ') }} FCFA
                    · {{ config('kekeli.countries.'.$payment->country.'.name', $payment->country) }}
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $payment->user->name }} — {{ $payment->user->email }}</div>
                <div class="text-xs text-zinc-500">{{ __('Sent on :date (Lomé time)', ['date' => $payment->created_at->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]) }}</div>
                <a href="{{ route('admin.payments.screenshot', $payment) }}" target="_blank" class="mt-2 block">
                    <img src="{{ route('admin.payments.screenshot', $payment) }}" alt="{{ __('Screenshot of the payment') }}" class="max-h-96 w-auto rounded-lg border border-zinc-200 dark:border-zinc-700" loading="lazy">
                </a>
                <div class="mt-3 flex flex-col gap-2">
                    <flux:button variant="primary" icon="check" wire:click="validatePayment({{ $payment->id }})" wire:confirm="{{ __('Validate this payment and activate the formula for 30 days?') }}" data-validate-payment>{{ __('Validate') }}</flux:button>
                    <flux:input size="sm" wire:model="reasons.{{ $payment->id }}" :placeholder="__('Reason of the refusal (seen by the client)')" :aria-label="__('Reason of the refusal (seen by the client)')" />
                    <flux:button variant="danger" icon="x-mark" wire:click="refusePayment({{ $payment->id }})" data-refuse-payment>{{ __('Refuse') }}</flux:button>
                </div>
            </div>
        @empty
            <flux:text>{{ __('No payment is waiting.') }}</flux:text>
        @endforelse
    </div>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('History') }}</flux:heading>
        @forelse ($this->history as $payment)
            <div wire:key="history-{{ $payment->id }}" class="rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                <span class="font-medium">{{ $payment->user->email }}</span>
                · {{ $this->plans[$payment->plan]['name'] ?? $payment->plan }}
                · {{ number_format($payment->amount_fcfa, 0, ',', ' ') }} FCFA
                ·
                @if ($payment->status === \App\Models\Payment::STATUS_VALIDATED)
                    <span class="text-green-700 dark:text-green-400">{{ __('Validated, active until :date', ['date' => $payment->expires_at?->timezone(config('kekeli.display_timezone'))->format('d/m/Y')]) }}</span>
                @else
                    <span class="text-red-700 dark:text-red-400">{{ __('Refused') }}@if ($payment->refusal_reason) : {{ $payment->refusal_reason }}@endif</span>
                @endif
            </div>
        @empty
            <flux:text>{{ __('No payment yet.') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="saveNumbers" class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Payment numbers') }}</flux:heading>
        <flux:text class="text-sm">{{ __('The client chooses a country and sees these numbers and the name of the holder. In Togo, always write « Mixx by Yas ».') }}</flux:text>
        @foreach ($countries as $index => $place)
            <div wire:key="country-{{ $place['code'] }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-payment-country-setting="{{ $place['code'] }}">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="font-medium">{{ config('kekeli.countries.'.$place['code'].'.name', $place['code']) }}</span>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeCountry({{ $index }})" :aria-label="__('Remove')" />
                </div>
                <flux:input size="sm" wire:model="countries.{{ $index }}.holder" :label="__('Holder of the numbers')" />
                @foreach ($place['methods'] as $m => $method)
                    <div wire:key="country-{{ $place['code'] }}-{{ $m }}" class="mt-2 flex items-end gap-2">
                        <flux:input size="sm" wire:model="countries.{{ $index }}.methods.{{ $m }}.name" :label="__('Operator')" class="flex-1" />
                        <flux:input size="sm" wire:model="countries.{{ $index }}.methods.{{ $m }}.number" :label="__('Number')" class="flex-1" />
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeMethod({{ $index }}, {{ $m }})" :aria-label="__('Remove')" />
                    </div>
                @endforeach
                <flux:button size="sm" variant="subtle" icon="plus" class="mt-2" wire:click="addMethod({{ $index }})">{{ __('Add an operator') }}</flux:button>
            </div>
        @endforeach
        <div class="flex items-end gap-2">
            <flux:select size="sm" wire:model="newCountry" :label="__('Add a country')" class="flex-1">
                <flux:select.option value="">—</flux:select.option>
                @foreach (config('kekeli.countries') as $code => $details)
                    <flux:select.option value="{{ $code }}">{{ $details['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button size="sm" wire:click="addCountry">{{ __('Add') }}</flux:button>
        </div>
        <flux:error name="newCountry" />
        <flux:input wire:model="whatsapp" :label="__('WhatsApp number for the other countries')" data-setting="payment_whatsapp" />
        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <x-action-message on="numbers-saved">{{ __('Saved.') }}</x-action-message>
        </div>
    </form>
</section>
