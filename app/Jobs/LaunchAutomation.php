<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * « Lancer maintenant »: asks the Runner to run the automation once in the computer of the project. The log then
 * arrives step by step through the events of the Runner.
 */
class LaunchAutomation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public AgentRun $run) {}

    public function handle(RunnerClient $runner): void
    {
        $this->run->refresh();
        if (! $this->run->isActive() || ! $this->run->isLaunch() || ! $this->run->project->isAutomation()) {
            return;
        }

        try {
            $runner->launch($this->run);
            $this->run->forceFill(['sent_at' => now()])->save();
        } catch (Throwable $error) {
            report($error);
            $text = __('Kekeli could not start your automation. Please try again in a moment.');
            $this->run->forceFill(['status' => AgentRun::STATUS_FAILED, 'finished_at' => now(), 'error' => $text, 'report' => RunnerEventHandler::launchReport(false, $text, [])])->save();
        }
    }
}
