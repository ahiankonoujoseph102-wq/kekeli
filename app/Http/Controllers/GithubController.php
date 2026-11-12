<?php

namespace App\Http\Controllers;

use App\Github\GithubClient;
use App\Jobs\ExportToGithub;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connection of the client's GitHub account, to send the code of their projects to it.
 * One gesture, as in the best-known tools: « Connect GitHub », the client accepts on GitHub, and the code of the
 * project is sent at once (private repository created automatically).
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

        return redirect()->away($github->authorizeUrl($state));
    }

    public function callback(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);

        $expected = (string) $request->session()->pull('github_state');
        $return = (string) $request->session()->pull('github_return', route('projects.index'));
        $project = $this->ownProject((string) $request->session()->pull('github_project'));

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect($return)->with('github', 'refused');
        }

        try {
            $account = $github->connect((string) $request->query('code'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect($return)->with('github', 'failed');
        }

        Auth::user()->forceFill(['github_token' => $account['token'], 'github_login' => $account['login']])->save();

        // Le code du projet part aussitôt : le client n'a rien d'autre à faire.
        if ($project && $project->backup_at && $project->github_status !== Project::GITHUB_IN_PROGRESS) {
            $project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(function () use ($project) {
                ExportToGithub::dispatch($project);
            }, report: true);
        }

        return redirect($return)->with('github', 'connected');
    }

    public function disconnect(): RedirectResponse
    {
        Auth::user()->forceFill(['github_token' => null, 'github_login' => null])->save();

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
