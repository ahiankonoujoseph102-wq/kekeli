<?php

namespace App\Jobs;

use App\Knowledge\KnowledgeException;
use App\Knowledge\KnowledgeIndexer;
use App\Models\KnowledgeDocument;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes a document of the knowledge base in the queue (en attente → traitement → prêt, ou erreur). A Runner that
 * cannot be reached is tried again (three times in all); a file that cannot be used stops at once with its reason.
 */
class IndexKnowledgeDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public KnowledgeDocument $document) {}

    public function uniqueId(): string
    {
        return (string) $this->document->id;
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        $document = $this->document->fresh();
        if (! $document || ! in_array($document->status, [KnowledgeDocument::PENDING, KnowledgeDocument::PROCESSING], true)) {
            return;
        }
        try {
            $indexer->index($document);
        } catch (KnowledgeException $exception) {
            $document->chunks()->delete();
            $document->forceFill(['status' => KnowledgeDocument::FAILED, 'error' => $exception->reason, 'chunks_count' => 0])->save();
        } catch (\Throwable $exception) {
            // Encore des essais : le document reste « en attente » ; au dernier, il passe en erreur (failed()).
            $document->forceFill(['status' => KnowledgeDocument::PENDING])->save();
            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->document->fresh()?->forceFill(['status' => KnowledgeDocument::FAILED, 'error' => 'indisponible'])->save();
    }
}
