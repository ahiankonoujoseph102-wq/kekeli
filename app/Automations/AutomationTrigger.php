<?php

namespace App\Automations;

use App\Agent\ProjectFiles;
use App\Models\Project;
use Illuminate\Support\Str;
use Throwable;

/**
 * The trigger of an automation, read in the code written by Kekeli (never guessed by a model): a fixed time
 * (« schedule »), an event received by the entry point on the client's Cloudflare account (« repository_dispatch »), or
 * a regular check of a service that sends no event (« schedule » every few minutes with a memory of what was seen).
 * Everything runs on the client's GitHub and Cloudflare accounts, never at Kekeli.
 */
class AutomationTrigger
{
    public const SCHEDULE = 'heure';

    public const EVENT = 'evenement';

    public const POLLING = 'verification';

    public const MANUAL = 'main';

    /** The secret of the entry point, created by Kekeli and never shown. */
    public const ENTRY_SECRET = 'ENTREE_SECRET';

    /** The sites whose public form may call the entry point. */
    public const ORIGINS = 'FORMULAIRE_ORIGINES';

    /**
     * What the files of the automation say about its trigger.
     *
     * @param  array<string, string>  $files
     * @return array{kind: string, cron: ?string, timezone: ?string, entry: bool, trial: ?array<string, mixed>}
     */
    public static function detect(array $files): array
    {
        $workflow = (string) ($files['.github/workflows/automatisation.yml'] ?? collect($files)->first(fn ($content, $path) => str_starts_with($path, '.github/workflows/') && $path !== '.github/workflows/point-entree.yml') ?? '');
        // Les lignes en commentaire ne comptent pas.
        $active = collect(preg_split('/\R/', $workflow))->reject(fn ($line) => str_starts_with(ltrim($line), '#'))->implode("\n");
        preg_match('/cron:\s*[\'"]([^\'"]+)[\'"]/', $active, $cron);
        preg_match('/timezone:\s*[\'"]?([A-Za-z_\/]+)[\'"]?/', $active, $timezone);
        $dispatch = (bool) preg_match('/^\s*repository_dispatch\s*:/m', $active);
        $memory = str_contains($active, 'actions/cache') || str_contains((string) ($files['src/automatisation.js'] ?? ''), 'lireVus');

        $kind = match (true) {
            $dispatch => self::EVENT,
            $cron !== [] && $memory => self::POLLING,
            $cron !== [] => self::SCHEDULE,
            default => self::MANUAL,
        };

        $trial = null;
        if (isset($files['evenement-essai.json'])) {
            $decoded = json_decode((string) $files['evenement-essai.json'], true);
            if (is_array($decoded)) {
                $trial = ['type' => Str::limit((string) ($decoded['type'] ?? 'evenement'), 40, ''), 'donnees' => array_slice(array_map(fn ($value) => is_scalar($value) ? Str::limit((string) $value, 200) : '…', (array) ($decoded['donnees'] ?? [])), 0, 12, true)];
            }
        }

        return [
            'kind' => $kind,
            'cron' => $cron[1] ?? null,
            'timezone' => $timezone[1] ?? null,
            'entry' => $kind === self::EVENT && isset($files['point-entree/worker.js']),
            'trial' => $trial,
        ];
    }

    /**
     * After each work of Kekeli on an automation: the trigger is read again, and an automation launched by an event
     * gets the secret of its entry point (random, encrypted, never shown).
     */
    public static function refresh(Project $project): void
    {
        if (! $project->isAutomation()) {
            return;
        }
        try {
            $files = app(ProjectFiles::class)->read($project, true);
        } catch (Throwable) {
            return;
        }
        $trigger = self::detect($files);
        $previous = (array) ($project->automation_trigger ?? []);
        $project->forceFill(['automation_trigger' => [...$trigger, 'branched' => $previous['branched'] ?? []]])->save();

        if ($trigger['entry']) {
            self::entrySecret($project);
        }
    }

    /** The secret of the entry point, created once. */
    public static function entrySecret(Project $project): string
    {
        $secret = $project->secrets()->where('name', self::ENTRY_SECRET)->first();
        if ($secret === null) {
            $secret = $project->secrets()->create(['name' => self::ENTRY_SECRET, 'value' => bin2hex(random_bytes(32))]);
        }

        return (string) $secret->value;
    }

    /** A site may call the entry point from its public form (its address added to FORMULAIRE_ORIGINES). */
    public static function allowOrigin(Project $automation, string $origin): void
    {
        $current = (string) ($automation->secrets()->where('name', self::ORIGINS)->first()?->value ?? '');
        $origins = collect(explode(',', $current))->map(fn ($o) => rtrim(trim($o), '/'))->filter()->push(rtrim($origin, '/'))->unique()->values();
        $automation->secrets()->updateOrCreate(['name' => self::ORIGINS], ['value' => $origins->implode(',')]);
    }

    /**
     * The trigger in simple words, for the client.
     *
     * @param  array<string, mixed>|null  $trigger
     */
    public static function describe(?array $trigger): string
    {
        return match ($trigger['kind'] ?? null) {
            self::EVENT => (string) __('As soon as the event happens (through your entry point).'),
            self::POLLING => (string) __('By checking regularly: :when.', ['when' => self::cronText((string) ($trigger['cron'] ?? ''))]),
            self::SCHEDULE => (string) __('At a fixed time: :when.', ['when' => self::cronText((string) ($trigger['cron'] ?? ''))]),
            default => (string) __('When you run it.'),
        };
    }

    /** A cron line in simple French (the most common cases), otherwise the line itself. */
    public static function cronText(string $cron): string
    {
        $parts = preg_split('/\s+/', trim($cron)) ?: [];
        if (count($parts) !== 5) {
            return $cron;
        }
        [$minute, $hour, $day, $month, $weekday] = $parts;
        if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return (string) __('every :count minutes', ['count' => $m[1]]);
        }
        if (ctype_digit($minute) && ctype_digit($hour) && $day === '*' && $month === '*') {
            $time = sprintf('%d h %02d', (int) $hour, (int) $minute);

            return $weekday === '*' ? (string) __('every day at :time', ['time' => $time]) : ($weekday === '1-5' ? (string) __('from Monday to Friday at :time', ['time' => $time]) : (string) __('at :time on some days', ['time' => $time]));
        }

        return $cron;
    }
}
