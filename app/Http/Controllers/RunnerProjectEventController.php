<?php

namespace App\Http\Controllers;

use App\Agent\RunnerEventHandler;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunnerProjectEventController extends Controller
{
    /**
     * Receive an event about a project from the Runner, outside of a request to the agent.
     */
    public function __invoke(Request $request, Project $project, RunnerEventHandler $handler): JsonResponse
    {
        $request->validate([
            'evenement' => ['required', 'array'],
            'evenement.type' => ['required', 'string', 'max:40'],
        ]);

        $handler->handleProject($project, (array) $request->input('evenement'));

        return response()->json(['ok' => true]);
    }
}
