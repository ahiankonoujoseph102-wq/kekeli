<?php

namespace App\Http\Controllers;

use App\Agent\Energy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « + » → « Mode Pro » (docs/plan.md, partie 26.26) : le client d'une formule payante choisit les modèles les plus
 * puissants pour ses demandes ; la formule Gratuite reste toujours en mode Standard.
 */
class ModelModeController extends Controller
{
    public function update(Request $request, Energy $energy): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:standard,pro']]);
        $user = $request->user();
        $mode = $energy->isFree($user) ? 'standard' : $data['mode'];
        $user->forceFill(['model_mode' => $mode])->save();

        return response()->json(['mode' => $mode]);
    }
}
