<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\ProjectMission;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/**
 * Mode équipe, phase 1 (docs/multi-agents-etude.md, partie 11): an application built by a team of specialised agents,
 * one mission after the other. Off for the clients: only an administrator can choose it, with a second button under the
 * plan (« Construire en mode équipe »); the usual single agent stays the normal way and the fallback.
 * Setting « team_mode »: « admin » (default) or « off ».
 */
class TeamMode
{
    public function __construct(private Settings $settings) {}

    public function allowed(User $user): bool
    {
        return $this->settings->get('team_mode') === 'admin' && (bool) $user->is_admin;
    }

    /**
     * An event « equipe » of the Runner: the plan (the missions are written), or the result of one mission.
     *
     * @param  array<string, mixed>  $event
     */
    public function record(AgentRun $run, array $event): void
    {
        if (! $run->team) {
            return;
        }

        match ($event['etat'] ?? null) {
            'plan' => $this->plan($run, (array) ($event['missions'] ?? [])),
            'mission' => $this->mission($run, $event),
            default => null,
        };
    }

    /**
     * @param  list<mixed>  $missions
     */
    private function plan(AgentRun $run, array $missions): void
    {
        foreach (array_values(array_filter($missions, 'is_array')) as $position => $mission) {
            $code = Str::limit(preg_replace('/[^a-z0-9]/', '', Str::lower((string) ($mission['code'] ?? ''))), 10, '');
            $role = (string) ($mission['role'] ?? '');
            if ($code === '' || ! in_array($role, ProjectMission::ROLES, true) || $position >= 12) {
                continue;
            }
            $run->missions()->updateOrCreate(['code' => $code], [
                'project_id' => $run->project_id,
                'position' => $position + 1,
                'role' => $role,
                'title' => Str::limit(trim((string) ($mission['titre'] ?? '')), 80, ''),
                'objective' => Str::limit(trim((string) ($mission['objectif'] ?? '')), 600, ''),
                'criteria' => $this->texts($mission['criteres'] ?? [], 5, 240),
                'files' => $this->texts($mission['fichiers'] ?? [], 12, 160),
                'depends_on' => $this->texts($mission['depend_de'] ?? [], 10, 10),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function mission(AgentRun $run, array $event): void
    {
        $status = (string) ($event['statut'] ?? '');
        $mission = $run->missions()->where('code', (string) ($event['code'] ?? ''))->first();
        if (! $mission || ! in_array($status, ProjectMission::STATUSES, true)) {
            return;
        }
        $facts = collect((array) ($event['faits'] ?? []))->filter(fn ($fact) => is_array($fact))->take(8)->map(fn (array $fact) => [
            'type' => Str::limit((string) ($fact['type'] ?? 'note'), 20, ''),
            'titre' => Str::limit((string) ($fact['titre'] ?? ''), 80, ''),
            'resume' => Str::limit((string) ($fact['resume'] ?? ''), 300, ''),
        ])->values()->all();

        $mission->forceFill(array_filter([
            'status' => $status,
            'problem' => Str::limit((string) ($event['probleme'] ?? ''), 300, '') ?: null,
            'summary' => Str::limit((string) ($event['bilan'] ?? ''), 600, '') ?: null,
            'facts' => $facts ?: null,
            'seconds' => max(0, (int) ($event['secondes'] ?? 0)),
            'requests' => max(0, (int) ($event['requetes'] ?? 0)),
            'tokens_in' => max(0, (int) ($event['tokens_entree'] ?? 0)),
            'tokens_out' => max(0, (int) ($event['tokens_sortie'] ?? 0)),
        ], fn ($value) => $value !== null || $status !== 'en_cours'))->save();
    }

    /**
     * @return list<string>
     */
    private function texts(mixed $values, int $count, int $length): array
    {
        return collect(is_array($values) ? $values : [])->filter(fn ($v) => is_string($v) && trim($v) !== '')->take($count)->map(fn (string $v) => Str::limit(trim($v), $length, ''))->values()->all();
    }
}
