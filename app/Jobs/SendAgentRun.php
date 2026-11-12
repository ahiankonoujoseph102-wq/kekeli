<?php

namespace App\Jobs;

use App\Agent\AgentPlaces;
use App\Agent\AnswerModels;
use App\Agent\RunnerClient;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Sends a request (or the resumption of a waiting request) to the Runner, as soon as one of the places of Kekeli
 * is free (10 requests at the same time by default): until then, the job comes back every few seconds.
 */
class SendAgentRun implements ShouldQueue
{
    use Queueable;

    /** Errors allowed before giving up (waiting for a place is not an error). */
    public int $maxExceptions = 3;

    /** Seconds before checking again whether a place is free. */
    public const WAIT_SECONDS = 15;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 20];

    public function __construct(public AgentRun $run, public bool $resume = false) {}

    public function handle(RunnerClient $runner): void
    {
        $this->run->refresh();

        if (! $this->run->isActive() || $this->run->stoppedByClient()) {
            return;
        }

        // Une reprise vient d'une demande en attente, ou d'une demande arrêtée que l'utilisateur continue.
        if ($this->resume && ! in_array($this->run->status, [AgentRun::STATUS_WAITING, AgentRun::STATUS_QUEUED], true)) {
            return;
        }

        // Plafond du jour du modèle de construction atteint : la demande s'arrête tout de suite (« Reprise à … »)
        // et se reprend avec « Continuer », au lieu de partir vers un modèle gratuit qui ne sait pas finir.
        if (app(AnswerModels::class)->buildCapReached($this->run->project->user)) {
            $this->run->update(['status' => AgentRun::STATUS_STOPPED, 'stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_MODEL_CAP, 'finished_at' => now(), 'resume_at' => null]);

            return;
        }

        if (! $this->takePlace()) {
            // Signe de vie : la demande attend toujours son tour.
            $this->run->touch();
            $this->release(self::WAIT_SECONDS);

            return;
        }

        $runner->send($this->run, $this->resume);
    }

    /**
     * A request waits at most a few hours for a place, then it is given up.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * Take one of the places of Kekeli for this request (one job at a time, so that two requests never take the
     * last place together).
     */
    private function takePlace(): bool
    {
        $places = app(AgentPlaces::class);
        if ($this->run->sent_at !== null) {
            return true;
        }

        try {
            return (bool) Cache::lock('kekeli:places-agent', 10)->block(5, function () use ($places) {
                if (! $places->canStart($this->run)) {
                    return false;
                }

                $this->run->forceFill(['sent_at' => now()])->save();

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->run->refresh()->update([
            'status' => AgentRun::STATUS_FAILED,
            'error' => 'runner : '.($exception?->getMessage() ?? ''),
            'finished_at' => now(),
        ]);

        AdminAlert::create([
            'kind' => 'runner_unreachable',
            'message' => __('The Runner could not be reached. Check that it is running.'),
        ]);
    }
}
