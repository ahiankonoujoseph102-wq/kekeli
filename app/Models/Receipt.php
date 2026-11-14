<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A receipt of a subscription (KEK-2026-000123): its information is fixed at the time of the payment and never
 * changed afterwards. In case of error, a cancellation receipt points to it. Kept for the accounts even when the
 * account is deleted (the client's name and e-mail are part of it).
 */
class Receipt extends Model
{
    public const RECEIPT = 'receipt';

    public const CANCELLATION = 'cancellation';

    /** Fields set once after creation: the PDF and the date it was sent. Nothing else ever changes. */
    private const SET_ONCE = ['pdf_path', 'sent_at'];

    protected $fillable = [
        'number', 'year', 'sequence', 'kind', 'cancels_receipt_id', 'payment_id', 'user_id', 'client_name', 'client_email',
        'plan_name', 'paid_at', 'period_start', 'period_end', 'amount_fcfa', 'amount_usd', 'method', 'reference', 'reason', 'issuer',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'period_start' => 'datetime', 'period_end' => 'datetime', 'sent_at' => 'datetime', 'issuer' => 'array', 'amount_fcfa' => 'integer', 'amount_usd' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Receipt $receipt) {
            foreach (array_keys($receipt->getDirty()) as $field) {
                if (! in_array($field, [...self::SET_ONCE, 'updated_at'], true) || ($field === 'pdf_path' && filled($receipt->getOriginal('pdf_path')))) {
                    throw new LogicException('Un reçu n’est jamais modifié après sa création : créez un reçu d’annulation.');
                }
            }
        });
        static::deleting(fn () => throw new LogicException('Un reçu n’est jamais effacé : créez un reçu d’annulation.'));
    }

    public static function disk(): string
    {
        return (string) config('kekeli.backups.disk', 'local');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function cancels(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cancels_receipt_id');
    }

    public function cancellation(): ?self
    {
        return self::query()->where('cancels_receipt_id', $this->id)->first();
    }

    public function isCancellation(): bool
    {
        return $this->kind === self::CANCELLATION;
    }

    public function cancelledNumber(): string
    {
        return (string) $this->cancels?->number;
    }

    /** « 7 500 FCFA », or « 13 $ (7 500 FCFA payés par Mobile Money) » outside the FCFA zone. */
    public function amountText(): string
    {
        $fcfa = number_format(abs($this->amount_fcfa), 0, ',', ' ').' FCFA';
        $sign = $this->amount_fcfa < 0 ? '- ' : '';

        return $this->amount_usd !== null ? $sign.abs($this->amount_usd).' $ ('.$fcfa.' payés par Mobile Money)' : $sign.$fcfa;
    }
}
