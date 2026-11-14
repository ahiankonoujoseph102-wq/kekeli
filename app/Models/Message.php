<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'content',
        'agent_run_id',
    ];

    /**
     * What Kekeli remembered or forgot from this message (« Mémoire mise à jour »), encrypted like the memories.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['memory_updates' => 'encrypted:array'];
    }

    /**
     * Get the project the message belongs to.
     */
    /**
     * Photos joined to this message in the message field (the discussion shows them in the message).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class);
    }

    /**
     * @return HasMany<ProjectAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
