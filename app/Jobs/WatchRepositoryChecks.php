<?php

namespace App\Jobs;

use App\Agent\RunStarter;
use App\Github\RepositoryClient;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Developer mode: watches GitHub's automatic checks on the last commit sent by Kekeli. When they are green,
 * the client is told in simple French. When they fail, Kekeli reads the errors and fixes them by itself (a limited
 * number of times, counted in the client's energy), then sends the correction to the same pull request.
 */
class WatchRepositoryChecks implements ShouldQueue
{
    use Queueable;

    /** Seconds between two looks at the checks. */
    public const WAIT_SECONDS = 60;

    /** Without any check after this many minutes, the repository has no automatic check. */
    private const NO_CHECK_MINUTES = 10;

    public int $maxExceptions = 3;

    public function __construct(public Project $project, public string $sha, public ?string $since = null)
    {
        $this->since ??= now()->toIso8601String();
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(90);
    }

    public function handle(RepositoryClient $github, RunStarter $runs, Settings $settings): void
    {
        $project = $this->project->refresh();
        // Un envoi plus récent a sa propre surveillance.
        if ($project->pushed_sha !== $this->sha || ! $project->user->github_token) {
            return;
        }

        $checks = $github->checks($project, $this->sha, (string) $project->user->github_token);

        if ($checks['state'] === Project::CI_PENDING || ($checks['state'] === Project::CI_NONE && Carbon::parse($this->since)->diffInMinutes(now()) < self::NO_CHECK_MINUTES)) {
            $this->release(self::WAIT_SECONDS);

            return;
        }

        if ($checks['state'] === Project::CI_NONE) {
            $project->forceFill(['ci_status' => Project::CI_NONE, 'ci_summary' => null])->save();
            $this->say($project, __('This repository has no automatic check on GitHub: the pull request :url is ready for you to read.', ['url' => $project->pr_url]));

            return;
        }

        if ($checks['state'] === Project::CI_SUCCESS) {
            $project->forceFill(['ci_status' => Project::CI_SUCCESS, 'ci_summary' => null, 'ci_fix_attempts' => 0])->save();
            $this->say($project, __('The automatic checks of GitHub are green (:count check(s)). The pull request :url is ready. Kekeli merges it only if you ask.', ['count' => $checks['total'], 'url' => $project->pr_url]));

            return;
        }

        // Rouge : Kekeli lit les erreurs et corrige lui-même, un nombre limité de fois.
        $summary = collect($checks['failed'])->map(fn ($f) => $f['name'].($f['summary'] !== '' ? ' : '.$f['summary'] : ''))->implode("\n");
        $project->forceFill(['ci_status' => Project::CI_FAILURE, 'ci_summary' => mb_substr($summary, 0, 3000)])->save();

        $maximum = max(0, $settings->int('ci_fix_attempts_max'));
        if ($project->ci_fix_attempts >= $maximum) {
            $this->say($project, __('❌ The automatic checks of GitHub failed again (:names). Kekeli has already tried to fix them :count time(s): tell Kekeli what to do, or look at the pull request :url.', [
                'names' => collect($checks['failed'])->pluck('name')->implode(', '), 'count' => $maximum, 'url' => $project->pr_url,
            ]));

            return;
        }

        $run = $runs->start(
            $project,
            __('The automatic checks of GitHub failed (:names): fix them.', ['names' => collect($checks['failed'])->pluck('name')->implode(', ')]),
            $this->instructions($checks['failed']),
            AgentRun::PURPOSE_CI_FIX,
        );

        if ($run === null) {
            $this->say($project, __('❌ The automatic checks of GitHub failed (:names). Kekeli cannot fix them now (energy used up or a request in progress): ask again later.', [
                'names' => collect($checks['failed'])->pluck('name')->implode(', '),
            ]));

            return;
        }

        $project->increment('ci_fix_attempts');
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    /**
     * @param  list<array{name: string, summary: string, log: string}>  $failed
     */
    private function instructions(array $failed): string
    {
        $parts = ['La vérification automatique de GitHub (intégration continue) a échoué sur la pull request de ta branche. Voici les erreurs :'];
        foreach ($failed as $check) {
            $parts[] = "\n### ".$check['name'];
            if ($check['summary'] !== '') {
                $parts[] = $check['summary'];
            }
            if ($check['log'] !== '') {
                $parts[] = "Fin du journal :\n```\n".$check['log']."\n```";
            }
        }
        $parts[] = "\nTrouve la cause, corrige-la dans le code (jamais en désactivant un test), relance les mêmes vérifications dans l'ordinateur, puis enregistre la correction dans un commit. Kekeli l'enverra lui-même dans la même pull request.";

        return implode("\n", $parts);
    }

    private function say(Project $project, string $text): void
    {
        $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $text]);
    }
}
