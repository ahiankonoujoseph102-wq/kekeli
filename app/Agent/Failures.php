<?php

namespace App\Agent;

use App\Models\AgentRun;

/**
 * When Kekeli cannot do something, it says so like Claude Code (15 novembre 2026): in one or two simple sentences, what
 * happened, why, what is kept, and what it offers instead (« Réessayer », another way, or the time the service comes
 * back). Never a generic or technical sentence; never the name of a provider, never « free offer ».
 */
final class Failures
{
    /** The failures where touching « Réessayer » can work. */
    private const NOT_RETRYABLE = ['images_indisponibles'];

    /**
     * The sentence the client reads for a failed request.
     */
    public static function explain(?string $error, ?AgentRun $run = null): string
    {
        $reason = self::reason($error);
        $kept = self::kept($run);

        return match ($reason) {
            'aucun_fournisseur', 'limite' => __('Kekeli could not finish: its artificial intelligence models are all very busy right now. :kept Touch « Try again » in a few minutes.', ['kept' => $kept]),
            'images_indisponibles' => __('Kekeli could not create the images: image creation has reached its limit for today and comes back at 00 h 00. :kept Until then, you can add your own photos with « + ».', ['kept' => $kept]),
            'trop_lourd' => __('This creation is too large for Kekeli right now: nothing unfinished was delivered. Ask first for a simpler version (for example a single page), or touch « Try again » in a few hours.'),
            'duree' => __('Kekeli could not finish within the time allowed for one request. :kept Ask for a smaller part at a time, or touch « Try again ».', ['kept' => $kept]),
            'ordinateur', 'atelier' => __('Kekeli\'s work computer stopped answering. :kept Touch « Try again »: a new computer will be prepared.', ['kept' => $kept]),
            'scenario', 'reponse_illisible', 'document' => __('Kekeli did not manage to write the content this time. :kept Touch « Try again », or say a little more about what you want.', ['kept' => $kept]),
            'rendu' => __('The file could not be put together at the last step. :kept Touch « Try again ».', ['kept' => $kept]),
            default => __('Kekeli could not finish because of a technical problem on its side. :kept Touch « Try again »; if it happens again, write to us.', ['kept' => $kept]),
        };
    }

    /** « Réessayer » is offered under the failure (not when the service comes back only later in the day). */
    public static function retryable(?string $error): bool
    {
        return ! in_array(self::reason($error), self::NOT_RETRYABLE, true);
    }

    /** The sentence when no model could answer a message, even after waiting. */
    public static function busy(): string
    {
        return __('Kekeli could not answer: it is very busy right now. Your message is kept; touch « Try again » in a few minutes.');
    }

    /** The reason saved by the site (« aucun_fournisseur : détail technique »): its first word only. */
    public static function reason(?string $error): string
    {
        return trim(strtok(trim((string) $error), ' :') ?: '');
    }

    /** What is kept after the failure: the last working version of a project, the previous file, or simply nothing lost. */
    private static function kept(?AgentRun $run): string
    {
        $project = $run?->project;
        if ($project && ! $project->isDocument() && $project->versions()->exists()) {
            return __('Your project went back to its last working version.');
        }
        if ($project && $project->isDocument() && $project->documentVersions()->exists()) {
            return __('The previous version is still available.');
        }

        return __('Nothing is lost.');
    }
}
