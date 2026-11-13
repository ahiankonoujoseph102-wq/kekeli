<?php

use App\Agent\AgentPlaces;
use App\Agent\AnswerModels;
use App\Agent\Tools\CreateAgent;
use App\Agent\Tools\CreateAutomation;
use App\Agent\Tools\CreateDocument;
use App\Agent\Tools\CreateImage;
use App\Agent\Tools\CreateVideo;
use App\Agent\Tools\CreatePresentation;
use App\Agent\Tools\CreateApplication;
use App\Agent\Tools\CreateSite;
use App\Models\ProjectSecret;
use App\Support\SecretScanner;
use App\Jobs\RespondToMessage;
use App\Agent\Energy;
use App\Agent\PreviewToken;
use App\Agent\RunnerClient;
use App\Github\GithubClient;
use App\Jobs\PushProjectToGithub;
use App\Github\RepositoryClient;
use App\Jobs\PublishProject;
use App\Jobs\PushRepository;
use App\Jobs\LaunchAutomation;
use App\Automations\AutomationTrigger;
use App\Automations\FormWiring;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendAgentRun;
use App\Jobs\WakePreview;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Support\Connectors;
use App\Projects\ProjectVersions;
use App\Models\ProjectVersion;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectAttachment;
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
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public string $content = '';

    /** Message of a showcase site that asks for accounts or an administrator area: Kekeli suggests a site with a database. */

    public string $newSite = '';

    /** « Variables secrètes » : the name and the value typed in the form (the value is never shown again). */
    /** « Brancher un formulaire » : the site chosen and the address of the entry point (on the client's Cloudflare). */
    public string $wireSite = '';

    public string $entryUrl = '';

    public string $secretName = '';

    public string $secretValue = '';

    /** « Brancher votre modèle » : le fournisseur choisi par le client pour SON agent ou SON automatisation, et sa clé. */
    public string $modelProvider = 'openai';

    public string $modelName = '';

    public string $modelKey = '';

    /** A key pasted in the discussion was masked: Kekeli suggests the « Variables secrètes ». */
    public bool $keyMasked = false;

    /** Request whose changes are shown (« See the changes »). */
    public ?string $changesRunId = null;

    /** Address of the client's own domain name, typed on the phone (for example www.my-restaurant.com). */
    public string $newDomain = '';

    /** Photos joined in the message field, sent with the next message. */
    public array $uploads = [];

    /** Files joined in the message field (Word, PDF, PowerPoint), sent with the next message. */
    public array $attachments = [];

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
        $this->keyMasked = (bool) session('kekeli.cle_masquee');

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
     * The project was renamed from « Récents »: the bar shows the new name.
     */
    #[On('projet-renomme')]
    public function refreshName(): void
    {
        $this->project->refresh();
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
        return $this->project->messages()->with('photos', 'attachments')->get();
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
     * @return array{plan: array<string, mixed>, free: bool, free_cap: bool, percent: int, exhausted: bool, recharges_at: string, monthly: bool, line: string, cap: bool, cap_resumes_at: string}
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
            'monthly' => $monthly = $energy->plan($owner)['period'] === 'month',
            // La ligne sobre de l'arrêt, comme Claude Code : « Énergie épuisée · Recharge à 00 h 00 ».
            'line' => match (true) {
                $freeCap => __('Kekeli is recharging · Resumes at :time', ['time' => $energy->rechargeText($owner)]),
                $monthly => __('Energy used up · Recharges on :date', ['date' => $energy->rechargeText($owner)]),
                default => __('Energy used up · Recharges at :time', ['time' => $energy->rechargeText($owner)]),
            },
            // Plafond du jour du modèle de construction : les constructions attendent, les questions continuent.
            'cap' => app(AnswerModels::class)->buildCapReached($owner),
            'cap_resumes_at' => app(AnswerModels::class)->buildResumesText(),
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
    /**
     * The views next to the discussion: the preview (or « Tester l'agent », « Lancer », the document) and the code. A
     * document has no code: its preview shows its pages and its versions.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    #[Computed]
    public function tabs(): array
    {
        if ($this->project->isDocument()) {
            return ['apercu' => match (true) {
                $this->project->isImage() => ['photo', __('Image')],
                $this->project->isVideo() => ['film', __('Video')],
                $this->project->isPresentation() => ['presentation-chart-bar', __('Presentation')],
                default => ['document-text', __('Document')],
            }];
        }

        return [
            'apercu' => match (true) {
                $this->project->isAgent() => ['chat-bubble-oval-left-ellipsis', __('Test the agent')],
                $this->project->isAutomation() => ['play', __('Run')],
                default => ['eye', __('Preview')],
            },
            'code' => ['code-bracket', __('Code')],
        ];
    }

    /** Version shown in the preview of a document (null: the last one). */
    public ?int $versionNumber = null;

    /**
     * @return Collection<int, \App\Models\DocumentVersion>
     */
    #[Computed]
    public function versions(): Collection
    {
        return $this->project->documentVersions()->get();
    }

    #[Computed]
    public function shownVersion(): ?\App\Models\DocumentVersion
    {
        $versions = $this->versions;

        return ($this->versionNumber ? $versions->firstWhere('number', $this->versionNumber) : null) ?? $versions->last();
    }

    /**
     * The version made by each request (to show the card of the file under the answer of Kekeli).
     *
     * @return Collection<string, \App\Models\DocumentVersion>
     */
    #[Computed]
    public function versionsByRun(): Collection
    {
        return $this->versions->keyBy('agent_run_id');
    }

    /**
     * The folded line of the work on a file, like Claude: « A créé le document · Word, PDF », « A modifié la
     * présentation · version 2 ».
     */
    public function fileActionLine(AgentRun $run): ?string
    {
        $version = $this->versionsByRun->get($run->id);
        if (! $version) {
            return null;
        }
        $kind = match ($this->project->document_type) {
            'presentation' => __('the presentation'),
            'image' => __('the image'),
            'video' => __('the video'),
            default => __('the document'),
        };
        $formats = implode(', ', $version->formats());

        return $version->number === 1
            ? __('Created :kind · :formats', ['kind' => $kind, 'formats' => $formats])
            : __('Changed :kind · version :number', ['kind' => $kind, 'number' => $version->number]);
    }

    public function showVersion(int $number): void
    {
        $this->versionNumber = $number;
        unset($this->shownVersion);
    }

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

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
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

    /**
     * The versions of a site, an application, an agent or an automation, from the newest to the oldest.
     *
     * @return Collection<int, ProjectVersion>
     */
    #[Computed]
    public function projectVersions(): Collection
    {
        return $this->project->hasVersions() ? $this->project->versions()->reorder('number', 'desc')->get() : collect();
    }

    /** The version that « Restaurer cette version » will put back, waiting for the confirmation. */
    public ?int $restoreNumber = null;

    /** « Voir » : the preview shows an old version, nothing else changes. */
    public function viewVersion(int $number): void
    {
        abort_unless($this->isOwner, 403);
        $version = $this->project->versions()->where('number', $number)->first();
        if (! $version || $this->activeRun) {
            return;
        }
        if ($version->number === $this->projectVersions->first()?->number) {
            $this->backToCurrent();

            return;
        }
        app(ProjectVersions::class)->view($this->project, $version);
        $this->project->refresh();
        $this->modal('versions')->close();
    }

    /** « Revenir à la version actuelle ». */
    public function backToCurrent(): void
    {
        abort_unless($this->isOwner, 403);
        app(ProjectVersions::class)->back($this->project);
        $this->project->refresh();
    }

    /** « Restaurer cette version » : a confirmation first. */
    public function askRestore(int $number): void
    {
        abort_unless($this->isOwner, 403);
        $this->restoreNumber = $number;
        $this->modal('versions')->close();
        $this->modal('restore-version')->show();
    }

    /**
     * The confirmed restoration: a NEW version with the files of the old one (nothing is erased; it can be undone).
     * No model is asked: no energy of model is used.
     */
    public function restoreVersion(): void
    {
        abort_unless($this->isOwner, 403);
        $version = $this->restoreNumber ? $this->project->versions()->where('number', $this->restoreNumber)->first() : null;
        $this->restoreNumber = null;
        $this->modal('restore-version')->close();
        if (! $version || $this->activeRun || $version->number === $this->projectVersions->first()?->number) {
            return;
        }
        app(ProjectVersions::class)->restore($this->project, $version);
        $this->project->refresh();
        unset($this->projectVersions, $this->messages, $this->runs);
    }

    public function send(): void
    {
        abort_unless($this->isOwner, 403);

        // Une ancienne version est montrée : Kekeli travaille toujours sur la version actuelle.
        if ($this->project->viewing_version !== null) {
            $this->addError('content', __('You are looking at an older version. Go back to the current version (or restore this one) to write to Kekeli.'));

            return;
        }

        $limits = config('kekeli.client_photos');
        $room = $limits['max_per_project'] - $this->project->photos()->count();
        $validated = $this->validate([
            'content' => [$this->uploads || $this->attachments ? 'nullable' : 'required', 'string', 'max:4000'],
            'uploads' => ['array', 'max:'.max(0, $room)],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['max_kilobytes']],
            'attachments' => ['array', 'max:3'],
            'attachments.*' => ['file', 'mimes:'.implode(',', \App\Models\ProjectAttachment::extensions()), 'max:'.\App\Models\ProjectAttachment::MAX_MEDIA_KILOBYTES, \App\Models\ProjectAttachment::documentSizeRule()],
        ], [
            'content.required' => __('A few words are enough: what should Kekeli change?'),
            'uploads.max' => __('You can keep at most :count photos in a project.', ['count' => $limits['max_per_project']]),
        ], [
            'uploads' => __('photos'),
            'uploads.*' => __('photo'),
            'attachments' => __('files'),
            'attachments.*' => __('file'),
        ]);

        // Des photos sans texte : Kekeli les place dans le site.
        $content = trim((string) ($validated['content'] ?? '')) ?: ($this->attachments ? __('Here is my file.') : __('Here are my photos: use them in my site.'));

        // Une clé collée dans la discussion n'est jamais gardée ni envoyée au modèle : elle est masquée.
        [$content, $this->keyMasked] = SecretScanner::redact($content);

        // Comme Claude, ChatGPT ou Manus : le message va d'abord au premier répondant (le modèle), qui répond à une
        // question sans ordinateur, lance le travail sur le site avec l'outil prévu, ou pose une question courte.
        if ($this->sendToFirstResponder($content)) {
            $this->reset('content', 'uploads', 'attachments');
        }
    }

    /**
     * A showcase site cannot have accounts nor an administrator area: the first responder chose to change the site,
     * and Kekeli suggests a site with a database. This is the client's message, until the client chooses.
     */
    #[Computed]
    public function databaseSuggestion(): ?string
    {
        $last = $this->project->agentRuns()->with('message')->get()->last();

        return $last && $last->isQuestion() && data_get($last->report, 'database_suggestion') ? $last->message?->content : null;
    }

    /**
     * The client keeps the showcase site: Kekeli works on it with the same message.
     */
    public function sendAnyway(): void
    {
        abort_unless($this->isOwner, 403);

        $last = $this->project->agentRuns()->with('message')->get()->last();
        if (! $last || ! data_get($last->report, 'database_suggestion') || ! $this->canSendNow()) {
            return;
        }

        $last->forceFill(['report' => array_merge((array) $last->report, ['database_suggestion' => false])])->save();
        app(RunStarter::class)->startWith($this->project, $last->message);
        unset($this->messages, $this->runs, $this->activeRun, $this->energy, $this->databaseSuggestion);
    }

    /**
     * The client accepts the suggestion: a new site with a database is created from the same request
     * (the showcase site stays as it is).
     */
    public function createDatabaseSite(): void
    {
        abort_unless($this->isOwner && filled($this->databaseSuggestion), 403);

        $last = $this->project->agentRuns()->get()->last();
        $last->forceFill(['report' => array_merge((array) $last->report, ['database_suggestion' => false])])->save();

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
     * The message of the client, with its photos, goes to the first responder (a small request to the model, without a
     * computer): it costs very little energy when it is a question.
     */
    private function sendToFirstResponder(string $content): bool
    {
        if (! $this->canSendNow()) {
            return false;
        }

        $run = DB::transaction(function () use ($content) {
            $message = $this->project->messages()->create(['role' => Message::ROLE_USER, 'content' => $content]);

            // Les photos jointes partent avec le message (celles envoyées avant, sans message, aussi).
            $this->project->photos()->whereNull('message_id')->update(['message_id' => $message->id]);
            foreach ($this->uploads as $upload) {
                ProjectPhoto::store($this->project, $upload, $message);
            }
            foreach ($this->attachments as $file) {
                ProjectAttachment::store($this->project, $file, $message);
            }

            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        });

        rescue(function () use ($run) {
            RespondToMessage::dispatch($run);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy, $this->photos, $this->databaseSuggestion);

        return true;
    }

    /**
     * « Arrêter », like Claude, ChatGPT or Lovable: Kekeli stops answering or working. An answer being written is
     * dropped; a work on the site stops at the agent's next step and what is already done is kept (the Runner saves
     * the project as it is). The energy already used stays counted.
     */
    /** « Toujours autoriser pour cet outil », sous la demande d'accord d'un connecteur. */
    public bool $alwaysAllow = false;

    /** Message court après un choix refusé dans « + » → « Connecteurs » (limite de la formule Gratuite). */
    public ?string $connectorNotice = null;

    /** « + » → « Connecteurs » : activer ou désactiver un connecteur pour cette discussion. */
    public function toggleConnector(int $id): void
    {
        abort_unless($this->isOwner, 403);
        $connectors = app(Connectors::class);
        $this->connectorNotice = $connectors->toggle(Auth::user(), $id, $this->project) ? null : __('Free formula: :count connectors active at most. Turn one off first, or choose a paid formula.', ['count' => $connectors->maxActive(Auth::user()) ?? 0]);
        $this->project->refresh();
    }

    /**
     * « Autoriser » : the action shown to the client is done exactly as shown, then Kekeli finishes its answer. With
     * « Toujours autoriser pour cet outil », Kekeli will not ask again for this tool (removable in Réglages).
     */
    public function approveConnector(int $actionId): void
    {
        abort_unless($this->isOwner, 403);
        $action = ConnectorAction::query()->where('project_id', $this->project->id)->where('user_id', Auth::id())->whereKey($actionId)->first();
        $run = $action ? $this->project->agentRuns()->whereKey($action->agent_run_id)->first() : null;
        if (! $action || ! $run || $action->status !== ConnectorAction::PENDING || ! $this->canSendNow()) {
            return;
        }

        $run2 = DB::transaction(function () use ($action, $run) {
            $action->update(['status' => ConnectorAction::ALLOWED]);
            if ($this->alwaysAllow && $action->connector) {
                $action->connector->forceFill(['always_allowed' => array_values(array_unique([...$action->connector->alwaysAllowed(), $action->tool]))])->save();
            }
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $run->message_id, 'purpose' => AgentRun::PURPOSE_QUESTION, 'connector_action_id' => $action->id]);
        });
        $this->alwaysAllow = false;

        rescue(function () use ($run2) {
            RespondToMessage::dispatch($run2);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /** « Refuser » : rien n'est fait ; une phrase calme le dit. */
    public function refuseConnector(int $actionId): void
    {
        abort_unless($this->isOwner, 403);
        $action = ConnectorAction::query()->where('project_id', $this->project->id)->where('user_id', Auth::id())->whereKey($actionId)->first();
        if (! $action || $action->status !== ConnectorAction::PENDING) {
            return;
        }
        $action->forceFill(['status' => ConnectorAction::REFUSED, 'arguments' => null])->save();
        $this->project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => __('Action refused: nothing was changed in :name.', ['name' => $action->connector_name])]);
        $this->project->touch();

        unset($this->messages, $this->runs);
    }

    public function stop(): void
    {
        abort_unless($this->isOwner, 403);

        $run = $this->project->agentRuns()->whereIn('status', AgentRun::ACTIVE_STATUSES)->get()->last();
        if (! $run) {
            return;
        }

        $working = ! $run->isQuestion() && $run->sent_at !== null && in_array($run->status, [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING], true);
        if ($working) {
            // L'agent travaille dans son ordinateur : le Runner l'arrête à son prochain signe de vie, puis sauvegarde.
            if (! $run->stop_requested_at) {
                $run->update(['stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_CLIENT]);
            }
        } else {
            $run->update([
                'status' => AgentRun::STATUS_STOPPED,
                'stop_requested_at' => now(),
                'stop_reason' => AgentRun::STOP_CLIENT,
                'finished_at' => now(),
                'resume_at' => null,
            ]);
        }

        unset($this->runs, $this->activeRun, $this->energy);
    }

    /**
     * « Régénérer », under the last written answer of Kekeli, like Claude or ChatGPT: the same message of the client
     * goes again to the first responder. It costs energy like a new message; the previous answer is replaced.
     */
    public function regenerate(string $runId): void
    {
        abort_unless($this->isOwner, 403);

        $last = $this->project->agentRuns()->get()->last();
        if (! $last || $last->id !== $runId || ! $last->isQuestion() || $last->status !== AgentRun::STATUS_COMPLETED
            || blank(data_get($last->report, 'done')) || filled(data_get($last->report, 'tool')) || ! $last->message_id) {
            return;
        }

        if (! $this->canSendNow()) {
            return;
        }

        $run = DB::transaction(function () use ($last) {
            // L'ancienne réponse sort de la discussion ; sa consommation reste comptée dans l'énergie.
            $this->project->messages()->where('agent_run_id', $last->id)->where('role', Message::ROLE_ASSISTANT)->delete();
            $last->forceFill(['report' => array_merge((array) $last->report, ['replaced' => true])])->save();
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $last->message_id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        });

        rescue(function () use ($run) {
            RespondToMessage::dispatch($run);
        }, report: true);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /**
     * « Oui, créer le site », under a question of Kekeli that offers to create the site, like the cards of Lovable or
     * Bolt.new: the discussion becomes a site and the work starts, without asking the model again.
     */
    public function acceptSiteOffer(string $runId): void
    {
        abort_unless($this->isOwner, 403);

        $last = $this->project->agentRuns()->get()->last();
        if (! $last || $last->id !== $runId || data_get($last->report, 'offer') !== 'creer_site' || ! $this->project->isChat()) {
            return;
        }

        if (! $this->canSendNow()) {
            return;
        }

        $run = DB::transaction(function () {
            $message = $this->project->messages()->create(['role' => Message::ROLE_USER, 'content' => __('Yes, create the site')]);
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            $run = $this->project->agentRuns()->make(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
            $run->forceFill([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'report' => ['done' => '', 'note' => null, 'question' => true, 'tool' => 'creer_site', 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
            ])->save();

            return $run;
        });

        app(CreateSite::class)->handle($run->fresh(['project', 'message']));

        $this->project->refresh();
        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /**
     * « Construire ce plan », like the plan mode of Claude Code: the discussion becomes an application project and
     * Kekeli builds the plan it proposed. The client can also accept by writing, or correct the plan.
     */
    public function acceptPlan(string $runId): void
    {
        abort_unless($this->isOwner, 403);

        $last = $this->project->agentRuns()->get()->last();
        $offer = data_get($last?->report, 'offer');
        $plans = [CreateApplication::OFFER => [CreateApplication::class, 'creer_application'], CreateAgent::OFFER => [CreateAgent::class, 'creer_agent'], CreateAutomation::OFFER => [CreateAutomation::class, 'creer_automatisation'], CreateDocument::OFFER => [CreateDocument::class, 'creer_document'], CreatePresentation::OFFER => [CreatePresentation::class, 'creer_presentation'], CreateImage::OFFER => [CreateImage::class, 'creer_image'], CreateVideo::OFFER => [CreateVideo::class, 'creer_video']];
        if (! $last || $last->id !== $runId || ! isset($plans[$offer]) || ! $this->project->isChat()) {
            return;
        }
        [$tool, $toolName] = $plans[$offer];

        if (! $this->canSendNow()) {
            return;
        }

        $run = DB::transaction(function () use ($toolName) {
            $message = $this->project->messages()->create(['role' => Message::ROLE_USER, 'content' => __('Build this plan')]);
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            $run = $this->project->agentRuns()->make(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
            $run->forceFill([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'report' => ['done' => '', 'note' => null, 'question' => true, 'tool' => $toolName, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
            ])->save();

            return $run;
        });

        app($tool)->build($this->project, $run->message, (string) data_get($last->report, 'plan'), (string) data_get($last->report, 'pile', 'auto'));

        $this->project->refresh();
        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /**
     * The free trial of an AI agent in the preview: the client's own key, or the messages left today.
     *
     * @return array{own_key: bool, remaining: int}
     */
    #[Computed]
    public function agentTrial(): array
    {
        return [
            'own_key' => \App\Agent\AgentTrial::hasOwnKey($this->project),
            'remaining' => app(\App\Agent\AgentTrial::class)->remaining($this->project->user),
        ];
    }

    /**
     * The « Variables secrètes » of the project: names and dates only, never the values.
     *
     * @return Collection<int, ProjectSecret>
     */
    #[Computed]
    public function secrets(): Collection
    {
        return $this->project->secrets()->orderBy('name')->get(['id', 'project_id', 'name', 'updated_at']);
    }

    /**
     * Adds or replaces a secret variable: encrypted in the database, then sent only to the computer of the project.
     */
    public function saveSecret(): void
    {
        abort_unless($this->isOwner, 403);

        $this->secretName = Str::upper(trim($this->secretName));
        $this->validate([
            'secretName' => ['required', 'string', 'regex:'.ProjectSecret::NAME_PATTERN, Rule::notIn(ProjectSecret::RESERVED)],
            'secretValue' => ['required', 'string', 'max:4000'],
        ], [
            'secretName.regex' => __('A name in capital letters, digits and « _ », for example DATABASE_URL.'),
            'secretName.not_in' => __('This name is used by Kekeli: choose another one.'),
        ], [
            'secretName' => __('name'),
            'secretValue' => __('value'),
        ]);

        if ($this->project->secrets()->count() >= 50 && ! $this->project->secrets()->where('name', $this->secretName)->exists()) {
            $this->addError('secretName', __('A project can keep 50 secret variables at most.'));

            return;
        }

        $this->project->secrets()->updateOrCreate(['name' => $this->secretName], ['value' => $this->secretValue]);
        $this->reset('secretName', 'secretValue');
        unset($this->secrets);
        $this->dispatch('secret-saved');
        $this->syncGithubSecrets();
    }

    /**
     * Automation linked to GitHub: the Secret variables go to the encrypted secrets of the repository (with the code,
     * if it changed). The automation runs there, never at Kekeli.
     */
    private function syncGithubSecrets(): void
    {
        if ($this->project->isAutomation() && $this->project->isLinkedToGithub() && Auth::user()->github_token && $this->project->github_status !== Project::GITHUB_IN_PROGRESS) {
            $this->project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(fn () => PushProjectToGithub::dispatch($this->project), report: true);
        }
    }

    /**
     * « Brancher votre modèle », the guided choice of the client's own provider: the address and a suggested model are
     * filled by Kekeli (config kekeli.client_model_providers, checked on the official sites); the client only pastes
     * the key. Three secret variables are saved, encrypted: MODELE_API_URL, MODELE_NOM and MODELE_API_CLE.
     */
    public function connectModel(): void
    {
        abort_unless($this->isOwner, 403);

        $providers = (array) config('kekeli.client_model_providers');
        $this->validate([
            'modelProvider' => ['required', Rule::in(array_keys($providers))],
            'modelName' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'modelKey' => ['required', 'string', 'max:4000'],
        ], [], ['modelProvider' => __('provider'), 'modelName' => __('model'), 'modelKey' => __('key')]);

        $provider = $providers[$this->modelProvider];
        foreach (['MODELE_API_URL' => $provider['url'], 'MODELE_NOM' => trim($this->modelName) ?: $provider['model'], 'MODELE_API_CLE' => $this->modelKey] as $name => $value) {
            $this->project->secrets()->updateOrCreate(['name' => $name], ['value' => $value]);
        }

        $this->reset('modelKey', 'modelName');
        unset($this->secrets);
        $this->dispatch('secret-saved');
        $this->syncGithubSecrets();
    }

    public function deleteSecret(int $id): void
    {
        abort_unless($this->isOwner, 403);

        $this->project->secrets()->whereKey($id)->delete();
        unset($this->secrets);
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
        if (! app(GithubClient::class)->token(Auth::user())) {
            $this->addError('repository', __('Connect your GitHub account again to send the work.'));

            return;
        }

        $this->project->forceFill(['push_status' => Project::PUSH_IN_PROGRESS, 'push_error' => null, 'ci_fix_attempts' => 0])->save();
        rescue(function () {
            PushRepository::dispatch($this->project);
        }, report: true);
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
            app(RepositoryClient::class)->merge($this->project, (string) app(GithubClient::class)->token(Auth::user()));
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
        if (! $this->project->backup_at || ! app(GithubClient::class)->token(Auth::user()) || $this->project->github_status === Project::GITHUB_IN_PROGRESS || $this->activeRun) {
            return;
        }

        $this->project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();

        rescue(function () {
            PushProjectToGithub::dispatch($this->project);
        }, report: true);
    }

    /**
     * How Kekeli sends its work to GitHub in this project: a pull request (default, like Claude Code) or the main branch.
     */
    public function setPushMode(string $mode): void
    {
        abort_unless($this->isOwner, 403);
        if (! in_array($mode, [Project::PUSH_PULL_REQUEST, Project::PUSH_DIRECT], true)) {
            return;
        }
        $this->project->forceFill(['github_push_mode' => $mode])->save();
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
        // « Continuer » reprend un travail arrêté faute d'énergie, jamais un travail que le client a arrêté lui-même.
        if ($run->status !== AgentRun::STATUS_STOPPED || $run->stoppedByClient() || $this->energy['exhausted'] || $this->comingSoon) {
            return;
        }

        // Arrêté par le plafond du jour du modèle de construction : la reprise attend sa remise à zéro.
        if ($run->stop_reason === AgentRun::STOP_MODEL_CAP && $this->energy['cap']) {
            return;
        }

        // Un travail arrêté avant même d'être parti (plafond déjà atteint) démarre normalement, sans reprise de session.
        // Le projet est revenu à sa dernière version qui fonctionnait : la demande repart de cette version.
        $resume = ! ($run->stop_reason === AgentRun::STOP_MODEL_CAP && $run->sent_at === null) && data_get($run->report, 'kept_version') === null;

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

        rescue(function () use ($run, $resume) {
            // Une réponse avec un connecteur arrêtée au plafond du jour repart au premier répondant.
            $run->isQuestion() ? RespondToMessage::dispatch($run) : SendAgentRun::dispatch($run, $resume);
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
     * « Lancer maintenant »: the automation runs once in the computer of the project, with the client's Secret
     * variables, and its readable log appears step by step. It counts in the energy like any work of Kekeli.
     */
    public function launchNow(): void
    {
        if (! $this->project->isAutomation() || ! $this->project->backup_at || ! $this->canSendNow()) {
            return;
        }

        $run = DB::transaction(function () {
            $message = $this->project->messages()->create(['role' => Message::ROLE_USER, 'content' => __('Run now')]);
            $this->project->touch();
            app(Energy::class)->startWindowIfNeeded(Auth::user());

            return $this->project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_LAUNCH]);
        });

        LaunchAutomation::dispatch($run);

        unset($this->messages, $this->runs, $this->activeRun, $this->energy);
    }

    /**
     * The last run of the automation (« Lancer maintenant »), with its log.
     */
    #[Computed]
    public function lastLaunch(): ?AgentRun
    {
        return $this->project->agentRuns()->where('purpose', AgentRun::PURPOSE_LAUNCH)->latest('created_at')->latest('id')->first();
    }

    /**
     * Journal of the triggers of an automation: its launches by GitHub Actions on the client's repository (an event,
     * the planned time, by hand), read with the client's GitHub access, and its trials in Kekeli. Never their content.
     *
     * @return list<array{origin: string, at: \Illuminate\Support\Carbon, ok: ?bool, url: ?string}>
     */
    #[Computed]
    public function triggerLog(): array
    {
        $project = $this->project;
        $entries = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_LAUNCH)->latest('created_at')->limit(5)->get()
            ->map(fn (AgentRun $run) => ['origin' => 'kekeli', 'at' => $run->created_at, 'ok' => $run->isActive() ? null : (bool) data_get($run->report, 'launch.ok'), 'url' => null]);

        if ($project->isLinkedToGithub() && $project->user?->github_token) {
            $runs = Cache::remember('declenchements:'.$project->id, 60, fn () => rescue(function () use ($project) {
                $token = app(GithubClient::class)->token($project->user);

                return $token ? app(RepositoryClient::class)->workflowRuns((string) $project->repository, $token) : [];
            }, [], report: false));
            foreach ($runs as $run) {
                $entries->push([
                    'origin' => match ($run['event']) { 'repository_dispatch' => 'evenement', 'schedule' => 'heure', 'workflow_dispatch' => 'main', default => 'autre' },
                    'at' => \Illuminate\Support\Carbon::parse($run['at']),
                    'ok' => $run['status'] === 'completed' ? $run['conclusion'] === 'success' : null,
                    'url' => str_starts_with($run['url'], 'https://github.com/') ? $run['url'] : null,
                ]);
            }
        }

        return $entries->sortByDesc('at')->take(10)->values()->all();
    }

    /**
     * The client's sites that can send their form to this automation.
     */
    #[Computed]
    public function wirableSites(): Collection
    {
        return app(FormWiring::class)->candidates(Auth::user());
    }

    /**
     * « Brancher » : with the client's agreement (confirmation in the window), Kekeli plugs the form of one of their
     * sites into this automation. The work is done on the site, like any other change (energy, version).
     */
    public function wireForm(): void
    {
        if (! $this->isOwner || ! $this->project->isAutomation() || data_get($this->project->automation_trigger, 'kind') !== AutomationTrigger::EVENT) {
            return;
        }
        $this->validate([
            'wireSite' => ['required', 'string'],
            'entryUrl' => ['required', 'string', 'max:300', fn ($attribute, $value, $fail) => FormWiring::validUrl((string) $value) ? null : $fail(__('Paste the address of your entry point (it starts with https://).'))],
        ]);
        $site = Auth::user()->projects()->whereKey($this->wireSite)->firstOrFail();
        if (! $this->wirableSites->contains('id', $site->id)) {
            return;
        }

        // Le travail sur le site part tout seul (même chemin que toute demande à Kekeli).
        app(FormWiring::class)->wire($this->project, $site, $this->entryUrl);
        $this->reset('wireSite', 'entryUrl');
        Flux::modal('wire-form')->close();
        unset($this->triggerLog);
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
        (bool) $this->activeRun => ['working', $project->isChat() ? __('Kekeli is thinking…') : __('Kekeli is working…'), 'bg-sky-500 animate-pulse'],
        $project->isChat() => ['chat', __('Discussion'), 'bg-zinc-400'],
        filled($project->published_url) => ['online', __('Online'), 'bg-emerald-500'],
        default => ['draft', __('Draft'), 'bg-zinc-400'],
    };
@endphp
{{--
    Espace de travail du projet, comme Claude Code, Lovable, Bolt.new ou Manus : une barre fine en haut ; sur un
    téléphone, trois vues (Discussion, Aperçu, Code) ; sur un ordinateur, la discussion à gauche, l'aperçu ou le code
    à droite. Le travail de Kekeli s'affiche dans la discussion, en étapes cochées qu'on peut déplier.
--}}
<div class="flex h-svh min-w-0 flex-col" x-data="{ vue: @js($view) }" @if ($this->activeRun || $project->previewIsWaking() || $project->publish_status === Project::PUBLISH_IN_PROGRESS) wire:poll.2s @elseif ($project->hasLivePreview()) wire:poll.60s @endif>
    {{-- Barre fine : retour, nom du site, petit état, « Mettre en ligne », menu « ⋯ ». --}}
    <header class="flex h-14 shrink-0 items-center gap-1 border-b border-zinc-200 px-2 sm:gap-2 sm:px-3 dark:border-zinc-700" data-project-bar>
        {{-- Sur un téléphone, le menu ☰ ouvre la même navigation que partout ailleurs. --}}
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" :aria-label="__('Open the menu')" data-nav-toggle />
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-[15px] font-semibold leading-tight text-zinc-900 dark:text-white" data-project-name>{{ $project->name }}</h1>
            {{-- L'état (brouillon, en ligne) ne concerne que la mise en ligne : rien sous le titre d'un document ou d'une image. --}}
            @unless ($project->isDocument())
                <p class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-project-state="{{ $state[0] }}">
                    <span class="size-1.5 rounded-full {{ $state[2] }}"></span>{{ $state[1] }}
                </p>
            @endunless
        </div>

        @if (filled($project->repository) && ! $project->isChat())
            {{-- Pastille GitHub, comme Claude Code : le dépôt relié et la dernière pull request (icône seule sur un téléphone). --}}
            <div class="flex shrink-0 items-center gap-1 rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" data-github-badge>
                <a href="https://github.com/{{ $project->repository }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white" title="github.com/{{ $project->repository }}" aria-label="{{ __('Repository :name on GitHub', ['name' => $project->repository]) }}">
                    <flux:icon.github class="size-3.5" />
                    <span class="max-w-40 truncate max-sm:hidden">{{ $project->repository }}</span>
                </a>
                @if ($project->pr_url)
                    <a href="{{ $project->pr_url }}" target="_blank" rel="noopener noreferrer" class="tabular-nums underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900 dark:decoration-zinc-600 dark:hover:text-white" title="{{ __('Pull request :number', ['number' => $project->pr_number]) }}" data-github-badge-pr>#{{ $project->pr_number }}</a>
                @endif
            </div>
        @endif

        @if ($this->isOwner)
            {{-- Document, présentation ou image : un seul « Télécharger », celui du panneau du fichier (onglet du fichier sur un téléphone). --}}
            {{-- Une discussion n'a pas de site : ni « Mettre en ligne », ni aperçu, ni code. --}}
            @if ($this->projectVersions->isNotEmpty())
                {{-- « Versions » (icône horloge, comme pour les documents) : la liste, de la plus récente à la plus ancienne. --}}
                <flux:modal.trigger name="versions">
                    <flux:button size="sm" variant="ghost" square icon="clock" class="sm:hidden" :aria-label="__('Versions')" :title="__('Versions')" data-versions-button="icon" />
                    <flux:button size="sm" variant="ghost" icon="clock" class="max-sm:hidden" data-versions-button>{{ __('Versions') }}</flux:button>
                </flux:modal.trigger>
            @endif
            @unless ($project->isChat() || $project->isDocument())
            <flux:modal.trigger name="publish">
                @if ($project->isRepository())
                    <flux:button size="sm" variant="primary" icon="arrow-up-tray" data-publish-open>GitHub</flux:button>
                @elseif ($project->isWebApp())
                    {{-- Kekeli n'héberge pas les sites avec base de données : le bouton ne promet pas de publication. --}}
                    {{-- Sur un téléphone : l'icône seule (avec son libellé accessible), pour laisser la place au titre ; le bouton complet sur un ordinateur. --}}
                    <flux:button size="sm" variant="primary" square icon="arrow-down-tray" class="sm:hidden" :aria-label="__('Get the code')" :title="__('Get the code')" data-publish-open="icon" />
                    <flux:button size="sm" variant="primary" icon="arrow-down-tray" class="max-sm:hidden" data-publish-open>{{ __('Get the code') }}</flux:button>
                @else
                    <flux:button size="sm" variant="primary" icon="rocket-launch" data-publish-open>{{ __('Put online') }}</flux:button>
                @endif
            </flux:modal.trigger>
            @endunless

            <flux:dropdown position="bottom" align="end">
                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More')" data-project-more />
                <flux:menu>
                    <flux:menu.item icon="pencil-square" wire:click="startRenaming" data-rename>{{ __('Rename') }}</flux:menu.item>
                    @unless ($project->isChat() || $project->isDocument())
                        <flux:modal.trigger name="secrets">
                            <flux:menu.item icon="key" data-open-secrets>{{ __('Secret variables') }}</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="allowed-sites">
                            <flux:menu.item icon="globe-alt" data-open-sites>{{ __('Internet access of Kekeli') }}</flux:menu.item>
                        </flux:modal.trigger>
                    @endunless
                    <flux:menu.separator />
                    {{-- L'énergie, discrète, comme les crédits dans le menu du projet chez Lovable. --}}
                    <flux:menu.item icon="sun" :href="route('plans')" wire:navigate data-energy="{{ $this->energy['percent'] }}">{{ __('Energy: :percent %', ['percent' => $this->energy['percent']]) }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </header>

    @if ($project->viewing_version !== null && ($viewed = $this->projectVersions->firstWhere('number', $project->viewing_version)))
        {{-- Une ancienne version dans l'aperçu : un bandeau clair, rien d'autre n'a changé. --}}
        <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100" data-viewing-version="{{ $viewed->number }}" @if ($project->version_busy_at && $project->version_busy_at->gt(now()->subMinutes(3))) wire:poll.3s @endif>
            <flux:icon.clock variant="micro" class="shrink-0" />
            <p class="min-w-0 flex-1 basis-56">
                <span class="font-medium">{{ __('You are looking at version :number', ['number' => $viewed->number]) }}</span>
                <span class="text-amber-900/80 dark:text-amber-100/80">· {{ $viewed->title }}</span>
                @if ($project->version_busy_at && $project->version_busy_at->gt(now()->subMinutes(3)))
                    <span class="ms-1 inline-flex items-center gap-1 text-xs" data-version-busy><flux:icon.loading class="size-3.5" /> {{ __('Kekeli is preparing the preview…') }}</span>
                @endif
            </p>
            @if ($this->isOwner)
                <div class="flex shrink-0 flex-wrap gap-1.5">
                    <flux:button size="xs" variant="ghost" wire:click="backToCurrent" data-back-to-current>{{ __('Back to the current version') }}</flux:button>
                    <flux:button size="xs" variant="primary" icon="arrow-uturn-left" wire:click="askRestore({{ $viewed->number }})" data-banner-restore>{{ __('Restore this version') }}</flux:button>
                </div>
            @endif
        </div>
    @elseif ($project->version_busy_at && $project->version_busy_at->gt(now()->subMinutes(3)))
        <p class="flex shrink-0 items-center gap-2 border-b border-zinc-200 px-4 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400" wire:poll.3s data-version-busy>
            <flux:icon.loading class="size-3.5" /> {{ __('Kekeli is putting the version back in the preview…') }}
        </p>
    @endif

    @unless ($this->isOwner)
        <p class="shrink-0 border-b border-sky-200 bg-sky-50 px-4 py-2 text-xs text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200" data-admin-view>
            {{ __('You are looking at this project as an administrator (read only). It belongs to :owner.', ['owner' => $project->user->email]) }}
        </p>
    @endunless

    {{-- Sur un téléphone : trois vues, juste sous la barre (une discussion n'a que la discussion). --}}
    @unless ($project->isChat())
    <nav @class(['grid shrink-0 gap-1 border-b border-zinc-200 p-1.5 lg:hidden dark:border-zinc-700', 'grid-cols-3' => count($this->tabs) === 2, 'grid-cols-2' => count($this->tabs) === 1]) role="tablist" aria-label="{{ __('Project views') }}" wire:ignore>
        @foreach (['discussion' => ['chat-bubble-left-right', __('Discussion')]] + $this->tabs as $key => [$icon, $label])
            <button type="button" role="tab" data-tab="{{ $key }}"
                x-on:click="vue = '{{ $key }}'@if ($key === 'code'); $wire.loadCode()@endif" x-bind:aria-selected="vue === '{{ $key }}'"
                x-bind:class="vue === '{{ $key }}' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium">
                <flux:icon :name="$icon" variant="micro" />
                {{ $label }}
            </button>
        @endforeach
    </nav>
    @endunless

    <div @class(['flex min-h-0 flex-1', 'lg:grid lg:grid-cols-[minmax(22rem,2fr)_3fr]' => ! $project->isChat()]) data-project-kind="{{ $project->kind }}">
        {{-- Discussion : les messages, le travail de Kekeli, puis la zone de saisie collée en bas. --}}
        <section id="panneau-discussion" role="tabpanel" class="flex min-h-0 min-w-0 flex-1 flex-col lg:border-r lg:border-zinc-200 dark:lg:border-zinc-700" x-bind:class="vue === 'discussion' ? '' : 'max-lg:hidden'" wire:ignore.self data-panel="discussion">
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
                                @foreach ($message->attachments as $file)
                                    {{-- Un fichier joint : son nom et son format, comme chez Claude (jamais ouvert sur le site). --}}
                                    <div wire:key="fichier-message-{{ $file->id }}" class="flex max-w-[85%] items-center gap-2.5 rounded-2xl border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900" data-message-file>
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800"><flux:icon.document-text variant="mini" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm text-zinc-900 dark:text-zinc-100">{{ $file->name }}</span>
                                            <span class="block text-xs text-zinc-500">{{ $file->label() }}</span>
                                        </span>
                                    </div>
                                @endforeach
                                <div class="max-w-[85%] whitespace-pre-line break-words rounded-3xl rounded-br-lg bg-zinc-100 px-4 py-2.5 text-[0.95rem] leading-relaxed text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ $message->content }}</div>
                            </div>
                        @else
                            <div wire:key="message-{{ $message->id }}" class="flex items-start gap-2.5">
                                <x-app-logo-icon class="mt-0.5 size-6 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1 whitespace-pre-line break-words text-sm text-zinc-800 dark:text-zinc-100">{{ $message->content }}</div>
                            </div>
                        @endif

                        @php($run = $message->role === Message::ROLE_USER ? $this->runs->get($message->id) : null)

                        {{-- Le premier répondant qui a choisi un outil ne dit rien : le travail sur le site s'affiche à sa place. --}}
                        @if ($run && ! ($run->isQuestion() && filled(data_get($run->report, 'tool'))))
                            <div wire:key="run-{{ $run->id }}" class="flex items-start gap-2.5" data-run-status="{{ $run->status }}">
                                <x-app-logo-icon class="mt-0.5 size-6 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1 space-y-2 text-sm">
                                    {{-- L'attente : une petite ligne calme, avec la place dans la file. --}}
                                    @if ($run->status === AgentRun::STATUS_QUEUED || $run->status === AgentRun::STATUS_RUNNING)
                                        @php($sayingStep = $run->steps->filter(fn ($step) => $step->isNarration() || $step->kind === 'rendu')->last())
                                        @php($saying = $run->status === AgentRun::STATUS_RUNNING ? ($sayingStep?->kind === 'rendu' ? \App\Models\AgentStep::renderingText((int) $sayingStep->target) : \App\Agent\RunReport::plain($sayingStep?->target, 280)) : '')
                                        @php($workingText = $run->stop_requested_at && $run->stoppedByClient() ? __('Stopping…') : ($run->isLaunch() ? __('Kekeli is running your automation…') : ($run->isQuestion() ? (($usingConnector = $run->steps->where('kind', 'connecteur')->last()) ? __('Using :name…', ['name' => $usingConnector->target]) : ($run->steps->contains('kind', 'recherche_web') ? __('Searching on the Internet…') : __('Kekeli is thinking…'))) : ($run->status === AgentRun::STATUS_QUEUED ? __('Kekeli is getting ready…') : ($saying !== '' ? $saying : __('Kekeli is working…'))))))
                                        {{-- Une seule ligne d'état pendant une construction : dès qu'il y a des actions, c'est elle qu'on déplie (plus bas). --}}
                                        @php($workingFolded = ! $run->isQuestion() && $run->steps->reject->isInternal()->isNotEmpty())
                                        <p @class(['flex items-center gap-2 text-zinc-500 dark:text-zinc-400', 'hidden' => $workingFolded]) @unless ($workingFolded) data-run-working @endunless>
                                            <flux:icon.loading class="size-4" />
                                            @unless ($workingFolded){{ $workingText }}@endunless
                                            @if ($run->isQuestion() && ! $run->stop_requested_at)
                                                {{-- Comme Claude : un petit minuteur pendant la réflexion. --}}
                                                <span class="tabular-nums text-xs text-zinc-400" wire:ignore x-data="{ depart: {{ (int) (($run->started_at ?? $run->created_at)?->getTimestamp() ?? time()) }}, s: 0 }" x-init="const t = () => s = Math.max(0, Math.floor(Date.now() / 1000) - depart); t(); setInterval(t, 1000)" x-show="s > 0" x-text="s + ' s'" data-thinking-timer></span>
                                            @endif
                                        </p>
                                        @if ($run->status === AgentRun::STATUS_QUEUED && ! $run->isQuestion() && ($position = app(AgentPlaces::class)->position($run)))
                                            <p class="pl-6 text-xs text-zinc-500 dark:text-zinc-400" data-run-waiting-turn="{{ $position }}">{{ trans_choice('{1} Your request is next in line. It will start by itself: you lose nothing.|[2,*] Your request is waiting for its turn (position :count). It will start by itself: you lose nothing.', $position, ['count' => $position]) }}</p>
                                        @endif
                                    @elseif ($run->status === AgentRun::STATUS_WAITING)
                                        <p class="text-zinc-500 dark:text-zinc-400" data-run-waiting>
                                            <span>{{ __('Kekeli is in high demand right now. Your request is waiting and will resume automatically around :time.', ['time' => $run->resume_at?->timezone(config('kekeli.display_timezone'))->format('H:i')]) }}</span>
                                        </p>
                                    @elseif ($run->status === AgentRun::STATUS_STOPPED && $run->stoppedByClient())
                                        {{-- Le client a touché « Arrêter » : une ligne calme, sans alerte. --}}
                                        <p class="text-zinc-500 dark:text-zinc-400" data-run-stopped-by-client>
                                            {{ $run->isQuestion() ? __('You stopped Kekeli.') : (data_get($run->report, 'kept_version') ? __('You stopped the work.') : __('You stopped the work. What was already done is kept.')) }}
                                        </p>
                                    @elseif ($run->status === AgentRun::STATUS_STOPPED)
                                        {{-- Comme Claude Code : tout s'arrête, avec une seule ligne sobre. Le travail fait est sauvegardé ; « Continuer » le reprend. --}}
                                        @php($capStop = $run->stop_reason === AgentRun::STOP_MODEL_CAP)
                                        @if ($capStop ? $this->energy['cap'] : $this->energy['exhausted'])
                                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-zinc-500 dark:text-zinc-400" data-run-stopped="{{ $capStop ? 'cap' : 'energy' }}">
                                                <span data-stop-line>{{ $capStop ? __('Kekeli is in high demand · Resumes at :time', ['time' => $this->energy['cap_resumes_at']]) : $this->energy['line'] }}</span>
                                                @if (! $capStop && $this->isOwner)
                                                    <a href="{{ route('plans') }}" wire:navigate class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-see-plans>{{ __('See the formulas') }}</a>
                                                @endif
                                            </p>
                                        @else
                                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-zinc-500 dark:text-zinc-400" data-run-stopped="ready">
                                                <span>{{ $capStop ? __('Kekeli is ready again') : __('Energy recharged') }}</span>
                                                @if ($this->isOwner && ! $this->activeRun)
                                                    <flux:button variant="primary" size="xs" icon="play" wire:click="continueRun('{{ $run->id }}')" data-continue>{{ __('Continue') }}</flux:button>
                                                @endif
                                            </p>
                                        @endif
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
                                    {{-- Application et dépôt GitHub, comme Claude Code : chaque fichier lu ou modifié et chaque commande, avec son nom. --}}
                                    @php($details = $project->isApplication() || $project->isAgent() || $project->isRepository())
                                    @php($timeline = $run->steps->reject->isInternal()->reduce(function ($lines, $step) use ($details) {
                                        if ($step->isNarration() || $step->kind === 'rendu') {
                                            // Le rendu d'une vidéo : une seule ligne « Rendu… 60 % », mise à jour sur place.
                                            $text = $step->kind === 'rendu' ? \App\Models\AgentStep::renderingText((int) $step->target) : \App\Agent\RunReport::plain($step->target, 280);
                                            if ($text !== '' && ! collect($lines)->contains(fn ($line) => $line['type'] === 'phrase' && $line['text'] === $text)) {
                                                $lines[] = ['type' => 'phrase', 'text' => $text, 'count' => 1, 'icon' => 'check', 'tone' => 'normal'];
                                            }

                                            return $lines;
                                        }
                                        $last = $lines === [] ? null : $lines[count($lines) - 1];
                                        if ($details && filled($step->target) && isset(\App\Models\AgentStep::DETAILS[$step->kind])) {
                                            $lines[] = ['type' => 'action', 'text' => __(\App\Models\AgentStep::DETAILS[$step->kind]), 'detail' => \Illuminate\Support\Str::limit(\App\Models\AgentStep::cleanTarget($step->kind === 'commande' ? \App\Models\AgentStep::projectCommand((string) $step->target) : (string) $step->target), 90), 'count' => 1, 'icon' => $step->icon(), 'tone' => $step->tone()];

                                            return $lines;
                                        }
                                        if ($last && $last['type'] === 'action' && $last['text'] === $step->label()) {
                                            $lines[count($lines) - 1]['count']++;
                                        } else {
                                            $lines[] = ['type' => 'action', 'text' => $step->label(), 'count' => 1, 'icon' => $step->icon(), 'tone' => $step->tone()];
                                        }

                                        return $lines;
                                    }, []))
                                    @php($searches = $run->isQuestion() ? \App\Jobs\RespondToMessage::searchSummary($run) : null)
                                    @php($usedConnectors = $run->isQuestion() && ! $run->isActive() ? collect((array) data_get($run->report, 'connectors', [])) : collect())
                                    @if ($usedConnectors->isNotEmpty())
                                        {{-- Les connecteurs utilisés, repliés comme chez Claude : « A utilisé Notion · 2 actions » ; dépliés, chaque action. --}}
                                        @php($names = $usedConnectors->pluck('name')->filter()->unique()->values())
                                        <details class="group" data-connectors-used>
                                            <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-lg py-0.5 text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden" data-step>
                                                <flux:icon.puzzle-piece variant="micro" class="shrink-0" />
                                                <span data-connectors-summary>{{ trans_choice('{1} Used :names · :count action|[2,*] Used :names · :count actions', $usedConnectors->count(), ['names' => $names->implode(', '), 'count' => $usedConnectors->count()]) }}</span>
                                                <flux:icon.chevron-down variant="micro" class="shrink-0 transition group-open:rotate-180" />
                                            </summary>
                                            <ol class="mt-1.5 space-y-1 border-l border-zinc-200 pl-3 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                                @foreach ($usedConnectors as $used)
                                                    <li class="flex items-start gap-1.5" data-connector-used>
                                                        <flux:icon :name="($used['status'] ?? '') === 'erreur' ? 'x-circle' : ($used['writes'] ?? false ? 'pencil-square' : 'eye')" variant="micro" class="mt-0.5 size-3.5 shrink-0 text-zinc-400" />
                                                        <span class="min-w-0 break-words">{{ $used['name'] ?? '' }} · {{ $used['title'] ?? '' }}@if (($used['status'] ?? '') === 'erreur') <span class="text-zinc-400">· {{ __('Failed') }}</span>@endif</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </details>
                                    @endif
                                    @if ($searches && ! $run->isActive())
                                        {{-- Une recherche : « A cherché sur le Web · N sources », comme Claude ou ChatGPT ; dépliée, la recherche faite et les sites consultés. --}}
                                        <details class="group" data-web-search>
                                            <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-lg py-0.5 text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden" data-step>
                                                <flux:icon.globe-alt variant="micro" class="shrink-0" />
                                                <span data-search-summary>{{ $searches['sources'] > 0 ? trans_choice('{1} Searched the Internet · :count source|[2,*] Searched the Internet · :count sources', $searches['sources'], ['count' => $searches['sources']]) : __('Searched the Internet') }}</span>
                                                <flux:icon.chevron-down variant="micro" class="shrink-0 transition group-open:rotate-180" />
                                            </summary>
                                            <div class="mt-1.5 space-y-2 border-l border-zinc-200 pl-3 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                                @foreach ($searches['queries'] as $query)
                                                    <p class="flex items-start gap-1.5" data-search-query>
                                                        <flux:icon.magnifying-glass variant="micro" class="mt-0.5 size-3.5 shrink-0 text-zinc-400" />
                                                        <span class="min-w-0 break-words">{{ $query }}</span>
                                                    </p>
                                                @endforeach
                                                @if ($searches['sites'] !== [])
                                                    <div data-search-sites>
                                                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Sites consulted') }}</p>
                                                        <ul class="mt-1 flex flex-wrap gap-1">
                                                            @foreach ($searches['sites'] as $site)
                                                                <li class="rounded-full bg-zinc-100 px-2 py-0.5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $site }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        </details>
                                    @elseif ($searches)
                                        {{-- Pendant la recherche : seulement la ligne « Recherche sur le Web… » ci-dessus. --}}
                                    @elseif ($timeline !== [] && $usedConnectors->isEmpty() && ! ($run->isQuestion() && $run->steps->every(fn ($step) => $step->kind === 'connecteur')))
                                        @php($actionCount = $run->steps->reject->isNarration()->reject(fn ($step) => $step->kind === 'rendu')->reject->isInternal()->count() ?: count($timeline))
                                        <details class="group" data-narration>
                                            <summary class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-lg py-0.5 text-zinc-500 select-none hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden" data-step @if ($run->isActive()) data-run-working @endif>
                                                @if ($run->isActive())
                                                    {{-- Pendant le travail : la ligne d'état elle-même, qu'on déplie pour voir les actions. --}}
                                                    <flux:icon.loading class="size-4 shrink-0" />
                                                    <span data-actions-count="{{ $actionCount }}">{{ $workingText }}</span>
                                                @elseif ($fileLine = $this->fileActionLine($run))
                                                    {{-- Un fichier, comme Claude : ce qui a été fait, et ses formats ; dépliable comme avant. --}}
                                                    <span data-actions-count="{{ $actionCount }}" data-file-action>{{ $fileLine }}</span>
                                                @else
                                                    <span data-actions-count="{{ $actionCount }}">{{ trans_choice('{1} Kekeli did :count action|[2,*] Kekeli did :count actions', $actionCount, ['count' => $actionCount]) }}</span>
                                                @endif
                                                <flux:icon.chevron-down variant="micro" class="shrink-0 transition group-open:rotate-180" />
                                            </summary>
                                            <ol class="mt-1.5 space-y-1 border-l border-zinc-200 pl-3 text-xs text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                                                @foreach ($timeline as $line)
                                                    {{-- La phrase en cours est déjà la ligne d'état : elle n'est pas répétée dans la liste. --}}
                                                    @continue($run->isActive() && $line['type'] === 'phrase' && $line['text'] === $workingText)
                                                    <li wire:key="ligne-{{ $run->id }}-{{ $loop->index }}" class="flex items-start gap-1.5" @if ($line['type'] === 'phrase') data-phrase @endif>
                                                        <flux:icon :name="$line['type'] === 'phrase' ? 'check' : $line['icon']" variant="micro" @class(['mt-0.5 size-3.5 shrink-0', 'text-orange-700' => $line['tone'] === 'danger', 'text-emerald-500' => $line['type'] === 'phrase' || $line['tone'] === 'success', 'text-zinc-400' => $line['type'] === 'action' && $line['tone'] === 'normal']) />
                                                        <span class="min-w-0 break-words">{{ $line['text'] }}@if ($line['count'] > 1) ({{ $line['count'] }})@endif @if (filled($line['detail'] ?? null))<span class="text-zinc-400">·</span> <code class="break-all font-mono text-[0.7rem] text-zinc-700 dark:text-zinc-200" data-step-detail>{{ $line['detail'] }}</code>@endif</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </details>
                                    @elseif ($run->status === AgentRun::STATUS_COMPLETED && ! $run->report)
                                        <p class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><flux:icon.check-circle variant="micro" class="text-emerald-500" /> {{ __('Finished.') }}</p>
                                    @endif

                                    {{-- Le compte rendu final : le dernier message de Kekeli. --}}
                                    @if ($run->report)
                                        <x-run-report :run="$run" :last="$this->runs->last()?->id === $run->id" :owner="$this->isOwner" :busy="(bool) $this->activeRun" />
                                    @endif

                                    @if ($versionNumber = data_get($run->report, 'version'))
                                        {{-- Comme Lovable : la ligne du travail dit la version créée. --}}
                                        <p class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-version-created="{{ $versionNumber }}">
                                            <flux:icon.clock variant="micro" class="shrink-0" />
                                            <span>{{ __('Version :number created', ['number' => $versionNumber]) }}</span>
                                        </p>
                                    @elseif (($kept = data_get($run->report, 'kept_version')) && in_array($run->status, [AgentRun::STATUS_FAILED, AgentRun::STATUS_STOPPED], true))
                                        {{-- Un travail échoué ou arrêté : le projet reste sur sa dernière version qui fonctionnait. --}}
                                        <p class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-version-kept="{{ $kept }}">
                                            <flux:icon.shield-check variant="micro" class="shrink-0" />
                                            <span>{{ __('The project stayed on version :number, the last one that worked.', ['number' => $kept]) }}</span>
                                        </p>
                                    @endif

                                    @if ($approvalId = data_get($run->report, 'approval'))
                                        @include('projects.partials.connector-approval', ['action' => \App\Models\ConnectorAction::query()->where('project_id', $project->id)->find($approvalId)])
                                    @endif

                                    @if ($cardVersion = $this->versionsByRun->get($run->id))
                                        {{-- La carte du fichier, comme Claude : icône du type, nom, formats, « Ouvrir » et « Télécharger ». --}}
                                        @include('projects.partials.file-card', ['version' => $cardVersion])
                                    @endif

                                    @if ($run->status === AgentRun::STATUS_COMPLETED && ! $run->isQuestion() && $project->hasLivePreview())
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
                        {{-- Énergie épuisée, comme Claude Code : une ligne sobre et un lien discret, à la place de la zone de saisie. Si le dernier travail vient d'être arrêté pour cela, la ligne est déjà sous lui. --}}
                        @php($lastRun = $this->runs->last())
                        @unless ($lastRun && $lastRun->status === AgentRun::STATUS_STOPPED && ! $lastRun->stoppedByClient() && $lastRun->stop_reason !== AgentRun::STOP_MODEL_CAP)
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 px-1 py-2 text-sm text-zinc-500 dark:text-zinc-400" data-energy-exhausted="{{ $this->energy['free_cap'] ? 'free-cap' : ($this->energy['free'] ? 'free' : 'paid') }}">
                                <span data-stop-line>{{ $this->energy['line'] }}</span>
                                <a href="{{ route('plans') }}" wire:navigate class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-see-plans>{{ __('See the formulas') }}</a>
                            </p>
                        @endunless
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
                            @if ($this->databaseSuggestion)
                                <div class="rounded-xl bg-amber-50 p-3 text-sm dark:bg-amber-950" data-database-suggestion>
                                    <flux:heading size="sm">{{ __('This request needs a site with a database') }}</flux:heading>
                                    <flux:text class="mt-1 text-sm">{{ __('User accounts and an administrator area need a database. This project is a showcase site: Kekeli can create a new site with a database from your request (this site stays as it is).') }}</flux:text>
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <flux:button variant="primary" size="sm" icon="sparkles" wire:click="createDatabaseSite" data-create-database-site>{{ __('Create a site with a database') }}</flux:button>
                                        <flux:button variant="filled" size="sm" wire:click="sendAnyway" data-send-anyway>{{ __('Send to this site anyway') }}</flux:button>
                                    </div>
                                </div>
                            @endif
                            @if ($keyMasked)
                                {{-- Une clé collée dans la discussion : masquée, jamais réutilisée ; Kekeli propose les Variables secrètes. --}}
                                <div class="mb-2 flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" role="status" data-key-masked>
                                    <flux:icon.lock-closed variant="micro" class="shrink-0 text-zinc-400" />
                                    <span class="min-w-0 flex-1">{{ __('For your safety, Kekeli masked the key you pasted and will not use it. Put it in « Secret variables » instead: it is encrypted there.') }}</span>
                                    @unless ($project->isChat())
                                        <flux:modal.trigger name="secrets"><flux:button size="sm" variant="filled" icon="key">{{ __('Secret variables') }}</flux:button></flux:modal.trigger>
                                    @endunless
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('keyMasked', false)" :aria-label="__('Close')" />
                                </div>
                            @endif
                            <form wire:submit="send">
                                <x-prompt-box
                                    model="content"
                                    photos-model="uploads"
                                    :photos="$uploads"
                                    :files-model="$project->isChat() || $project->isDocument() ? 'attachments' : null"
                                    :files="$attachments"
                                    remove-file="removeAttachment"
                                    :placeholder="$project->isChat() ? __('A question or a request for Kekeli…') : __('Write to Kekeli…')"
                                    :label="__('Message')"
                                    submit="send"
                                    remove-photo="removeUpload"
                                    :rows="2"
                                    :disabled="(bool) $this->activeRun"
                                    :stop="$this->activeRun ? 'stop' : null"
                                    input-attribute="data-message-input"
                                >
                                    @if ($this->isOwner)
                                        <x-slot:menu>
                                            @php($connecteurs = app(Connectors::class))
                                            <x-connectors-menu :connectors="$connecteurs->connected(auth()->user())" :active="$connecteurs->activeIds(auth()->user(), $project)" :max="$connecteurs->maxActive(auth()->user())" />
                                        </x-slot:menu>
                                    @endif
                                </x-prompt-box>
                                @if ($connectorNotice)
                                    <p class="kekeli-erreur mt-2 text-sm" data-connector-notice>{{ $connectorNotice }}</p>
                                @endif
                                <flux:error name="content" />
                                <flux:error name="uploads" />
                                <flux:error name="uploads.*" />
                                <flux:error name="attachments" />
                                <flux:error name="attachments.*" />
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </section>

        {{-- À droite sur un ordinateur : l'aperçu ou le code (pas dans une discussion). --}}
        @unless ($project->isChat())
        <section class="flex min-h-0 flex-1 flex-col" x-bind:class="vue === 'discussion' ? 'max-lg:hidden' : ''" wire:ignore.self data-right-panel>
            <div class="hidden h-11 shrink-0 items-center gap-1 border-b border-zinc-200 px-3 lg:flex dark:border-zinc-700" role="tablist" wire:ignore>
                @foreach ($this->tabs as $key => [$icon, $label])
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
                @if ($project->isDocument())
                    {{-- Document ou présentation : les pages ou les diapositives, les versions et « Télécharger ». --}}
                    @include('projects.partials.document-preview')
                @else
                @if ($project->isWebApp() && $project->app_admin_email && $this->isOwner)
                    {{--
                        Compte d'essai pour se connecter dans l'aperçu : discret et dépliable, avec la même police et les
                        mêmes icônes que le reste (une clé à gauche, une flèche qui tourne à droite), jamais le triangle brut.
                    --}}
                    <details class="group shrink-0 rounded-xl border border-zinc-200 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-900" data-test-admin x-data="{ voir: false }">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2.5 text-zinc-700 select-none transition hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800 [&::-webkit-details-marker]:hidden" data-test-admin-toggle>
                            <flux:icon.key variant="micro" class="text-zinc-400 dark:text-zinc-500" />
                            <span class="flex-1 font-medium">{{ __('Test administrator account') }}</span>
                            <flux:icon.chevron-down variant="micro" class="text-zinc-400 transition-transform duration-200 group-open:rotate-180 dark:text-zinc-500" />
                        </summary>
                        <dl class="grid grid-cols-[auto_minmax(0,1fr)] items-baseline gap-x-3 gap-y-1.5 border-t border-zinc-200 px-3 py-2.5 dark:border-zinc-700" data-test-admin-details>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Email address') }}</dt>
                            <dd class="break-all text-zinc-900 dark:text-zinc-100">{{ $project->app_admin_email }}</dd>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Password') }}</dt>
                            <dd class="flex flex-wrap items-baseline gap-x-2 text-zinc-900 dark:text-zinc-100">
                                <span x-show="voir" x-cloak class="break-all" data-test-password>{{ $project->app_admin_password }}</span>
                                <span x-show="! voir" class="tracking-widest text-zinc-400" aria-hidden="true">••••••••</span>
                                <button type="button" class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900 dark:text-zinc-300 dark:decoration-zinc-600 dark:hover:text-white" x-show="! voir" x-on:click="voir = true">{{ __('Show') }}</button>
                            </dd>
                        </dl>
                    </details>
                @endif

                @if ($project->isAgent() && $this->isOwner)
                    {{--
                        Panneau « Tester l'agent », comme le panneau « Test » de Dify : l'agent répond pour de vrai dans
                        l'aperçu. Avec la clé du client (Variables secrètes), c'est son modèle ; sinon, un petit essai
                        gratuit par jour avec le modèle gratuit de Kekeli.
                    --}}
                    <div class="flex shrink-0 flex-wrap items-center gap-x-2 gap-y-1 px-1 text-xs text-zinc-500 dark:text-zinc-400" data-agent-trial="{{ $this->agentTrial['own_key'] ? 'own-key' : ($this->agentTrial['remaining'] > 0 ? 'free' : 'over') }}">
                        @if ($this->agentTrial['own_key'])
                            <flux:icon.key variant="micro" class="text-zinc-400" />
                            <span>{{ __('Your agent uses your own key (Secret variables).') }}</span>
                        @elseif ($this->agentTrial['remaining'] > 0)
                            <span>{{ trans_choice('{1} Free trial: :count message left today|[2,*] Free trial: :count messages left today', $this->agentTrial['remaining'], ['count' => $this->agentTrial['remaining']]) }}</span>
                            <flux:modal.trigger name="secrets"><button type="button" class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-add-key>{{ __('Add your key') }}</button></flux:modal.trigger>
                        @else
                            <span class="text-zinc-700 dark:text-zinc-200" data-trial-over>{{ __('The free trial of the day is over.') }}</span>
                            <flux:modal.trigger name="secrets"><button type="button" class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800 dark:decoration-zinc-600 dark:hover:text-zinc-100" data-add-key>{{ __('Add your key in the Secret variables to continue') }}</button></flux:modal.trigger>
                        @endif
                    </div>
                @endif

                @php($warning = (int) config('kekeli.preview_pause_warning'))
                @if ($project->isAutomation())
                    {{-- Automatisation : pas d'aperçu, mais « Lancer maintenant » et le journal du dernier lancement. --}}
                    @include('projects.partials.launch')
                @elseif ($this->previewUrl && ! $project->previewIsPausing())
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
                @endif
            </div>

            {{-- Code : les fichiers du site en lecture seule, le fichier ZIP et GitHub. --}}
            @unless ($project->isDocument())
            <div id="panneau-code" role="tabpanel" class="min-h-0 flex-1 overflow-y-auto" x-bind:class="vue === 'code' ? '' : 'hidden'" x-cloak wire:ignore.self data-panel="code">
                @include('projects.partials.code')
            </div>
            @endunless
        </section>
        @endunless
    </div>

    <x-run-changes-modal :run="$this->changesRun" />

    @if ($this->isOwner)
        @unless ($project->isChat() || $project->isDocument())
            @include('projects.partials.publish')
        @endunless

        <flux:modal name="rename-project" class="w-full max-w-md">
            <form wire:submit="renameProject" class="space-y-5">
                <flux:heading size="lg">{{ $project->isChat() ? __('Rename the discussion') : __('Rename the site') }}</flux:heading>
                <flux:input wire:model="newName" :label="$project->isChat() ? __('Name of the discussion') : __('Name of the site')" maxlength="100" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        @include('projects.partials.allowed-sites')
        @include('projects.partials.versions')
        @include('projects.partials.secrets')
    @endif
</div>
