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
    ];

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
            'finished_at' => 'datetime',
            'model_cost_usd' => 'float',
            'computer_cost_usd' => 'float',
            'image_cost_usd' => 'float',
            'images_created' => 'integer',
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

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
