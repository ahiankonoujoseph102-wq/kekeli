<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A document imported in a space of the knowledge base: the file (kept on the disk of the backups), its state of
 * processing (en attente, traitement, prêt, erreur) and its passages.
 */
class KnowledgeDocument extends Model
{
    protected $fillable = ['name', 'format', 'size', 'path', 'hash', 'status', 'error', 'pages', 'chunks_count', 'characters', 'embedding_model', 'processed_at', 'user_id'];

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /** Read in the isolated computer of the Runner (never on the site nor on the Runner). */
    public const BINARY_FORMATS = ['pdf', 'docx', 'pptx'];

    /** Plain texts, read by the site itself (no program runs on them). */
    public const TEXT_FORMATS = ['txt', 'md', 'csv', 'json', 'html'];

    public const LABELS = ['pdf' => 'PDF', 'docx' => 'Word', 'pptx' => 'PowerPoint', 'txt' => 'Texte', 'md' => 'Markdown', 'csv' => 'CSV', 'json' => 'JSON', 'html' => 'HTML'];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'size' => 'integer',
            'pages' => 'integer',
            'chunks_count' => 'integer',
            'characters' => 'integer',
        ];
    }

    /** @return list<string> */
    public static function extensions(): array
    {
        return [...self::BINARY_FORMATS, ...self::TEXT_FORMATS];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSpace::class, 'knowledge_space_id');
    }

    /** @return HasMany<KnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::READY;
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    public function label(): string
    {
        return self::LABELS[$this->format] ?? strtoupper($this->format);
    }

    /** Deletes the document, its passages (and their vectors) and its file. */
    public function deleteWithFile(): void
    {
        $path = $this->path;
        $this->chunks()->delete();
        $this->delete();
        // Le même fichier peut servir à un autre document du client (même empreinte) : effacé seulement s'il ne sert plus.
        if (! self::query()->where('path', $path)->exists()) {
            Storage::disk(ProjectPhoto::disk())->delete($path);
        }
    }
}
