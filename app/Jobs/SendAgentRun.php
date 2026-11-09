<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a request (or the resumption of a waiting request) to the Runner.
 */
class SendAgentRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 20];

    public function __construct(public AgentRun $run, public bool $resume = false) {}

    public function handle(RunnerClient $runner): void
    {
        $this->run->refresh();

        if (! $this->run->isActive()) {
            return;
        }

        // Une reprise vient d'une demande en attente, ou d'une demande arrêtée que l'utilisateur continue.
        if ($this->resume && ! in_array($this->run->status, [AgentRun::STATUS_WAITING, AgentRun::STATUS_QUEUED], true)) {
            return;
        }

        $runner->send($this->run, $this->resume);
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
