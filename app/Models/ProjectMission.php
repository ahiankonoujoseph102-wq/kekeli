<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mode équipe: one mission of a request (its role, its acceptance criteria, its files), with its result, the facts it
 * shared with the next missions, and its measures (requests, tokens, duration).
 */
class ProjectMission extends Model
{
    /** States of a mission: waiting, in progress, verified by Kekeli, done without check, failed, stopped by the guard. */
    public const STATUSES = ['en_attente', 'en_cours', 'verifiee', 'faite', 'echouee', 'delai', 'arretee'];

    public const ROLES = ['base-de-donnees', 'backend', 'frontend', 'testeur'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'files' => 'array',
            'depends_on' => 'array',
            'facts' => 'array',
            'position' => 'integer',
            'seconds' => 'integer',
            'requests' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /** The role as the administrator reads it. */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'base-de-donnees' => __('Database'),
            'backend' => __('Server'),
            'frontend' => __('Pages'),
            default => __('Tests'),
        };
    }
}
