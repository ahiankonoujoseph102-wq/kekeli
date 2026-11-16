<?php

namespace App\Http\Controllers;

use App\Github\GithubClient;
use App\Github\RepositoryClient;
use App\Github\RepositoryList;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A repository the client linked to Kekeli, read by the first responder to answer « lis mon dépôt et dis-moi ce que
 * c'est » (16 novembre 2026), without changing anything. Temporary signed address (10 minutes) given by the site with
 * the message; the client's GitHub key never leaves the site, and only the repositories the client chose on GitHub
 * for Kekeli can be read.
 */
class RunnerGithubReadController extends Controller
{
    public function show(Request $request, User $user, GithubClient $github, RepositoryClient $repositories): JsonResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['depot', 'chemin']), 403);
        $token = $github->token($user);
        abort_unless($token !== null, 404);

        $depot = trim((string) $request->query('depot', ''), '/ ');
        $chemin = trim((string) $request->query('chemin', ''), '/ ');
        $accessibles = collect(RepositoryList::forUser($user))->pluck('full_name')->map(fn ($name) => mb_strtolower($name));
        if ($depot === '' || ! $accessibles->contains(mb_strtolower($depot))) {
            return response()->json(['erreur' => 'depot_inconnu', 'depots' => $accessibles->take(30)->values()], 404);
        }

        try {
            if ($chemin === '') {
                return response()->json($repositories->overview($depot, $token));
            }
            $content = $repositories->fileContent($depot, $chemin, $token);
        } catch (RequestException) {
            return response()->json(['erreur' => 'github'], 502);
        }

        return $content === null
            ? response()->json(['erreur' => 'introuvable'], 404)
            : response()->json(['depot' => $depot, 'chemin' => $chemin, 'contenu' => $content]);
    }
}
