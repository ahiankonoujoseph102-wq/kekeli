<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kekeli Motion: an animation put in a site or an application of the client (the version chosen, and its name in the
 * site: « animations/<name>.js »). The Runner places the script in the project before each work.
 */
class ProjectAnimation extends Model
{
    protected $fillable = ['document_version_id', 'name'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
