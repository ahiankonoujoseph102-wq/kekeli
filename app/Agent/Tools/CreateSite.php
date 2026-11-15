<?php

namespace App\Agent\Tools;

use App\Agent\RunStarter;
use App\Models\AgentRun;
use App\WebApps\NewSite;

/**
 * Tool « creer_site »: the discussion becomes a project of site, with all its history, and Kekeli builds the site
 * from the client's message, exactly as before (showcase site or site with a database, chosen by Kekeli).
 */
class CreateSite
{
    public function __construct(private NewSite $sites, private RunStarter $starter) {}

    /**
     * @param  array<string, string>  $arguments  « nom »: the name of the activity, when the client gave or chose it
     */
    public function handle(AgentRun $responder, array $arguments = []): ?AgentRun
    {
        $project = $responder->project;
        if (! $project->isChat() || ! $responder->message) {
            return null;
        }

        // Le nom donné par le client (ou choisi dans ses réponses aux questions) : le projet et le site le portent.
        $instructions = $this->sites->convert($project, $responder->message, $arguments['nom'] ?? null);

        return $this->starter->startWith($project->fresh(), $responder->message, $instructions);
    }
}
