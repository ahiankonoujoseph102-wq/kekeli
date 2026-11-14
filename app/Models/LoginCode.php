<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A login code sent by e-mail: only the fingerprints of the code and of the link are kept; valid 10 minutes, used
 * once, a few tries at most.
 */
class LoginCode extends Model
{
    protected $fillable = ['email', 'code_hash', 'token_hash', 'attempts', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture() && $this->attempts < (int) config('kekeli.login_codes.attempts');
    }
}
