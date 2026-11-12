<?php

namespace App\Agent\Tools;

use App\Jobs\GenerateDocument;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\WebApps\ChatTitles;
use Illuminate\Support\Str;

/**
 * Tool « creer_document », like Claude and ChatGPT: a real Word and PDF file (letter, CV, quote, invoice, report), or a
 * work on a file joined by the client (correct, summarize, translate, transform). A short and clear request is built
 * at once; a long document (report, thesis) first gets a short plan with « Construire ce plan ».
 */
class CreateDocument extends CreateApplication
{
    public const OFFER = 'construire_document';

    public const TYPE = 'document';

    public const ACTIONS = ['creer', 'corriger', 'resumer', 'traduire', 'transformer'];

    protected function defaultPlan(): string
    {
        return (string) __('Kekeli will write a document from your request.');
    }

    /**
     * @param  array<string, string>  $arguments
     */
    public function handle(AgentRun $responder, array $arguments = []): ?AgentRun
    {
        $project = $responder->project;
        $message = $responder->message;
        if (! $project->isChat() || ! $message) {
            return null;
        }

        $options = $this->options($project, $message, $arguments);
        $plan = self::cleanPlan((string) ($arguments['plan'] ?? ''));
        $accepted = Str::lower(trim((string) ($arguments['confirme'] ?? ''))) === 'oui';
        $offered = $this->lastOffer($project);

        // Le client accepte en écrivant le plan déjà proposé.
        if ($accepted && ($plan !== '' || $offered)) {
            return $this->build($project, $message, $plan !== '' ? $plan : (string) data_get($offered->report, 'plan'), 'auto', $offered ? (array) data_get($offered->report, 'document_options', $options) : $options);
        }

        // Gros document : d'abord un plan court, comme Claude ; petit document : tout de suite.
        if (static::isLong($arguments) && $plan !== '' && $options['action'] === 'creer') {
            $this->propose($responder, $plan, 'auto');
            $responder->refresh()->forceFill(['report' => array_merge((array) $responder->report, ['document_options' => $options])])->save();
            $this->nameFromPlan($project, $message, (string) ($arguments['nom'] ?? ''));

            return null;
        }

        $this->nameFromPlan($project, $message, (string) ($arguments['nom'] ?? ''));

        return $this->build($project, $message, $plan, 'auto', $options);
    }

    /**
     * The name of a document is the short name given by the model (« CV d'Esi Lawson »), never a company found in the
     * text (a CV names the former employers), and never over a title chosen by the client.
     */
    protected function nameFromPlan(Project $project, Message $message, string $proposed): void
    {
        $titles = app(ChatTitles::class);
        if (! $titles->isTemporary($project->fresh())) {
            return;
        }
        $name = $titles->fromModel($proposed);
        if (filled($name)) {
            $project->forceFill(['name' => $name])->save();
        }
    }

    /**
     * A report, a thesis or more than 4 pages; for a presentation, more than 10 slides.
     *
     * @param  array<string, string>  $arguments
     */
    public static function isLong(array $arguments): bool
    {
        return Str::lower(trim((string) ($arguments['taille'] ?? ''))) === 'long';
    }

    /**
     * What the Runner must do: the type, the action, the formats, the file joined, the search on the Internet.
     *
     * @param  array<string, string>  $arguments
     * @return array{type: string, action: string, formats: list<string>, fichier: ?int, recherche: string}
     */
    protected function options(Project $project, Message $message, array $arguments): array
    {
        $action = Str::lower(trim((string) ($arguments['action'] ?? 'creer')));
        $formats = array_values(array_intersect(array_map('trim', explode(',', Str::lower((string) ($arguments['formats'] ?? '')))), ['docx', 'pptx', 'pdf']));
        $attachment = $this->attachment($project, $message, (string) ($arguments['fichier'] ?? ''));

        return [
            'type' => static::TYPE,
            'action' => in_array($action, self::ACTIONS, true) ? $action : 'creer',
            'formats' => $formats,
            'fichier' => $attachment?->id,
            'recherche' => Str::limit(trim((string) ($arguments['recherche'] ?? '')), 300, ''),
        ];
    }

    /**
     * The file joined by the client: the one named by the model, else the last one joined in this discussion.
     */
    protected function attachment(Project $project, Message $message, string $name): ?ProjectAttachment
    {
        $files = $project->attachments()->latest('id')->get();
        if ($files->isEmpty()) {
            return null;
        }

        return $files->first(fn (ProjectAttachment $file) => $name !== '' && $file->name === $name)
            ?? $files->first(fn (ProjectAttachment $file) => $file->message_id === $message->id)
            ?? ($name !== '' ? $files->first() : null);
    }

    /**
     * The discussion becomes a document project, and the work starts (« Construire ce plan » or a short document).
     *
     * @param  array<string, mixed>|null  $options
     */
    public function build(Project $project, Message $message, string $plan, string $stack = 'auto', ?array $options = null): ?AgentRun
    {
        $options ??= (array) data_get($this->lastOffer($project)?->report, 'document_options', ['type' => static::TYPE, 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '']);
        $project->forceFill([
            'kind' => Project::KIND_DOCUMENT,
            'document_type' => static::TYPE,
        ])->save();

        return self::start($project, $message, $options + ['plan' => $plan]);
    }

    /**
     * A new version: a request of the Runner, with its options.
     *
     * @param  array<string, mixed>  $options
     */
    public static function start(Project $project, Message $message, array $options): AgentRun
    {
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
        $run->forceFill(['document_options' => $options])->save();
        $project->touch();

        rescue(fn () => GenerateDocument::dispatch($run), report: true);

        return $run;
    }
}
