<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A « Variable secrète » of a project, like the environment variables of Claude Code: a key of a service chosen by the
 * client (database, payments…). The value is encrypted in the database, never shown again in full, and only sent to
 * the virtual computer of the project, where the application reads it from its environment.
 */
class ProjectSecret extends Model
{
    /** Name of a variable: capital letters, digits and « _ », like the environment variables of every hosting. */
    public const NAME_PATTERN = '/^[A-Z][A-Z0-9_]{1,63}$/';

    /** Names that belong to Kekeli or to the test database of the preview: never replaced by the client. */
    public const RESERVED = ['APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'ESSAI_ADMIN_EMAIL', 'ESSAI_ADMIN_MOT_DE_PASSE', 'PATH', 'HOME', 'NODE_OPTIONS', 'OPENCODE_SERVER_PASSWORD'];

    protected $fillable = ['name', 'value'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * What the client sees: never the value, only that it is set and its length class.
     */
    public function masked(): string
    {
        return str_repeat('•', 8);
    }
}
