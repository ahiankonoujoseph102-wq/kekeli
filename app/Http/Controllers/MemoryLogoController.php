<?php

namespace App\Http\Controllers;

use App\Models\ProjectPhoto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The logo saved in the memory of the signed-in client (Réglages → Mémoire): only his own, never another client's.
 */
class MemoryLogoController extends Controller
{
    public function __invoke(): Response
    {
        $path = Auth::user()->memory_logo_path;
        abort_unless(filled($path) && Storage::disk(ProjectPhoto::disk())->exists($path), 404);

        return Storage::disk(ProjectPhoto::disk())->response($path, null, ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
