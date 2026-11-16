<?php

namespace App\Github;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The repositories the client chose on GitHub for Kekeli, kept 5 minutes (same key as « Ouvrir un dépôt GitHub »),
 * so that Kekeli knows its real connections without asking GitHub at every message.
 */
class RepositoryList
{
    /**
     * @return list<array{full_name: string, private: bool, default_branch: string, can_write: bool, description: ?string}>
     */
    public static function forUser(User $user): array
    {
        $token = app(GithubClient::class)->token($user);
        if (! $token) {
            return [];
        }

        return (array) rescue(
            fn () => Cache::remember('kekeli.depots.'.$user->id, now()->addMinutes(5), fn () => app(RepositoryClient::class)->repositories($token, $user->github_installation_id)),
            [],
            report: false,
        );
    }
}
