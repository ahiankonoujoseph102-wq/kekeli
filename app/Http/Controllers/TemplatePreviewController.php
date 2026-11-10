<?php

namespace App\Http\Controllers;

use App\Templates\TemplateGallery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Files of a template's site, open to everyone, for the preview in the gallery.
 */
class TemplatePreviewController extends Controller
{
    private const TYPES = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'md' => 'text/plain; charset=utf-8',
    ];

    public function show(TemplateGallery $gallery, string $slug, string $path = 'index.html'): BinaryFileResponse
    {
        $file = $gallery->file($slug, $path) ?? abort(404);
        $type = self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? abort(404);

        return response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=3600']);
    }
}
