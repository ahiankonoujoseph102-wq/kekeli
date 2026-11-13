<?php

namespace App\Agent\Tools;

use App\Models\AgentRun;
use Illuminate\Support\Str;

/**
 * Tool « modifier_document », in a document project: a new version from the client's request (« mets le titre en
 * bleu », « ajoute une diapositive sur les prix »). The previous versions stay available.
 */
class ModifyDocument
{
    /**
     * @param  array<string, string>  $arguments
     */
    public function handle(AgentRun $responder, array $arguments = []): ?AgentRun
    {
        $project = $responder->project;
        $message = $responder->message;
        if (! $project->isDocument() || ! $message) {
            return null;
        }

        $formats = array_values(array_intersect(array_map('trim', explode(',', Str::lower((string) ($arguments['formats'] ?? '')))), ['docx', 'pptx', 'png', 'pdf']));
        $attachment = $project->attachments()->where('message_id', $message->id)->latest('id')->first();

        return CreateDocument::start($project, $message, [
            'type' => $project->document_type ?: 'document',
            'action' => 'modifier',
            'formats' => $formats,
            'fichier' => $attachment?->id,
            'recherche' => Str::limit(trim((string) ($arguments['recherche'] ?? '')), 300, ''),
            // « petite » : une retouche (couleur, texte, prix, date, faute), faite par le modèle moins cher de la formule.
            'ampleur' => Str::lower(trim((string) ($arguments['ampleur'] ?? ''))) === 'petite' ? 'petite' : 'grande',
        ]);
    }
}
