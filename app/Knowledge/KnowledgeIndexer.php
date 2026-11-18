<?php

namespace App\Knowledge;

use App\Agent\ProviderList;
use App\Agent\RunnerClient;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\ProjectPhoto;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The processing of an imported document: its text is read (by the site for a plain text, in the isolated computer
 * of the Runner for Word, PDF and PowerPoint), cut into passages, and each passage gets its vector (free offers).
 *
 * The same file is never processed twice: if the client already has it ready in another space, its passages and
 * vectors are copied, without any request to a model.
 */
class KnowledgeIndexer
{
    /** Passages sent at once to the Runner for their vectors. */
    public const BATCH = 100;

    public function __construct(private RunnerClient $runner, private Settings $settings, private ProviderList $providers) {}

    /**
     * @throws KnowledgeException when the document cannot be used (the reason is shown to the client)
     * @throws RuntimeException|ConnectionException when the Runner cannot be reached (try again)
     */
    public function index(KnowledgeDocument $document): void
    {
        $document->forceFill(['status' => KnowledgeDocument::PROCESSING, 'error' => null])->save();

        if ($this->copyFromTwin($document)) {
            return;
        }

        $units = $this->read($document);
        $characters = array_sum(array_map(fn ($unit) => mb_strlen($unit['text']), $units));
        if ($characters === 0) {
            throw new KnowledgeException('vide');
        }
        if ($characters > $this->settings->int('knowledge_characters_per_document')) {
            throw new KnowledgeException('trop_long');
        }

        $chunks = Chunker::split($units);
        $already = KnowledgeChunk::query()->where('knowledge_space_id', $document->knowledge_space_id)->where('knowledge_document_id', '!=', $document->id)->count();
        if ($already + count($chunks) > $this->settings->int('knowledge_chunks_per_space')) {
            throw new KnowledgeException('espace_plein');
        }

        // Les vecteurs, par lots. Sans fournisseur disponible, le document reste utilisable par la recherche par mots ;
        // le Runner injoignable fait réessayer la tâche plus tard.
        $vectors = [];
        foreach (array_chunk($chunks, self::BATCH) as $batch) {
            $result = $this->runner->knowledgeVectors(array_map(fn ($chunk) => $chunk['content'], $batch), 'document');
            $ok = ($result['ok'] ?? false) && count((array) ($result['vecteurs'] ?? [])) === count($batch);
            foreach ($batch as $index => $chunk) {
                $vectors[] = $ok ? ['model' => (string) $result['modele'], 'vector' => Vectors::pack((array) $result['vecteurs'][$index])] : ['model' => null, 'vector' => null];
            }
            if ($ok) {
                $this->count((string) $result['modele'], (int) ($result['requetes'] ?? 1));
            }
        }

        $models = array_values(array_unique(array_filter(array_column($vectors, 'model'))));
        DB::transaction(function () use ($document, $chunks, $vectors, $characters, $models) {
            $document->chunks()->delete();
            $now = now();
            foreach (array_chunk(array_keys($chunks), 200) as $keys) {
                foreach ($keys as $position) {
                    $document->chunks()->create([
                        'knowledge_space_id' => $document->knowledge_space_id,
                        'position' => $position,
                        'page' => $chunks[$position]['page'],
                        'content' => $chunks[$position]['content'],
                        'vector' => $vectors[$position]['vector'],
                        'embedding_model' => $vectors[$position]['model'],
                    ]);
                }
            }
            $document->forceFill([
                'status' => KnowledgeDocument::READY,
                'error' => null,
                'pages' => (int) max([0, ...array_map(fn ($chunk) => (int) $chunk['page'], $chunks)]),
                'chunks_count' => count($chunks),
                'characters' => $characters,
                'embedding_model' => $models[0] ?? null,
                'processed_at' => $now,
            ])->save();
        });
    }

    /**
     * The same file (same SHA-256 fingerprint), already ready for this client in another document: its passages and
     * vectors are copied, nothing is read again nor sent to a model.
     */
    private function copyFromTwin(KnowledgeDocument $document): bool
    {
        $twin = KnowledgeDocument::query()
            ->where('user_id', $document->user_id)
            ->where('hash', $document->hash)
            ->where('id', '!=', $document->id)
            ->where('status', KnowledgeDocument::READY)
            ->where('chunks_count', '>', 0)
            ->latest('processed_at')
            ->first();
        if (! $twin) {
            return false;
        }
        $already = KnowledgeChunk::query()->where('knowledge_space_id', $document->knowledge_space_id)->where('knowledge_document_id', '!=', $document->id)->count();
        if ($already + $twin->chunks_count > $this->settings->int('knowledge_chunks_per_space')) {
            throw new KnowledgeException('espace_plein');
        }

        DB::transaction(function () use ($document, $twin) {
            $document->chunks()->delete();
            foreach ($twin->chunks()->orderBy('position')->get() as $chunk) {
                $document->chunks()->create([
                    'knowledge_space_id' => $document->knowledge_space_id,
                    'position' => $chunk->position,
                    'page' => $chunk->page,
                    'content' => $chunk->content,
                    'vector' => $chunk->vector,
                    'embedding_model' => $chunk->embedding_model,
                ]);
            }
            $document->forceFill([
                'status' => KnowledgeDocument::READY,
                'error' => null,
                'pages' => $twin->pages,
                'chunks_count' => $twin->chunks_count,
                'characters' => $twin->characters,
                'embedding_model' => $twin->embedding_model,
                'processed_at' => now(),
            ])->save();
        });

        return true;
    }

    /** @return list<array{page: ?int, text: string, title?: bool}> */
    private function read(KnowledgeDocument $document): array
    {
        if (in_array($document->format, KnowledgeDocument::TEXT_FORMATS, true)) {
            $bytes = Storage::disk(ProjectPhoto::disk())->get($document->path);
            if (! is_string($bytes)) {
                throw new KnowledgeException('illisible');
            }

            return TextReader::read($bytes, $document->format);
        }

        $result = $this->runner->knowledgeExtract($document);
        if (! ($result['ok'] ?? false)) {
            // Un fichier abîmé ou protégé : inutile de réessayer. Sinon (Runner occupé), la tâche réessaie.
            if (in_array($result['erreur'] ?? null, ['illisible', 'format'], true)) {
                throw new KnowledgeException('illisible');
            }
            throw new RuntimeException('Lecture du document impossible pour le moment.');
        }

        return array_values(array_map(fn ($piece) => [
            'page' => isset($piece['page']) && (int) $piece['page'] > 0 ? (int) $piece['page'] : null,
            'text' => (string) ($piece['texte'] ?? ''),
            'title' => (bool) ($piece['titre'] ?? false),
        ], array_filter((array) ($result['morceaux'] ?? []), 'is_array')));
    }

    /** The requests to the free model of vectors, counted per provider (Administration → Fournisseurs). */
    public function count(string $model, int $requests): void
    {
        [$provider, $name] = array_pad(explode('/', $model, 2), 2, '');
        if ($provider === '' || $name === '') {
            return;
        }
        $attributes = ['day' => rescue(fn () => $this->providers->quotaDay($provider), CarbonImmutable::now('UTC')->toDateString(), report: false), 'provider' => $provider, 'model' => $name];
        DB::transaction(function () use ($attributes, $requests) {
            $usage = ProviderUsage::query()->where($attributes)->lockForUpdate()->first() ?? ProviderUsage::create($attributes);
            $usage->requests += max(1, $requests);
            $usage->save();
        });
    }
}
