<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use App\Models\ProjectPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A document of the knowledge base, taken by the Runner to read it in an isolated computer: the address is signed
 * for 10 minutes (« signed ») and the request carries the signature of the Runner (VerifyRunnerSignature).
 */
class RunnerKnowledgeController extends Controller
{
    public function show(KnowledgeDocument $document): StreamedResponse
    {
        return Storage::disk(ProjectPhoto::disk())->download($document->path, 'document.'.$document->format);
    }
}
