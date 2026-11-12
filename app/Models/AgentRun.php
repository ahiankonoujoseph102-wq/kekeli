<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One request sent to the agent, from the user's message to the final answer.
 */
class AgentRun extends Model
{
    use HasUlids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Work stopped on the way because the user's energy ran out; « Continuer » resumes it. */
    public const STATUS_STOPPED = 'stopped';

    /** Reason of a stop asked by the client with the « Arrêter » button (the other reasons are about the energy). */
    public const STOP_CLIENT = 'client';

    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_WAITING];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'status',
        'provider',
        'model',
        'resume_at',
        'error',
        'started_at',
        'finished_at',
        'computer_seconds',
        'computer_cost_usd',
        'stop_requested_at',
        'stop_reason',
        'instructions',
        'purpose',
    ];

    /** Request started by Kekeli to fix a failed automatic check of GitHub. */
    public const PURPOSE_CI_FIX = 'ci_fix';

    /** A question of the client: Kekeli answers in the discussion, without a computer and without changing the site. */
    public const PURPOSE_QUESTION = 'question';

    public function isQuestion(): bool
    {
        return $this->purpose === self::PURPOSE_QUESTION;
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_QUEUED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resume_at' => 'datetime',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'finished_at' => 'datetime',
            'model_cost_usd' => 'float',
            'computer_cost_usd' => 'float',
            'image_cost_usd' => 'float',
            'web_search_cost_usd' => 'float',
            'stop_requested_at' => 'datetime',
            'images_created' => 'integer',
            'report' => 'array',
            'changes' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AgentPermission::class)->orderBy('id');
    }

    /**
     * Whether the client stopped this request with the « Arrêter » button (asked, or already stopped).
     */
    public function stoppedByClient(): bool
    {
        return $this->stop_reason === self::STOP_CLIENT;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
