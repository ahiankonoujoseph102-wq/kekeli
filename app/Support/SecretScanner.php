<?php

namespace App\Support;

/**
 * A key or a password pasted in the discussion is never stored, never sent to the model and never reused: it is
 * replaced by « [clé masquée] », and Kekeli suggests the « Variables secrètes » of the project, where it is encrypted.
 * The forms of the most common services are recognised (OpenAI, Google, GitHub, Supabase, Stripe, Slack, AWS,
 * connection addresses with a password, private keys, tokens).
 */
class SecretScanner
{
    public const MASK = '[clé masquée]';

    private const PATTERNS = [
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?(-----END [A-Z ]*PRIVATE KEY-----|$)/',
        '/\bsk-(?:proj-|ant-)?[A-Za-z0-9_-]{20,}/',
        '/\bAIza[0-9A-Za-z_-]{30,}/',
        '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}|\bgithub_pat_[A-Za-z0-9_]{40,}/',
        '/\bxox[abposr]-[A-Za-z0-9-]{20,}/',
        '/\bsbp_[A-Za-z0-9]{30,}/',
        '/\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{16,}/',
        '/\bAKIA[0-9A-Z]{16}\b/',
        '/\bre_[A-Za-z0-9_]{20,}/',
        '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
    ];

    public const PASSWORD_MASK = '[mot de passe masqué]';

    public const CODE_MASK = '[code masqué]';

    /** Codes that are not secrets (promotion, postal code, colors, source code…): never masked. */
    private const PUBLIC_CODES = 'promo|promotionnel|postal|couleur|source|html|css|qr|barre|r[ée]duction|remise|article|produit|client|du\s+site|de\s+mon\s+site|de\s+la\s+route';

    /**
     * A message of the client with every key masked (redact), then every password or code clearly announced
     * (« mon mot de passe est… », « le code SMS : 482 913 ») replaced before it is stored, so it never reaches the
     * model. Prices, amounts and phone numbers are never touched: the value must follow a word like « est » or « : »
     * and look like a secret (a digit, a sign or mixed case for a password, a digit for a code). Returns the text,
     * whether a key was masked, and whether a password or a code was masked.
     *
     * @return array{0: string, 1: bool, 2: bool}
     */
    public static function redactMessage(string $text): array
    {
        [$text, $key] = self::redact($text);
        $announced = false;
        $filler = '(?:\s+(?:de\s+|du\s+|mon\s+|ma\s+|mes\s+|le\s+|la\s+)?[\p{L}\'’]+){0,4}?';
        $connector = '\s*(?:est|:|=|c[\'’]est|sera|re[çc]u\s*:?)\s*(?:le\s+|la\s+)?[«"“\']?\s*';
        $end = '(?=[\s,;»"”\'\)]|[.!?](?:\s|$)|$)';

        $text = preg_replace_callback('/(?<![\p{L}])((?:mots?\s+de\s+passe|mdp|password|passcode)'.$filler.$connector.')(\S{3,64})(?=\s|$)/iu', function (array $m) use (&$announced) {
            // Une ponctuation de fin de phrase (point, virgule) reste dans le texte ; « ! » ou « ? » font partie du mot de passe.
            preg_match('/^(.*?)([.,;:)»"”\']*)$/u', $m[2], $parts);
            [$value, $after] = [$parts[1] ?? $m[2], $parts[2] ?? ''];
            if (mb_strlen($value) < 3 || ! preg_match('/\d|[^\p{L}]|\p{Lu}.*\p{Ll}.*\p{Lu}|\p{Ll}.*\p{Lu}/u', $value) || str_contains($value, '[')) {
                return $m[0];
            }
            $announced = true;

            return $m[1].self::PASSWORD_MASK.$after;
        }, $text) ?? $text;

        $text = preg_replace_callback('/(?<![\p{L}])((?:code(?!\s+(?:'.self::PUBLIC_CODES.')(?![\p{L}]))|otp|pin)'.$filler.$connector.')(\d{3}[\s-]\d{3}|[A-Za-z0-9]{4,10})'.$end.'/iu', function (array $m) use (&$announced) {
            if (! preg_match('/\d/', $m[2])) {
                return $m[0];
            }
            $announced = true;

            return $m[1].self::CODE_MASK;
        }, $text) ?? $text;

        return [$text, $key, $announced];
    }

    /**
     * The text with every key masked, and whether one was found.
     *
     * @return array{0: string, 1: bool}
     */
    public static function redact(string $text): array
    {
        $found = false;
        foreach (self::PATTERNS as $pattern) {
            $text = preg_replace_callback($pattern, function () use (&$found) {
                $found = true;

                return self::MASK;
            }, $text) ?? $text;
        }

        // Adresse de connexion avec un mot de passe (postgresql://utilisateur:motdepasse@hôte) : le mot de passe disparaît.
        $text = preg_replace_callback('#\b((?:postgres(?:ql)?|mysql|mariadb|mongodb(?:\+srv)?|redis|rediss|amqps?)://[^:\s/@]+:)([^@\s]+)(@)#i', function (array $match) use (&$found) {
            $found = true;

            return $match[1].self::MASK.$match[3];
        }, $text) ?? $text;

        return [$text, $found];
    }
}
