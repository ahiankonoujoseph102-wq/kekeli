<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Billing\Receipts;
use App\Mail\Emails;
use App\Models\Receipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Lays out the PDF of a receipt with the document engine (through the Runner, in the isolated workshop), keeps it,
 * then sends it to the client. Tried again when the Runner is not available.
 */
class GenerateReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 240;

    public function __construct(public int $receiptId, public bool $send = true) {}

    public function handle(RunnerClient $runner, Emails $emails): void
    {
        $receipt = Receipt::find($this->receiptId);
        if (! $receipt) {
            return;
        }

        if (! $receipt->pdf_path) {
            $result = $runner->receiptPdf(Receipts::structure($receipt));
            $pdf = base64_decode((string) ($result['pdf'] ?? ''), true);
            if (! $pdf || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('Reçu '.$receipt->number.' : PDF non reçu.');
            }
            $path = 'recus/'.$receipt->year.'/'.$receipt->number.'.pdf';
            Storage::disk(Receipt::disk())->put($path, $pdf);
            if ($image = base64_decode((string) ($result['apercu'] ?? ''), true)) {
                Storage::disk(Receipt::disk())->put('recus/'.$receipt->year.'/'.$receipt->number.'.jpg', $image);
            }
            $receipt->forceFill(['pdf_path' => $path])->save();
        }

        if ($this->send) {
            $emails->receipt($receipt);
            if (! $receipt->sent_at) {
                $receipt->forceFill(['sent_at' => now()])->save();
            }
        }
    }
}
