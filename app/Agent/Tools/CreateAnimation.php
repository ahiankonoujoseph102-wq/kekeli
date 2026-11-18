<?php

namespace App\Agent\Tools;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Tool « creer_animation » (Kekeli Motion): an illustration drawn in ink, in SVG, that moves live in the page and
 * reacts when it is touched (characters that walk, run, wave; a garden, a street, a village; day and night). The model
 * only describes the scene (JSON); the engine of Kekeli draws it. Always made at once: no plan. The client watches it
 * in Kekeli, downloads it (page, SVG image, TypeScript code) and puts it in a site made by Kekeli.
 */
class CreateAnimation extends CreateDocument
{
    public const OFFER = 'construire_animation';

    public const TYPE = 'animation';

    public const FORMATS = ['paysage', 'carre', 'vertical'];

    protected function defaultPlan(): string
    {
        return (string) __('Kekeli will make an animation from your request.');
    }

    /**
     * @param  array<string, string>  $arguments
     */
    public static function isLong(array $arguments): bool
    {
        return false;
    }

    /**
     * @param  array<string, string>  $arguments
     * @return array{type: string, action: string, formats: list<string>, fichier: ?int, recherche: string, format: string}
     */
    protected function options(Project $project, Message $message, array $arguments): array
    {
        $format = Str::lower(trim((string) ($arguments['format'] ?? '')));

        return [
            'type' => self::TYPE,
            'action' => 'creer',
            'formats' => [],
            'fichier' => null,
            'recherche' => '',
            'format' => in_array($format, self::FORMATS, true) ? $format : 'auto',
        ];
    }
}
