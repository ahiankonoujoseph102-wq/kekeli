<?php

namespace App\Http\Controllers;

use App\Github\GithubClient;
use App\Jobs\PushProjectToGithub;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connection of the client's GitHub account with the GitHub App of Kekeli, like Claude Code: the client installs the
 * application, chooses on GitHub the repositories Kekeli may see, and accepts. Started from a project (« projet
 * d'abord »), Kekeli then creates a private repository from it and sends the code at once.
 */
class GithubController extends Controller
{
    public function connect(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);

        $state = Str::random(40);
        $request->session()->put('github_state', $state);
        $project = $this->ownProject((string) $request->query('projet'));
        $request->session()->put('github_project', $project?->id);
        $request->session()->put('github_return', $project ? route('projects.show', $project) : $this->returnUrl((string) $request->query('retour')));

        // Première fois : installation (choix des dépôts) ; ensuite, seulement l'accord du client.
        return redirect()->away(Auth::user()->github_installation_id ? $github->authorizeUrl($state) : $github->installUrl($state));
    }

    public function callback(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);

        $expected = (string) $request->session()->pull('github_state');
        $return = (string) $request->session()->pull('github_return', route('projects.index'));
        $project = $this->ownProject((string) $request->session()->pull('github_project'));

        // Le « state » protège contre une connexion forcée. Après une installation, GitHub peut revenir sans lui : on
        // l'accepte alors seulement si le client vient de partir installer l'application depuis Kekeli (même session).
        $state = (string) $request->query('state');
        $fromInstall = $state === '' && in_array($request->query('setup_action'), ['install', 'update'], true);
        if ($expected === '' || (! $fromInstall && ! hash_equals($expected, $state)) || ! $request->filled('code')) {
            return redirect($return)->with('github', 'refused');
        }

        try {
            $account = $github->connect((string) $request->query('code'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect($return)->with('github', 'failed');
        }

        Auth::user()->forceFill([
            'github_token' => $account['token'],
            'github_refresh_token' => $account['refresh'],
            'github_token_expires_at' => $account['expires_at'],
            'github_login' => $account['login'],
            'github_installation_id' => $account['installation'],
        ])->save();

        // « Projet d'abord » : le dépôt privé est créé et le code part aussitôt ; le client n'a rien d'autre à faire.
        if ($project && ! $project->isRepository() && ! $project->isChat() && $project->backup_at && $project->github_status !== Project::GITHUB_IN_PROGRESS) {
            $project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(function () use ($project) {
                PushProjectToGithub::dispatch($project);
            }, report: true);
        }

        return redirect($return)->with('github', 'connected');
    }

    public function disconnect(): RedirectResponse
    {
        Auth::user()->forceFill(['github_token' => null, 'github_refresh_token' => null, 'github_token_expires_at' => null, 'github_login' => null, 'github_installation_id' => null])->save();

        return back();
    }

    private function ownProject(string $id): ?Project
    {
        return $id === '' ? null : Auth::user()->projects()->whereKey($id)->first();
    }

    /**
     * Only an address of Kekeli itself: never a redirection to another site.
     */
    private function returnUrl(string $url): string
    {
        $base = rtrim(url('/'), '/');

        return Str::startsWith($url, $base.'/') ? $url : route('projects.index');
    }
}
