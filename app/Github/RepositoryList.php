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
    /** Seconds GitHub may take while Kekeli answers a message (never more: an answer is never slowed down by GitHub). */
    public const QUICK_TIMEOUT = 4;

    /**
     * @return list<array{full_name: string, private: bool, default_branch: string, can_write: bool, description: ?string}>
     */
    public static function forUser(User $user, bool $quick = false): array
    {
        $key = 'kekeli.depots.'.$user->id;
        if ($quick && Cache::has($key)) {
            return (array) Cache::get($key);
        }
        $token = app(GithubClient::class)->token($user);
        if (! $token) {
            return [];
        }
        try {
            return (array) Cache::remember($key, now()->addMinutes(5), fn () => app(RepositoryClient::class)->repositories($token, $user->github_installation_id, $quick ? self::QUICK_TIMEOUT : null));
        } catch (\Throwable) {
            // GitHub lent ou indisponible : rien n'est demandé de nouveau pendant 2 minutes (la réponse n'attend pas).
            if ($quick) {
                Cache::put($key, [], now()->addMinutes(2));
            }

            return [];
        }
    }

    /** Only the repositories already known (no call to GitHub), for a message that does not talk about code. */
    public static function known(User $user): ?array
    {
        $list = Cache::get('kekeli.depots.'.$user->id);

        return is_array($list) ? $list : null;
    }
}
