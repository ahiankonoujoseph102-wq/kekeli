<?php

namespace App\Agent;

use App\Jobs\PushProjectToGithub;
use App\Jobs\PushRepository;
use App\Jobs\SendAgentRun;
use App\Models\AdminAlert;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\DocumentVersion;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProviderUsage;
use App\Projects\ProjectVersions;
use App\Support\SecretScanner;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records the events sent by the Runner while the agent works.
 */
class RunnerEventHandler
{
    public function __construct(private ProviderList $providers, private Costs $costs, private Energy $energy) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(AgentRun $run, array $event): void
    {
        match ($event['type'] ?? null) {
            'demarrage' => $this->started($run),
            'apercu' => $this->preview($run->project, $event),
            'permission' => $this->permissionAsked($run, $event),
            'permission_reglee' => $this->permissionSettled($run, $event),
            'fournisseur' => $run->update([
                'provider' => $this->text($event, 'fournisseur', 30),
                'model' => $this->text($event, 'modele', 120),
            ]),
            'etape' => $this->step($run, $event),
            'requete' => $this->request($run, $event),
            'recherche' => $this->searching($run, $event),
            // Un connecteur du client utilisé pendant la réponse (« Utilise Notion… »).
            'connecteur' => $run->steps()->create(['kind' => 'connecteur', 'target' => $this->text($event, 'nom', 60)]),
            'images' => $this->imagesCreated($run, $event),
            'image_payante' => $this->paidImage($run, $event),
            'voix' => $this->voice($run, $event),
            'rendu' => $this->rendering($run, $event),
            'depot' => $this->repositoryState($run->project, $event),
            'narration' => $run->steps()->create(['kind' => 'narration', 'target' => $this->text($event, 'texte', 300)]),
            'modifications' => $this->changes($run, $event),
            'depot_detection' => $this->repositoryDetection($run->project, $event),
            'application' => $this->webAppAccess($run->project, $event),
            'refus' => $this->increment($event, ['rate_limited' => 1]),
            'mis_de_cote' => $this->setAside($event),
            'document' => $this->documentVersion($run, $event),
            'journal' => $this->launchLine($run, $event),
            'lancement' => $this->launched($run, $event),
            'terminee' => $this->completed($run, $event),
            'en_attente' => $this->waiting($run, $event),
            'arretee' => $this->stopped($run, $event),
            'erreur' => $this->failed($run, $event),
            default => null,
        };
    }

    /**
     * A new version of a document or a presentation: its files were sent before (RunnerDocumentController); only those
     * really received are kept. The number follows the previous one; the older versions stay downloadable.
     *
     * @param  array<string, mixed>  $event
     */
    private function documentVersion(AgentRun $run, array $event): void
    {
        if (! $run->isDocumentWork() || ! $run->isActive()) {
            return;
        }
        $project = $run->project;
        $disk = Storage::disk(ProjectPhoto::disk());
        $folder = DocumentVersion::folder($project, $run->id);
        $files = collect((array) ($event['fichiers'] ?? []))
            ->filter(fn ($name, $key) => in_array($key, [...array_keys(DocumentVersion::FORMATS), 'fond'], true) || preg_match(DocumentVersion::VIDEO_EXTRA_FILES, (string) $key))
            ->filter(fn ($name) => is_string($name) && preg_match(DocumentVersion::FILE_NAMES, $name) && ! str_starts_with($name, 'apercu-') && $disk->exists($folder.'/'.$name))
            ->all();
        $previews = collect((array) ($event['apercus'] ?? []))
            ->filter(fn ($name) => is_string($name) && preg_match('/^apercu-\d{1,3}\.jpg$/', $name) && $disk->exists($folder.'/'.$name))
            ->take(40)->values()->all();
        if (array_diff_key($files, ['fond' => true, 'medias' => true]) === [] || ($project->isVideo() && ! isset($files['mp4']))) {
            return;
        }
        $structure = is_array($event['structure'] ?? null) ? $event['structure'] : null;
        $title = $this->text($event, 'titre', 120) ?: $project->name;

        DB::transaction(function () use ($run, $project, $files, $previews, $structure, $title, $event) {
            $project->newQuery()->whereKey($project->id)->lockForUpdate()->first();
            $number = (int) $project->documentVersions()->max('number') + 1;
            $project->documentVersions()->create([
                'agent_run_id' => $run->id,
                'number' => $number,
                'title' => $title,
                'request' => mb_substr((string) $run->message?->content, 0, 2000),
                'files' => $files,
                'previews' => $previews,
                'pages' => max(count($previews), (int) ($event['pages'] ?? 0)),
                // La structure (sans le texte du fichier joint) sert à faire la version suivante.
                'structure' => $structure,
            ]);
            // Le premier document donne son nom au projet, sauf si le client l'a déjà renommé.
            $first = (string) $project->messages()->where('role', Message::ROLE_USER)->value('content');
            $temporary = $project->name === app(ChatTitles::class)->guess($first);
            if ($number === 1 && $temporary && filled($title)) {
                $project->forceFill(['name' => $title])->save();
            }
        });

        // Une recherche sur Internet faite pour ce document : comptée comme celles du premier répondant.
        $searches = max(0, min(2, (int) ($event['recherches'] ?? 0)));
        if ($searches > 0) {
            $cost = $searches * app(Settings::class)->float('web_search_price_usd');
            AgentRun::query()->whereKey($run->id)->update([
                'web_searches' => DB::raw('web_searches + '.$searches),
                'web_search_cost_usd' => DB::raw('web_search_cost_usd + '.number_format($cost, 6, '.', '')),
            ]);
        }
    }

    /** Status of a line of the log of an automation. */
    public const LAUNCH_STATUSES = ['ok', 'erreur', 'attention', 'info'];

    /**
     * One line of the readable log of « Lancer maintenant », shown live (step, status, message).
     *
     * @param  array<string, mixed>  $event
     */
    private function launchLine(AgentRun $run, array $event): void
    {
        if (! $run->isLaunch() || ! $run->isActive()) {
            return;
        }
        $line = self::launchLineFrom($event);
        if ($line === null) {
            return;
        }
        DB::transaction(function () use ($run, $line) {
            $fresh = AgentRun::query()->lockForUpdate()->find($run->id);
            $lines = (array) data_get($fresh?->report, 'launch.lines', []);
            if (! $fresh || count($lines) >= 60) {
                return;
            }
            $lines[] = $line;
            $fresh->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => $fresh->started_at ?? now(), 'report' => self::launchReport(null, '', $lines)])->save();
        });
    }

    /**
     * The end of « Lancer maintenant »: the summary in French and the whole log.
     *
     * @param  array<string, mixed>  $event
     */
    private function launched(AgentRun $run, array $event): void
    {
        if (! $run->isLaunch()) {
            return;
        }
        $lines = collect((array) ($event['lignes'] ?? []))->map(fn ($line) => is_array($line) ? self::launchLineFrom($line) : null)->filter()->take(60)->values()->all();
        $ok = (bool) ($event['ok'] ?? false);
        $summary = $this->text($event, 'resume', 600) ?: ($ok ? __('Run succeeded.') : __('The run stopped on an error.'));

        DB::transaction(function () use ($run, $lines, $ok, $summary) {
            $run->project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $summary, 'agent_run_id' => $run->id]);
            $run->update(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'resume_at' => null, ...$this->computerTime($run)]);
            $run->forceFill(['report' => self::launchReport($ok, $summary, $lines)])->save();
            $run->project->touch();
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{step: string, status: string, message: string}|null
     */
    public static function launchLineFrom(array $line): ?array
    {
        $step = Str::limit(trim((string) ($line['etape'] ?? '')), 80, '');
        if ($step === '') {
            return null;
        }
        $status = in_array($line['statut'] ?? null, self::LAUNCH_STATUSES, true) ? $line['statut'] : 'info';

        return ['step' => $step, 'status' => $status, 'message' => Str::limit(SecretScanner::redact(trim((string) ($line['message'] ?? '')))[0], 2000)];
    }

    /**
     * The report of a run of an automation: the summary, then the log (folded under it in the discussion).
     *
     * @param  list<array{step: string, status: string, message: string}>  $lines
     * @return array<string, mixed>
     */
    public static function launchReport(?bool $ok, string $summary, array $lines): array
    {
        return ['done' => $summary, 'note' => null, 'launch' => ['ok' => $ok, 'lines' => $lines], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []];
    }

    private function started(AgentRun $run): void
    {
        $run->update([
            'status' => AgentRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
            'resume_at' => null,
        ]);
    }

    /**
     * Remember the link of the project's live preview and when the virtual computer will be deleted.
     *
     * @param  array<string, mixed>  $event
     */
    public function preview(Project $project, array $event): void
    {
        $url = trim((string) ($event['url'] ?? ''));

        if (! $this->isPreviewUrl($url)) {
            return;
        }

        $expiresAt = $this->date($event, 'expire_a') ?? CarbonImmutable::now()->addMinutes(30);

        $this->quietly($project, [
            'preview_url' => $url,
            'preview_expires_at' => $expiresAt,
            'preview_waking_at' => null,
        ]);
    }

    /**
     * Events that concern a project without a request to the agent (turning the preview back on).
     *
     * @param  array<string, mixed>  $event
     */
    public function handleProject(Project $project, array $event): void
    {
        match ($event['type'] ?? null) {
            'apercu' => $this->preview($project, $event),
            'apercu_impossible' => $this->quietly($project, ['preview_waking_at' => null, 'version_busy_at' => null]),
            // Une version remise dans l'ordinateur (voir, revenir, restaurer) : l'aperçu la montre.
            'version_appliquee' => $this->quietly($project, ['version_busy_at' => null]),
            default => null,
        };
    }

    /**
     * Change a project without changing its « modified » date.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function quietly(Project $project, array $attributes): void
    {
        $project->timestamps = false;
        $project->forceFill($attributes)->save();
        $project->timestamps = true;
    }

    /**
     * The agent asks the user before a risky action.
     *
     * @param  array<string, mixed>  $event
     */
    private function permissionAsked(AgentRun $run, array $event): void
    {
        $requestId = $this->text($event, 'id', 100);

        if ($requestId === '') {
            return;
        }

        $run->permissions()->firstOrCreate(['request_id' => $requestId], [
            'tool' => $this->text($event, 'outil', 40),
            'target' => $this->text($event, 'cible', 300),
        ]);
    }

    /**
     * The permission was answered (or refused automatically after the waiting time).
     *
     * @param  array<string, mixed>  $event
     */
    private function permissionSettled(AgentRun $run, array $event): void
    {
        $permission = $run->permissions()->where('request_id', $this->text($event, 'id', 100))->first();

        if (! $permission || ! $permission->isPending()) {
            return;
        }

        $permission->update([
            'status' => match ($event['decision'] ?? null) {
                'autoriser' => AgentPermission::STATUS_ALLOWED,
                'refuser' => AgentPermission::STATUS_REFUSED,
                default => AgentPermission::STATUS_EXPIRED,
            },
            'decided_at' => now(),
        ]);
    }

    /**
     * Only HTTPS links to the virtual computers are accepted.
     */
    private function isPreviewUrl(string $url): bool
    {
        if (strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach (config('kekeli.preview_domains', []) as $domain) {
            if (str_ends_with($host, '.'.strtolower($domain))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function step(AgentRun $run, array $event): void
    {
        $kind = $this->text($event, 'genre', 30);

        $run->steps()->create([
            'kind' => array_key_exists($kind, AgentStep::LABELS) ? $kind : 'outil',
            'target' => $this->text($event, 'cible', 300),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function request(AgentRun $run, array $event): void
    {
        $input = max(0, (int) ($event['tokens_entree'] ?? 0));
        $output = max(0, (int) ($event['tokens_sortie'] ?? 0));
        $reasoning = max(0, (int) ($event['tokens_reflexion'] ?? 0));
        $cached = max(0, (int) ($event['tokens_cache'] ?? 0));
        $cost = $this->costs->modelCost($this->text($event, 'fournisseur', 30), $this->text($event, 'modele', 120), $input, $output, $reasoning, $cached);
        // Le premier répondant peut faire plusieurs requêtes pour un message (recherche, puis réponse) : un seul événement.
        $count = max(1, min(10, (int) ($event['requetes'] ?? 1)));

        // Consommation de la demande, pour l'énergie de l'utilisateur et la mesure des coûts.
        AgentRun::query()->whereKey($run->id)->update([
            'requests' => DB::raw('requests + '.$count),
            'input_tokens' => DB::raw('input_tokens + '.$input),
            'output_tokens' => DB::raw('output_tokens + '.$output),
            'reasoning_tokens' => DB::raw('reasoning_tokens + '.$reasoning),
            'cached_tokens' => DB::raw('cached_tokens + '.$cached),
            'model_cost_usd' => DB::raw('model_cost_usd + '.number_format($cost, 6, '.', '')),
        ]);

        $this->increment($event, [
            'requests' => $count,
            'input_tokens' => (int) ($event['tokens_entree'] ?? 0),
            'output_tokens' => (int) ($event['tokens_sortie'] ?? 0),
            'reasoning_tokens' => (int) ($event['tokens_reflexion'] ?? 0),
            'cached_tokens' => (int) ($event['tokens_cache'] ?? 0),
            'cost_usd' => $cost,
        ]);
    }

    /**
     * The first responder searches on the Internet: a discreet line « Recherche sur le Web… », folded with the actions.
     *
     * @param  array<string, mixed>  $event
     */
    private function searching(AgentRun $run, array $event): void
    {
        $run->steps()->create(['kind' => 'recherche_web', 'target' => $this->text($event, 'requete', 300)]);
    }

    /**
     * Images created by the agent during the request: counted in the client's energy at the price set
     * in the administration (even while the image service is used on its free offer).
     *
     * @param  array<string, mixed>  $event
     */
    private function imagesCreated(AgentRun $run, array $event): void
    {
        $count = max(0, min(100, (int) ($event['nombre'] ?? 0)));

        if ($count === 0) {
            return;
        }

        $cost = $count * app(Settings::class)->float('image_price_usd');

        AgentRun::query()->whereKey($run->id)->update([
            'images_created' => DB::raw('images_created + '.$count),
            'image_cost_usd' => DB::raw('image_cost_usd + '.number_format($cost, 6, '.', '')),
        ]);
    }

    /**
     * The voice-over of a video (or the subtitles of the client's voice): its cost from the real duration and the price
     * per minute of Administration → Réglages, counted in the client's energy and in the day's OpenAI spending.
     *
     * @param  array<string, mixed>  $event
     */
    private function voice(AgentRun $run, array $event): void
    {
        $seconds = max(0, min(3600, (int) ($event['secondes'] ?? 0)));
        if ($seconds === 0) {
            return;
        }
        $transcription = ($event['usage'] ?? '') === 'transcription';
        $cost = $seconds / 60 * app(Settings::class)->float($transcription ? 'video_transcription_usd_per_minute' : 'video_voice_usd_per_minute');

        AgentRun::query()->whereKey($run->id)->update(['voice_cost_usd' => DB::raw('voice_cost_usd + '.number_format($cost, 6, '.', ''))]);
        $this->increment(['fournisseur' => 'openai', 'modele' => $this->text($event, 'modele', 120)], ['requests' => 1, 'cost_usd' => $cost]);
    }

    /**
     * The rendering of a video, shown live: one line « Rendu… 60 % » updated in place.
     *
     * @param  array<string, mixed>  $event
     */
    private function rendering(AgentRun $run, array $event): void
    {
        if (! $run->isActive()) {
            return;
        }
        $percent = max(0, min(100, (int) ($event['pourcentage'] ?? 0)));
        $step = $run->steps()->where('kind', 'rendu')->latest('id')->first();
        $step ? $step->update(['target' => (string) $percent]) : $run->steps()->create(['kind' => 'rendu', 'target' => (string) $percent]);
    }

    /**
     * An image made by a paid generator (OpenAI): its real cost from the tokens and the official prices of
     * « image_prices », counted in the client's energy and in the day's OpenAI spending (ceiling of the day).
     *
     * @param  array<string, mixed>  $event
     */
    private function paidImage(AgentRun $run, array $event): void
    {
        $provider = $this->text($event, 'fournisseur', 30);
        $model = $this->text($event, 'modele', 120);
        $price = config('kekeli.image_prices')[$provider.'/'.$model] ?? null;
        if (! is_array($price)) {
            return;
        }
        $text = max(0, (int) ($event['tokens_texte'] ?? 0));
        $imageInput = max(0, (int) ($event['tokens_image_entree'] ?? 0));
        $output = max(0, (int) ($event['tokens_sortie'] ?? 0));
        $cost = ($text * $price['text'] + $imageInput * $price['image_input'] + $output * $price['output']) / 1_000_000;

        AgentRun::query()->whereKey($run->id)->update([
            'images_created' => DB::raw('images_created + 1'),
            'image_cost_usd' => DB::raw('image_cost_usd + '.number_format($cost, 6, '.', '')),
        ]);
        $this->increment($event, ['requests' => 1, 'input_tokens' => $text + $imageInput, 'output_tokens' => $output, 'cost_usd' => $cost]);
    }

    /**
     * Web application: the test administrator of the preview (an invented account of the test database).
     *
     * @param  array<string, mixed>  $event
     */
    private function webAppAccess(Project $project, array $event): void
    {
        if (! $project->isWebApp()) {
            return;
        }

        $email = $this->text($event, 'identifiant', 120);
        $password = $this->text($event, 'mot_de_passe', 120);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== '') {
            $project->forceFill(['app_admin_email' => $email, 'app_admin_password' => $password])->save();
        }
    }

    /**
     * Add to the counters of a provider for its current quota day.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, int>  $amounts
     */
    private function increment(array $event, array $amounts): void
    {
        $provider = $this->text($event, 'fournisseur', 30);
        $model = $this->text($event, 'modele', 120);

        if ($provider === '' || $model === '') {
            return;
        }

        $attributes = ['day' => $this->providers->quotaDay($provider), 'provider' => $provider, 'model' => $model];

        DB::transaction(function () use ($attributes, $amounts) {
            $usage = ProviderUsage::query()->where($attributes)->lockForUpdate()->first()
                ?? ProviderUsage::create($attributes);

            foreach ($amounts as $column => $amount) {
                $usage->{$column} = $usage->{$column} + max(0, $amount);
            }

            $usage->save();
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function setAside(array $event): void
    {
        $until = $this->date($event, 'jusqu_a');
        $key = $this->text($event, 'fournisseur', 30).'/'.$this->text($event, 'modele', 120);

        if ($until && $until->isFuture()) {
            Cache::put('kekeli.set_aside.'.$key, $until->toIso8601String(), $until);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function completed(AgentRun $run, array $event): void
    {
        $answer = trim((string) ($event['reponse'] ?? ''));

        DB::transaction(function () use ($run, $answer) {
            $run->project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => Str::limit($answer !== '' ? $answer : __('Done.'), 20000),
                'agent_run_id' => $run->id,
            ]);

            $run->update([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'resume_at' => null,
                ...$this->computerTime($run),
            ]);
            // Compte rendu, toujours dans le même ordre, construit sans aucune requête de plus au modèle.
            $run->forceFill(['report' => RunReport::build($run, $answer)])->save();

            $run->project->touch();
        });

        // Versions, comme Lovable et Bolt.new : chaque travail réussi crée une version (« Version 5 créée »).
        if ($run->purpose === null) {
            rescue(fn () => app(ProjectVersions::class)->capture($run->fresh(['project', 'message'])), report: true);
        }

        // Mode développeur : la correction d'une vérification de GitHub qui avait échoué part dans la même pull request.
        $project = $run->project->refresh();
        if ($run->purpose === AgentRun::PURPOSE_CI_FIX && $project->canWriteRepository() && $project->pr_number) {
            $project->forceFill(['push_status' => Project::PUSH_IN_PROGRESS])->save();
            rescue(function () use ($project) {
                PushRepository::dispatch($project);
            }, report: true);

            return;
        }

        // Comme Claude Code : après chaque travail réussi, Kekeli envoie les changements sur GitHub (pull request par
        // défaut, ou branche principale si le client l'a choisi), quand le projet est relié à un dépôt.
        if ($run->purpose === null && $project->user?->github_token && $project->canWriteRepository() && $project->push_status !== Project::PUSH_IN_PROGRESS) {
            $project->forceFill(['push_status' => Project::PUSH_IN_PROGRESS, 'push_error' => null, 'ci_fix_attempts' => 0])->save();
            rescue(fn () => PushRepository::dispatch($project), report: true);
        } elseif ($run->purpose === null && $project->user?->github_token && $project->isLinkedToGithub() && $project->github_status !== Project::GITHUB_IN_PROGRESS) {
            $project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(fn () => PushProjectToGithub::dispatch($project, $run), report: true);
        }
    }

    /**
     * Developer mode: branch, commits and changed files after a request.
     *
     * @param  array<string, mixed>  $event
     */
    private function repositoryState(Project $project, array $event): void
    {
        if (! $project->isRepository()) {
            return;
        }

        $state = (array) $project->repository_state;
        $state['branche'] = $this->text($event, 'branche', 120);
        $state['commits'] = collect((array) ($event['commits'] ?? []))->filter(fn ($c) => is_string($c))->map(fn ($c) => mb_substr($c, 0, 200))->take(50)->values()->all();
        $state['fichiers'] = collect((array) ($event['fichiers'] ?? []))
            ->filter(fn ($f) => is_array($f) && is_string($f['chemin'] ?? null))
            ->map(fn ($f) => ['etat' => in_array($f['etat'] ?? '', ['ajoute', 'modifie', 'supprime', 'renomme'], true) ? $f['etat'] : 'modifie', 'chemin' => mb_substr($f['chemin'], 0, 300)])
            ->take(300)->values()->all();
        $state['nombre_fichiers'] = (int) ($event['nombre_fichiers'] ?? count($state['fichiers']));

        $project->timestamps = false;
        $project->forceFill(['repository_state' => $state])->save();
        $project->timestamps = true;
    }

    /**
     * Developer mode: type of project recognized by Kekeli, and the commands to install and test it.
     *
     * @param  array<string, mixed>  $event
     */
    private function repositoryDetection(Project $project, array $event): void
    {
        $detection = (array) ($event['detection'] ?? []);
        if (! $project->isRepository() || $detection === []) {
            return;
        }

        $state = (array) $project->repository_state;
        $state['detection'] = [
            'nom' => mb_substr((string) ($detection['nom'] ?? ''), 0, 80),
            'tester' => collect((array) ($detection['tester'] ?? []))->filter(fn ($c) => is_string($c))->map(fn ($c) => mb_substr($c, 0, 120))->take(5)->values()->all(),
            'instructions' => collect((array) ($detection['instructions'] ?? []))->filter(fn ($c) => is_string($c))->take(5)->values()->all(),
        ];

        $project->timestamps = false;
        $project->forceFill(['repository_state' => $state])->save();
        $project->timestamps = true;
    }

    /**
     * Time the computer worked for this request, and its cost (the computer is billed while it runs).
     *
     * @return array{computer_seconds: int, computer_cost_usd: string}
     */
    private function computerTime(AgentRun $run): array
    {
        // Une demande reprise avec « Continuer » ajoute son nouveau temps à celui d'avant l'arrêt.
        $seconds = $run->computer_seconds + ($run->started_at ? (int) max(0, $run->started_at->diffInSeconds(now())) : 0);

        return [
            'computer_seconds' => $seconds,
            'computer_cost_usd' => number_format($this->costs->computerCost($seconds), 6, '.', ''),
        ];
    }

    /**
     * Whether the Runner must stop the request now (the user's energy is used up, or the client touched « Arrêter »). The answer is given to the
     * Runner with each event; the decision is remembered so that it stays the same until the request stops.
     *
     * @return array{arreter: true, raison: string}|null
     */
    public function stopRequest(AgentRun $run): ?array
    {
        $run->refresh();

        if (! in_array($run->status, [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING], true)) {
            return null;
        }

        if (! $run->stop_requested_at) {
            $reason = $this->energy->stopReason($run->project->user, $run);
            // Plafond du jour du modèle de construction atteint : la construction s'arrête, elle ne passe pas à un
            // modèle gratuit qui ne saurait pas la finir.
            if (! $reason && ! $run->isQuestion() && app(AnswerModels::class)->buildCapReached($run->project->user)) {
                $reason = AgentRun::STOP_MODEL_CAP;
            }

            if (! $reason) {
                return null;
            }

            $run->update(['stop_requested_at' => now(), 'stop_reason' => $reason]);
        }

        return ['arreter' => true, 'raison' => (string) $run->stop_reason];
    }

    /**
     * The Runner stopped the work (energy used up, or « Arrêter ») after saving the project as it is.
     *
     * @param  array<string, mixed>  $event
     */
    private function stopped(AgentRun $run, array $event): void
    {
        $run->update([
            'status' => AgentRun::STATUS_STOPPED,
            'stop_reason' => $run->stop_reason ?? ($this->text($event, 'raison', 30) ?: 'energie'),
            'finished_at' => now(),
            'resume_at' => null,
            ...$this->computerTime($run),
        ]);

        // Le projet reste sur sa dernière version qui fonctionnait (fichiers et aperçu), et le client le voit.
        rescue(fn () => app(ProjectVersions::class)->keepLastVersion($run->fresh('project')), report: true);
        $run->project->touch();
    }

    /**
     * Every provider is exhausted: the request waits and resumes by itself.
     *
     * @param  array<string, mixed>  $event
     */
    private function waiting(AgentRun $run, array $event): void
    {
        $resumeAt = $this->date($event, 'reprise_vers');

        if (! $resumeAt || $resumeAt->isPast()) {
            $resumeAt = CarbonImmutable::now()->addMinutes(5);
        }

        $run->update([
            'status' => AgentRun::STATUS_WAITING,
            'resume_at' => $resumeAt,
        ]);

        AdminAlert::create([
            'kind' => 'providers_exhausted',
            'message' => __('Every AI provider has reached its limit. A request is waiting and will resume around :time (Lomé time).', [
                'time' => $resumeAt->setTimezone(config('kekeli.display_timezone'))->format('H:i'),
            ]),
        ]);

        SendAgentRun::dispatch($run, true)->delay($resumeAt->addSeconds(30));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function failed(AgentRun $run, array $event): void
    {
        $reason = $this->text($event, 'raison', 40);
        $detail = $this->text($event, 'detail', 1000);

        $run->update([
            'status' => AgentRun::STATUS_FAILED,
            'error' => trim($reason.' : '.$detail, ' :'),
            'finished_at' => now(),
            'resume_at' => null,
            ...$this->computerTime($run),
        ]);

        $run->forceFill(['report' => RunReport::build($run, '', true, $this->failureForClient($reason))])->save();
        rescue(fn () => app(ProjectVersions::class)->keepLastVersion($run->fresh('project')), report: true);

        AdminAlert::create([
            'kind' => 'run_failed',
            'message' => __('A request to the agent failed (:reason): :detail', ['reason' => $reason, 'detail' => $detail]),
        ]);
    }

    /**
     * « See the changes »: files changed during the request, and the differences (cut when too long).
     *
     * @param  array<string, mixed>  $event
     */
    private function changes(AgentRun $run, array $event): void
    {
        $files = collect((array) ($event['fichiers'] ?? []))
            ->filter(fn ($f) => is_array($f) && is_string($f['chemin'] ?? null))
            ->map(fn ($f) => [
                'chemin' => mb_substr($f['chemin'], 0, 300),
                'etat' => in_array($f['etat'] ?? '', ['ajoute', 'modifie', 'supprime', 'renomme'], true) ? $f['etat'] : 'modifie',
                'ajouts' => max(0, (int) ($f['ajouts'] ?? 0)),
                'suppressions' => max(0, (int) ($f['suppressions'] ?? 0)),
                'binaire' => (bool) ($f['binaire'] ?? false),
            ])->take(300)->values()->all();

        $run->forceFill([
            'changes' => ['fichiers' => $files, 'nombre_fichiers' => (int) ($event['nombre_fichiers'] ?? count($files)), 'tronque' => (bool) ($event['tronque'] ?? false)],
            'diff' => mb_substr((string) ($event['diff'] ?? ''), 0, 200000),
        ])->save();
    }

    /**
     * Reason of a failure, in simple words for the client (never the technical detail).
     */
    private function failureForClient(string $reason): string
    {
        return match ($reason) {
            'duree' => __('the request took longer than the maximum time'),
            'ordinateur' => __('Kekeli\'s computer stopped answering'),
            'aucun_fournisseur' => __('the artificial intelligence is not available'),
            default => __('technical problem'),
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function text(array $event, string $key, int $limit): string
    {
        return Str::limit(trim((string) ($event[$key] ?? '')), $limit, '…');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function date(array $event, string $key): ?CarbonImmutable
    {
        try {
            return isset($event[$key]) ? CarbonImmutable::parse((string) $event[$key]) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
