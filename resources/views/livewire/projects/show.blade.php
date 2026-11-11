<?php

use App\Agent\AgentPlaces;
use App\Agent\MessageIntent;
use App\Jobs\AnswerQuestion;
use App\Agent\Energy;
use App\Agent\PreviewToken;
use App\Agent\RunnerClient;
use App\Github\GithubClient;
use App\Jobs\ExportToGithub;
use App\Github\RepositoryClient;
use App\Jobs\PublishProject;
use App\Jobs\PushRepository;
use App\Jobs\SendAgentRun;
use App\Jobs\WakePreview;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectSite;
use App\Support\Availability;
use App\Agent\ProjectFiles;
use App\Agent\RunStarter;
use App\WebApps\SiteNeeds;
use App\WebApps\WebAppTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public string $content = '';

    /** Message of a showcase site that asks for accounts or an administrator area: Kekeli suggests a site with a database. */
    public ?string $databaseSuggestion = null;

    public string $newSite = '';

    /** Request whose changes are shown (« See the changes »). */
    public ?string $changesRunId = null;

    /** Address of the client's own domain name, typed on the phone (for example www.my-restaurant.com). */
    public string $newDomain = '';

    /** Photos joined in the message field, sent with the next message. */
    public array $uploads = [];

    /** View shown on a phone: « discussion », « apercu » or « code » (on a computer, the discussion stays on the left). */
    public string $view = 'discussion';

    /** The « Code » view reads the files of the last backup only once it is opened. */
    public bool $codeLoaded = false;

    /** File shown in the « Code » view. */
    public ?string $openedFile = null;

    public string $country = '';

    /**
     * Mount the component with one of the current user's projects. An administrator may look at
     * any project, without changing anything.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->user()->is(Auth::user()) || Auth::user()->can('admin'), 404);

        $this->project = $project;
        $this->country = (string) (Auth::user()->country ?? config('kekeli.default_country'));

        // Une vue peut être demandée dans l'adresse (par exemple ?vue=apercu).
        $view = (string) request()->query('vue', 'discussion');
        $this->view = in_array($view, ['discussion', 'apercu', 'code'], true) ? $view : 'discussion';
        $this->codeLoaded = $this->view === 'code';

        // Texte écrit dans « Que voulez-vous créer aujourd'hui ? » qui n'a pas pu partir tout de suite (pays, énergie) : il attend ici.
        if ($draft = session('brouillon.'.$project->id)) {
            $this->content = (string) $draft;
        }
    }

    /**
     * Render the page with the project's name as the title.
     */
    public function rendering($view): void
    {
        // Espace de travail en plein écran, comme Lovable ou Bolt.new : pas de menu latéral.
        $view->layout('components.layouts.workspace')->title($this->project->name);
    }

    /** New name typed in « Rename ». */
    public string $newName = '';

    public function startRenaming(): void
    {
        abort_unless($this->isOwner, 403);
        $this->resetErrorBag('newName');
        $this->newName = $this->project->name;
        Flux::modal('rename-project')->show();
    }

    public function renameProject(): void
    {
        abort_unless($this->isOwner, 403);
        $validated = $this->validate(['newName' => ['required', 'string', 'max:100']]);
        $this->project->update(['name' => trim($validated['newName'])]);
        Flux::modal('rename-project')->close();
    }

    /**
     * Whether the current user owns the project (an administrator only looks at it).
     */
    #[Computed]
    public function isOwner(): bool
    {
        return $this->project->user()->is(Auth::user());
    }

    /**
     * The messages of the conversation.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->project->messages()->with('photos')->get();
    }

    /**
     * The requests sent to the agent, with their steps and permissions, by user message.
     *
     * @return Collection<int, AgentRun>
     */
    #[Computed]
    public function runs(): Collection
    {
        return $this->project->agentRuns()->with('steps', 'permissions')->get()->keyBy('message_id');
    }

    /**
     * The request the agent is working on, if any.
     */
    #[Computed]
    public function activeRun(): ?AgentRun
    {
        return $this->runs->first(fn (AgentRun $run) => $run->isActive());
    }

    /**
     * Private address of the preview (with a temporary token), if the preview is on.
     */
    #[Computed]
    public function previewUrl(): ?string
    {
        return $this->project->hasLivePreview() ? PreviewToken::url($this->project) : null;
    }

    /**
     * Sites added by the user, and the history of the changes.
     *
     * @return Collection<int, ProjectSite>
     */
    #[Computed]
    public function sites(): Collection
    {
        return $this->project->sites()->get();
    }

    /**
     * The owner's energy: formula, energy left and when it recharges (« l'énergie qui se recharge »).
     *
     * @return array{plan: array<string, mixed>, free: bool, free_cap: bool, percent: int, exhausted: bool, recharges_at: string, monthly: bool}
     */
    #[Computed]
    public function energy(): array
    {
        $energy = app(Energy::class);
        $owner = $this->project->user;

        $free = $energy->isFree($owner);
        $freeCap = $free && $energy->freeCapReached();

        return [
            'plan' => $energy->plan($owner),
            'free' => $free,
            'free_cap' => $freeCap,
            'percent' => $energy->percentLeft($owner),
            'exhausted' => $freeCap || $energy->isExhausted($owner),
            'recharges_at' => $energy->rechargeText($owner),
            // Formule payante au mois : l'énergie revient à une date, pas à une heure.
            'monthly' => $energy->plan($owner)['period'] === 'month',
        ];
    }

    /**
     * Whether the client still has to say in which country they work (accounts created before the question existed).
     */
    #[Computed]
    public function needsCountry(): bool
    {
        return $this->isOwner && ! Auth::user()->countryDetails();
    }

    /**
     * Kekeli is not yet available in the client's country (beta): the request cannot be sent.
     */
    #[Computed]
    public function comingSoon(): bool
    {
        return $this->isOwner && app(Availability::class)->comingSoon(Auth::user());
    }

    /**
     * Save the client's country: Kekeli adapts photos, prices and phone numbers to it.
     */
    public function saveCountry(): void
    {
        abort_unless($this->isOwner, 403);

        $this->validate(['country' => ['required', 'string', Rule::in(array_keys(config('kekeli.countries')))]]);

        Auth::user()->update(['country' => $this->country]);

        unset($this->needsCountry, $this->comingSoon);
    }

    /**
     * The photos sent by the client for this project.
     *
     * @return Collection<int, ProjectPhoto>
     */
    #[Computed]
    public function photos(): Collection
    {
        return $this->project->photos()->get();
    }

    /**
     * Remove a photo joined in the message field, before sending.
     */
    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    // Vue « Code » : les fichiers de la dernière sauvegarde, en lecture seule (comme Bolt sur un téléphone).

    public function loadCode(): void
    {
        $this->codeLoaded = true;
    }

    /**
     * Files of the site (last backup), without secrets nor technical folders.
     *
     * @return array<string, string> path → content
     */
    #[Computed]
    public function codeFiles(): array
    {
        if (! $this->codeLoaded || ! $this->project->backup_at) {
            return [];
        }

        try {
            return app(ProjectFiles::class)->read($this->project);
        } catch (\Throwable) {
            return [];
        }
    }

    public function openFile(string $path): void
    {
        $this->openedFile = array_key_exists($path, $this->codeFiles) ? $path : null;
    }

    public function closeFile(): void
    {
        $this->openedFile = null;
    }

    public function send(): void
    {
        abort_unless($this->isOwner, 403);

        $limits = config('kekeli.client_photos');
        $room = $limits['max_per_project'] - $this->project->photos()->count();
        $validated = $this->validate([
            'content' => [$this->uploads ? 'nullable' : 'required', 'string', 'max:4000'],
            'uploads' => ['array', 'max:'.max(0, $room)],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
        ], [
            'content.required' => __('A few words are enough: what should Kekeli change?'),
            'uploads.max' => __('You can keep at most :count photos in a project.', ['count' => $limits['max_per_project']]),
        ], [
            'uploads' => __('photos'),
            'uploads.*' => __('photo'),
        ]);

        // Des photos sans texte : Kekeli les place dans le site.
        $content = trim((string) ($validated['content'] ?? '')) ?: __('Here are my photos: use them in my site.');

        // Une question (« Où héberger mon site ? ») : Kekeli répond dans la discussion, sans toucher au site. En cas de
        // doute, il répond puis demande s'il doit modifier le site (comme Lovable et Claude Code).
        $intent = $this->uploads ? MessageIntent::CHANGE : app(MessageIntent::class)->detect($content);
        if ($intent !== MessageIntent::CHANGE) {
            if ($this->askQuestion($content, $intent === MessageIntent::DOUBT)) {
                $this->reset('content', 'databaseSuggestion');
            }

            return;
        }

        // Un site vitrine ne peut pas avoir de comptes ni d'espace administrateur : Kekeli propose un site avec base de données.
        if ($this->project->kind === Project::KIND_SITE && $this->databaseSuggestion !== $content) {
            $detection = app(SiteNeeds::class)->detect($content);
            if ($detection['database'] && array_intersect($detection['reasons'], ['accounts', 'admin'])) {
                $this->databaseSuggestion = $content;

                return;
            }
        }

        if ($this->startRun($content)) {
            $this->reset('content', 'databaseSuggestion', 'uploads');
        }
    }

    /**
     * The client accepts the suggestion: a new site with a database is created from the same request
     * (the showcase site stays as it is).
     */
    public function createDatabaseSite(): void
    {
        abort_unless($this->isOwner && filled($this->databaseSuggestion), 403);

        $templates = app(WebAppTemplates::class);
        $content = trim($this->project->description ? $this->project->description."\n".$this->databaseSuggestion : (string) $this->databaseSuggestion);
        $stack = $templates->suggestStack($content);

        $site = Auth::user()->projects()->make(['name' => $this->project->name, 'description' => null]);
        $site->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => $stack, 'app_template' => 'socle'])->save();

        $user = Auth::user();
        $started = $user->countryDetails() && ! $this->comingSoon
            && app(RunStarter::class)->start($site, $content, $templates->firstInstructions($stack, ['name' => $site->name]));
        if (! $started) {
            session()->flash('brouillon.'.$site->id, $content);
        }

        $this->redirectRoute('projects.show', ['project' => $site], navigate: true);
    }

    /**
     * Whether a new message can be given to Kekeli now (country known, available, energy left, nothing running).
     */
    private function canSendNow(): bool
    {
        abort_unless($this->isOwner, 403);

        if ($this->needsCountry) {
            $this->addError('country', __('Choose your country first: Kekeli adapts your project to it.'));

            return false;
        }

        if ($this->comingSoon) {
            return false;
        }

        unset($this->energy);
        if ($this->energy['exhausted']) {
            return false;
        }

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            $this->addError('content', __('Kekeli is still working on your previous request. Please wait until it is finished.'));

            return false;
        }

        return true;
    }

    /**
     * A question of the client: Kekeli answers in the discussion with the model only (no computer, no change of the
     * site), so it costs very little energy.
     */
    private function askQuestion(string $content, bool $doubt): bool
    {
        if (! $this->canSendNow()) {
            return false;
        }

        $run = DB::transaction(function () use ($content) {
            $message = $this->project->messages()->create(['role' => Message::ROLE_USER, 'content' => $content]);
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        });

        rescue(fn () => AnswerQuestion::dispatch($run, $doubt), report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy);

        return true;
    }

    /**
     * Give a request to the agent (the client's message, and possibly instructions the client does not see).
     * Returns false when it cannot start (country missing, not available, energy used up, previous request running).
     */
    private function startRun(string $content, ?string $instructions = null): bool
    {
        if (! $this->canSendNow()) {
            return false;
        }

        // Premier message d'un site avec base de données : Kekeli reçoit les consignes du point de départ.
        if ($instructions === null && $this->project->isWebApp() && ! $this->project->agentRuns()->exists()) {
            $templates = app(WebAppTemplates::class);
            $instructions = $templates->firstInstructions((string) $this->project->app_stack, ['name' => $this->project->name]);
        }

        $run = DB::transaction(function () use ($content, $instructions) {
            $message = $this->project->messages()->create([
                'role' => Message::ROLE_USER,
                'content' => $content,
            ]);

            // Les photos jointes partent avec le message (celles envoyées avant, sans message, aussi).
            $this->project->photos()->whereNull('message_id')->update(['message_id' => $message->id]);
            foreach ($this->uploads as $upload) {
                ProjectPhoto::store($this->project, $upload, $message);
            }

            $this->project->touch();

            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $message->id, 'instructions' => $instructions]);
        });

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy, $this->photos);

        return true;
    }

    // Vue « Code » : les modifications d'une demande, fichier par fichier.

    #[Computed]
    public function changesRun(): ?AgentRun
    {
        return $this->changesRunId ? $this->project->agentRuns()->whereKey($this->changesRunId)->first() : null;
    }

    /**
     * The last request that changed files: its changes are shown in the « Code » view.
     */
    #[Computed]
    public function lastChangedRun(): ?AgentRun
    {
        return $this->project->agentRuns()->whereNotNull('changes')->latest('created_at')->latest('id')->first();
    }

    public function showChanges(string $runId): void
    {
        $this->changesRunId = $this->project->agentRuns()->whereKey($runId)->value('id');
        unset($this->changesRun);
        Flux::modal('run-changes')->show();
    }

    // Mode développeur : envoyer le travail sur GitHub (pull request), puis fusionner seulement si le client le demande.

    /**
     * Send Kekeli's work to its branch of the client's repository and open the pull request (the client's agreement).
     */
    public function pushRepository(): void
    {
        abort_unless($this->isOwner, 403);
        $this->project->refresh();

        if (! $this->project->canWriteRepository() || $this->project->push_status === Project::PUSH_IN_PROGRESS || ! $this->project->backup_at) {
            return;
        }
        if ($this->activeRun) {
            $this->addError('repository', __('Kekeli is still working: send the work once the request is finished.'));

            return;
        }
        if (! Auth::user()->github_token) {
            $this->addError('repository', __('Connect your GitHub account again to send the work.'));

            return;
        }

        $this->project->forceFill(['push_status' => Project::PUSH_IN_PROGRESS, 'push_error' => null, 'ci_fix_attempts' => 0])->save();
        rescue(fn () => PushRepository::dispatch($this->project), report: true);
    }

    /**
     * Merge the pull request into the repository's main branch: only when the client asks, never while GitHub's
     * automatic checks are running or red.
     */
    public function mergePullRequest(): void
    {
        abort_unless($this->isOwner, 403);
        $this->project->refresh();

        if (! $this->project->canWriteRepository() || ! $this->project->pr_number || $this->project->pr_status !== 'open') {
            return;
        }
        if (in_array($this->project->ci_status, [Project::CI_PENDING, Project::CI_FAILURE], true) || $this->activeRun) {
            $this->addError('repository', __('The pull request can be merged once the automatic checks of GitHub are green.'));

            return;
        }

        try {
            app(RepositoryClient::class)->merge($this->project, (string) Auth::user()->github_token);
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            report($exception);
            $this->addError('repository', __('GitHub did not accept the merge. The pull request stays open: you can look at it on GitHub.'));

            return;
        }

        $this->project->forceFill(['pr_status' => 'merged'])->save();
        $this->project->messages()->create([
            'role' => Message::ROLE_ASSISTANT,
            'content' => __('The pull request :url is merged into :branch, as you asked.', ['url' => $this->project->pr_url, 'branch' => $this->project->repository_base_branch]),
        ]);
        unset($this->messages);
    }

    /**
     * Whether the GitHub application of Kekeli is set up (client number and secret in the environment).
     */
    #[Computed]
    public function githubConfigured(): bool
    {
        return app(GithubClient::class)->configured();
    }

    /**
     * Own domain name: paid formulas only (the code itself, GitHub and ZIP, is free for everyone).
     */
    #[Computed]
    public function paidPlan(): bool
    {
        return $this->isOwner && ! app(Energy::class)->isFree(Auth::user());
    }

    /**
     * Send the code of the last backup to the client's GitHub account (every formula).
     */
    public function exportToGithub(): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->refresh();
        if (! $this->project->backup_at || ! Auth::user()->github_token || $this->project->github_status === Project::GITHUB_IN_PROGRESS || $this->activeRun) {
            return;
        }

        $this->project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();

        rescue(function () {
            ExportToGithub::dispatch($this->project);
        }, report: true);
    }

    /**
     * Connect the client's own domain name to the published site (paid formulas).
     */
    public function addDomain(): void
    {
        abort_unless($this->paidPlan, 403);

        $this->newDomain = strtolower(trim(preg_replace('#^https?://|/.*$#i', '', trim($this->newDomain))));
        $this->validate(['newDomain' => ['required', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.){2,}[a-z]{2,63}$/']], [
            'newDomain.regex' => __('Write the full address with « www », for example www.my-restaurant.com.'),
        ], ['newDomain' => __('domain name')]);

        if (! $this->project->published_url) {
            return;
        }

        $this->domainAction('ajouter', $this->newDomain);
        $this->reset('newDomain');
    }

    public function checkDomain(): void
    {
        abort_unless($this->paidPlan, 403);

        if ($this->project->custom_domain) {
            $this->domainAction('verifier', $this->project->custom_domain);
        }
    }

    public function removeDomain(): void
    {
        abort_unless($this->isOwner, 403);

        if ($this->project->custom_domain && $this->domainAction('retirer', $this->project->custom_domain)) {
            $this->project->forceFill(['custom_domain' => null, 'custom_domain_status' => null, 'custom_domain_message' => null])->save();
        }
    }

    private function domainAction(string $action, string $domain): bool
    {
        try {
            $result = app(RunnerClient::class)->domain($this->project, $action, $domain);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError($action === 'ajouter' ? 'newDomain' : 'domain', __('The address could not be connected this time. Check it and try again in a moment.'));

            return false;
        }

        if ($action !== 'retirer') {
            $this->project->forceFill([
                'custom_domain' => $domain,
                'custom_domain_status' => mb_substr((string) $result['statut'], 0, 20),
                'custom_domain_message' => isset($result['explication']) ? mb_substr((string) $result['explication'], 0, 500) : null,
            ])->save();
        }

        return true;
    }

    /**
     * Publish the project online, on a free pages.dev address (the version saved after the last request).
     */
    public function publish(): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->refresh();

        if (! $this->project->backup_at || $this->project->publish_status === Project::PUBLISH_IN_PROGRESS) {
            return;
        }

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            $this->addError('publish', __('Kekeli is still working: publish once the request is finished.'));

            return;
        }

        $this->project->forceFill(['publish_status' => Project::PUBLISH_IN_PROGRESS, 'publish_error' => null])->save();

        rescue(function () {
            PublishProject::dispatch($this->project);
        }, report: true);
    }

    /**
     * Resume a request stopped because the energy ran out, exactly where it stopped (after the recharge,
     * or after a formula has been paid).
     */
    public function continueRun(string $runId): void
    {
        abort_unless($this->isOwner, 403);

        $run = $this->project->agentRuns()->whereKey($runId)->firstOrFail();

        unset($this->energy);
        if ($run->status !== AgentRun::STATUS_STOPPED || $this->energy['exhausted'] || $this->comingSoon) {
            return;
        }

        if ($this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->exists()) {
            return;
        }

        $run->update([
            'status' => AgentRun::STATUS_QUEUED,
            'stop_requested_at' => null,
            'started_at' => null,
            'sent_at' => null,
            'finished_at' => null,
        ]);

        app(Energy::class)->startWindowIfNeeded(Auth::user());

        rescue(function () use ($run) {
            SendAgentRun::dispatch($run, true);
        }, report: true);

        unset($this->runs, $this->activeRun, $this->energy);
    }

    /**
     * The user allows or refuses a risky action asked by the agent.
     */
    public function answerPermission(int $permissionId, bool $allowed): void
    {
        abort_unless($this->isOwner, 403);

        $permission = AgentPermission::query()
            ->whereKey($permissionId)
            ->whereHas('run', fn ($query) => $query->where('project_id', $this->project->id))
            ->firstOrFail();

        if (! $permission->isPending()) {
            return;
        }

        if (! app(RunnerClient::class)->answerPermission($permission, $allowed)) {
            $this->addError('permission', __('Kekeli could not receive your answer. Please try again.'));

            return;
        }

        $permission->update([
            'status' => $allowed ? AgentPermission::STATUS_ALLOWED : AgentPermission::STATUS_REFUSED,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        unset($this->runs);
    }

    /**
     * Turn the preview back on: the paused computer is woken up, with all the project's files.
     */
    public function wakePreview(): void
    {
        $project = $this->project;

        // Seul le propriétaire rallume son aperçu (un administrateur regarde sans rien changer).
        if (! $this->isOwner || $this->activeRun || $project->hasLivePreview() || $project->previewIsWaking() || blank($project->preview_url)) {
            return;
        }

        $project->timestamps = false;
        $project->forceFill(['preview_waking_at' => now()])->save();
        $project->timestamps = true;

        rescue(function () use ($project) {
            WakePreview::dispatch($project);
        }, report: true);
    }

    /**
     * Add a site to the list of sites the project's computer may reach.
     */
    public function addSite(): void
    {
        abort_unless($this->isOwner, 403);

        $domain = strtolower(trim($this->newSite));
        $domain = preg_replace('#^[a-z]+://#', '', $domain);
        $domain = rtrim(explode('/', $domain)[0], '.');
        $this->newSite = $domain;

        $this->validate([
            'newSite' => ['required', 'string', 'max:253', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/'],
        ], [], ['newSite' => __('site')]);

        $defaults = array_merge(...array_values(config('kekeli.default_sites')));

        if (in_array($domain, $defaults, true) || in_array($domain, $this->project->allowedDomains(), true)) {
            $this->addError('newSite', __('This site is already allowed.'));

            return;
        }

        if (count($this->project->allowedDomains()) >= 30) {
            $this->addError('newSite', __('You can add up to 30 sites.'));

            return;
        }

        $this->project->sites()->create(['domain' => $domain, 'user_id' => Auth::id()]);
        $this->reset('newSite');
        unset($this->sites);
    }

    /**
     * Remove a site added by the user. The row is kept, so the change stays recorded.
     */
    public function removeSite(int $siteId): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->sites()->whereKey($siteId)->whereNull('removed_at')->update(['removed_at' => now()]);
        unset($this->sites);
    }
}; ?>

@php
    $state = match (true) {
        (bool) $this->activeRun => ['working', __('Kekeli is working…'), 'bg-sky-500 animate-pulse'],
        filled($project->published_url) => ['online', __('Online'), 'bg-emerald-500'],
        default => ['draft', __('Draft'), 'bg-zinc-400'],
    };
@endphp
{{--
    Espace de travail du projet, comme Claude Code, Lovable, Bolt.new ou Manus : une barre fine en haut ; sur un
    téléphone, trois vues (Discussion, Aperçu, Code) ; sur un ordinateur, la discussion à gauche, l'aperçu ou le code
    à droite. Le travail de Kekeli s'affiche dans la discussion, en étapes cochées qu'on peut déplier.
--}}
<div class="flex h-svh flex-col" x-data="{ vue: @js($view) }" @if ($this->activeRun || $project->previewIsWaking() || $project->publish_status === Project::PUBLISH_IN_PROGRESS) wire:poll.2s @elseif ($project->hasLivePreview()) wire:poll.60s @endif>
    {{-- Barre fine : retour, nom du site, petit état, « Mettre en ligne », menu « ⋯ ». --}}
    <header class="flex h-14 shrink-0 items-center gap-1 border-b border-zinc-200 px-2 sm:gap-2 sm:px-3 dark:border-zinc-700" data-project-bar>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('projects.index')" wire:navigate :aria-label="__('My projects')" data-back />
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-[15px] font-semibold leading-tight text-zinc-900 dark:text-white" data-project-name>{{ $project->name }}</h1>
            <p class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-project-state="{{ $state[0] }}">
                <span class="size-1.5 rounded-full {{ $state[2] }}"></span>{{ $state[1] }}
            </p>
        </div>

        @if ($this->isOwner)
            <flux:modal.trigger name="publish">
                @if ($project->isRepository())
                    <flux:button size="sm" variant="primary" icon="arrow-up-tray" data-publish-open>GitHub</flux:button>
                @elseif ($project->isWebApp())
                    {{-- Kekeli n'héberge pas les sites avec base de données : le bouton ne promet pas de publication. --}}
                    <flux:button size="sm" variant="primary" icon="arrow-down-tray" data-publish-open>{{ __('Get the code') }}</flux:button>
                @else
                    <flux:button size="sm" variant="primary" icon="rocket-launch" data-publish-open>{{ __('Put online') }}</flux:button>
                @endif
            </flux:modal.trigger>

            <flux:dropdown position="bottom" align="end">
                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More')" data-project-more />
                <flux:menu>
                    <flux:menu.item icon="pencil-square" wire:click="startRenaming" data-rename>{{ __('Rename') }}</flux:menu.item>
                    <flux:modal.trigger name="allowed-sites">
                        <flux:menu.item icon="globe-alt" data-open-sites>{{ __('Internet access of Kekeli') }}</flux:menu.item>
                    </flux:modal.trigger>
                    <flux:menu.separator />
                    {{-- L'énergie, discrète, comme les crédits dans le menu du projet chez Lovable. --}}
                    <flux:menu.item icon="sun" :href="route('plans')" wire:navigate data-energy="{{ $this->energy['percent'] }}">{{ __('Energy: :percent %', ['percent' => $this->energy['percent']]) }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </header>

    @unless ($this->isOwner)
        <p class="shrink-0 border-b border-sky-200 bg-sky-50 px-4 py-2 text-xs text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200" data-admin-view>
            {{ __('You are looking at this project as an administrator (read only). It belongs to :owner.', ['owner' => $project->user->email]) }}
        </p>
    @endunless

    {{-- Sur un téléphone : trois vues, juste sous la barre. --}}
    <nav class="grid shrink-0 grid-cols-3 gap-1 border-b border-zinc-200 p-1.5 lg:hidden dark:border-zinc-700" role="tablist" aria-label="{{ __('Project views') }}" wire:ignore>
        @foreach (['discussion' => ['chat-bubble-left-right', __('Discussion')], 'apercu' => ['eye', __('Preview')], 'code' => ['code-bracket', __('Code')]] as $key => [$icon, $label])
            <button type="button" role="tab" data-tab="{{ $key }}"
                x-on:click="vue = '{{ $key }}'@if ($key === 'code'); $wire.loadCode()@endif" x-bind:aria-selected="vue === '{{ $key }}'"
                x-bind:class="vue === '{{ $key }}' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium">
                <flux:icon :name="$icon" variant="micro" />
                {{ $label }}
            </button>
        @endforeach
    </nav>

    <div class="flex min-h-0 flex-1 lg:grid lg:grid-cols-[minmax(22rem,2fr)_3fr]">
        {{-- Discussion : les messages, le travail de Kekeli, puis la zone de saisie collée en bas. --}}
        <section id="panneau-discussion" role="tabpanel" class="flex min-h-0 flex-1 flex-col lg:border-r lg:border-zinc-200 dark:lg:border-zinc-700" x-bind:class="vue === 'discussion' ? '' : 'max-lg:hidden'" wire:ignore.self data-panel="discussion">
            <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-4" data-conversation
                x-data="{ bas() { this.$el.scrollTop = this.$el.scrollHeight } }"
                x-init="bas(); new MutationObserver(() => { if ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 500) bas() }).observe($el, { childList: true, subtree: true })">
                <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
                    @forelse ($this->messages as $message)
                        @continue($message->agent_run_id && $this->runs->firstWhere('id', $message->agent_run_id)?->report)

                        @if ($message->role === Message::ROLE_USER)
                            <div wire:key="message-{{ $message->id }}" class="flex flex-col items-end gap-1.5" data-user-message>
                                @if ($message->photos->isNotEmpty())
                                    <div class="flex max-w-[85%] flex-wrap justify-end gap-1.5" data-message-photos>
                                        @foreach ($message->photos as $photo)
                                            <a wire:key="photo-message-{{ $photo->id }}" href="{{ route('projects.photos.show', [$project, $photo]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                                <img src="{{ route('projects.photos.show', [$project, $photo]) }}" alt="{{ $photo->name }}" class="size-20 object-cover" loading="lazy">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-br-md bg-amber-500 px-4 py-2 text-sm text-white">{{ $message->content }}</div>
                            </div>
                        @else
                            <div wire:key="message-{{ $message->id }}" class="flex items-start gap-2.5">
                                <x-app-logo-icon class="mt-0.5 size-6 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1 whitespace-pre-line break-words text-sm text-zinc-800 dark:text-zinc-100">{{ $message->content }}</div>
                            </div>
                        @endif

                        @php($run = $message->role === Message::ROLE_USER ? $this->runs->get($message->id) : null)

                        @if ($run)
                            <div wire:key="run-{{ $run->id }}" class="flex items-start gap-2.5" data-run-status="{{ $run->status }}">
                                <x-app-logo-icon class="mt-0.5 size-6 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1 space-y-2 text-sm">
                                    {{-- L'attente : une petite ligne calme, avec la place dans la file. --}}
                                    @if ($run->status === AgentRun::STATUS_QUEUED || $run->status === AgentRun::STATUS_RUNNING)
                                        <p class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400" data-run-working>
                                            <flux:icon.loading class="size-4" />
                                            @php($saying = $run->status === AgentRun::STATUS_RUNNING ? \App\Agent\RunReport::plain($run->steps->filter->isNarration()->last()?->target, 280) : '')
                                            {{ $run->isQuestion() ? __('Kekeli is thinking…') : ($run->status === AgentRun::STATUS_QUEUED ? __('Kekeli is getting ready…') : ($saying !== '' ? $saying : __('Kekeli is working…'))) }}
                                        </p>
                                        @if ($run->status === AgentRun::STATUS_QUEUED && ! $run->isQuestion() && ($position = app(AgentPlaces::class)->position($run)))
                                            <p class="pl-6 text-xs text-zinc-500 dark:text-zinc-400" data-run-waiting-turn="{{ $position }}">{{ trans_choice('{1} Your request is next in line. It will start by itself: you lose nothing.|[2,*] Your request is waiting for its turn (position :count). It will start by itself: you lose nothing.', $position, ['count' => $position]) }}</p>
                                        @endif
                                    @elseif ($run->status === AgentRun::STATUS_WAITING)
                                        <p class="text-zinc-500 dark:text-zinc-400" data-run-waiting>
                                            <span>{{ __('Kekeli is in high demand right now. Your request is waiting and will resume automatically around :time.', ['time' => $run->resume_at?->timezone(config('kekeli.display_timezone'))->format('H:i')]) }}</span>
                                        </p>
                                    @elseif ($run->status === AgentRun::STATUS_STOPPED)
                                        <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950" data-run-stopped>
                                            @if ($this->energy['exhausted'])
                                                <p class="text-zinc-800 dark:text-zinc-100">{{ $this->energy['monthly']
                                                    ? __('The energy of your formula for this month is used up: Kekeli has stopped the work. Your project is saved as it is, with nothing lost. The energy comes back on :date (Lomé time); you will then continue exactly where the work stopped.', ['date' => $this->energy['recharges_at']])
                                                    : __('Your energy is used up: Kekeli has stopped the work. Your project is saved as it is, with nothing lost. The energy recharges at :time (Lomé time); you will then continue exactly where the work stopped.', ['time' => $this->energy['recharges_at']]) }}</p>
                                                @if ($this->isOwner)
                                                    <flux:button :href="route('plans')" variant="primary" size="sm" icon="sun" class="mt-3" wire:navigate>{{ __('See the formulas') }}</flux:button>
                                                @endif
                                            @else
                                                <p class="text-zinc-800 dark:text-zinc-100">{{ __('Kekeli had stopped because the energy was used up. Your energy is recharged: continue exactly where the work stopped.') }}</p>
                                                @if ($this->isOwner && ! $this->activeRun)
                                                    <flux:button variant="primary" size="sm" icon="play" class="mt-3" wire:click="continueRun('{{ $run->id }}')" data-continue>{{ __('Continue') }}</flux:button>
                                                @endif
                                            @endif
                                        </div>
                                    @elseif ($run->status === AgentRun::STATUS_FAILED)
                                        <p class="kekeli-erreur" data-run-failed>{{ __('Sorry, Kekeli could not finish this request. Please try again in a moment.') }}</p>
                                    @endif

                                    @foreach ($run->permissions as $permission)
                                        @if ($permission->isPending() && $run->isActive())
                                            <div wire:key="permission-{{ $permission->id }}" class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950" data-permission="pending">
                                                <div class="flex items-center gap-2 font-medium text-zinc-900 dark:text-white">
                                                    <flux:icon.shield-exclamation variant="micro" class="text-amber-600" />
                                                    {{ __('Kekeli asks for your permission') }}
                                                </div>
                                                <div class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $permission->toolLabel() }}</div>
                                                @if ($permission->target)
                                                    <code class="mt-1 block break-all rounded bg-white px-2 py-1 text-xs dark:bg-zinc-800">{{ $permission->target }}</code>
                                                @endif
                                                @if ($this->isOwner)
                                                    <div class="mt-3 flex gap-2">
                                                        <flux:button size="sm" variant="primary" icon="check" wire:click="answerPermission({{ $permission->id }}, true)" data-allow>{{ __('Allow') }}</flux:button>
                                                        <flux:button size="sm" variant="filled" icon="x-mark" wire:click="answerPermission({{ $permission->id }}, false)" data-refuse>{{ __('Refuse') }}</flux:button>
                                                    </div>
                                                    <flux:error name="permission" />
                                                @endif
                                            </div>
                                        @elseif (! $permission->isPending())
                                            <p wire:key="permission-{{ $permission->id }}" class="flex items-start gap-2 text-zinc-600 dark:text-zinc-300" data-permission="{{ $permission->status }}">
                                                <flux:icon :name="$permission->status === AgentPermission::STATUS_ALLOWED ? 'check-circle' : 'x-circle'" variant="micro" @class(['mt-0.5 shrink-0', 'text-emerald-500' => $permission->status === AgentPermission::STATUS_ALLOWED, 'text-zinc-400' => $permission->status !== AgentPermission::STATUS_ALLOWED]) />
                                                <span class="min-w-0 break-words">
                                                    {{ match ($permission->status) { AgentPermission::STATUS_ALLOWED => __('Permission given'), AgentPermission::STATUS_REFUSED => __('Permission refused'), default => __('No answer: action refused') } }} :
                                                    {{ $permission->toolLabel() }}
                                                </span>
                                            </p>
                                        @endif
                                    @endforeach

                                    {{--
                                        Le travail de Kekeli, comme Lovable ou Claude Code : un seul bloc replié « Kekeli a fait
                                        X actions », avec des libellés simples (jamais de nom de fichier ni de commande), sans
                                        phrase répétée.
                                    --}}
                                    @php($timeline = $run->steps->reduce(function ($lines, $step) {
                                        if ($step->isNarration()) {
                                            $text = \App\Agent\RunReport::plain($step->target, 280);
                                            if ($text !== '' && ! collect($lines)->contains(fn ($line) => $line['type'] === 'phrase' && $line['text'] === $text)) {
                                                $lines[] = ['type' => 'phrase', 'text' => $text, 'count' => 1, 'icon' => 'check', 'tone' => 'normal'];
                                            }

                                            return $lines;
                                        }
                                        $last = $lines === [] ? null : $lines[count($lines) - 1];
                                        if ($last && $last['type'] === 'action' && $last['text'] === $step->label()) {
                                            $lines[count($lines) - 1]['count']++;
                                        } else {
                                            $lines[] = ['type' => 'action', 'text' => $step->label(), 'count' => 1, 'icon' => $step->icon(), 'tone' => $step->tone()];
                                        }

                                        return $lines;
                                    }, []))
                                    @if ($timeline !== [])
                                        @php($actionCount = $run->steps->reject->isNarration()->count() ?: count($timeline))
                                        <details class="group" data-narration>
                                            <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-lg py-0.5 text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden" data-step>
                                                <span data-actions-count="{{ $actionCount }}">{{ trans_choice('{1} Kekeli did :count action|[2,*] Kekeli did :count actions', $actionCount, ['count' => $actionCount]) }}</span>
                                                <flux:icon.chevron-down variant="micro" class="shrink-0 transition group-open:rotate-180" />
                                            </summary>
                                            <ol class="mt-1.5 space-y-1 border-l border-zinc-200 pl-3 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                                @foreach ($timeline as $line)
                                                    <li wire:key="ligne-{{ $run->id }}-{{ $loop->index }}" class="flex items-start gap-1.5" @if ($line['type'] === 'phrase') data-phrase @endif>
                                                        <flux:icon :name="$line['type'] === 'phrase' ? 'check' : $line['icon']" variant="micro" @class(['mt-0.5 size-3.5 shrink-0', 'text-orange-700' => $line['tone'] === 'danger', 'text-emerald-500' => $line['type'] === 'phrase' || $line['tone'] === 'success', 'text-zinc-400' => $line['type'] === 'action' && $line['tone'] === 'normal']) />
                                                        <span class="min-w-0 break-words">{{ $line['text'] }}@if ($line['count'] > 1) ({{ $line['count'] }})@endif</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </details>
                                    @elseif ($run->status === AgentRun::STATUS_COMPLETED && ! $run->report)
                                        <p class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><flux:icon.check-circle variant="micro" class="text-emerald-500" /> {{ __('Finished.') }}</p>
                                    @endif

                                    {{-- Le compte rendu final : le dernier message de Kekeli. --}}
                                    @if ($run->report)
                                        <x-run-report :run="$run" />
                                    @endif

                                    @if ($run->status === AgentRun::STATUS_COMPLETED && $project->hasLivePreview())
                                        <flux:button size="sm" variant="filled" icon="eye" class="lg:hidden" x-on:click="vue = 'apercu'" data-show-preview>
                                            {{ __('See the preview') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="m-auto flex max-w-sm flex-col items-center gap-3 py-10 text-center">
                            <x-app-logo-icon class="size-10 text-amber-500" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The conversation will start here') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- En bas : les messages importants, puis la zone de saisie, la même que sur l'accueil. --}}
            <div class="shrink-0 border-t border-zinc-200 p-2 sm:p-3 dark:border-zinc-700">
                <div class="mx-auto flex w-full max-w-2xl flex-col gap-2">
                    @if ($this->isOwner && $this->energy['exhausted'] && ! $this->activeRun)
                        <div class="rounded-xl bg-amber-50 p-3 text-sm dark:bg-amber-950" data-energy-exhausted="{{ $this->energy['free_cap'] ? 'free-cap' : ($this->energy['free'] ? 'free' : 'paid') }}">
                            <p class="text-zinc-800 dark:text-zinc-100">
                                @if ($this->energy['free_cap'])
                                    {{ __('Kekeli is recharging right now. It will be ready again at :time. Don\'t want to wait? Switch to a paid formula and continue right away.', ['time' => $this->energy['recharges_at']]) }}
                                @elseif ($this->energy['free'])
                                    {{ __('Your energy for today is used up. Kekeli is recharging and will be ready again at :time. Don\'t want to wait? Switch to a paid formula and continue right away.', ['time' => $this->energy['recharges_at']]) }}
                                @elseif ($this->energy['monthly'])
                                    {{ __('You have used all the energy of your formula for this month. It comes back on :date. Your project is saved: you will pick up exactly where you left off. Need more? Switch to the higher formula.', ['date' => $this->energy['recharges_at']]) }}
                                @else
                                    {{ __('You have used all the energy of your formula for now. It recharges at :time. Your project is saved: you will pick up exactly where you left off. Need more? Switch to the higher formula.', ['time' => $this->energy['recharges_at']]) }}
                                @endif
                            </p>
                            <flux:button :href="route('plans')" variant="primary" size="sm" icon="sun" class="mt-3" wire:navigate>{{ __('See the formulas') }}</flux:button>
                        </div>
                    @elseif ($this->isOwner)
                        @if ($this->needsCountry)
                            <form wire:submit="saveCountry" class="flex flex-col gap-2 rounded-xl bg-amber-50 p-3 dark:bg-amber-950" data-country-question>
                                <flux:heading size="sm">{{ __('In which country is your activity?') }}</flux:heading>
                                <div class="flex items-end gap-2">
                                    <flux:select wire:model="country" class="flex-1" :aria-label="__('Your country')">
                                        @foreach (config('kekeli.countries') as $code => $details)
                                            <flux:select.option value="{{ $code }}">{{ $details['name'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                                </div>
                                <flux:error name="country" />
                            </form>
                        @endif
                        @if ($this->comingSoon)
                            <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950" data-coming-soon>
                                <flux:heading size="sm">{{ __('Coming soon in your country') }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ __('Kekeli is in beta and is not yet available in your country. We will open it as soon as possible. Thank you for your patience!') }}</flux:text>
                            </div>
                        @else
                            @if ($databaseSuggestion)
                                <div class="rounded-xl bg-amber-50 p-3 text-sm dark:bg-amber-950" data-database-suggestion>
                                    <flux:heading size="sm">{{ __('This request needs a site with a database') }}</flux:heading>
                                    <flux:text class="mt-1 text-sm">{{ __('User accounts and an administrator area need a database. This project is a showcase site: Kekeli can create a new site with a database from your request (this site stays as it is).') }}</flux:text>
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <flux:button variant="primary" size="sm" icon="sparkles" wire:click="createDatabaseSite" data-create-database-site>{{ __('Create a site with a database') }}</flux:button>
                                        <flux:button variant="filled" size="sm" wire:click="send" data-send-anyway>{{ __('Send to this site anyway') }}</flux:button>
                                    </div>
                                </div>
                            @endif
                            <form wire:submit="send">
                                <x-prompt-box
                                    model="content"
                                    photos-model="uploads"
                                    :photos="$uploads"
                                    :placeholder="__('Write to Kekeli…')"
                                    :label="__('Message')"
                                    submit="send"
                                    remove-photo="removeUpload"
                                    :rows="2"
                                    :disabled="(bool) $this->activeRun"
                                    input-attribute="data-message-input"
                                >
                                </x-prompt-box>
                                <flux:error name="content" />
                                <flux:error name="uploads" />
                                <flux:error name="uploads.*" />
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </section>

        {{-- À droite sur un ordinateur : l'aperçu ou le code. --}}
        <section class="flex min-h-0 flex-1 flex-col" x-bind:class="vue === 'discussion' ? 'max-lg:hidden' : ''" wire:ignore.self data-right-panel>
            <div class="hidden h-11 shrink-0 items-center gap-1 border-b border-zinc-200 px-3 lg:flex dark:border-zinc-700" role="tablist" wire:ignore>
                @foreach (['apercu' => ['eye', __('Preview')], 'code' => ['code-bracket', __('Code')]] as $key => [$icon, $label])
                    <button type="button" role="tab" data-desktop-tab="{{ $key }}"
                        x-on:click="vue = '{{ $key }}'@if ($key === 'code'); $wire.loadCode()@endif"
                        x-bind:class="(vue === '{{ $key }}' || ('{{ $key }}' === 'apercu' && vue === 'discussion')) ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium">
                        <flux:icon :name="$icon" variant="micro" />
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{--
                Aperçu, comme Lovable ou Bolt.new : quand il s'est mis en pause, il se rallume tout seul dès qu'on
                l'ouvre, avec une petite animation de chargement. Jamais d'écran « en pause » avec un bouton à toucher.
            --}}
            <div id="panneau-apercu" role="tabpanel" class="flex min-h-0 flex-1 flex-col gap-2 p-2 sm:p-3" x-bind:class="vue === 'code' ? 'hidden' : ''" wire:ignore.self data-panel="apercu">
                @if ($project->isWebApp() && $project->app_admin_email && $this->isOwner)
                    {{-- Compte d'essai pour se connecter dans l'aperçu : discret, dépliable. --}}
                    <details class="shrink-0 rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800" data-test-admin x-data="{ voir: false }">
                        <summary class="cursor-pointer select-none font-medium text-zinc-700 dark:text-zinc-200">{{ __('Test administrator account') }}</summary>
                        <p class="mt-2 break-all">{{ __('Email address:') }} <code>{{ $project->app_admin_email }}</code></p>
                        <p class="mt-1 break-all">{{ __('Password:') }}
                            <code x-show="voir" x-cloak data-test-password>{{ $project->app_admin_password }}</code>
                            <button type="button" class="underline" x-show="! voir" x-on:click="voir = true">{{ __('Show') }}</button>
                        </p>
                    </details>
                @endif

                @php($warning = (int) config('kekeli.preview_pause_warning'))
                @if ($this->previewUrl && ! $project->previewIsPausing())
                    @php($remaining = max(0, now()->diffInSeconds($project->preview_expires_at, false)))
                    {{-- Juste avant la pause, l'aperçu montre le chargement au lieu d'une erreur, puis se rallume. --}}
                    <div wire:key="preview-{{ md5($this->previewUrl) }}" class="flex min-h-0 flex-1 flex-col"
                        x-data="{ bientot: false, recharger() { this.$refs.cadre.src = this.$refs.cadre.src } }"
                        x-init="setTimeout(() => { bientot = true }, {{ max(0, (int) $remaining - $warning) * 1000 }}); setTimeout(() => $wire.$refresh(), {{ ((int) $remaining + 5) * 1000 }})">
                        <div x-show="! bientot" class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-preview="live">
                            <div class="flex shrink-0 items-center justify-end gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-900">
                                <flux:button size="sm" variant="ghost" icon="arrow-path" x-on:click="recharger()" :aria-label="__('Reload the preview')" :tooltip="__('Reload the preview')" />
                                <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$this->previewUrl" target="_blank" rel="noopener noreferrer" :aria-label="__('Open the preview in a new tab')" :tooltip="__('Open the preview in a new tab')" />
                            </div>
                            <iframe
                                x-ref="cadre"
                                src="{{ $this->previewUrl }}"
                                title="{{ __('Preview of :name', ['name' => $project->name]) }}"
                                class="min-h-0 w-full flex-1 bg-white"
                                sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals allow-downloads"
                                referrerpolicy="no-referrer"
                                loading="eager"
                            ></iframe>
                        </div>
                        <div x-show="bientot" x-cloak class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-zinc-200 p-6 text-center dark:border-zinc-700" data-preview-pausing-soon>
                            <flux:icon.loading class="size-7 text-amber-500" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Loading the preview…') }}</p>
                        </div>
                    </div>
                @else
                    @php($previewState = match (true) {
                        (bool) $this->activeRun => 'starting',
                        $project->previewIsPausing() => 'pausing',
                        $project->previewIsWaking() => 'waking',
                        filled($project->preview_url) => 'asleep',
                        default => 'empty',
                    })
                    {{-- En pause : l'aperçu se rallume dès qu'il est visible (sur un téléphone, quand on ouvre l'onglet). --}}
                    <div wire:key="preview-state-{{ $previewState }}" class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-zinc-200 p-6 text-center dark:border-zinc-700" data-preview="{{ $previewState }}"
                        @if ($previewState === 'asleep' && $this->isOwner) x-intersect.once="$wire.wakePreview()" data-wake-preview @endif>
                        @if ($previewState === 'empty')
                            <x-app-logo-icon class="size-10 text-amber-500" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The preview will appear here') }}</p>
                        @else
                            <flux:icon.loading class="size-7 text-amber-500" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $previewState === 'starting' ? __('Kekeli is preparing the preview…') : __('Loading the preview…') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Code : les fichiers du site en lecture seule, le fichier ZIP et GitHub. --}}
            <div id="panneau-code" role="tabpanel" class="min-h-0 flex-1 overflow-y-auto" x-bind:class="vue === 'code' ? '' : 'hidden'" x-cloak wire:ignore.self data-panel="code">
                @include('projects.partials.code')
            </div>
        </section>
    </div>

    <x-run-changes-modal :run="$this->changesRun" />

    @if ($this->isOwner)
        @include('projects.partials.publish')

        <flux:modal name="rename-project" class="w-full max-w-md">
            <form wire:submit="renameProject" class="space-y-5">
                <flux:heading size="lg">{{ __('Rename the site') }}</flux:heading>
                <flux:input wire:model="newName" :label="__('Name of the site')" maxlength="100" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        @include('projects.partials.allowed-sites')
    @endif
</div>
