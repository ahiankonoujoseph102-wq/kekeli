<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The screenshot of a payment, shown only to the administrator (the route is reserved to administrators).
 */
class PaymentScreenshotController extends Controller
{
    public function show(Payment $payment): Response
    {
        abort_unless($payment->screenshotExists(), 404);

        return Storage::disk(Payment::disk())->response($payment->screenshot_path, null, ['Cache-Control' => 'private, max-age=600']);
    }
}
