<?php

namespace App\Auth;

use App\Mail\Emails;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Connection by a code sent by e-mail, like Claude and ChatGPT: a code of 6 digits and a link, valid 10 minutes,
 * used once, 5 tries at most, 5 sendings per hour and per address (and 20 per hour and per connection). The answer
 * is always the same, whether the account exists or not.
 */
class LoginCodes
{
    public const SENT = 'sent';

    public const TOO_MANY = 'too_many';

    public const VALID = 'valid';

    public const WRONG = 'wrong';

    public const EXPIRED = 'expired';

    public function __construct(private Emails $emails) {}

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Sends a new code (the older ones of this address stop working). Returns SENT or TOO_MANY.
     */
    public function send(string $email, string $ip): string
    {
        $email = self::normalize($email);
        $perEmail = 'code-envoi:'.sha1($email);
        $perIp = 'code-envoi-ip:'.$ip;
        if (RateLimiter::tooManyAttempts($perEmail, (int) config('kekeli.login_codes.sends_per_hour')) || RateLimiter::tooManyAttempts($perIp, 20)) {
            return self::TOO_MANY;
        }
        RateLimiter::hit($perEmail, 3600);
        RateLimiter::hit($perIp, 3600);

        LoginCode::query()->where('email', $email)->whereNull('used_at')->update(['used_at' => now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(48);
        LoginCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes((int) config('kekeli.login_codes.minutes')),
        ]);
        $this->emails->loginCode($email, $code, route('login.link', $token));

        return self::SENT;
    }

    /**
     * Checks the code typed: VALID (then used), WRONG (one try counted) or EXPIRED (too late, too many tries, or no
     * code: a new one must be asked).
     */
    public function check(string $email, string $code): string
    {
        $email = self::normalize($email);
        $login = LoginCode::query()->where('email', $email)->whereNull('used_at')->latest('id')->first();
        if (! $login || ! $login->isUsable()) {
            return self::EXPIRED;
        }
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || ! Hash::check($code, $login->code_hash)) {
            $login->increment('attempts');

            return $login->fresh()->isUsable() ? self::WRONG : self::EXPIRED;
        }
        $login->forceFill(['used_at' => now()])->save();

        return self::VALID;
    }

    /** The link of the e-mail: the address it proves, or null (unknown, used, expired). The link is used once. */
    public function useLink(string $token): ?string
    {
        $login = LoginCode::query()->where('token_hash', hash('sha256', $token))->first();
        if (! $login || ! $login->isUsable()) {
            return null;
        }
        $login->forceFill(['used_at' => now()])->save();

        return $login->email;
    }

    /** The account of a proved address (its e-mail is now verified), or null when it does not exist yet. */
    public static function accountFor(string $email): ?User
    {
        $user = User::query()->where('email', self::normalize($email))->first();
        if ($user && ! $user->email_verified_at) {
            // L'adresse n'avait jamais été prouvée : le mot de passe choisi alors (peut-être par quelqu'un d'autre)
            // ne sert plus ; le vrai propriétaire de l'adresse se connecte par code ou par Google.
            $user->forceFill(['email_verified_at' => now(), 'password' => null])->save();
        }

        return $user;
    }
}
