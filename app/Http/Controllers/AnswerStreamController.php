<?php

namespace App\Http\Controllers;

use App\Agent\Timings;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La réponse de Kekeli mot par mot, comme Claude et ChatGPT (chantier « vitesse », partie 3) : la page ouvre cette
 * adresse dès que le message est envoyé ; la réponse commence aussitôt (sans file d'attente) et chaque morceau de
 * texte est envoyé au navigateur dès qu'il arrive du modèle. À la fin, la page affiche la réponse mise en forme.
 *
 * Seulement le propriétaire du projet, seulement une demande qui attend encore sa réponse. La file d'attente répond
 * en secours si la page ne vient pas (RespondToMessage::STREAM_FALLBACK_SECONDS) ; un verrou empêche les deux.
 */
class AnswerStreamController extends Controller
{
    public function __invoke(Request $request, Project $project, AgentRun $run): StreamedResponse
    {
        abort_unless($project->user_id === Auth::id() && $run->project_id === $project->id && $run->isQuestion(), 404);

        // Le toucher sur « Envoyer » vu par le téléphone : seulement des durées (millisecondes), jamais de texte.
        Timings::browser($run, (array) $request->input('chronos', []));

        return response()->stream(function () use ($run) {
            // Le client peut fermer la page : la réponse se termine quand même et reste dans la discussion.
            ignore_user_abort(true);
            set_time_limit(180);
            Timings::mark($run, 'tache_recue');

            $send = function (array $event) {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            // Tout de suite : la page sait que la réponse a commencé (et peut dire « Kekeli se réveille… » sinon).
            $send(['type' => 'debut']);

            if (! in_array($run->fresh()?->status, [AgentRun::STATUS_QUEUED, AgentRun::STATUS_WAITING], true)) {
                $send(['type' => 'fin']);

                return;
            }

            $job = new RespondToMessage($run);
            $job->onEvent = $send;
            try {
                app()->call([$job, 'handle']);
            } catch (\Throwable $exception) {
                report($exception);
            }
            $send(['type' => 'fin']);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Les étapes vues par le téléphone pour une réponse déjà finie (premier mot affiché, réponse finie), envoyées
     * à la fin par la page : seulement des durées.
     */
    public function chronos(Request $request, Project $project, AgentRun $run): JsonResponse
    {
        abort_unless($project->user_id === Auth::id() && $run->project_id === $project->id, 404);
        Timings::browser($run, (array) $request->input('chronos', []));

        return response()->json(['ok' => true]);
    }
}
