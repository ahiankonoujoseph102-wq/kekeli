<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\Project;
use App\WebApps\HostingPackage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ask the Runner for the ZIP file of a Laravel web application, ready for shared hosting (cPanel, Hostinger).
 * The Runner wakes the computer (or recreates it from the last backup), installs the libraries and sends the file.
 */
class BuildHostingPackage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Project $project) {}

    public function handle(RunnerClient $runner): void
    {
        $this->project->forceFill(['package_status' => HostingPackage::BUILDING])->save();

        try {
            $runner->package($this->project);
            $this->project->refresh()->forceFill(['package_status' => $this->project->package_at ? HostingPackage::READY : HostingPackage::FAILED])->save();
        } catch (Throwable $e) {
            report($e);
            $this->project->forceFill(['package_status' => HostingPackage::FAILED])->save();
        }
    }
}
