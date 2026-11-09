<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
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
            'preview_expires_at' => 'datetime',
            'backup_at' => 'datetime',
            'preview_waking_at' => 'datetime',
            'computer_erased_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public const PUBLISH_IN_PROGRESS = 'publishing';

    public const PUBLISH_DONE = 'published';

    public const PUBLISH_FAILED = 'failed';

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
        return $this->hasMany(AgentRun::class)->orderBy('created_at');
    }
}
