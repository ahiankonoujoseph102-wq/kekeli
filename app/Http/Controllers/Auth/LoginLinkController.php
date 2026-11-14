<?php

namespace App\Http\Controllers\Auth;

use App\Auth\LoginCodes;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The button of the login e-mail: the link proves the address once, like the code. An existing account is signed
 * in; a new address goes to the last step (the name).
 */
class LoginLinkController extends Controller
{
    public function __invoke(string $token, LoginCodes $codes): RedirectResponse
    {
        $email = $codes->useLink($token);
        if (! $email) {
            return redirect()->route('login')->with('login-error', __('This link has expired or was already used. Ask for a new code.'));
        }
        $user = LoginCodes::accountFor($email);
        if (! $user) {
            session()->put('kekeli.compte_a_creer', ['email' => $email, 'until' => time() + 1800]);

            return redirect()->route('login');
        }
        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('projects.index', absolute: false));
    }
}
