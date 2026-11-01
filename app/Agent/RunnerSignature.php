<?php

namespace App\Agent;

/**
 * Signature of the messages exchanged with the Runner (HMAC-SHA256 of « timestamp.body »).
 * The same rule is written in runner/src/signature.js.
 */
class RunnerSignature
{
    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * @return array<string, string>
     */
    public static function headers(string $secret, string $body): array
    {
        $timestamp = (string) now()->getTimestamp();

        return [
            'X-Kekeli-Horodatage' => $timestamp,
            'X-Kekeli-Signature' => self::sign($secret, $timestamp, $body),
        ];
    }

    public static function verify(?string $secret, ?string $timestamp, ?string $signature, string $body): bool
    {
        if (blank($secret) || blank($timestamp) || blank($signature) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), $signature);
    }
}
