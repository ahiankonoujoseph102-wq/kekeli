<?php

namespace App\Console\Commands;

use App\Jobs\ErasePausedComputer;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ErasePausedComputers extends Command
{
    protected $signature = 'kekeli:effacer-ordinateurs-en-pause';

    protected $description = 'Efface les ordinateurs en pause depuis trop longtemps ; les projets restent sauvegardés dans Kekeli.';

    public function handle(Settings $settings): int
    {
        $days = max(1, $settings->int('erase_paused_after_days'));
        $limit = now()->subDays($days);
        $count = 0;

        Project::query()
            // En pause depuis plus de N jours…
            ->whereNotNull('preview_expires_at')
            ->where('preview_expires_at', '<', $limit)
            // … pas déjà effacé depuis cette pause…
            ->where(fn (Builder $query) => $query->whereNull('computer_erased_at')->orWhereColumn('computer_erased_at', '<', 'preview_expires_at'))
            // … et seulement avec une sauvegarde à jour : jamais de perte.
            ->whereNotNull('backup_at')
            ->whereDoesntHave('agentRuns', fn (Builder $query) => $query->where(fn (Builder $runs) => $runs
                ->whereIn('status', AgentRun::ACTIVE_STATUSES)
                ->orWhereColumn('agent_runs.finished_at', '>', 'projects.backup_at')))
            ->each(function (Project $project) use (&$count) {
                ErasePausedComputer::dispatch($project);
                $count++;
            });

        $this->info($count === 0
            ? "Aucun ordinateur en pause depuis plus de {$days} jours."
            : "{$count} ordinateur(s) en pause depuis plus de {$days} jours : effacement demandé, projets sauvegardés.");

        return self::SUCCESS;
    }
}
