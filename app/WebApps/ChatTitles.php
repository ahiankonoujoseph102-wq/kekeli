<?php

namespace App\WebApps;

use Illuminate\Support\Str;

/**
 * Short title of a new discussion, given automatically from the client's first message (a few words, free: no request
 * to the model). The client can change it with « Rename ».
 */
class ChatTitles
{
    /** Words of politeness at the start of a message, left out of the title. */
    private const GREETINGS = '/^(?:(?:bonjour|bonsoir|salut|coucou|hello|bjr|slt|svp|s[’\']il (?:te|vous) pla[iî]t|kekeli|merci)[\s,.!:;-]*)+/iu';

    public const MAX_WORDS = 6;

    public function guess(string $message): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        $text = trim(preg_replace(self::GREETINGS, '', $text) ?? $text);
        // La première phrase seulement.
        $text = trim(preg_split('/(?<=[.!?…])\s+|\R/u', $text)[0] ?? '');
        $words = array_slice(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY), 0, self::MAX_WORDS);
        $title = rtrim(implode(' ', $words), " \t.,;:!?…-");

        if ($title === '') {
            return __('New discussion');
        }

        return Str::limit(Str::ucfirst($title), 60, '…');
    }
}
