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
