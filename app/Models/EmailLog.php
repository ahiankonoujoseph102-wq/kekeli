<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One e-mail sent by Kekeli: its type, the masked recipient, success or failure. Never its content.
 */
class EmailLog extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DEFERRED = 'deferred';

    public const FAILED = 'failed';

    protected $fillable = ['type', 'user_id', 'recipient_masked', 'priority', 'status', 'attempts', 'error', 'sent_at', 'deferred_until'];

    protected function casts(): array
    {
        return ['priority' => 'boolean', 'sent_at' => 'datetime', 'deferred_until' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** « a•••@g•••.com » : enough to recognise an address, never the whole of it. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', mb_strtolower(trim($email)), 2), 2, '');
        $dot = strrpos($domain, '.');
        $name = $dot === false ? $domain : substr($domain, 0, $dot);
        $end = $dot === false ? '' : substr($domain, $dot);

        return mb_substr($local, 0, 1).'•••@'.mb_substr($name, 0, 1).'•••'.$end;
    }
}
