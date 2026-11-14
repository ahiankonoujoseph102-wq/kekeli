<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * « Continuer avec Google » (Laravel Socialite, licence MIT). Only the name, the e-mail and the photo are asked. An
 * address already known is linked to Google (Google has proved it); a new address goes to the last step (the
 * country and the conditions) before the account is created. Without the settings GOOGLE_*, nothing here exists.
 */
class GoogleController extends Controller
{
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        abort_unless(self::configured(), 404);

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(self::configured(), 404);
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->with('login-error', __('The connection with Google did not work. Try again, or continue with your e-mail.'));
        }

        $email = strtolower(trim((string) $google->getEmail()));
        $verified = (bool) data_get($google->user ?? [], 'email_verified', data_get($google->user ?? [], 'verified_email', false));
        if ($email === '' || ! $verified) {
            return redirect()->route('login')->with('login-error', __('Google did not give a verified e-mail address. Continue with your e-mail.'));
        }

        $user = User::query()->where('google_id', (string) $google->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if (! $user) {
            session()->put('kekeli.compte_a_creer', ['email' => $email, 'until' => time() + 1800, 'name' => (string) $google->getName(), 'google_id' => (string) $google->getId(), 'avatar' => (string) $google->getAvatar()]);

            return redirect()->route('login');
        }

        $fields = ['google_id' => (string) $google->getId(), 'avatar_url' => (string) $google->getAvatar() ?: $user->avatar_url];
        if (! $user->email_verified_at) {
            // L'adresse n'avait jamais été prouvée : Google la prouve maintenant ; l'ancien mot de passe ne sert plus.
            $fields += ['email_verified_at' => now(), 'password' => null];
        }
        $user->forceFill($fields)->save();
        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('projects.index', absolute: false));
    }
}
