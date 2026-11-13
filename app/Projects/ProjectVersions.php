<?php

namespace App\Projects;

use App\Agent\Energy;
use App\Agent\ProjectBackup;
use App\Github\GithubFiles;
use App\Jobs\ApplyProjectVersion;
use App\Jobs\PushProjectToGithub;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Support\SecretScanner;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Versions and going back, like Lovable and Bolt.new (docs/plan.md, partie 26.17). After each successful work of
 * Kekeli, the files of the last backup are frozen in a cleaned archive (never .env, test database, dependencies or a
 * file with a key) kept by the site next to the backup. Restoring a version creates a NEW version with the files of
 * the old one: nothing is erased, a restoration can itself be undone. The Runner puts the files back in the computer
 * (preview of an old version, restoration, return after a failed work); no model is ever asked.
 */
class ProjectVersions
{
    public const ACTION_VIEW = 'voir';

    public const ACTION_BACK = 'revenir';

    public const ACTION_RESTORE = 'remettre';

    public function __construct(private ProjectBackup $backups, private Settings $settings, private Energy $energy) {}

    public function directory(Project $project): string
    {
        return 'versions/'.$project->id;
    }

    public function path(ProjectVersion $version): string
    {
        return $this->directory($version->project).'/'.$version->number.'.tar.gz';
    }

    public function current(Project $project): ?ProjectVersion
    {
        return $project->versions()->reorder('number', 'desc')->first();
    }

    /**
     * After a successful work: a new version with the files of the backup just received. Nothing when the project
     * has no versions (discussion, document, repository), when the backup is older than the work, or when this work
     * already has its version.
     */
    public function capture(AgentRun $run): ?ProjectVersion
    {
        $project = $run->project;
        if (! $project->hasVersions() || ! $this->backups->exists($project) || $run->status !== AgentRun::STATUS_COMPLETED) {
            return null;
        }
        $start = $run->sent_at ?? $run->started_at ?? $run->created_at;
        if ($start && $project->backup_at && $project->backup_at->lt($start->copy()->subSeconds(5))) {
            return null;
        }
        if ($project->versions()->where('agent_run_id', $run->id)->exists()) {
            return null;
        }

        [$archive, $count] = $this->cleanArchive($this->backups->disk()->get($this->backups->path($project->id)));
        $current = $this->current($project);
        // Rien n'a changé depuis la version actuelle : pas de nouvelle version.
        if ($current && $this->backups->disk()->exists($this->path($current)) && $this->sameFiles($this->backups->disk()->get($this->path($current)), $archive)) {
            return null;
        }

        $version = $this->store($project, $archive, $count, [
            'title' => $this->titleFor($run, $current === null),
            'kind' => ProjectVersion::KIND_WORK,
            'agent_run_id' => $run->id,
        ]);
        $run->forceFill(['report' => array_merge((array) $run->report, ['version' => $version->number])])->save();
        $this->prune($project);

        return $version;
    }

    /**
     * « Restaurer cette version » : a new version with the files of the old one, put back in the computer (the
     * preview follows), and sent to GitHub as a new commit when the project is linked (never a forced push).
     */
    public function restore(Project $project, ProjectVersion $version): ProjectVersion
    {
        $archive = $this->backups->disk()->get($this->path($version));
        if ($archive === null) {
            throw new RuntimeException('Version introuvable.');
        }

        $restored = DB::transaction(function () use ($project, $version, $archive) {
            $new = $this->store($project, $archive, $version->files_count, [
                'title' => __('Back to version :number', ['number' => $version->number]),
                'kind' => ProjectVersion::KIND_RESTORE,
                'restored_from' => $version->number,
            ]);
            $project->forceFill(['viewing_version' => null, 'version_busy_at' => now()])->save();
            $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => __(':label created: the project is back to version :number.', ['label' => $new->label(), 'number' => $version->number])]);

            return $new;
        });

        ApplyProjectVersion::dispatch($project, self::ACTION_RESTORE, $restored->number);

        // Toujours envoyée, même si un envoi précédent est en cours : elle passe après lui, en nouveau commit.
        if ($project->user?->github_token && $project->isLinkedToGithub()) {
            $project->forceFill(['github_status' => Project::GITHUB_IN_PROGRESS, 'github_error' => null])->save();
            rescue(fn () => PushProjectToGithub::dispatch($project, null, $restored->number), report: true);
        }
        $this->prune($project);

        return $restored;
    }

    /** « Voir » : the preview shows an old version, nothing else changes (« Vous regardez la version 3 »). */
    public function view(Project $project, ProjectVersion $version): void
    {
        $project->forceFill(['viewing_version' => $version->number, 'version_busy_at' => now()])->save();
        ApplyProjectVersion::dispatch($project, self::ACTION_VIEW, $version->number);
    }

    /** « Revenir à la version actuelle ». */
    public function back(Project $project): void
    {
        if ($project->viewing_version === null) {
            return;
        }
        $project->forceFill(['viewing_version' => null, 'version_busy_at' => now()])->save();
        ApplyProjectVersion::dispatch($project, self::ACTION_BACK, null);
    }

    /**
     * A work failed or was stopped: the project goes back to its last version that worked (files and preview), and
     * the client sees it. Nothing to do when the work changed nothing or when the project has no version yet.
     */
    public function keepLastVersion(AgentRun $run): ?ProjectVersion
    {
        $project = $run->project;
        if (! $project->hasVersions() || $run->isQuestion() || $run->purpose !== null) {
            return null;
        }
        $current = $this->current($project);
        if (! $current) {
            return null;
        }

        $run->forceFill(['report' => array_merge((array) $run->report, ['kept_version' => $current->number])])->save();
        if (($run->changes ?? []) !== []) {
            $project->forceFill(['version_busy_at' => now()])->save();
            ApplyProjectVersion::dispatch($project, self::ACTION_RESTORE, $current->number);
        }

        return $current;
    }

    /**
     * How many versions are kept for this project: the oldest are erased beyond the limit of the formula, never the
     * current version, a version created by a restoration, nor a version that was restored.
     */
    public function limit(Project $project): int
    {
        return max(5, $this->settings->int($this->energy->isFree($project->user) ? 'versions_kept_free' : 'versions_kept_paid'));
    }

    public function prune(Project $project): void
    {
        $versions = $project->versions()->get();
        $extra = $versions->count() - $this->limit($project);
        if ($extra <= 0) {
            return;
        }
        $current = $versions->max('number');
        $protected = $versions->pluck('restored_from')->filter()->all();
        $versions->reject(fn ($v) => $v->number === $current || $v->isRestore() || in_array($v->number, $protected, true))
            ->take($extra)
            ->each(function (ProjectVersion $version) {
                $this->backups->disk()->delete($this->path($version));
                $version->delete();
            });
    }

    public function deleteAll(string $projectId): void
    {
        $this->backups->disk()->deleteDirectory('versions/'.$projectId);
    }

    /**
     * The short title of a version: given by the first responder with the tool (« Ajout de la page Contact »), or
     * else made from the client's request.
     */
    public function titleFor(AgentRun $run, bool $first): string
    {
        $title = trim((string) $run->version_title);
        if ($title !== '') {
            return Str::limit(Str::ucfirst($title), 80, '…');
        }
        if ($first) {
            return $run->project->isWebApp() ? __('Creation of the project') : __('Creation of the site');
        }
        $request = trim((string) preg_replace('/\s+/u', ' ', (string) $run->message?->content));
        $request = (string) preg_split('/(?<=[.!?])\s/u', $request)[0];

        return $request !== '' ? Str::limit(Str::ucfirst(rtrim($request, '.!? ')), 60, '…') : __('Changes by Kekeli');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(Project $project, string $archive, int $count, array $attributes): ProjectVersion
    {
        $number = (int) $project->versions()->max('number') + 1;
        $version = $project->versions()->create($attributes + ['number' => $number, 'size' => strlen($archive), 'files_count' => $count]);
        $this->backups->disk()->put($this->path($version), $archive);

        return $version;
    }

    /**
     * The backup without what must never be in a version: .env files (only .env.example), test databases (SQLite,
     * PGlite), dependencies and builds, key files, and any file in which a key is found.
     *
     * @return array{0: string, 1: int} The new archive (tar.gz) and its number of files.
     */
    public function cleanArchive(string $backup): array
    {
        $tar = gzdecode($backup);
        if ($tar === false) {
            throw new RuntimeException('La sauvegarde est illisible.');
        }

        $out = '';
        $count = 0;
        foreach (TarArchive::entries($tar) as $path => [$content, $mode]) {
            $path = (string) preg_replace('#^(\./)+#', '', str_replace('\\', '/', $path));
            if ($path === '' || str_contains('/'.$path.'/', '/../') || GithubFiles::forbidden($path) || preg_match('#(^|/)\.(cache|kekeli)/#', $path)
                || (strlen($content) < 2_000_000 && SecretScanner::redact($content)[1])) {
                continue;
            }
            $out .= TarArchive::entry($path, $content, $mode);
            $count++;
        }

        return [(string) gzencode($out.str_repeat("\0", 1024), 6), $count];
    }

    /**
     * The files of an archive (path → content), for the tests and the comparison of two versions.
     *
     * @return array<string, string>
     */
    public function files(string $archive): array
    {
        $files = [];
        foreach (TarArchive::entries((string) gzdecode($archive)) as $path => [$content]) {
            $files[(string) preg_replace('#^(\./)+#', '', $path)] = $content;
        }
        ksort($files);

        return $files;
    }

    private function sameFiles(string $a, string $b): bool
    {
        return $this->files($a) === $this->files($b);
    }
}
