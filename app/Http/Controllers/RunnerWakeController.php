<?php

namespace App\Http\Controllers;

use App\Agent\RunnerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Le Runner réveillé pendant que le client écrit (16 novembre 2026) : sur Laravel Cloud, le Runner s'endort quand
 * personne ne s'en sert, et le premier message attendait son réveil (plusieurs secondes de « Réflexion… »). Dès que
 * le client touche la zone du message, la page le prévient ; le site répond tout de suite et réveille le Runner après la
 * réponse (une simple visite de son adresse « /sante », sans secret ni donnée). Au plus une fois par minute pour tous.
 */
class RunnerWakeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (filled(config('kekeli.runner.url')) && Cache::add('kekeli.reveil-runner', true, now()->addMinute())) {
            dispatch(fn () => app(RunnerClient::class)->wake())->afterResponse();
        }

        return response()->json(['ok' => true]);
    }
}
