<?php

namespace App\Http\Controllers;

use App\Agent\RunnerClient;
use App\Models\UserConnector;
use App\Support\Connectors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Linking a connector (MCP) with the account of the client, like Claude and ChatGPT: the Runner registers Kekeli with
 * the authorization server of the service (dynamic registration), prepares PKCE, then exchanges the code for tokens.
 * The tokens are encrypted here, specific to each client, never shown, and erased by « Déconnecter ».
 */
class ConnectorController extends Controller
{
    private const SESSION = 'connecteurs_oauth';

    public function __construct(private Connectors $connectors, private RunnerClient $runner) {}

    /** « Connecter » on a connector of the catalog. */
    public function connect(string $key): RedirectResponse
    {
        $entry = $this->connectors->catalog()[$key] ?? null;
        abort_if($entry === null, 404);

        $connector = Auth::user()->connectors()->firstOrNew(['url' => $entry['url']]);
        $connector->fill(['catalog_key' => $key, 'name' => $entry['name'], 'auth' => $entry['auth']]);

        if ($entry['auth'] === UserConnector::AUTH_NONE) {
            $connector->forceFill(['connected_at' => now(), 'active' => true])->save();

            return $this->done(__(':name is connected.', ['name' => $entry['name']]));
        }
        $connector->save();

        return $this->authorize($connector);
    }

    /** « Ajouter un connecteur personnalisé » : the address of a remote MCP server and a name. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('connecteur', [
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:500', fn ($attribute, $value, $fail) => Connectors::safeUrl((string) $value) ? null : $fail(__('The address must start with https:// and be a public address of an MCP server.'))],
        ], [], ['name' => __('name'), 'url' => __('address')]);
        abort_unless($this->connectors->enabled(), 404);

        $probe = $this->runner->connector('sonder', ['url' => $data['url']]);
        $mode = $probe['connexion'] ?? 'impossible';
        if (! ($probe['ok'] ?? false) || ! in_array($mode, [UserConnector::AUTH_NONE, UserConnector::AUTH_OAUTH], true)) {
            return redirect()->route('settings.connectors')->withErrors(['url' => $mode === 'impossible' && ($probe['ok'] ?? false)
                ? __('This server asks for an application registered with its publisher: Kekeli cannot connect to it by itself.')
                : __('Kekeli could not reach this MCP server. Check the address.')], 'connecteur')->withInput();
        }

        $connector = Auth::user()->connectors()->firstOrNew(['url' => $data['url']]);
        $connector->fill(['catalog_key' => null, 'name' => trim($data['name']), 'auth' => $mode]);
        if ($mode === UserConnector::AUTH_NONE) {
            $connector->forceFill(['connected_at' => now(), 'active' => true])->save();

            return $this->done(__(':name is connected.', ['name' => $connector->name]));
        }
        $connector->save();

        return $this->authorize($connector);
    }

    /** The return from the consent page of the service. */
    public function callback(Request $request): RedirectResponse
    {
        $pending = (array) $request->session()->pull(self::SESSION.'.'.Str::limit((string) $request->query('state'), 80, ''), []);
        $connector = isset($pending['id']) ? Auth::user()->connectors()->find($pending['id']) : null;
        if (! $connector || ! is_string($request->query('code'))) {
            return redirect()->route('settings.connectors')->with('connector-error', __('The connection was not completed. You can try again.'));
        }

        $result = $this->runner->connector('echanger', [
            'url' => $connector->url,
            'client' => $connector->client,
            'code' => $request->query('code'),
            'verificateur' => $pending['verifier'] ?? '',
            'redirection' => route('connectors.callback'),
            'ressource' => $connector->resource,
            'iss' => is_string($request->query('iss')) ? $request->query('iss') : null,
        ]);
        if (! ($result['ok'] ?? false) || ! is_string($result['jeton'] ?? null)) {
            return redirect()->route('settings.connectors')->with('connector-error', __('The connection was not completed. You can try again.'));
        }

        $this->connectors->saveTokens($connector, $result);
        $connector->forceFill(['active' => true])->save();

        return $this->done(__(':name is connected.', ['name' => $connector->name]));
    }

    /** « Déconnecter » : the tokens and the registration are erased; the journal keeps the past actions. */
    public function destroy(UserConnector $connector): RedirectResponse
    {
        abort_unless($connector->user_id === Auth::id(), 404);
        $name = $connector->name;
        $connector->delete();

        return $this->done(__(':name is disconnected.', ['name' => $name]));
    }

    private function authorize(UserConnector $connector): RedirectResponse
    {
        $state = Str::random(40);
        $result = $this->runner->connector('preparer', ['url' => $connector->url, 'redirection' => route('connectors.callback'), 'etat' => $state, 'client' => $connector->client]);
        if (! ($result['ok'] ?? false) || ! is_string($result['adresse'] ?? null) || ! str_starts_with($result['adresse'], 'https://')) {
            return redirect()->route('settings.connectors')->with('connector-error', ($result['raison'] ?? null) === 'inscription'
                ? __('This server asks for an application registered with its publisher: Kekeli cannot connect to it by itself.')
                : __('Kekeli could not reach :name right now. Try again in a moment.', ['name' => $connector->name]));
        }

        $connector->forceFill(['client' => (array) ($result['client'] ?? []), 'resource' => is_string($result['ressource'] ?? null) ? $result['ressource'] : null])->save();
        session()->put(self::SESSION.'.'.$state, ['id' => $connector->id, 'verifier' => (string) ($result['verificateur'] ?? '')]);

        return redirect()->away($result['adresse']);
    }

    private function done(string $status): RedirectResponse
    {
        return redirect()->route('settings.connectors')->with('connector-status', $status);
    }
}
