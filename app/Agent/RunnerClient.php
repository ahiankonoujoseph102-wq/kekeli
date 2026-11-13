<?php

namespace App\Agent;

use App\Agent\Tools\CreateVideo;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Models\DocumentVersion;
use App\Models\Message;
use App\Models\Project;
use App\Support\Connectors;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
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
            // Le modèle de construction de la formule du client d'abord, puis les fournisseurs gratuits en secours.
            'fournisseurs' => app(AnswerModels::class)->buildForRunner($project->user),
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
                'installable' => $project->isApplication(),
                'service' => $project->app_service,
            ] : null,
            // Agent IA : où le Runner demande si l'essai gratuit du jour permet encore un message (adresse signée).
            'essai_agent' => $project->usesClientModel() ? route('runner.agent-trial', $project) : null,
            // Variables secrètes du client (ses propres services) : seulement pour l'ordinateur de ce projet.
            'secrets' => $project->isWebApp() ? $project->secrets()->get()->mapWithKeys(fn ($secret) => [$secret->name => $secret->value])->all() : [],
        ]);
    }

    /**
     * « Lancer maintenant »: the automation runs once in the computer of the project (woken up from its backup), with
     * the client's Secret variables. The Runner answers at once, then sends the log line by line.
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    public function launch(AgentRun $run): void
    {
        $project = $run->project;

        $secrets = $project->secrets()->get()->mapWithKeys(fn ($secret) => [$secret->name => $secret->value])->all();
        // Pour ce lancement seulement : les services du client (e-mail, son modèle) en plus des sites autorisés.
        $hosts = array_filter([
            filled($secrets['RESEND_API_KEY'] ?? null) ? 'api.resend.com' : null,
            parse_url((string) ($secrets['MODELE_API_URL'] ?? ''), PHP_URL_HOST) ?: null,
        ]);

        $this->post('/lancer', [
            'tache' => $run->id,
            'projet' => $project->id,
            'rappel' => route('runner.events'),
            'sites' => array_values(array_unique([...$project->allowedDomains(), ...$hosts])),
            'sauvegarde' => $this->backupUrls($project),
            'duree_vie_minutes' => app(Energy::class)->pauseMinutes($project->user),
            'application' => ['pile' => $project->app_stack, 'modele' => $project->app_template ?: 'socle', 'installable' => false, 'service' => $project->app_service],
            // Sans clé de modèle du client : l'essai gratuit du jour, par le relais du Runner.
            'essai_agent' => route('runner.agent-trial', $project),
            'secrets' => $secrets,
        ]);
    }

    /**
     * The text of a version, short, for the first responder (to answer a question on the document without a new one).
     *
     * @param  array<string, mixed>  $structure
     */
    public static function documentSummary(array $structure): string
    {
        $parts = [(string) ($structure['titre'] ?? '')];
        foreach ((array) ($structure['blocs'] ?? $structure['diapositives'] ?? []) as $block) {
            $parts[] = collect((array) $block)->flatten()->filter(fn ($value) => is_string($value) && ! in_array($value, ['paragraphe', 'liste', 'tableau', 'titre1', 'titre2', 'titre3', 'points'], true))->implode(' ');
        }

        return mb_substr(trim(implode("\n", array_filter($parts))), 0, 3000);
    }

    /**
     * A version of a document or a presentation: the model writes it from the Runner, an isolated computer lays it
     * out, and the files come back to the site (signed address « envoi »). The previous version is given for a change.
     *
     * @throws RuntimeException|ConnectionException|RequestException
     */
    public function document(AgentRun $run, bool $freeOnly = false): void
    {
        $run->loadMissing('message', 'project');
        $project = $run->project;
        $options = (array) $run->document_options;
        $attachment = filled($options['fichier'] ?? null) ? $project->attachments()->find($options['fichier']) : null;
        $previous = $project->documentVersions()->latest('number')->first();
        $country = $this->country($project);
        $searches = app(WebSearchQuota::class)->remaining($project->user);

        $type = $options['type'] ?? ($project->document_type ?: 'document');
        $image = $type === 'image' ? $this->imageRequest($run, $previous, ($options['action'] ?? '') === 'modifier') : [];
        $video = $type === 'video' ? $this->videoRequest($run, $previous, ($options['action'] ?? '') === 'modifier') : [];

        $this->post('/documents', [
            ...($image ? ['images' => app(AnswerModels::class)->imageFor($project->user)] : []),
            ...($video ? ['video' => $video['reglages']] : []),
            'tache' => $run->id,
            'projet' => $project->id,
            'rappel' => route('runner.events'),
            'envoi' => route('runner.documents.store', ['project' => $project, 'run' => $run->id]),
            // Le modèle de construction de la formule ; les fournisseurs gratuits seulement pour un petit document
            // quand le plafond du jour est atteint.
            'fournisseurs' => $freeOnly ? $this->providers->forRunner() : app(AnswerModels::class)->buildForRunner($project->user),
            'document' => [
                'type' => $type,
                'action' => $options['action'] ?? 'creer',
                'demande' => $this->documentRequest($run),
                'plan' => (string) ($options['plan'] ?? ''),
                'precedente' => ($options['action'] ?? '') === 'modifier' ? $previous?->structure : null,
                'formats' => array_values((array) ($options['formats'] ?? [])),
                'source' => $attachment ? ['nom' => $attachment->name, 'format' => $attachment->format, 'url' => route('runner.attachments.show', ['project' => $project, 'attachment' => $attachment])] : null,
                'recherche' => ['requete' => (string) ($options['recherche'] ?? ''), 'permise' => $searches > 0],
                'pays' => $country['nom'] ?? null,
                'monnaie' => $country['monnaie'] ?? null,
                ...$image,
                ...($video['document'] ?? []),
            ],
        ]);
    }

    /**
     * For a video: the settings of the client's formula (longest video, voice-over while the day's OpenAI ceiling is
     * not reached, images created at most), the client's media (photos, videos and sounds of the project, numbered in
     * the order they were sent, read by the Runner with signed addresses), the format, and for a change the images
     * created for the previous version (kept when the scene does not change).
     *
     * @return array{reglages: array<string, mixed>, document: array<string, mixed>}
     */
    private function videoRequest(AgentRun $run, ?DocumentVersion $previous, bool $change): array
    {
        $project = $run->project;
        $settings = app(Settings::class);
        $openAi = app(AnswerModels::class)->openAiAvailable();
        $options = (array) $run->document_options;

        $media = collect()
            ->concat($project->photos()->get()->map(fn ($photo) => ['moment' => $photo->created_at, 'id' => 'p'.$photo->id, 'type' => 'image', 'nom' => $photo->name ?? null, 'url' => route('runner.photos.show', ['project' => $project, 'photo' => $photo])]))
            ->concat($project->attachments()->get()->filter(fn ($file) => $file->isMedia())->map(fn ($file) => ['moment' => $file->created_at, 'id' => 'f'.$file->id, 'type' => $file->mediaType(), 'nom' => $file->name, 'url' => route('runner.attachments.show', ['project' => $project, 'attachment' => $file])]))
            ->sortBy([['moment', 'asc'], ['id', 'asc']])
            ->take(12)
            ->map(fn ($item) => ['url' => $item['url'], 'type' => $item['type'], 'nom' => $item['nom']])
            ->values()->all();

        $previousMedia = [];
        if ($change && $previous) {
            foreach ((array) $previous->files as $key => $name) {
                if (is_string($name) && preg_match('/^media-\d{1,2}\.jpg$/', $name)) {
                    $previousMedia[$name] = route('runner.versions.media', ['project' => $project, 'version' => $previous, 'name' => $name]);
                }
            }
        }

        return [
            'reglages' => [
                'duree_max' => CreateVideo::maxSeconds($project->user),
                'images_max' => $settings->int('video_images_max'),
                'voix' => ['permise' => $openAi, 'modele' => (string) $settings->get('video_voice_model'), 'voix' => (string) $settings->get('video_voice')],
                'voix_gratuites' => array_values(array_filter((array) $settings->get('video_free_voices'), 'is_array')),
                // Le générateur d'images de la formule, le même que pour les affiches (payant pour les formules payantes).
                'images' => app(AnswerModels::class)->imageFor($project->user),
                'transcription' => ['permise' => $openAi, 'modele' => (string) $settings->get('video_transcription_model')],
            ],
            'document' => [
                'format' => in_array($options['format'] ?? null, CreateVideo::FORMATS, true) ? $options['format'] : 'auto',
                // Une modification garde la durée de la version précédente, sauf si le client en demande une autre.
                'duree_voulue' => (int) ($options['duree'] ?? ($change && $previous ? round((float) data_get($previous->structure, 'duree', 0)) : 0)),
                'joints' => $media,
                'medias_precedents' => $previousMedia,
            ],
        ];
    }

    /**
     * For an image: the photos or the logo joined with « + » (the last message that has some, two at most), read by the
     * Runner with signed addresses, and the image of the previous version (to keep its drawing for a change).
     *
     * @return array{format: string, joints: list<array{url: string}>, fond_precedent: ?string}
     */
    private function imageRequest(AgentRun $run, ?DocumentVersion $previous, bool $change): array
    {
        $project = $run->project;
        $message = $project->messages()->where('role', Message::ROLE_USER)->where('id', '<=', $run->message_id)->whereHas('photos')->latest('id')->first();
        $photos = $message ? $message->photos()->oldest('id')->limit(2)->get() : collect();

        return [
            'format' => in_array($run->document_options['format'] ?? null, ['carre', 'vertical', 'paysage', 'a4', 'a3'], true) ? $run->document_options['format'] : 'auto',
            'joints' => $photos->map(fn ($photo) => ['url' => route('runner.photos.show', ['project' => $project, 'photo' => $photo])])->values()->all(),
            'fond_precedent' => $change && $previous && filled(data_get($previous->files, 'fond')) ? route('runner.versions.background', ['project' => $project, 'version' => $previous]) : null,
        ];
    }

    /**
     * The request of the client, with the few messages before it (« Construire ce plan » or « oui » alone say nothing).
     */
    private function documentRequest(AgentRun $run): string
    {
        $said = $run->project->messages()->where('role', Message::ROLE_USER)->where('id', '<=', $run->message_id)->latest('id')->limit(4)->pluck('content')->reverse()->implode("\n");

        return mb_substr($said, 0, 6000);
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
        // Les fichiers joints par le client (Word, PDF, PowerPoint) : leur nom et leur format, jamais leur contenu ici.
        $attachments = $project->attachments()->latest('id')->limit(10)->get();
        if ($attachments->isNotEmpty()) {
            $context['fichiers_joints'] = $attachments->map(fn ($file) => ['nom' => $file->name, 'format' => $file->label()])->values()->all();
        }
        if ($place === 'document') {
            $version = $project->documentVersions()->latest('number')->first();
            $context += [
                'nom' => $project->name,
                'type' => $project->document_type ?: 'document',
                'resume' => $version ? self::documentSummary((array) $version->structure) : null,
            ];
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
                    $project->isAgent() => 'agent',
                    $project->isAutomation() => 'automatisation',
                    $project->isWebApp() => $project->app_stack === 'nextjs' ? 'nextjs' : 'laravel',
                    default => 'vitrine',
                },
                'publie' => $project->published_url,
                'application' => $project->isApplication(),
                'fichiers' => array_slice($files, 0, 40),
            ];
        }

        // Les connecteurs seulement avec le modèle payant de la formule (sinon, Kekeli répond sans eux).
        $connectors = app(AnswerModels::class)->connectorModelsFor($project->user) !== [] ? app(Connectors::class)->forRunner($project->user, $project) : [];
        $approval = null;
        if ($run->connector_action_id && ($action = ConnectorAction::query()->where('user_id', $project->user_id)->find($run->connector_action_id))) {
            $approval = ['decision' => 'autoriser', 'connecteur' => $action->user_connector_id, 'outil' => $action->tool, 'arguments' => (object) ((array) $action->arguments)];
        }

        $body = json_encode([
            'projet' => $project->id,
            'lieu' => $place,
            'message' => (string) $run->message?->content,
            'contexte' => $context,
            // Question sur le code : le premier répondant peut lire les fichiers de la dernière sauvegarde (10 minutes).
            'lecture' => $place === 'site' && $project->backup_at ? URL::temporarySignedRoute('runner.file.show', now()->addMinutes(10), ['project' => $project]) : null,
            // Une longue discussion : les derniers messages en entier, le début résumé (invisible pour le client).
            ...$this->historyWithSummary($run, $context),
            // Le modèle de la formule du client d'abord (qualité), puis les fournisseurs gratuits en secours. Avec un
            // connecteur actif, seulement le modèle payant : les données du client ne vont jamais à une offre gratuite.
            'fournisseurs' => $connectors !== [] ? app(AnswerModels::class)->connectorModelsFor($project->user) : app(AnswerModels::class)->forRunner($project->user),
            // Les connecteurs actifs du client (MCP), et l'action qu'il vient d'autoriser.
            'connecteurs' => $connectors,
            'accord' => $approval,
            // Recherches sur Internet encore permises au client (par jour selon sa formule, et plafond mensuel global).
            'recherche' => ['permise' => true, 'restantes' => app(WebSearchQuota::class)->remaining($project->user)],
            // Pendant une recherche, le Runner l'annonce (« Recherche sur le Web… »).
            'rappel' => route('runner.events'),
            'tache' => $run->id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->request($body)->timeout(110)->post($this->url('/repondre'));

        return (array) $response->json() + ['ok' => false];
    }

    /**
     * The connection of a connector (MCP), done by the Runner with the official kit: « sonder » (does the server need
     * a login?), « preparer » (registration of Kekeli, PKCE, address of consent), « echanger » (code for tokens),
     * « renouveler » (new access token). The tokens come back here and are encrypted.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function connector(string $action, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            return (array) $this->request($body)->timeout(40)->post($this->url('/connecteurs/'.$action))->json() + ['ok' => false];
        } catch (\Throwable $exception) {
            report($exception);

            return ['ok' => false];
        }
    }

    /**
     * Have a short voice dictation written by OpenAI (only when the browser of the client cannot do it).
     *
     * @return array{ok: bool, texte?: string, secondes?: int|null}
     */
    public function transcribe(string $audio, string $type, string $language, string $model): array
    {
        $body = json_encode(['audio' => $audio, 'type' => $type, 'langue' => $language, 'modele' => $model], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return (array) $this->request($body)->timeout(70)->post($this->url('/transcrire'))->json() + ['ok' => false];
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
            // Application ou agent IA : relancé dans l'aperçu ; agent : son essai gratuit suivi par le Runner.
            'application' => $project->isWebApp() ? ['pile' => $project->app_stack, 'modele' => $project->app_template ?: 'socle', 'installable' => $project->isApplication(), 'service' => $project->app_service] : null,
            'essai_agent' => $project->usesClientModel() ? route('runner.agent-trial', $project) : null,
        ]);
    }

    /**
     * Put the files of a version in the computer of the project, like the waking of the preview: « voir » (an old
     * version in the preview, the current files kept aside), « revenir » (back to the current files) or « remettre »
     * (restoration, or return to the last version that worked; the Runner then sends a new backup).
     */
    public function applyVersion(Project $project, string $action, ?int $number): void
    {
        $version = $number !== null ? $project->versions()->where('number', $number)->first() : null;
        $this->post('/apercus', [
            'projet' => $project->id,
            'rappel' => route('runner.project-events', $project),
            'sites' => $project->allowedDomains(),
            'sauvegarde' => $this->backupUrls($project),
            'duree_vie_minutes' => app(Energy::class)->pauseMinutes($project->user),
            'application' => $project->isWebApp() ? ['pile' => $project->app_stack, 'modele' => $project->app_template ?: 'socle', 'installable' => $project->isApplication(), 'service' => $project->app_service] : null,
            'essai_agent' => $project->usesClientModel() ? route('runner.agent-trial', $project) : null,
            'version' => ['action' => $action, 'numero' => $number, 'archive' => $version ? route('runner.project-versions.archive', [$project, $version->number]) : null],
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
    /** Messages au-delà desquels le début d'une discussion est résumé, et messages gardés entiers ensuite. */
    public const SUMMARY_ABOVE = 10;

    public const WHOLE_MESSAGES = 6;

    /** Messages nouveaux à ajouter au résumé avant de le refaire (le résumé est refait par paquets, pas à chaque message). */
    public const SUMMARY_BATCH = 4;

    /**
     * The conversation sent to the first responder: the last messages whole; when the discussion is long, its
     * beginning as a summary kept by the site (names, prices, numbers and decisions are kept word for word). The
     * summary is redone by batches by the economic model, never at each message.
     *
     * @param  array<string, mixed>  $context
     * @return array{historique: list<array{role: string, contenu: string}>, contexte?: array<string, mixed>, resumer?: array<string, mixed>}
     */
    private function historyWithSummary(AgentRun $run, array $context): array
    {
        $entries = $this->history($run, 200);
        if (count($entries) <= self::SUMMARY_ABOVE) {
            return ['historique' => $entries];
        }

        $project = $run->project;
        $older = array_slice($entries, 0, -self::WHOLE_MESSAGES);
        $new = array_values(array_filter($older, fn ($entry) => $entry['id'] > (int) $project->summary_until_message_id));
        $result = ['historique' => array_map(fn ($entry) => ['role' => $entry['role'], 'contenu' => $entry['contenu']], array_slice($entries, -self::WHOLE_MESSAGES))];
        if (filled($project->conversation_summary)) {
            $result['contexte'] = $context + ['resume' => (string) $project->conversation_summary];
        }
        // Pas encore de résumé, ou assez de nouveaux messages : le Runner refait le résumé avant de répondre.
        if ($new !== [] && (blank($project->conversation_summary) || count($new) >= self::SUMMARY_BATCH)) {
            $result['resumer'] = [
                'precedent' => (string) $project->conversation_summary,
                'messages' => array_map(fn ($entry) => ['role' => $entry['role'], 'contenu' => $entry['contenu']], $new),
                'jusqu_a' => end($new)['id'],
                // Le modèle économique (celui de la formule Gratuite), puis les fournisseurs gratuits.
                'fournisseurs' => app(AnswerModels::class)->economic(),
            ];
        }

        return $result;
    }

    private function history(AgentRun $run, ?int $limit = null): array
    {
        $project = $run->project;
        $reports = $project->agentRuns()->whereNotNull('report')
            ->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))
            ->get()->keyBy('message_id');

        return $project->messages()
            ->when($run->message_id, fn ($query) => $query->where('id', '<', $run->message_id))
            ->get()
            ->take(-($limit ?? 10))
            ->flatMap(function (Message $message) use ($reports, $limit) {
                $id = $limit !== null ? ['id' => $message->id] : [];
                $entries = [['role' => $message->role, 'contenu' => mb_substr($message->content, 0, 1500)] + $id];
                // Le dernier message de Kekeli après un travail sur le site fait aussi partie de la conversation.
                $done = (string) data_get($reports->get($message->id), 'report.done', '');
                if ($message->role === Message::ROLE_USER && $done !== '') {
                    $entries[] = ['role' => Message::ROLE_ASSISTANT, 'contenu' => mb_substr($done, 0, 1500)] + $id;
                }

                return $entries;
            })
            ->take(-($limit ?? 10))
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
