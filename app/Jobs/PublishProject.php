<?php

namespace App\Jobs;

use App\Agent\RunnerClient;
use App\Models\AdminAlert;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Publishes the last backup of a project on Cloudflare Pages (free pages.dev address), through the Runner.
 */
class PublishProject implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Project $project) {}

    public function handle(RunnerClient $runner): void
    {
        $project = $this->project->refresh();

        try {
            $result = $runner->publish($project);
        } catch (Throwable $exception) {
            $this->failed($exception);

            return;
        }

        $project->forceFill([
            'pages_name' => $project->pagesName(),
            'publish_status' => Project::PUBLISH_DONE,
            'published_url' => $result['adresse'],
            'published_at' => now(),
            'publish_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $message = (string) ($exception?->getMessage() ?? '');

        $this->project->refresh()->forceFill([
            'publish_status' => Project::PUBLISH_FAILED,
            'publish_error' => mb_substr($message, 0, 500),
        ])->save();

        AdminAlert::create([
            'kind' => 'publish_failed',
            'message' => $message === 'cloudflare_absent'
                ? __('A site could not be published: the Cloudflare key is missing in the Runner.')
                : __('A site could not be published (project :project): :error', ['project' => $this->project->id, 'error' => mb_substr($message, 0, 300)]),
        ]);
    }
}
