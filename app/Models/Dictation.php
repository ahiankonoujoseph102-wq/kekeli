<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voice dictation written by OpenAI (only when the browser cannot write it itself), counted in the energy.
 */
class Dictation extends Model
{
    protected $fillable = ['user_id', 'model', 'seconds', 'cost_usd'];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'seconds' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
