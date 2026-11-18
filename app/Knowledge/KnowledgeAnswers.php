<?php

namespace App\Knowledge;

use App\Models\AgentRun;
use App\Models\KnowledgeDocument;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * The answers of a conversation of the knowledge base (the « AG » of RAG): the passages found for the message go to
 * the model with the rules of the citations (runner/src/repondant.js, « blocBase »); the passages actually cited by
 * the answer become its sources, with the name of the document and the page.
 */
class KnowledgeAnswers
{
    /** Words under which a message is a follow-up (« et pour Kara ? »): the previous question helps the search. */
    private const SHORT_MESSAGE_WORDS = 7;

    public function __construct(private KnowledgeSearch $search) {}

    /**
     * The documents chosen for a conversation (all the documents of its space when none is chosen).
     *
     * @return list<int>|null
     */
    public static function chosen(Project $project): ?array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($project->knowledge_documents ?? [])), fn (int $id) => $id > 0));

        return $project->knowledge_documents === null || $ids === [] ? null : $ids;
    }

    /**
     * What the Runner needs for the message: the name of the space, the chosen documents and the passages, numbered.
     * The passages are kept on the request (agent_runs.knowledge), to show the sources after the answer.
     *
     * @return array{nom: string, documents: list<string>, passages: list<array{n: int, document: string, page: ?int, texte: string}>}
     */
    public function contextFor(AgentRun $run): array
    {
        $project = $run->project;
        $space = $project->knowledgeSpace;
        if (! $space) {
            return ['nom' => '', 'documents' => [], 'passages' => []];
        }
        $chosen = self::chosen($project);
        $documents = $space->documents()->where('status', KnowledgeDocument::READY)
            ->when($chosen !== null, fn ($q) => $q->whereIn('id', $chosen))
            ->orderBy('name')->pluck('name')->all();

        $found = $this->search->search($space, $chosen, $this->query($run))['passages'];
        // Les vidéos YouTube de l'espace : leur passage s'ouvre au bon moment.
        $videos = $space->documents()->where('source', KnowledgeDocument::SOURCE_YOUTUBE)->pluck('source_url', 'id');
        $passages = [];
        foreach ($found as $index => $passage) {
            $seconds = isset($videos[$passage['document_id']]) && $passage['seconds'] !== null ? (int) $passage['seconds'] : null;
            $passages[] = ['n' => $index + 1, 'chunk' => $passage['chunk'], 'document' => $passage['document'], 'document_id' => $passage['document_id'], 'page' => $passage['page'], 'seconds' => $seconds, 'video' => $seconds !== null ? $videos[$passage['document_id']].'&t='.$seconds.'s' : null, 'texte' => $passage['text']];
        }
        // Gardés sans le texte entier : un court extrait suffit pour la source affichée sous la réponse.
        $run->forceFill(['knowledge' => array_map(fn ($p) => ['n' => $p['n'], 'chunk' => $p['chunk'], 'document' => $p['document'], 'document_id' => $p['document_id'], 'page' => $p['page'], 'seconds' => $p['seconds'], 'video' => $p['video'], 'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $p['texte']) ?? '', 280)], $passages)])->save();

        return [
            'nom' => (string) $space->name,
            'documents' => array_values($documents),
            'passages' => array_map(fn ($p) => ['n' => $p['n'], 'document' => $p['document'], 'page' => $p['page'], 'moment' => $p['seconds'] !== null ? self::moment($p['seconds']) : null, 'texte' => $p['texte']], $passages),
        ];
    }

    /** « 3 min 20 s », « 1 h 02 min » : the moment of a video, as the client reads it. */
    public static function moment(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return match (true) {
            $hours > 0 => sprintf('%d h %02d min', $hours, $minutes),
            $minutes > 0 => $rest > 0 ? sprintf('%d min %d s', $minutes, $rest) : sprintf('%d min', $minutes),
            default => sprintf('%d s', $rest),
        };
    }

    /** The search text: the message, with the previous question of the client when the message is a short follow-up. */
    private function query(AgentRun $run): string
    {
        $message = trim((string) $run->message?->content);
        if (count(KnowledgeSearch::words($message)) >= self::SHORT_MESSAGE_WORDS || ! $run->message) {
            return $message;
        }
        $previous = $run->project->messages()->where('role', Message::ROLE_USER)->whereNull('hidden_at')
            ->where('id', '<', $run->message->id)->latest('id')->value('content');

        return trim(Str::limit((string) $previous, 500, '')."\n".$message);
    }

    /**
     * The sources of an answer: every passage sent, numbered like the references of the text, and whether the answer
     * cites it. A number the model invented (not in the list) is never shown.
     *
     * @param  array<int, mixed>|null  $passages
     * @return list<array{n: int, document: string, page: ?int, moment: ?string, video: ?string, excerpt: string, cited: bool}>
     */
    public static function sources(?array $passages, string $answer): array
    {
        $cited = [];
        if (preg_match_all('/\[(\d{1,2}(?:\s*[,;]\s*\d{1,2})*)\]/u', $answer, $matches)) {
            foreach ($matches[1] as $group) {
                foreach (preg_split('/\s*[,;]\s*/', $group) ?: [] as $number) {
                    $cited[(int) $number] = true;
                }
            }
        }

        return array_values(array_map(fn ($passage) => [
            'n' => (int) data_get($passage, 'n'),
            'document' => Str::limit((string) data_get($passage, 'document', ''), 160),
            'page' => data_get($passage, 'page') ? (int) data_get($passage, 'page') : null,
            'moment' => is_numeric(data_get($passage, 'seconds')) ? self::moment((int) data_get($passage, 'seconds')) : null,
            // Le lien d'une vidéo est reconstruit et vérifié : seulement YouTube, jamais une adresse écrite par le modèle.
            'video' => preg_match('#^https://www\.youtube\.com/watch\?v=[A-Za-z0-9_-]{11}&t=\d+s$#', (string) data_get($passage, 'video')) ? (string) data_get($passage, 'video') : null,
            'excerpt' => (string) data_get($passage, 'excerpt', ''),
            'cited' => isset($cited[(int) data_get($passage, 'n')]),
        ], array_filter((array) $passages, fn ($passage) => is_array($passage) && (int) data_get($passage, 'n') > 0)));
    }
}
