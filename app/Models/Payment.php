<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A Mobile Money payment: the client sends the screenshot of the confirmation, the administrator checks it in the
 * history of the operator and validates it (the formula is then active for 30 days) or refuses it.
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REFUSED = 'refused';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'plan',
        'amount_fcfa',
        'country',
        'screenshot_path',
        'method',
        'reference',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_fcfa' => 'integer',
            'reviewed_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The receipt of the payment, and its cancellation receipt if there is one.
     *
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->orderBy('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public static function disk(): string
    {
        return (string) config('kekeli.backups.disk', 'local');
    }

    public function screenshotExists(): bool
    {
        return Storage::disk(self::disk())->exists($this->screenshot_path);
    }
}
