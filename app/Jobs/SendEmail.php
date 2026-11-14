<?php

namespace App\Jobs;

use App\Mail\KekeliMail;
use App\Models\EmailLog;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends one e-mail of Kekeli from the queue. The free offer of Brevo is shared with other sites: Kekeli stops at its
 * own ceiling of the day (Lomé time, Administration → Réglages). Beyond it, a non urgent e-mail waits for the next
 * day; a login code, a password link and a receipt always go. A failure is tried again (4 tries in all).
 */
class SendEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $logId, public string $to, public KekeliMail $mail) {}

    public function handle(): void
    {
        $log = EmailLog::find($this->logId);
        if (! $log || $log->status === EmailLog::SENT) {
            return;
        }

        if (! $log->priority && self::sentToday() >= app(Settings::class)->int('email_daily_cap')) {
            $tomorrow = CarbonImmutable::now(config('kekeli.display_timezone'))->addDay()->startOfDay()->addMinutes(10)->utc();
            $log->update(['status' => EmailLog::DEFERRED, 'deferred_until' => $tomorrow]);
            // Une vraie file d'attente le reprend demain ; une file synchrone (essais) le laisse en attente.
            if ($this->job?->getConnectionName() !== 'sync' && config('queue.default') !== 'sync') {
                self::dispatch($this->logId, $this->to, $this->mail)->delay($tomorrow);
            }

            return;
        }

        $log->increment('attempts');
        try {
            Mail::to($this->to)->send($this->mail);
        } catch (\Throwable $exception) {
            // Jamais l'adresse ni un réglage dans le journal : seulement le genre de l'erreur.
            $log->update(['error' => Str::limit(class_basename($exception).' : '.preg_replace('/\S+@\S+/', '[adresse]', $exception->getMessage()), 280)]);
            throw $exception;
        }
        $log->update(['status' => EmailLog::SENT, 'sent_at' => now(), 'error' => null, 'deferred_until' => null]);
    }

    public function failed(?\Throwable $exception): void
    {
        EmailLog::query()->whereKey($this->logId)->update(['status' => EmailLog::FAILED]);
    }

    /** E-mails really sent today (Lomé time), all types together. */
    public static function sentToday(): int
    {
        $start = CarbonImmutable::now(config('kekeli.display_timezone'))->startOfDay()->utc();

        return EmailLog::query()->where('status', EmailLog::SENT)->where('sent_at', '>=', $start)->count();
    }
}
