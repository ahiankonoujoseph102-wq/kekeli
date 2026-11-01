<?php

namespace App\Http\Controllers;

use App\Agent\RunnerEventHandler;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunnerEventController extends Controller
{
    /**
     * Receive one event of the agent from the Runner.
     */
    public function __invoke(Request $request, RunnerEventHandler $handler): JsonResponse
    {
        $data = $request->validate([
            'tache' => ['required', 'string', 'max:40'],
            'evenement' => ['required', 'array'],
            'evenement.type' => ['required', 'string', 'max:40'],
        ]);

        $run = AgentRun::query()->findOrFail($data['tache']);

        $handler->handle($run, (array) $request->input('evenement'));

        return response()->json(['ok' => true]);
    }
}
