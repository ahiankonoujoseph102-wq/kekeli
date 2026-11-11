<?php

use App\Billing\Subscriptions;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public string $plan = '';

    public string $country = '';

    /** Screenshot of the payment confirmation, chosen on the phone. */
    public $screenshot = null;

    public function mount(string $plan): void
    {
        $paid = app(Subscriptions::class)->paidPlans();
        abort_unless(isset($paid[$plan]) && app(Settings::class)->get('plans_published'), 404);

        $this->plan = $plan;
        $countries = app(Subscriptions::class)->paymentCountries();
        $this->country = isset($countries[Auth::user()->country]) ? (string) Auth::user()->country : 'autre';
    }

    public function rendering($view): void
    {
        $view->title(__('Pay the :plan formula', ['plan' => $this->details['name']]));
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function details(): array
    {
        return app(Subscriptions::class)->paidPlans()[$this->plan];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function countries(): array
    {
        return app(Subscriptions::class)->paymentCountries();
    }

    #[Computed]
    public function pending(): ?Payment
    {
        return Auth::user()->payments()->where('status', Payment::STATUS_PENDING)->first();
    }

    /**
     * Link to write to Kekeli on WhatsApp, for the countries without Mobile Money numbers.
     */
    #[Computed]
    public function whatsappLink(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) app(Settings::class)->get('payment_whatsapp'));
        if ($number === '') {
            return null;
        }
        $country = config('kekeli.countries.'.Auth::user()->country.'.name', '');
        $text = __('Hello, I would like to pay the :plan formula of Kekeli (:price FCFA) from :country. My account: :email', [
            'plan' => $this->details['name'],
            'price' => number_format((int) $this->details['price_fcfa'], 0, ',', ' '),
            'country' => $country ?: __('my country'),
            'email' => Auth::user()->email,
        ]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    public function send(): void
    {
        if ($this->pending || ! isset($this->countries[$this->country])) {
            return;
        }

        $this->validate([
            'screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [], ['screenshot' => __('screenshot')]);

        $extension = strtolower($this->screenshot->extension() ?: 'jpg');
        $path = $this->screenshot->storeAs('paiements/'.Auth::id(), Str::random(24).'.'.$extension, Payment::disk());

        app(Subscriptions::class)->submit(Auth::user(), $this->plan, $this->country, $path);

        $this->reset('screenshot');
        unset($this->pending);
    }
}; ?>

<section class="mx-auto flex w-full max-w-xl flex-col gap-4">
    <div>
        <flux:link :href="route('plans')" wire:navigate class="text-sm">← {{ __('Formulas') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ __('Pay the :plan formula', ['plan' => $this->details['name']]) }}</flux:heading>
        <flux:subheading>{{ __(':price FCFA for 30 days, paid by Mobile Money.', ['price' => number_format((int) $this->details['price_fcfa'], 0, ',', ' ')]) }}</flux:subheading>
    </div>

    @if ($this->pending)
        <div class="rounded-xl border border-sky-300 bg-sky-50 p-4 dark:border-sky-700 dark:bg-sky-950" data-payment-pending>
            <p class="font-medium text-sky-800 dark:text-sky-200">{{ __('Your payment is being checked. Your formula will be active as soon as it is validated (usually within a few hours).') }}</p>
        </div>
    @else
        <flux:select wire:model.live="country" :label="__('Country where you pay')" data-payment-country>
            @foreach ($this->countries as $code => $details)
                <flux:select.option value="{{ $code }}">{{ config('kekeli.countries.'.$code.'.name', $code) }}</flux:select.option>
            @endforeach
            <flux:select.option value="autre">{{ __('Another country') }}</flux:select.option>
        </flux:select>

        @if (isset($this->countries[$country]))
            @php($place = $this->countries[$country])
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-payment-numbers>
                <p class="text-sm font-medium">{{ __('1. Send :price FCFA to one of these numbers:', ['price' => number_format((int) $this->details['price_fcfa'], 0, ',', ' ')]) }}</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($place['methods'] as $method)
                        <li wire:key="method-{{ $loop->index }}" class="flex items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800" x-data="{ copie: false }">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold">{{ $method['name'] }}</span>
                                <span class="block font-mono text-base">{{ $method['number'] }}</span>
                            </span>
                            <flux:button size="sm" variant="subtle" icon="clipboard" x-on:click="navigator.clipboard?.writeText('{{ preg_replace('/\s+/', '', $method['number']) }}'); copie = true; setTimeout(() => copie = false, 2000)">
                                <span x-show="! copie">{{ __('Copy') }}</span><span x-show="copie" x-cloak>{{ __('Copied') }}</span>
                            </flux:button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm">{{ __('Holder of the numbers: :holder', ['holder' => $place['holder']]) }}</p>
                <p class="mt-4 text-sm font-medium">{{ __('2. Take a screenshot of the confirmation message.') }}</p>
                <p class="mt-1 text-sm font-medium">{{ __('3. Send the screenshot here.') }}</p>

                <form wire:submit="send" class="mt-3 space-y-3">
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 p-5 text-center dark:border-zinc-600">
                        <flux:icon.photo class="size-8 text-amber-500" />
                        <span class="text-sm font-medium">{{ __('Choose the screenshot on your phone') }}</span>
                        <input type="file" wire:model="screenshot" accept="image/jpeg,image/png,image/webp" class="sr-only" data-screenshot-input>
                    </label>
                    <div wire:loading wire:target="screenshot" class="text-sm text-zinc-500">{{ __('Sending the photo…') }}</div>
                    @if ($screenshot)
                        <flux:text class="text-sm" data-screenshot-chosen>{{ __('Screenshot chosen.') }}</flux:text>
                    @endif
                    <flux:error name="screenshot" />
                    <flux:button type="submit" variant="primary" class="w-full" data-send-payment>{{ __('Send my payment') }}</flux:button>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-payment-other-country>
                @if ($this->whatsappLink)
                    <p class="text-sm">{{ __('Mobile Money payment is not yet available in your country. Write to us on WhatsApp: we will find a solution together.') }}</p>
                    <flux:button :href="$this->whatsappLink" target="_blank" variant="primary" icon="chat-bubble-left-right" class="mt-3 w-full !bg-[#25d366] !border-[#25d366]">{{ __('Write on WhatsApp') }}</flux:button>
                @else
                    <p class="text-sm" data-payment-unavailable>{{ __('Payment is not yet open in your country. Come back soon: Kekeli will tell you here as soon as it is possible.') }}</p>
                @endif
            </div>
        @endif
    @endif
</section>
