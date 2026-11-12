<?php

namespace App\Http\Controllers;

use App\Agent\AgentTrial;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * The Runner asks whether one more message of the free trial of an agent is allowed (signed request).
 */
class RunnerAgentTrialController extends Controller
{
    public function __invoke(Project $project, AgentTrial $trial): JsonResponse
    {
        return response()->json($trial->take($project));
    }
}
