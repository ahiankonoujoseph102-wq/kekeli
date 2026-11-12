<?php

namespace App\Jobs;

use App\Agent\AnswerModels;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a version of a document or a presentation to the Runner (writing by the model, layout in an isolated
 * computer). The day's ceiling of the build model reached: a long document stops (« Reprise à … »), a short one goes on
 * with the free providers (they know how to write a letter or a CV).
 */
class GenerateDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public AgentRun $run) {}

    public function handle(RunnerClient $runner, AnswerModels $models): void
    {
        $run = $this->run->refresh();
        if (! $run->isActive() || ! $run->isDocumentWork() || ! $run->project->isDocument()) {
            return;
        }

        $user = $run->project->user;
        $long = filled(data_get($run->document_options, 'plan')) || data_get($run->document_options, 'action') === 'modifier' && $run->project->documentVersions()->latest('number')->value('pages') > 4;
        if ($long && $models->buildCapReached($user)) {
            $run->update(['status' => AgentRun::STATUS_STOPPED, 'stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_MODEL_CAP, 'finished_at' => now(), 'resume_at' => null]);

            return;
        }

        try {
            $runner->document($run, freeOnly: $models->buildCapReached($user));
            $run->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $error) {
            report($error);
            app(RunnerEventHandler::class)->handle($run, ['type' => 'erreur', 'raison' => 'runner', 'detail' => mb_substr($error->getMessage(), 0, 300)]);
        }
    }
}
