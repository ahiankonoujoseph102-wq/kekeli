<?php

use App\Auth\LoginCodes;
use App\Mail\Emails;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * Une seule page pour se connecter ou créer un compte, comme Claude et ChatGPT : « Continuer avec Google » (seulement
 * quand Google est réglé), « ou », puis l'e-mail et « Continuer avec l'e-mail » ; Kekeli envoie un code à 6 chiffres
 * et un lien. Un nouveau compte donne ensuite son nom (et son pays, et accepte les conditions) en une étape. Les
 * anciens comptes avec un mot de passe gardent le lien discret « Se connecter avec un mot de passe ».
 */
new #[Layout('components.layouts.auth')] class extends Component {
    /** email, code, name (nouveau compte) ou password. */
    public string $step = 'email';

    public string $email = '';

    public string $code = '';

    public string $password = '';

    public string $name = '';

    public string $country = '';

    public bool $terms = false;

    public ?string $notice = null;

    /** Kept signed in on this device, like Lovable or Bolt.new (no « Remember me » box to tick). */
    public bool $remember = true;

    public function mount(): void
    {
        $this->country = (string) config('kekeli.default_country');
        $pending = $this->pendingAccount();
        if ($pending) {
            $this->step = 'name';
            $this->email = $pending['email'];
            $this->name = (string) ($pending['name'] ?? '');
        } elseif (request()->query('etape') === 'mot-de-passe') {
            $this->step = 'password';
        }
    }

    /** The address proved by a code, a link or Google, waiting for the name of the new account (30 minutes). */
    private function pendingAccount(): ?array
    {
        $pending = session('kekeli.compte_a_creer');

        return is_array($pending) && ($pending['until'] ?? 0) > time() && ! User::query()->where('email', $pending['email'] ?? '')->exists() ? $pending : null;
    }

    public function continueWithEmail(LoginCodes $codes): void
    {
        $this->email = LoginCodes::normalize($this->email);
        $this->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        if ($codes->send($this->email, (string) request()->ip()) === LoginCodes::TOO_MANY) {
            throw ValidationException::withMessages(['email' => __('Too many codes asked. Try again in a little while.')]);
        }
        $this->reset('code');
        $this->resetErrorBag();
        $this->step = 'code';
        // Toujours la même phrase, que le compte existe ou non.
        $this->notice = __('We sent a code to :email. It is valid 10 minutes.', ['email' => $this->email]);
    }

    public function resend(LoginCodes $codes): void
    {
        if ($codes->send($this->email, (string) request()->ip()) === LoginCodes::TOO_MANY) {
            $this->addError('code', __('Too many codes asked. Try again in a little while.'));

            return;
        }
        $this->reset('code');
        $this->resetErrorBag();
        $this->notice = __('A new code was sent to :email.', ['email' => $this->email]);
    }

    public function verifyCode(LoginCodes $codes): void
    {
        $this->validate(['code' => ['required', 'string', 'max:12']]);
        $result = $codes->check($this->email, $this->code);
        if ($result === LoginCodes::WRONG) {
            $this->addError('code', __('This code is not the right one. Check it and try again.'));

            return;
        }
        if ($result === LoginCodes::EXPIRED) {
            $this->addError('code', __('This code has expired or was tried too many times. Ask for a new one.'));

            return;
        }
        $this->signInOrAskName($this->email);
    }

    private function signInOrAskName(string $email): void
    {
        $user = LoginCodes::accountFor($email);
        if ($user) {
            Auth::login($user, $this->remember);
            Session::regenerate();
            $this->redirectIntended(default: route('projects.index', absolute: false), navigate: true);

            return;
        }
        session()->put('kekeli.compte_a_creer', ['email' => $email, 'until' => time() + 1800]);
        $this->step = 'name';
        $this->notice = null;
    }

    /** The last step of a new account: the name, the country, and the conditions accepted. */
    public function createAccount(Emails $emails): void
    {
        $pending = $this->pendingAccount();
        if (! $pending) {
            $this->step = 'email';
            $this->addError('email', __('This step has expired. Ask for a new code.'));

            return;
        }
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', Rule::in(array_keys(config('kekeli.countries')))],
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('Please accept the conditions of use and the privacy policy.')]);

        $user = new User(['name' => trim($this->name), 'email' => $pending['email'], 'country' => $this->country]);
        $user->forceFill([
            'password' => null,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'google_id' => $pending['google_id'] ?? null,
            'avatar_url' => $pending['avatar'] ?? null,
            'welcomed_at' => now(),
        ])->save();
        session()->forget('kekeli.compte_a_creer');
        $emails->welcome($user);

        Auth::login($user, $this->remember);
        Session::regenerate();
        $this->redirectIntended(default: route('projects.index', absolute: false), navigate: true);
    }

    public function usePassword(): void
    {
        $this->resetErrorBag();
        $this->step = 'password';
    }

    public function backToEmail(): void
    {
        $this->resetErrorBag();
        $this->reset('code', 'password', 'notice');
        $this->step = 'email';
    }

    /** Older accounts with a password: the same check as before (5 tries, then a pause). */
    public function login(): void
    {
        $this->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]);
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();
        $this->redirectIntended(default: route('projects.index', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function with(): array
    {
        return ['google' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))];
    }
}; ?>

{{-- Chaque étape a sa propre clé : ses champs sont recréés (sinon le code tapé partait dans le champ de l'e-mail). --}}
<div class="flex flex-col gap-6" data-login-step="{{ $step }}">
    <x-auth-header
        :title="$step === 'name' ? __('One last step') : ($step === 'code' ? __('Check your e-mails') : __('Welcome to Kekeli'))"
        :description="$step === 'name' ? __('How should Kekeli call you?') : ($step === 'code' ? '' : __('Log in or create an account'))" />

    @unless ($step === 'name')
        <x-pending-site />
    @endunless

    <x-auth-session-status class="text-center" :status="session('status')" />
    @if (session('login-error'))
        <p class="kekeli-erreur text-center text-sm" data-login-error>{{ session('login-error') }}</p>
    @endif

    @if ($step === 'email')
        @if ($google)
            <flux:button :href="route('auth.google')" class="w-full" icon="google" data-google-login>{{ __('Continue with Google') }}</flux:button>
            <div class="flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400" data-login-separator>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>{{ __('or') }}<span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
            </div>
        @endif
        <form wire:key="etape-email" wire:submit="continueWithEmail" class="flex flex-col gap-4">
            <flux:input wire:model="email" type="email" :label="__('Email address')" required autofocus autocomplete="email" placeholder="email@exemple.com" data-login-email />
            <flux:button type="submit" variant="primary" class="w-full" data-continue-email>{{ __('Continue with e-mail') }}</flux:button>
        </form>
        <div class="text-center">
            <button type="button" wire:click="usePassword" class="text-sm text-zinc-500 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-password-login>{{ __('Log in with a password') }}</button>
        </div>
        <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">
            {!! __('By continuing, you accept the :terms and the :privacy.', [
                'terms' => '<a href="'.route('legal.terms').'" target="_blank" class="underline">'.e(__('conditions of use')).'</a>',
                'privacy' => '<a href="'.route('legal.privacy').'" target="_blank" class="underline">'.e(__('privacy policy')).'</a>',
            ]) !!}
        </p>
    @elseif ($step === 'code')
        @if ($notice)
            <p class="text-center text-sm text-zinc-600 dark:text-zinc-300" data-login-notice>{{ $notice }}</p>
        @endif
        <form wire:key="etape-code" wire:submit="verifyCode" class="flex flex-col gap-4">
            <flux:input wire:model="code" :label="__('Code received by e-mail')" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" class="text-center tracking-[0.4em]" autofocus data-login-code />
            <flux:button type="submit" variant="primary" class="w-full" data-verify-code>{{ __('Continue') }}</flux:button>
        </form>
        <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('You can also touch the button in the e-mail.') }}</p>
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm">
            <button type="button" wire:click="resend" class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 dark:text-zinc-300 dark:decoration-zinc-600" data-resend-code>{{ __('Send a new code') }}</button>
            <button type="button" wire:click="backToEmail" class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 dark:text-zinc-300 dark:decoration-zinc-600">{{ __('Use another address') }}</button>
        </div>
    @elseif ($step === 'name')
        <form wire:key="etape-nom" wire:submit="createAccount" class="flex flex-col gap-4">
            <p class="text-center text-sm text-zinc-600 dark:text-zinc-300">{{ $email }}</p>
            <flux:input wire:model="name" :label="__('Name')" required autofocus autocomplete="name" :placeholder="__('Full name')" data-signup-name />
            <flux:select wire:model="country" :label="__('Your country')">
                @foreach (config('kekeli.countries') as $code => $details)
                    <flux:select.option value="{{ $code }}">{{ $details['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid gap-1">
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="terms" class="mt-1 size-4 shrink-0 accent-zinc-900" data-accept-terms>
                    <span>
                        {!! __('I accept the :terms and the :privacy.', [
                            'terms' => '<a href="'.route('legal.terms').'" target="_blank" class="font-medium underline">'.e(__('conditions of use')).'</a>',
                            'privacy' => '<a href="'.route('legal.privacy').'" target="_blank" class="font-medium underline">'.e(__('privacy policy')).'</a>',
                        ]) !!}
                    </span>
                </label>
                <flux:error name="terms" />
            </div>
            <flux:button type="submit" variant="primary" class="w-full" data-create-account>{{ __('Create my account') }}</flux:button>
        </form>
    @else
        <form wire:key="etape-mot-de-passe" wire:submit="login" class="flex flex-col gap-4">
            <flux:input wire:model="email" type="email" :label="__('Email address')" required autofocus autocomplete="email" placeholder="email@exemple.com" />
            <div class="relative">
                <flux:input wire:model="password" type="password" :label="__('Password')" required autocomplete="current-password" :placeholder="__('Password')" viewable />
                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>{{ __('Forgot your password?') }}</flux:link>
                @endif
            </div>
            <flux:button type="submit" variant="primary" class="w-full" data-password-submit>{{ __('Log in') }}</flux:button>
        </form>
        <div class="text-center">
            <button type="button" wire:click="backToEmail" class="text-sm text-zinc-500 underline decoration-zinc-300 underline-offset-2 dark:text-zinc-400 dark:decoration-zinc-600" data-back-to-code>{{ __('Receive a code by e-mail instead') }}</button>
        </div>
    @endif
</div>
