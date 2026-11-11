<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $country = '';

    public bool $terms = false;

    public function mount(): void
    {
        $this->country = (string) config('kekeli.default_country');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'country' => ['required', 'string', Rule::in(array_keys(config('kekeli.countries')))],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please accept the conditions of use and the privacy policy.'),
        ]);
        unset($validated['terms']);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));
        $user->forceFill(['terms_accepted_at' => now()])->save();

        Auth::login($user);

        $this->redirectIntended(default: route('projects.index', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

    <x-pending-site />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <div class="grid gap-2">
            <flux:input wire:model="name" id="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" :placeholder="__('Full name')" />
        </div>

        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
        </div>

        <!-- Country -->
        <div class="grid gap-2">
            <flux:select wire:model="country" id="country" name="country" :label="__('Your country')" :description="__('Kekeli adapts photos, prices and phone numbers to your country.')">
                @foreach (config('kekeli.countries') as $code => $details)
                    <flux:select.option value="{{ $code }}">{{ $details['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
            />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
            />
        </div>

        <div class="grid gap-1">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="terms" class="mt-1 size-4 shrink-0 accent-amber-500" data-accept-terms>
                <span>
                    {!! __('I accept the :terms and the :privacy.', [
                        'terms' => '<a href="'.route('legal.terms').'" target="_blank" class="font-medium underline">'.e(__('conditions of use')).'</a>',
                        'privacy' => '<a href="'.route('legal.privacy').'" target="_blank" class="font-medium underline">'.e(__('privacy policy')).'</a>',
                    ]) !!}
                </span>
            </label>
            <flux:error name="terms" />
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <x-text-link href="{{ route('login') }}">{{ __('Log in') }}</x-text-link>
    </div>
</div>
