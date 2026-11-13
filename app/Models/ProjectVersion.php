<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A version of a site, an application, an agent or an automation, like Lovable and Bolt.new: the files of the project
 * after a successful work of Kekeli (or after a restoration), frozen in a cleaned archive (never .env, test database or
 * key). Restoring a version creates a new one: nothing is ever erased by a restoration.
 */
class ProjectVersion extends Model
{
    public const KIND_WORK = 'travail';

    public const KIND_RESTORE = 'restauration';

    protected $fillable = ['number', 'title', 'kind', 'agent_run_id', 'restored_from', 'size', 'files_count'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** « Version 5 · Ajout de la page Contact ». */
    public function label(): string
    {
        return __('Version :number', ['number' => $this->number]).' · '.$this->title;
    }

    public function isRestore(): bool
    {
        return $this->kind === self::KIND_RESTORE;
    }
}
