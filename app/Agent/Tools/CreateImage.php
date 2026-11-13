<?php

namespace App\Agent\Tools;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Tool « creer_image », like Claude and ChatGPT: an image or a poster (PNG, and PDF for a poster to print), in the
 * format of its use (square for Facebook and Instagram, vertical for a WhatsApp status, A4 or A3 to print). Always made
 * at once: no plan. The photos or the logo joined with « + » are put on it as they are.
 */
class CreateImage extends CreateDocument
{
    public const OFFER = 'construire_image';

    public const TYPE = 'image';

    public const FORMATS = ['carre', 'vertical', 'paysage', 'a4', 'a3'];

    protected function defaultPlan(): string
    {
        return (string) __('Kekeli will make an image from your request.');
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
