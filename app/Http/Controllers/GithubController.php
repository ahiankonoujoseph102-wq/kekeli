<?php

namespace App\Http\Controllers;

use App\Github\GithubClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connection of the client's GitHub account, to send the code of their projects to it.
 */
class GithubController extends Controller
{
    public function connect(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);

        $state = Str::random(40);
        $request->session()->put('github_state', $state);
        $request->session()->put('github_return', $this->returnUrl((string) $request->query('retour')));

        return redirect()->away($github->authorizeUrl($state));
    }

    public function callback(Request $request, GithubClient $github): RedirectResponse
    {
        abort_unless($github->configured(), 404);

        $expected = (string) $request->session()->pull('github_state');
        $return = (string) $request->session()->pull('github_return', route('projects.index'));

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

        return redirect($return)->with('github', 'connected');
    }

    public function disconnect(): RedirectResponse
    {
        Auth::user()->forceFill(['github_token' => null, 'github_login' => null])->save();

        return back();
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
