<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A space of the knowledge base of a client (« Entreprise », « Formation », « Comptabilité »…): its own documents and
 * its own conversations. Only its owner sees it (always reached through Auth::user()->knowledgeSpaces()).
 */
class KnowledgeSpace extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'description'];

    /** Names suggested when the client creates his first space. */
    public const SUGGESTIONS = ['Entreprise', 'Formation', 'Comptabilité', 'Juridique', 'Documentation personnelle'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<KnowledgeDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /** @return HasMany<KnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    /** The conversations of the space (discussions linked to it). */
    public function conversations(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
