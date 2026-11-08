<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The agent asks the user before a risky action (for example deleting files or sending data out).
 */
class AgentPermission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ALLOWED = 'allowed';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_EXPIRED = 'expired';

    /**
     * What the agent wants to do, by tool (translated in lang/fr.json).
     */
    public const TOOLS = [
        'bash' => 'Run a command',
        'edit' => 'Modify a file',
        'webfetch' => 'Open a web page',
        'external_directory' => 'Work outside the project folder',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'tool',
        'target',
        'status',
        'decided_by',
        'decided_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function toolLabel(): string
    {
        return __(self::TOOLS[$this->tool] ?? 'Use a tool');
    }
}
