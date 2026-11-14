<?php

namespace App\Http\Controllers;

use App\Agent\AgentTrial;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Runner asks whether one more message of the free trial of an agent is allowed (signed request). « suite »: one
 * more call to the model for the same message of the visitor (a team of agents makes several), never counted again.
 */
class RunnerAgentTrialController extends Controller
{
    public function __invoke(Request $request, Project $project, AgentTrial $trial): JsonResponse
    {
        return response()->json($trial->take($project, continuation: $request->boolean('suite')));
    }
}
