<?php

namespace App\Agent;

use App\Models\Project;

/**
 * Private access to a project's preview. Only the Kekeli site and the Runner know how to make a
 * token (same rule as in runner/src/apercu/serveur.mjs), and a token only works for a short time.
 */
class PreviewToken
{
    /**
     * A token stays valid a little after the computer pauses, so the page has time to show the pause.
     */
    public const MARGIN_SECONDS = 300;

    public static function key(string $secret, string $projectId): string
    {
        return hash_hmac('sha256', 'apercu:'.$projectId, $secret);
    }

    public static function make(string $secret, string $projectId, int $expiresAt): string
    {
        $signature = substr(hash_hmac('sha256', $projectId.'.'.$expiresAt, self::key($secret, $projectId)), 0, 32);

        return $expiresAt.'.'.$signature;
    }

    /**
     * Address of the project's preview with a private token, or null if the preview is not on.
     * The address stays the same until the computer's pause time changes, so the frame does not reload.
     */
    public static function url(Project $project): ?string
    {
        $secret = config('kekeli.runner.secret');

        if (blank($secret) || blank($project->preview_url) || $project->preview_expires_at === null) {
            return null;
        }

        $token = self::make($secret, $project->id, $project->preview_expires_at->getTimestamp() + self::MARGIN_SECONDS);

        return rtrim($project->preview_url, '/').'/k/'.$token.'/';
    }
}
