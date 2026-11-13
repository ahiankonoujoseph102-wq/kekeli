<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUlids;

    public const STATUS_DRAFT = 'draft';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connectors' => 'array',
            'preview_expires_at' => 'datetime',
            'backup_at' => 'datetime',
            'preview_waking_at' => 'datetime',
            'computer_erased_at' => 'datetime',
            'published_at' => 'datetime',
            'github_exported_at' => 'datetime',
            'repository_state' => 'array',
            'pushed_at' => 'datetime',
            'pr_number' => 'integer',
            'ci_fix_attempts' => 'integer',
            // Mot de passe de l'administrateur d'essai : chiffré dans la base du site.
            'app_admin_password' => 'encrypted',
            'is_application' => 'boolean',
            'is_agent' => 'boolean',
            'is_automation' => 'boolean',
            'package_at' => 'datetime',
            'package_size' => 'integer',
        ];
    }

    public const PUBLISH_IN_PROGRESS = 'publishing';

    public const PUBLISH_DONE = 'published';

    public const PUBLISH_FAILED = 'failed';

    /** Kinds of project: a site built by Kekeli, an existing GitHub repository (developer mode), or a web application. */
    public const KIND_SITE = 'site';

    public const KIND_REPOSITORY = 'repository';

    public const KIND_WEBAPP = 'webapp';

    /** A discussion: a project without a site, for questions (it becomes a site when the client asks for one). */
    public const KIND_CHAT = 'chat';

    /** Technologies of a web application (same names as in the Runner, runner/src/applications/). */
    public const STACKS = ['laravel', 'nextjs', 'langgraph', 'crewai', 'automatisation'];

    /** States of the sending of the work to the client's repository, and of GitHub's automatic checks. */
    public const PUSH_IN_PROGRESS = 'pushing';

    public const PUSH_DONE = 'pushed';

    public const PUSH_NOTHING = 'nothing';

    public const PUSH_FAILED = 'failed';

    public const CI_PENDING = 'pending';

    public const CI_SUCCESS = 'success';

    public const CI_FAILURE = 'failure';

    public const CI_NONE = 'none';

    public function isRepository(): bool
    {
        return $this->kind === self::KIND_REPOSITORY;
    }

    /** A web application with a database (Laravel or Next.js), built by Kekeli. */
    public function isChat(): bool
    {
        return $this->kind === self::KIND_CHAT;
    }

    public function isWebApp(): bool
    {
        return $this->kind === self::KIND_WEBAPP;
    }

    /**
     * An installable web application (accounts, roles, database), created with the tool « creer_application ».
     */
    public function isApplication(): bool
    {
        return $this->isWebApp() && (bool) $this->is_application;
    }

    /**
     * An AI agent (LangGraph or CrewAI), created with the tool « creer_agent »: Kekeli writes its code, the client puts
     * their own model key in the « Variables secrètes » and hosts it where they want.
     */
    public function isAgent(): bool
    {
        return $this->isWebApp() && (bool) $this->is_agent;
    }

    /**
     * An automation (JavaScript and a scheduled GitHub Actions file), created with the tool « creer_automatisation »:
     * it runs on the client's GitHub repository, never at Kekeli; « Lancer maintenant » runs it once in the computer.
     */
    public function isAutomation(): bool
    {
        return $this->isWebApp() && (bool) $this->is_automation;
    }

    /**
     * Whether the client branches their own model (agent or automation): the guided choice of the provider is shown.
     */
    public function usesClientModel(): bool
    {
        return $this->isAgent() || $this->isAutomation();
    }

    /** A document (Word and PDF) or a presentation (PowerPoint and PDF), made with « creer_document » or « creer_presentation ». */
    public const KIND_DOCUMENT = 'document';

    public function isDocument(): bool
    {
        return $this->kind === self::KIND_DOCUMENT;
    }

    public function isPresentation(): bool
    {
        return $this->isDocument() && $this->document_type === 'presentation';
    }

    /** An image or a poster (« creer_image »): a document project whose files are PNG and PDF. */
    public function isImage(): bool
    {
        return $this->isDocument() && $this->document_type === 'image';
    }

    /** The types of project, in the order of the filters of « Récents », with their icon. */
    public const TYPES = [
        'site' => 'window',
        'application' => 'device-phone-mobile',
        'agent' => 'chat-bubble-oval-left-ellipsis',
        'automation' => 'arrow-path-rounded-square',
        'document' => 'document-text',
        'presentation' => 'presentation-chart-bar',
        'image' => 'photo',
        'chat' => 'chat-bubble-left-right',
    ];

    /**
     * The type of the project for the client: « site » (a site or a GitHub repository), « application », « agent »,
     * « automation », « document », « presentation », « image » or « chat ».
     */
    public function typeKey(): string
    {
        return match (true) {
            $this->isChat() => 'chat',
            $this->isImage() => 'image',
            $this->isPresentation() => 'presentation',
            $this->isDocument() => 'document',
            $this->isAutomation() => 'automation',
            $this->isAgent() => 'agent',
            $this->isApplication() => 'application',
            default => 'site',
        };
    }

    public function typeIcon(): string
    {
        return $this->isRepository() ? 'code-bracket' : self::TYPES[$this->typeKey()];
    }

    /**
     * Only the projects of one type (same rules as typeKey()).
     *
     * @param  Builder<Project>  $query
     */
    public function scopeOfType($query, string $type): void
    {
        $webapp = fn ($q, string $flag) => $q->where('kind', self::KIND_WEBAPP)->where($flag, true);
        match ($type) {
            'chat' => $query->where('kind', self::KIND_CHAT),
            'image', 'presentation' => $query->where('kind', self::KIND_DOCUMENT)->where('document_type', $type),
            'document' => $query->where('kind', self::KIND_DOCUMENT)->where(fn ($q) => $q->whereNull('document_type')->orWhereNotIn('document_type', ['image', 'presentation'])),
            'automation' => $webapp($query, 'is_automation'),
            'agent' => $webapp($query, 'is_agent')->where('is_automation', false),
            'application' => $webapp($query, 'is_application')->where('is_agent', false)->where('is_automation', false),
            default => $query->where(fn ($q) => $q->whereIn('kind', [self::KIND_SITE, self::KIND_REPOSITORY])
                ->orWhere(fn ($w) => $w->where('kind', self::KIND_WEBAPP)->where('is_application', false)->where('is_agent', false)->where('is_automation', false))),
        };
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProjectVersion::class)->orderBy('number');
    }

    /** Sites, applications, agents and automations have versions (never a discussion, a document or a repository). */
    public function hasVersions(): bool
    {
        return in_array($this->kind, [self::KIND_SITE, self::KIND_WEBAPP], true);
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('number');
    }

    /**
     * @return HasMany<ProjectAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    /**
     * @return HasMany<ProjectSecret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(ProjectSecret::class);
    }

    public function canWriteRepository(): bool
    {
        return $this->isRepository() && $this->repository_access === 'write';
    }

    /** How Kekeli sends its work to GitHub: a pull request on its own branch (default, like Claude Code), or the main branch. */
    public const PUSH_PULL_REQUEST = 'pull_request';

    public const PUSH_DIRECT = 'direct';

    /**
     * « Projet d'abord »: a project of Kekeli linked to a repository of the client (created by Kekeli).
     */
    public function isLinkedToGithub(): bool
    {
        return ! $this->isRepository() && ! $this->isChat() && filled($this->repository);
    }

    /** States of the sending of the code to GitHub. */
    public const GITHUB_IN_PROGRESS = 'sending';

    public const GITHUB_DONE = 'sent';

    public const GITHUB_FAILED = 'failed';

    /**
     * Name of the project at Cloudflare Pages (and so of its pages.dev address): simple letters from the
     * project's name, with the end of its identifier so that it is unique. It never changes once published.
     */
    public function pagesName(): string
    {
        $base = trim(substr(trim(Str::slug(Str::ascii($this->name)), '-'), 0, 30), '-');
        $suffix = substr(strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $this->id)), -6);

        return $this->pages_name ?: implode('-', array_filter([$base ?: 'site', $suffix]));
    }

    /**
     * Whether the live preview of the project can be shown right now.
     */
    public function hasLivePreview(): bool
    {
        return filled($this->preview_url) && $this->preview_expires_at?->isFuture() === true;
    }

    /**
     * The computer turns itself off soon: the preview warns instead of showing an error.
     */
    public function previewIsPausing(): bool
    {
        return $this->hasLivePreview()
            && $this->preview_expires_at->lte(now()->addSeconds((int) config('kekeli.preview_pause_warning', 90)));
    }

    /**
     * The user asked to turn the preview back on and the computer is waking up.
     */
    public function previewIsWaking(): bool
    {
        return $this->preview_waking_at !== null && $this->preview_waking_at->gt(now()->subMinutes(3));
    }

    /**
     * Get the sites added by the user (removed ones included, for the history).
     */
    public function sites(): HasMany
    {
        return $this->hasMany(ProjectSite::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Domains currently added by the user to the allowed sites.
     *
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return $this->sites()->whereNull('removed_at')->pluck('domain')->unique()->values()->all();
    }

    /**
     * Get the photos sent by the client for this project.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Get the user who owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages of the project's conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Get the requests sent to the agent for this project.
     */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class)->orderBy('created_at')->orderBy('id');
    }
}
