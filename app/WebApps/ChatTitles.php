<?php

namespace App\WebApps;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Short title of a new discussion, given automatically from the client's first message (a few words, free: no request
 * to the model). It is temporary: the model gives the real title, a short summary, with its first answer (no request
 * more). The client can change it with « Rename ».
 */
class ChatTitles
{
    /** Words of politeness at the start of a message, left out of the title. */
    private const GREETINGS = '/^(?:(?:bonjour|bonsoir|salut|coucou|hello|bjr|slt|svp|s[’\']il (?:te|vous) pla[iî]t|kekeli|merci)[\s,.!:;-]*)+/iu';

    public const MAX_WORDS = 6;

    /**
     * Whether the discussion still has the temporary title given from its first message (the client did not rename
     * it, and the model did not give a real title yet).
     */
    public function isTemporary(Project $project): bool
    {
        if (! $project->isChat()) {
            return false;
        }

        $first = $project->messages()->where('role', Message::ROLE_USER)->value('content');

        return $first !== null && $project->name === $this->guess((string) $first);
    }

    /**
     * The title written by the model with its first answer: a real summary in a few words, like Claude or ChatGPT.
     * Returns null when it is not usable (empty, one word, too long).
     */
    public function fromModel(?string $title): ?string
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $title)) ?? '', " \t\n.,;:!…-«»\"“”*#_");
        $words = count(preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words < 2 || $words > 8 || mb_strlen($title) > 60) {
            return null;
        }

        return Str::ucfirst($title);
    }

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
