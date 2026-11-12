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

    public function handle(AgentRun $responder): ?AgentRun
    {
        $project = $responder->project;
        if (! $project->isChat() || ! $responder->message) {
            return null;
        }

        $instructions = $this->sites->convert($project, $responder->message);

        return $this->starter->startWith($project->fresh(), $responder->message, $instructions);
    }
}
