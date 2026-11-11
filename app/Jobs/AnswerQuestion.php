<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A question of the client in the discussion: Kekeli answers with the model, through the Runner, without a computer
 * and without touching the site. The requests and tokens are counted like any request (energy, providers).
 */
class AnswerQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public AgentRun $run, public bool $doubt = false) {}

    public function handle(RunnerClient $runner, RunnerEventHandler $events): void
    {
        $run = $this->run->fresh();
        if (! $run || $run->status !== AgentRun::STATUS_QUEUED) {
            return;
        }

        $events->handle($run, ['type' => 'demarrage']);

        try {
            $result = $runner->question($run, $this->doubt);
        } catch (\Throwable $exception) {
            report($exception);
            $result = ['ok' => false];
        }

        foreach ((array) ($result['refus'] ?? []) as $refusal) {
            $events->handle($run, ['type' => 'refus', 'fournisseur' => $refusal['fournisseur'] ?? '', 'modele' => $refusal['modele'] ?? '']);
        }

        $answer = trim((string) ($result['reponse'] ?? ''));
        if (! ($result['ok'] ?? false) || $answer === '') {
            $run->update(['status' => AgentRun::STATUS_FAILED, 'finished_at' => now()]);

            return;
        }

        $events->handle($run, ['type' => 'fournisseur', 'fournisseur' => $result['fournisseur'] ?? '', 'modele' => $result['modele'] ?? '']);
        $events->handle($run, ['type' => 'requete'] + array_intersect_key($result, array_flip(['fournisseur', 'modele', 'tokens_entree', 'tokens_sortie', 'tokens_reflexion', 'tokens_cache'])));

        DB::transaction(function () use ($run, $answer) {
            $run->project->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'content' => Str::limit($answer, 6000),
                'agent_run_id' => $run->id,
            ]);

            $run->refresh()->forceFill([
                'status' => AgentRun::STATUS_COMPLETED,
                'finished_at' => now(),
                // Une question n'a ni fichier modifié ni vérification : seulement la réponse, sans émoji ni mise en forme.
                'report' => ['done' => Str::limit(RunReport::clean($answer), 3000), 'note' => null, 'question' => true, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
            ])->save();
        });
    }
}
