<?php

namespace App\Knowledge;

use App\Agent\Energy;
use App\Agent\Timings;
use App\Jobs\IndexKnowledgeDocument;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSpace;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\User;
use App\Support\Availability;
use App\Support\SecretScanner;
use App\Support\Settings;
use App\WebApps\ChatTitles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The actions of the knowledge base: import or replace a document (never the same file twice), delete a document or
 * a space with everything in it, and open a conversation that answers from the documents.
 */
class KnowledgeSpaces
{
    public function __construct(private Settings $settings, private Energy $energy, private Availability $availability, private ChatTitles $titles) {}

    /**
     * Imports a file in a space. Returns the document, or the reason of a refusal (« double », « plein »).
     */
    public function import(KnowledgeSpace $space, UploadedFile $upload): KnowledgeDocument|string
    {
        $hash = (string) hash_file('sha256', $upload->getRealPath());
        if ($space->documents()->where('hash', $hash)->exists()) {
            return 'double';
        }
        if ($space->documents()->count() >= $this->settings->int('knowledge_documents_per_space')) {
            return 'plein';
        }

        $document = $space->documents()->create([
            'user_id' => $space->user_id,
            'name' => self::cleanName($upload),
            'format' => strtolower($upload->getClientOriginalExtension()),
            'size' => (int) $upload->getSize(),
            'path' => $this->store($space, $upload),
            'hash' => $hash,
            'status' => KnowledgeDocument::PENDING,
        ]);
        IndexKnowledgeDocument::dispatch($document);

        return $document;
    }

    /**
     * Replaces the file of a document (a new version of the price list…): its passages and their vectors are made again,
     * unless the file is the same.
     */
    public function replace(KnowledgeDocument $document, UploadedFile $upload): KnowledgeDocument|string
    {
        $hash = (string) hash_file('sha256', $upload->getRealPath());
        if ($hash === $document->hash) {
            return 'identique';
        }
        if (KnowledgeDocument::query()->where('knowledge_space_id', $document->knowledge_space_id)->where('id', '!=', $document->id)->where('hash', $hash)->exists()) {
            return 'double';
        }
        $old = $document->path;
        $document->chunks()->delete();
        $document->forceFill([
            'name' => self::cleanName($upload),
            'format' => strtolower($upload->getClientOriginalExtension()),
            'size' => (int) $upload->getSize(),
            'path' => $this->store($document->space, $upload),
            'hash' => $hash,
            'status' => KnowledgeDocument::PENDING,
            'error' => null,
            'chunks_count' => 0,
            'pages' => 0,
            'characters' => 0,
            'embedding_model' => null,
            'processed_at' => null,
        ])->save();
        if (! KnowledgeDocument::query()->where('path', $old)->exists()) {
            Storage::disk(ProjectPhoto::disk())->delete($old);
        }
        IndexKnowledgeDocument::dispatch($document);

        return $document;
    }

    /** Tries again a document stopped by an error. */
    public function retry(KnowledgeDocument $document): void
    {
        $document->forceFill(['status' => KnowledgeDocument::PENDING, 'error' => null])->save();
        IndexKnowledgeDocument::dispatch($document);
    }

    /** Deletes a space: its documents (files, passages, vectors) and its conversations. */
    public function deleteSpace(KnowledgeSpace $space): void
    {
        foreach ($space->documents()->get() as $document) {
            $document->deleteWithFile();
        }
        foreach ($space->conversations()->get() as $conversation) {
            $conversation->delete();
        }
        $space->delete();
    }

    /**
     * Opens a conversation in a space with the first question of the client, then Kekeli answers (word by word, like
     * any discussion). When Kekeli cannot answer yet, the question waits in the conversation: nothing is lost.
     *
     * @param  list<int>|null  $documentIds  the documents chosen (null: all the documents of the space)
     */
    public function startConversation(User $user, KnowledgeSpace $space, string $text, ?array $documentIds = null): Project
    {
        [$text] = SecretScanner::redactMessage(trim($text));
        $project = $user->projects()->make(['name' => $this->titles->guess($text), 'description' => null]);
        $project->forceFill(['kind' => Project::KIND_CHAT, 'knowledge_space_id' => $space->id, 'knowledge_documents' => $this->validIds($space, $documentIds)])->save();

        $canStart = $user->countryDetails() && ! $this->availability->comingSoon($user)
            && ! ($this->energy->isFree($user) && $this->energy->freeCapReached()) && ! $this->energy->isExhausted($user);
        if (! $canStart) {
            session()->flash('brouillon.'.$project->id, $text);

            return $project;
        }

        $run = DB::transaction(function () use ($project, $text, $user) {
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $text]);
            $this->energy->startWindowIfNeeded($user);

            return $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        });
        Timings::mark($run, 'recu');
        rescue(fn () => RespondToMessage::dispatch($run)->delay(now()->addSeconds(RespondToMessage::STREAM_FALLBACK_SECONDS)), report: true);

        return $project;
    }

    /**
     * Only documents of this space; null when all of them are chosen (a document added later is then included).
     *
     * @param  list<int>|null  $ids
     * @return list<int>|null
     */
    public function validIds(KnowledgeSpace $space, ?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }
        $all = $space->documents()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $kept = array_values(array_intersect($all, array_map('intval', $ids)));

        return $kept === [] || count($kept) === count($all) ? null : $kept;
    }

    /**
     * The content matches the extension: a PDF starts with « %PDF », a Word or a PowerPoint is a ZIP archive, a text
     * has no zero byte. A program renamed « .pdf » is refused before being kept.
     */
    public static function looksValid(UploadedFile $upload): bool
    {
        $format = strtolower($upload->getClientOriginalExtension());
        $start = (string) @file_get_contents($upload->getRealPath(), false, null, 0, 8192);

        return match (true) {
            $format === 'pdf' => str_starts_with($start, '%PDF'),
            in_array($format, ['docx', 'pptx'], true) => str_starts_with($start, "PK\x03\x04"),
            in_array($format, KnowledgeDocument::TEXT_FORMATS, true) => ! str_contains($start, "\0"),
            default => false,
        };
    }

    private function store(KnowledgeSpace $space, UploadedFile $upload): string
    {
        $format = strtolower($upload->getClientOriginalExtension());

        return (string) $upload->storeAs('connaissances/'.$space->id, Str::random(32).'.'.$format, ProjectPhoto::disk());
    }

    private static function cleanName(UploadedFile $upload): string
    {
        $name = preg_replace('/[^\pL\pN ._()\'-]/u', '', $upload->getClientOriginalName()) ?: 'document.'.strtolower($upload->getClientOriginalExtension());

        return Str::limit(trim($name), 150, '');
    }
}
