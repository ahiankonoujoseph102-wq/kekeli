<?php

namespace App\Knowledge;

/**
 * A document that cannot be used, for a reason the client can understand (never a technical detail): « vide »,
 * « trop_long », « espace_plein », « illisible » or « indisponible ».
 */
class KnowledgeException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /** The sentence shown under the document, like Claude Code: what happened and what to do. */
    public static function explain(?string $reason): string
    {
        return match ($reason) {
            'vide' => __('Kekeli found no text in this file. A scanned document (a photo of pages) cannot be read yet: import a version with text.'),
            'trop_long' => __('This document is too long for the knowledge base. Split it into several files, then import them.'),
            'espace_plein' => __('This space is full. Delete a document you no longer need, or create another space.'),
            'illisible' => __('Kekeli could not open this file: it may be damaged or protected by a password. Import another copy of it.'),
            default => __('Kekeli could not process this document for the moment. Nothing is lost: touch « Try again ».'),
        };
    }
}
