<?php

namespace App\Automations;

use App\Agent\RunStarter;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kekeli plugs the form of a site it built into an automation launched by an event, with the client's agreement:
 * a showcase site (no server, so no secret possible in the browser) sends its form from its own address, allowed in
 * FORMULAIRE_ORIGINES; a site with a database (a server) signs each call with the secret of the entry point, copied
 * into its own Secret variables. The entry point stays on the client's Cloudflare account, never at Kekeli.
 */
class FormWiring
{
    /** Secret variables given to a site with a database (read by its server, never by the browser). */
    public const SITE_URL = 'AUTOMATISATION_ADRESSE';

    public const SITE_SECRET = 'AUTOMATISATION_SECRET';

    public function __construct(private RunStarter $starter) {}

    /**
     * The client's sites and applications that can send a form.
     *
     * @return Collection<int, Project>
     */
    public function candidates(User $user): Collection
    {
        return $user->projects()
            ->whereIn('kind', [Project::KIND_SITE, Project::KIND_WEBAPP])
            ->where('is_agent', false)->where('is_automation', false)
            ->whereNotNull('backup_at')
            ->latest('updated_at')->limit(20)->get();
    }

    /** An entry point address: https, a public host, no secret in it. */
    public static function validUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? '') === 'https' && $host !== '' && str_contains($host, '.')
            && ! preg_match('/^(localhost|127\.|10\.|192\.168\.|169\.254\.)|\.local$|\.internal$/', $host)
            && ! isset($parts['user']) && ! str_contains((string) ($parts['query'] ?? ''), 'jeton=');
    }

    /**
     * Plug the form: the site's address is allowed (showcase site) or the secret is copied (site with a server), then
     * Kekeli changes the form of the site, as a normal work on the site (energy counted, version created).
     */
    public function wire(Project $automation, Project $site, string $url): ?AgentRun
    {
        $url = rtrim(trim($url), '/');
        $name = $automation->name;
        $withServer = $site->isWebApp();

        if ($withServer) {
            $site->secrets()->updateOrCreate(['name' => self::SITE_URL], ['value' => $url]);
            $site->secrets()->updateOrCreate(['name' => self::SITE_SECRET], ['value' => AutomationTrigger::entrySecret($automation)]);
        } else {
            AutomationTrigger::allowOrigin($automation, $site->published_url ? (string) Str::of($site->published_url)->match('#^https://[^/]+#') : 'https://'.$site->pagesName().'.pages.dev');
        }

        $message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => __('Connect the form of this site to my automation « :name ».', ['name' => $name])]);

        $instructions = $withServer
            ? implode("\n", [
                '[Consignes de Kekeli] Le client a accepté de brancher le formulaire de ce site sur son automatisation « '.$name.' ».',
                '- Quand le formulaire (contact, commande ou inscription) est bien enregistré, le serveur envoie aussi ses champs à l\'automatisation : POST JSON vers la variable d\'environnement '.self::SITE_URL.', avec l\'en-tête « X-Kekeli-Signature: t=<secondes>,v1=<HMAC-SHA256 en hexadécimal de "<t>.<corps>" avec la variable '.self::SITE_SECRET.'> » et l\'en-tête « X-Evenement-Id » (identifiant unique de l\'enregistrement).',
                '- Lis ces deux variables seulement côté serveur (jamais dans le navigateur, jamais écrites dans le code) ; sans elles, rien n\'est envoyé. Un échec de l\'envoi ne bloque jamais l\'enregistrement ni la réponse au visiteur (délai court, erreur seulement dans le journal du serveur).',
                '- Ajoute un test avec un faux serveur HTTP qui vérifie la signature, et explique le branchement dans le LISEZ-MOI. Ne change rien d\'autre.',
            ])
            : implode("\n", [
                '[Consignes de Kekeli] Le client a accepté de brancher le formulaire de contact de ce site sur son automatisation « '.$name.' ».',
                '- Le formulaire envoie ses champs (méthode POST, format application/x-www-form-urlencoded) à l\'adresse '.$url.' avec fetch, puis affiche « Merci, votre message est bien envoyé. » sous le formulaire (ou un message d\'erreur poli). Garde les noms de champs simples en français (nom, telephone, email, message).',
                '- Ajoute un champ piège invisible pour les personnes, nommé « site_web » (classe qui le cache, tabindex="-1", autocomplete="off"), laissé vide : les robots le remplissent.',
                '- Le formulaire marche toujours au clavier et sur un téléphone ; aucune clé ni aucun secret dans la page (l\'adresse n\'est pas secrète : le point d\'entrée vérifie l\'adresse du site). Ne change rien d\'autre.',
            ]);

        $run = $this->starter->startWith($site, $message, $instructions);

        $trigger = (array) ($automation->automation_trigger ?? []);
        $trigger['branched'] = collect((array) ($trigger['branched'] ?? []))->reject(fn ($b) => ($b['project'] ?? null) === $site->id)
            ->push(['project' => $site->id, 'name' => $site->name, 'url' => $url, 'at' => now()->toIso8601String()])->values()->all();
        $automation->forceFill(['automation_trigger' => $trigger])->save();

        return $run;
    }
}
