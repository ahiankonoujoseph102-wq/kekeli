<?php

namespace App\Agent\Tools;

use App\Agent\Energy;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Tool « creer_video », like Claude Code: Kekeli writes the code that makes the video (a Revideo project) and runs it in
 * an isolated E2B computer; the client receives an MP4 file. A video of 30 seconds or less is made at once; a longer
 * one first gets a short plan (scenes, text shown, voice, music, format) with « Construire ce plan ». The longest video
 * depends on the formula (Administration → Réglages).
 */
class CreateVideo extends CreateDocument
{
    public const OFFER = 'construire_video';

    public const TYPE = 'video';

    public const FORMATS = ['vertical', 'carre', 'paysage'];

    /** Above this length (seconds), a plan comes first. */
    public const PLAN_ABOVE_SECONDS = 30;

    protected function defaultPlan(): string
    {
        return (string) __('Kekeli will make a video from your request.');
    }

    /**
     * @param  array<string, string>  $arguments
     */
    public static function isLong(array $arguments): bool
    {
        return self::seconds($arguments) > self::PLAN_ABOVE_SECONDS;
    }

    /**
     * @param  array<string, string>  $arguments
     */
    public static function seconds(array $arguments): int
    {
        return (int) preg_replace('/\D/', '', (string) ($arguments['duree'] ?? '')) ?: 20;
    }

    /** The longest video of the client's formula, in seconds (Administration → Réglages). */
    public static function maxSeconds(User $user): int
    {
        $settings = app(Settings::class);

        return max(5, app(Energy::class)->isFree($user) ? $settings->int('video_max_seconds_free') : $settings->int('video_max_seconds_paid'));
    }

    /**
     * @param  array<string, string>  $arguments
     * @return array{type: string, action: string, formats: list<string>, fichier: ?int, recherche: string, format: string, duree: int}
     */
    protected function options(Project $project, Message $message, array $arguments): array
    {
        $format = Str::lower(trim((string) ($arguments['format'] ?? '')));
        $attachment = $this->attachment($project, $message, (string) ($arguments['fichier'] ?? ''));

        return [
            'type' => self::TYPE,
            'action' => 'creer',
            'formats' => [],
            // Seul un document (Word, PDF, PowerPoint) est « le fichier à transformer » ; les vidéos et les sons du
            // client sont des médias, envoyés à part.
            'fichier' => $attachment && in_array($attachment->format, ['docx', 'pdf', 'pptx'], true) ? $attachment->id : null,
            'recherche' => '',
            'format' => in_array($format, self::FORMATS, true) ? $format : 'auto',
            'duree' => min(self::seconds($arguments), self::maxSeconds($project->user)),
        ];
    }
}
