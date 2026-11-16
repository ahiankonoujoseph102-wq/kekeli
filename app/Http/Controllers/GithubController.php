<?php

namespace App\Http\Controllers;

use App\Github\GithubClient;
use App\Github\RepositoryClient;
use App\Jobs\PushProjectToGithub;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
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

    public function callback(Request $request, GithubClient $github): RedirectResponse|View
    {
        abort_unless($github->configured(), 404);

        $expected = (string) $request->session()->pull('github_state');
        $return = (string) $request->session()->pull('github_return', route('projects.index'));
        $project = $this->ownProject((string) $request->session()->pull('github_project'));

        // Le client a refusé sur GitHub, ou GitHub signale un réglage faux (adresse de retour, par exemple) : un message
        // clair, jamais une page muette.
        if ($request->filled('error')) {
            return redirect($return)->with('github', 'refused')->with('github_error', Str::limit((string) $request->query('error'), 60, ''));
        }
        if (! $request->filled('code')) {
            // Installation faite sans l'autorisation du client (case « Request user authorization during installation »
            // décochée, ou « Setup URL ») : Kekeli enchaîne sur l'autorisation, au lieu d'abandonner en silence.
            if ($request->filled('installation_id') && in_array($request->query('setup_action'), ['install', 'update'], true)) {
                $next = Str::random(40);
                $request->session()->put(['github_state' => $next, 'github_return' => $return, 'github_project' => $project?->id]);

                return redirect()->away($github->authorizeUrl($next));
            }

            return redirect($return)->with('github', 'refused');
        }

        // Le « state » protège contre une connexion forcée (quelqu'un qui relierait son propre GitHub au compte Kekeli du
        // client). Après une installation, GitHub peut revenir sans lui ; et sur un téléphone, GitHub s'ouvre parfois dans
        // son application ou un autre navigateur, et la session de départ est perdue. Dans ces cas, Kekeli ne refuse plus
        // en silence : il demande au client de confirmer d'un geste (formulaire protégé), puis termine la connexion.
        $state = (string) $request->query('state');
        $verified = $expected !== '' && $state !== '' && hash_equals($expected, $state);
        if (! $verified) {
            $request->session()->put('github_pending', ['code' => (string) $request->query('code'), 'project' => $project?->id, 'return' => $return, 'at' => now()->timestamp]);

            return view('github.confirm');
        }

        return $this->finish($github, (string) $request->query('code'), $project, $return);
    }

    /**
     * The client confirms, with one tap, a return from GitHub that Kekeli could not check by itself.
     */
    public function confirm(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);
        $pending = (array) $request->session()->pull('github_pending', []);
        // Le code de GitHub ne vaut que 10 minutes.
        if (blank($pending['code'] ?? null) || now()->timestamp - (int) ($pending['at'] ?? 0) > 600) {
            return redirect()->route('projects.index')->with('github', 'refused');
        }

        return $this->finish($github, (string) $pending['code'], $this->ownProject((string) ($pending['project'] ?? '')), $this->returnUrl((string) ($pending['return'] ?? '')));
    }

    private function finish(GithubClient $github, string $code, ?Project $project, string $return): RedirectResponse
    {
        try {
            $account = $github->connect($code);
        } catch (Throwable $exception) {
            report($exception);

            return redirect($return)->with('github', 'failed');
        }

        $user = Auth::user();
        $user->forceFill([
            'github_token' => $account['token'],
            'github_refresh_token' => $account['refresh'],
            'github_token_expires_at' => $account['expires_at'],
            'github_login' => $account['login'],
            'github_installation_id' => $account['installation'],
        ])->save();
        Cache::forget('kekeli.depots.'.$user->id);

        // « Projet d'abord » : le dépôt privé est créé et le code part aussitôt ; le client n'a rien d'autre à faire.
        if ($project && ! $project->isRepository() && ! $project->isChat() && $project->backup_at && $project->github_status !== Project::GITHUB_IN_PROGRESS) {
            $project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(function () use ($project) {
                PushProjectToGithub::dispatch($project);
            }, report: true);
        }

        // Comme Claude Code : « GitHub est connecté : N dépôts accessibles ».
        $count = rescue(fn () => count(app(RepositoryClient::class)->repositories($account['token'], $account['installation'])), null, report: false);

        return redirect($return)->with('github', 'connected')->with('github_login', $account['login'])->with('github_repositories', $count);
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
