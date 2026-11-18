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
            'drive_prive' => __('Kekeli cannot open this Drive link. In Google Drive, touch « Share », choose « Anyone with the link », then touch « Try again ».'),
            'trop_grand' => __('This file is bigger than the size allowed. Split it into several files, then add them.'),
            'format' => __('Kekeli cannot read this kind of file yet. Use a PDF, Word, PowerPoint, Google Docs, Sheets or Slides, or a text file.'),
            'video_inaccessible' => __('Kekeli cannot watch this video: it may be private, removed or reserved to adults. Use a public or « unlisted » video.'),
            'videos_du_jour' => __('Kekeli has read many videos today: this one will be read tomorrow. Touch « Try again » after 00 h 00.'),
            'illisible' => __('Kekeli could not open this file: it may be damaged or protected by a password. Import another copy of it.'),
            default => __('Kekeli could not process this document for the moment. Nothing is lost: touch « Try again ».'),
        };
    }
}
