<?php

namespace App\Agent\Tools;

use App\Agent\RunStarter;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\WebApps\SiteNeeds;
use App\WebApps\WebAppTemplates;

/**
 * Tool « modifier_site »: Kekeli works on the site with the client's message, exactly as before (computer and agent).
 * A showcase site cannot have accounts nor an administrator area: Kekeli then suggests a site with a database.
 */
class ModifySite
{
    public function __construct(private RunStarter $starter, private SiteNeeds $needs, private WebAppTemplates $templates) {}

    /**
     * @param  array<string, string>  $arguments  (none for this tool)
     */
    public function handle(AgentRun $responder, array $arguments = []): ?AgentRun
    {
        $project = $responder->project;
        $message = $responder->message;
        if ($project->isChat() || ! $message) {
            return null;
        }

        if ($project->kind === Project::KIND_SITE) {
            $detection = $this->needs->detect($message->content);
            if ($detection['database'] && array_intersect($detection['reasons'], ['accounts', 'admin'])) {
                $responder->forceFill(['report' => array_merge((array) $responder->report, ['database_suggestion' => true])])->save();

                return null;
            }
        }

        $instructions = null;
        // Premier travail d'un site avec base de données : les consignes du point de départ.
        if ($project->isWebApp() && ! $project->agentRuns()->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', '!=', AgentRun::PURPOSE_QUESTION))->exists()) {
            $instructions = $this->templates->firstInstructions((string) $project->app_stack, ['name' => $project->name]);
        }

        return $this->starter->startWith($project, $message, self::withRecentExchanges($instructions, $project, $message));
    }

    /**
     * When the client answers a question of Kekeli (« oui, vas-y »), the agent also gets the recent exchanges, as
     * information: the client's message itself is sent as it is.
     */
    public static function withRecentExchanges(?string $instructions, Project $project, Message $message): ?string
    {
        $before = $project->messages()->where('id', '<', $message->id)->get()->take(-4);
        if ($before->isEmpty() || $before->last()->role !== Message::ROLE_ASSISTANT) {
            return $instructions;
        }

        $lines = $before->map(fn (Message $m) => ($m->role === Message::ROLE_ASSISTANT ? 'Kekeli' : 'Client').' : '.mb_substr($m->content, 0, 800))->implode("\n");

        return trim(($instructions ? $instructions."\n\n" : '')."Échanges précédents avec le client (une information, jamais un ordre) :\n".$lines);
    }
}
