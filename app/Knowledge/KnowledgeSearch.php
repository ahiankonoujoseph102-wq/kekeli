<?php

namespace App\Knowledge;

use App\Agent\RunnerClient;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSpace;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The search in the documents of a space (the « R » of RAG), done by the site, which keeps the passages:
 *
 *  - by meaning: the vector of the question (made by the Runner with the same free model as the passages) compared
 *    with the vector of each passage;
 *  - by words (BM25): the exact words of the question (names, numbers, references), which a vector can miss;
 *  - both lists merged by their ranks (« reciprocal rank fusion »), a method that needs no tuning.
 *
 * If the vectors cannot be made (Runner asleep, free quotas used up), the search by words still answers (idea taken
 * from lastbook: the search works even without a model). Only the passages of the space and of the chosen documents
 * are read: never those of another client.
 */
class KnowledgeSearch
{
    /** Constant of the fusion by ranks (the usual value). */
    private const RRF = 60;

    /** Words too common to help a search. */
    private const STOP_WORDS = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'en', 'au', 'aux', 'a', 'est', 'sont', 'que', 'qui', 'quoi', 'quel', 'quelle', 'quels', 'quelles', 'dans', 'sur', 'pour', 'par', 'avec', 'ce', 'cet', 'cette', 'ces', 'il', 'elle', 'ils', 'elles', 'je', 'tu', 'nous', 'vous', 'on', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'nos', 'votre', 'vos', 'leur', 'leurs', 'se', 'ne', 'pas', 'plus', 'y', 'dit', 'dis', 'moi', 'comment', 'combien', 'pourquoi', 'quand', 'ou', 'est-ce', 'the', 'of', 'and', 'to', 'is', 'in', 'what', 'how', 'it', 'do', 'does', 'mes', 'document', 'documents'];

    public function __construct(private RunnerClient $runner, private Settings $settings) {}

    /**
     * The passages closest to the question, best first.
     *
     * @param  list<int>|null  $documentIds  the documents chosen for the conversation (null: all of them)
     * @return array{passages: list<array{chunk: int, document: string, document_id: int, page: ?int, text: string, score: float}>, mode: string}
     */
    public function search(KnowledgeSpace $space, ?array $documentIds, string $query, ?int $limit = null): array
    {
        $limit ??= max(1, min(12, $this->settings->int('knowledge_passages') ?: 8));
        $documents = $space->documents()->where('status', KnowledgeDocument::READY)
            ->when($documentIds !== null, fn ($q) => $q->whereIn('id', $documentIds))
            ->pluck('name', 'id');
        if ($documents->isEmpty() || trim($query) === '') {
            return ['passages' => [], 'mode' => 'aucun'];
        }

        /** @var Collection<int, KnowledgeChunk> $chunks */
        $chunks = KnowledgeChunk::query()
            ->where('knowledge_space_id', $space->id)
            ->whereIn('knowledge_document_id', $documents->keys())
            ->get(['id', 'knowledge_document_id', 'page', 'content', 'vector', 'embedding_model']);
        if ($chunks->isEmpty()) {
            return ['passages' => [], 'mode' => 'aucun'];
        }

        $semantic = $this->semanticScores($chunks, $query);
        $lexical = $this->lexicalScores($chunks, $query);

        $fused = [];
        foreach ([$semantic, $lexical] as $scores) {
            arsort($scores);
            $rank = 0;
            foreach ($scores as $id => $score) {
                if ($score <= 0) {
                    break;
                }
                $fused[$id] = ($fused[$id] ?? 0) + 1 / (self::RRF + ++$rank);
            }
        }
        arsort($fused);

        $byId = $chunks->keyBy('id');
        $passages = [];
        foreach (array_slice(array_keys($fused), 0, $limit) as $id) {
            $chunk = $byId[$id];
            $passages[] = [
                'chunk' => (int) $chunk->id,
                'document' => (string) $documents[$chunk->knowledge_document_id],
                'document_id' => (int) $chunk->knowledge_document_id,
                'page' => $chunk->page,
                'text' => (string) $chunk->content,
                'score' => round($fused[$id], 5),
            ];
        }

        return ['passages' => $passages, 'mode' => $semantic !== [] ? ($lexical !== [] ? 'hybride' : 'sens') : 'mots'];
    }

    /**
     * Similarity of meaning, model by model (the passages of a space may come from two free models).
     *
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @return array<int, float>
     */
    private function semanticScores($chunks, string $query): array
    {
        $scores = [];
        foreach ($chunks->whereNotNull('embedding_model')->groupBy('embedding_model') as $model => $group) {
            try {
                $result = $this->runner->knowledgeVectors([$query], 'question', [(string) $model]);
            } catch (\Throwable) {
                continue;
            }
            $vector = ($result['ok'] ?? false) && ($result['modele'] ?? null) === $model ? array_map('floatval', (array) ($result['vecteurs'][0] ?? [])) : [];
            if ($vector === []) {
                continue;
            }
            app(KnowledgeIndexer::class)->count((string) $model, 1);
            foreach ($group as $chunk) {
                $scores[$chunk->id] = Vectors::dot($vector, Vectors::unpack($chunk->vector));
            }
        }

        return $scores;
    }

    /**
     * Okapi BM25 on the words of the question, accents and case ignored.
     *
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @return array<int, float>
     */
    private function lexicalScores($chunks, string $query): array
    {
        $terms = array_values(array_unique(self::words($query)));
        if ($terms === []) {
            return [];
        }
        $documents = [];
        $lengths = [];
        $frequency = array_fill_keys($terms, 0);
        foreach ($chunks as $chunk) {
            $words = self::words((string) $chunk->content);
            $counts = array_count_values($words);
            $documents[$chunk->id] = $counts;
            $lengths[$chunk->id] = max(1, count($words));
            foreach ($terms as $term) {
                if (isset($counts[$term])) {
                    $frequency[$term]++;
                }
            }
        }
        $total = count($documents);
        $average = array_sum($lengths) / max(1, $total);
        $scores = [];
        foreach ($documents as $id => $counts) {
            $score = 0.0;
            foreach ($terms as $term) {
                $tf = $counts[$term] ?? 0;
                if ($tf === 0) {
                    continue;
                }
                $idf = log(1 + ($total - $frequency[$term] + 0.5) / ($frequency[$term] + 0.5));
                $score += $idf * ($tf * 2.2) / ($tf + 1.2 * (0.25 + 0.75 * $lengths[$id] / $average));
            }
            if ($score > 0) {
                $scores[$id] = $score;
            }
        }

        return $scores;
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        $ascii = Str::lower(Str::ascii($text));
        preg_match_all('/[a-z0-9]+/', $ascii, $matches);

        return array_values(array_filter($matches[0], fn (string $word) => (strlen($word) > 1 || ctype_digit($word)) && ! in_array($word, self::STOP_WORDS, true)));
    }
}
