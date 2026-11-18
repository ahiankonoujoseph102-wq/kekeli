<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A passage of a document of the knowledge base: its text (encrypted, like the memories), its page when the document
 * has pages, and its vector (base 64 of 4-byte floats, App\Knowledge\Vectors).
 */
class KnowledgeChunk extends Model
{
    protected $fillable = ['knowledge_space_id', 'position', 'page', 'content', 'vector', 'embedding_model'];

    protected function casts(): array
    {
        return [
            'content' => 'encrypted',
            'position' => 'integer',
            'page' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
