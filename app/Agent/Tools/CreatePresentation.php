<?php

namespace App\Agent\Tools;

use Illuminate\Support\Str;

/**
 * Tool « creer_presentation »: a real PowerPoint and PDF file, one idea per slide, a sober theme. More than 10 slides:
 * a short plan first, with « Construire ce plan ».
 */
class CreatePresentation extends CreateDocument
{
    public const OFFER = 'construire_presentation';

    public const TYPE = 'presentation';

    protected function defaultPlan(): string
    {
        return (string) __('Kekeli will make a presentation from your request.');
    }

    /**
     * @param  array<string, string>  $arguments
     */
    public static function isLong(array $arguments): bool
    {
        $size = Str::lower(trim((string) ($arguments['taille'] ?? '')));

        return $size === 'long' || (int) $size > 10;
    }
}
