<?php

namespace App\Agent;

use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends the signed requests to the Runner.
 */
class RunnerClient
{
    public function __construct(private ProviderList $providers, private Settings $settings) {}

    /**
     * Send a request to the agent. The Runner answers at once, then reports each step.
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    public function send(AgentRun $run, bool $resume = false): void
    {
        $run->loadMissing('message', 'project');
        $project = $run->project;

        $this->post('/taches', [
            'tache' => $run->id,
            'projet' => $run->project_id,
            // Consignes en plus du message du client (par exemple : le nom du site, ou le site de départ avec base de données).
            'message' => trim(($run->message?->content ?? '').($run->instructions ? "\n\n".$run->instructions : '')),
            'reprise' => $resume,
            'fournisseurs' => $this->providers->forRunner(),
            'rappel' => route('runner.events'),
            'sites' => $project->allowedDomains(),
            'sauvegarde' => $this->backupUrls($project),
            'historique' => $this->history($run),
            'duree_vie_minutes' => app(Energy::class)->pauseMinutes($project->user),
            'pays' => $this->country($project),
            'photos_client' => $this->clientPhotos($project),
            'modele_vision' => config('kekeli.vision_model'),
            'images_restantes' => app(ImageQuota::class)->remainingToday(),
            // Mode développeur : le dépôt GitHub du client, ouvert dans l'ordinateur à la première demande.
            'depot' => $project->isRepository() ? [
                'depot' => $project->repository,
                'branche_base' => $project->repository_base_branch,
                'branche_travail' => $project->work_branch,
                'archive' => route('runner.repository.archive', $project),
                'lecture_seule' => ! $project->canWriteRepository(),
            ] : null,
            // Application web : technologie et modèle de départ (posé dans l'ordinateur à la première demande).
            'application' => $project->isWebApp() ? [
                'pile' => $project->app_stack,
                'modele' => $project->app_template ?: 'socle',
            ] : null,
        ]);
    }

    /**
     * The first responder: the model answers the client's message or chooses a tool, without a computer.
     *
     * @return array<string, mixed>
     */
    public function respond(AgentRun $run, string $place): array
    {
        $run->loadMissing('message.photos', 'project');
        $project = $run->project;
        $country = $this->country($project);

        $context = ['pays' => $country['nom'] ?? null, 'monnaie' => $country['monnaie'] ?? null, 'photos' => $run->message?->photos->count() ?? 0];
        // Une discussion qui n'a encore que son titre provisoire : le modèle donne le vrai titre avec sa réponse.
        if (app(ChatTitles::class)->isTemporary($project)) {
            $context['titre'] = true;
        }
        if ($place === 'site') {
            $files = [];
            if ($project->backup_at) {
                try {
                    $files = array_keys(app(ProjectFiles::class)->read($project));
                } catch (\Throwable) {
                    $files = [];
                }
            }
            $context += [
                'nom' => $project->name,
                'type' => match (true) {
                    $project->isRepository() => 'depot',
                    $project->isWebApp() => $project->app_stack === 'nextjs' ? 'nextjs' : 'laravel',
                    default => 'vitrine',
                },
                'publie' => $project->published_url,
                'fichiers' => array_slice($files, 0, 40),
            ];
        }

        $body = json_encode([
            'projet' => $project->id,
            'lieu' => $place,
            'message' => (string) $run->message?->content,
            'contexte' => $context,
            'historique' => $this->history($run),
            'fournisseurs' => $this->providers->forRunner(),
            // Recherches sur Internet encore permises au client (par jour selon sa formule, et plafond mensuel global).
            'recherche' => ['permise' => true, 'restantes' => app(WebSearchQuota::class)->remaining($project->user)],
            // Pendant une recherche, le Runner l'annonce (« Recherche sur Internet… »).
            'rappel' => route('runner.events'),
            'tache' => $run->id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->request($body)->timeout(110)->post($this->url('/repondre'));

        return (array) $response->json() + ['ok' => false];
    }

    /**
     * Give the user's answer to a permission asked by the agent.
     */
    public function answerPermission(AgentPermission $permission, bool $allowed): bool
    {
        $permission->loadMissing('run');

        try {
            $this->post('/permissions', [
                'projet' => $permission->run->project_id,
                'permission' => $permission->request_id,
                'decision' => $allowed ? 'autoriser' : 'refuser',
            ]);
        } catch (RequestException) {
            return false;
        }

        return true;
    }

    /**
     * Wake the project's computer to show the preview again, without a request to the agent.
     */
    public function wakePreview(Project $project): void
    {
        $this->post('/apercus', [
            'projet' => $project->id,
            'rappel' => route('runner.project-events', $project),
            'sites' => $project->allowedDomains(),
            'sauvegarde' => $this->backupUrls($project),
            'duree_vie_minutes' => app(Energy::class)->pauseMinutes($project->user),
        ]);
    }

    /**
     * Permanently erase the computers of a deleted project.
     */
    public function erase(string $projectId): void
    {
        $this->post('/projets/effacer', ['projet' => $projectId]);
    }

    /**
     * Where the Runner sends the backup after each request, and where it fetches the last one.
     *
     * @return array{envoi: string, telechargement: string|null}
     */
    /**
     * Country of the project's owner, as the agent reads it (null until the client has chosen it).
     *
     * @return array<string, string>|null
     */
    private function country(Project $project): ?array
    {
        $country = $project->user?->countryDetails();

        return $country ? [
            'code' => $country['code'],
            'nom' => $country['name'],
            'region' => $country['region'],
            'ville' => $country['city'],
            'anglais' => $country['english'],
            'monnaie' => $country['currency'],
            'telephone' => $country['phone'],
        ] : null;
    }

    /**
     * Photos sent by the client, with the signed address where the Runner fetches each one.
     *
     * @return list<array{nom: string, url: string}>
     */
    private function clientPhotos(Project $project): array
    {
        return $project->photos()->get()->map(fn ($photo) => [
            'nom' => $photo->name,
            'url' => route('runner.photos.show', [$project, $photo]),
        ])->values()->all();
    }

    private function backupUrls(Project $project): array
    {
        return [
            'envoi' => route('runner.backup.store', $project),
            'telechargement' => $project->backup_at ? route('runner.backup.show', $project) : null,
        ];
    }

    /**
     * The last exchanges of the conversation, for an agent that starts again from a backup.
     *
     * @return list<array{role: string, contenu: string}>
     */
    private function history(AgentRun $run): array
    {
        $project = $run->project;
        $reports = $project->agentRuns()->whereNotNull('report')
            ->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))
            ->get()->keyBy('message_id');

        return $project->messages()
            ->when($run->message_id, fn ($query) => $query->where('id', '<', $run->message_id))
            ->get()
            ->take(-10)
            ->flatMap(function (Message $message) use ($reports) {
                $entries = [['role' => $message->role, 'contenu' => mb_substr($message->content, 0, 1500)]];
                // Le dernier message de Kekeli après un travail sur le site fait aussi partie de la conversation.
                $done = (string) data_get($reports->get($message->id), 'report.done', '');
                if ($message->role === Message::ROLE_USER && $done !== '') {
                    $entries[] = ['role' => Message::ROLE_ASSISTANT, 'contenu' => mb_substr($done, 0, 1500)];
                }

                return $entries;
            })
            ->take(-10)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    private function post(string $path, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->request($body)->post($this->url($path))->throw();
    }

    /**
     * Publish the last backup of the project on Cloudflare Pages. The Runner answers once the site is online
     * (a few seconds to a minute).
     *
     * @return array{adresse: string, deploiement?: string|null, fichiers?: int}
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    public function publish(Project $project): array
    {
        $body = json_encode([
            'projet' => $project->id,
            'nom' => $project->pagesName(),
            'sauvegarde' => route('runner.backup.show', $project),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->request($body)->timeout(240)->post($this->url('/publier'));

        if ($response->status() === 503) {
            throw new RuntimeException('cloudflare_absent');
        }

        $data = $response->throw()->json();

        if (! is_array($data) || ! is_string($data['adresse'] ?? null) || ! preg_match('#^https://[a-z0-9.-]+\.pages\.dev$#', $data['adresse'])) {
            throw new RuntimeException('Réponse de publication inattendue.');
        }

        return $data;
    }

    /**
     * Add, check or remove the client's own domain name for the published site (paid formulas).
     *
     * @return array{domaine: ?string, statut: string, cible: string, explication: ?string}
     */
    public function domain(Project $project, string $action, string $domain): array
    {
        $body = json_encode([
            'action' => $action,
            'nom' => $project->pagesName(),
            'domaine' => $domain,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->request($body)->timeout(90)->post($this->url('/domaine'));

        if ($response->status() === 503) {
            throw new RuntimeException('cloudflare_absent');
        }

        $data = $response->throw()->json();

        if (! is_array($data) || ! is_string($data['statut'] ?? null)) {
            throw new RuntimeException('Réponse inattendue pour le nom de domaine.');
        }

        return $data;
    }

    /**
     * State of the providers as seen by the Runner (keys present, providers set aside).
     *
     * @return array{cles_presentes: list<string>, mis_de_cote: array<string, string>}|null
     */
    public function state(): ?array
    {
        try {
            $response = $this->request('')->timeout(3)->get($this->url('/etat'));
        } catch (ConnectionException|RuntimeException) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    private function request(string $body)
    {
        $secret = config('kekeli.runner.secret');

        if (blank($secret)) {
            throw new RuntimeException('KEKELI_RUNNER_SECRET is missing.');
        }

        $request = Http::acceptJson()
            ->timeout(config('kekeli.runner.timeout', 10))
            ->withHeaders(RunnerSignature::headers($secret, $body));

        return $body === '' ? $request : $request->withBody($body, 'application/json');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('kekeli.runner.url'), '/').$path;
    }
}
