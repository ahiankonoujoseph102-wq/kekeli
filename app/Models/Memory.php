<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A memory of Kekeli about its client (name of the business, numbers, colors, style…): encrypted in the database,
 * owned by one client only, never shared, deleted with the account.
 */
class Memory extends Model
{
    /** The kinds of memories, as the Runner writes them. */
    public const CATEGORIES = ['identite', 'activite', 'coordonnees', 'paiement', 'produits', 'style', 'preference'];

    /** Longest memory, in characters. */
    public const MAX_LENGTH = 200;

    /** Most memories kept for one client. */
    public const MAX_COUNT = 80;

    protected $fillable = ['content', 'category', 'project_id'];

    protected function casts(): array
    {
        return ['content' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
