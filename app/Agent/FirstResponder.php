<?php

namespace App\Agent;

use App\Agent\Tools\CreateAgent;
use App\Agent\Tools\CreateApplication;
use App\Agent\Tools\CreateAutomation;
use App\Agent\Tools\CreateDocument;
use App\Agent\Tools\CreateImage;
use App\Agent\Tools\CreatePresentation;
use App\Agent\Tools\CreateSite;
use App\Agent\Tools\ModifyDocument;
use App\Agent\Tools\ModifySite;
use App\Models\AgentRun;
use App\Models\Project;

/**
 * The « first responder » of Kekeli, like Claude, ChatGPT or Manus: every message of the client goes first to the
 * model (through the Runner, without a computer), with instructions and tools. The model decides: it answers a
 * question itself, calls a tool for a site to create or change, or asks one short question when in doubt.
 *
 * The tools offered depend on the place: a discussion can create a site, a project of site can be changed. To add a
 * capacity later (documents, presentations, images), add its tool here and in runner/src/repondant.js.
 */
class FirstResponder
{
    public const PLACE_DISCUSSION = 'discussion';

    public const PLACE_SITE = 'site';

    public const PLACE_DOCUMENT = 'document';

    /** Tools offered in each place, with the class that carries them out. */
    public const TOOLS = [
        self::PLACE_DISCUSSION => ['creer_site' => CreateSite::class, 'creer_application' => CreateApplication::class, 'creer_agent' => CreateAgent::class, 'creer_automatisation' => CreateAutomation::class, 'creer_document' => CreateDocument::class, 'creer_presentation' => CreatePresentation::class, 'creer_image' => CreateImage::class],
        self::PLACE_SITE => ['modifier_site' => ModifySite::class],
        self::PLACE_DOCUMENT => ['modifier_document' => ModifyDocument::class],
    ];

    public function __construct(private RunnerClient $runner) {}

    public static function placeOf(Project $project): string
    {
        return match (true) {
            $project->isChat() => self::PLACE_DISCUSSION,
            $project->isDocument() => self::PLACE_DOCUMENT,
            default => self::PLACE_SITE,
        };
    }

    /**
     * The class of a tool, only if it is offered in this place (the model can never use another one).
     *
     * @return class-string|null
     */
    public static function tool(string $place, ?string $name): ?string
    {
        return self::TOOLS[$place][(string) $name] ?? null;
    }

    /**
     * Asks the model. The answer has « ok », « decision » (« repondre » or « outil »), « reponse » or « outil », the
     * provider, the tokens and the refusals met on the way.
     *
     * @return array<string, mixed>
     */
    public function decide(AgentRun $run): array
    {
        return $this->runner->respond($run, self::placeOf($run->project));
    }
}
