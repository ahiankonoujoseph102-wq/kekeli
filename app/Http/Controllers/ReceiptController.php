<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a receipt: the client only his own (« Mes reçus »), the administrator any of them.
 */
class ReceiptController extends Controller
{
    public function show(Receipt $receipt): Response
    {
        abort_unless($receipt->user_id === Auth::id(), 404);

        return $this->pdf($receipt);
    }

    public function admin(Receipt $receipt): Response
    {
        return $this->pdf($receipt);
    }

    private function pdf(Receipt $receipt): Response
    {
        abort_unless($receipt->pdf_path && Storage::disk(Receipt::disk())->exists($receipt->pdf_path), 404);

        return Storage::disk(Receipt::disk())->download($receipt->pdf_path, $receipt->number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
