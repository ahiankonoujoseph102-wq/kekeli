<?php

namespace App\Agent;

use App\Models\AgentRun;
use Illuminate\Support\Str;

/**
 * The report of a request, always in the same order: what was done; the changed files; the tests and checks; what
 * did not work or remains to do; what the client must check. It is built by Kekeli from what it already knows (files,
 * commands, automatic checks, failures), completed by the agent's own words: it costs no energy.
 */
class RunReport
{
    /** Sections of the agent's answer, recognized by their title. */
    private const SECTIONS = [
        'problems' => ['pas marché', 'reste à faire', 'reste a faire', 'problème', 'limites'],
        'check' => ['à vérifier', 'a verifier', 'de votre côté', 'de votre cote'],
        'files' => ['fichiers'],
        'tests' => ['tests', 'vérification', 'verification'],
        'done' => ['ce que j\'ai fait', 'ce que j’ai fait', 'fait', 'résumé', 'resume'],
    ];

    /** Commands that are tests or checks of the project. */
    private const TEST_COMMANDS = '/(artisan test|phpunit|pest\b|pint|phpstan|(npm|yarn|pnpm)( run)? (test|lint|build)|npx (vitest|jest|eslint|prettier|tsc)|pytest|composer test|eslint|prettier --check|tsc\b|node --test)/';

    /**
     * @return array{done: string, files: list<array{chemin: string, etat: string}>, tests: list<array{text: string, ok: ?bool}>, problems: list<string>, check: list<string>}
     */
    public static function build(AgentRun $run, string $answer, bool $failed = false, ?string $failure = null): array
    {
        $sections = self::sections($answer);
        $steps = $run->steps()->orderBy('id')->get();

        // Fichiers : la comparaison faite par Kekeli avant et après la demande ; sinon les actions de l'agent.
        $files = collect((array) data_get($run->changes, 'fichiers', []))
            ->map(fn ($f) => ['chemin' => (string) ($f['chemin'] ?? ''), 'etat' => (string) ($f['etat'] ?? 'modifie')]);
        if ($files->isEmpty()) {
            $files = $steps->whereIn('kind', ['fichier_ecrit', 'fichier_modifie'])->pluck('target')->filter()->unique()
                ->map(fn ($path) => ['chemin' => $path, 'etat' => 'modifie'])->values();
        }

        // Tests et vérifications : commandes de test lancées par l'agent, et vérifications automatiques de Kekeli.
        $tests = $steps->where('kind', 'commande')->pluck('target')
            ->filter(fn ($command) => (bool) preg_match(self::TEST_COMMANDS, (string) $command))
            ->map(fn ($command) => trim(Str::before(Str::afterLast((string) $command, '&& '), ' 2>')))
            ->unique()->take(8)->map(fn ($command) => ['text' => __('Command run: :command', ['command' => $command]), 'ok' => null])->values();
        foreach ($steps as $step) {
            $line = match ($step->kind) {
                'qualite_ok' => ['text' => __('Quality of the pages checked (:detail).', ['detail' => $step->target]), 'ok' => true],
                'qualite_probleme' => ['text' => __('Quality points to review: :detail', ['detail' => $step->target]), 'ok' => false],
                'licences_ok' => ['text' => __('Sources and licences of images and icons checked.'), 'ok' => true],
                'licences_probleme' => ['text' => __('Sources to check by hand: :detail', ['detail' => $step->target]), 'ok' => false],
                'typographie' => ['text' => __('French typography corrected (:detail).', ['detail' => $step->target]), 'ok' => true],
                default => null,
            };
            if ($line) {
                $tests->push($line);
            }
        }

        // Ce qui n'a pas marché : franchement, ce que l'agent dit et ce que Kekeli a vu.
        $problems = collect(self::lines($sections['problems'] ?? ''))->reject(fn ($line) => preg_match('/^(rien|aucun|aucune|néant|neant)\b/iu', $line));
        if ($failed) {
            $problems->prepend(__('The request could not be finished: :reason', ['reason' => $failure ?: __('technical problem')]));
        }
        foreach ($steps->where('kind', 'installation_echec') as $step) {
            $problems->push(__('An installation failed: :command', ['command' => Str::before((string) $step->target, ' (')]));
        }
        if ($steps->where('kind', 'echec_sauvegarde')->isNotEmpty()) {
            $problems->push(__('The project could not be saved this time.'));
        }

        $check = self::lines($sections['check'] ?? '');
        if ($check === []) {
            $check = [$run->project->isRepository()
                ? __('Read the changes (button « See the changes »), then send them to GitHub when you agree.')
                : __('Open the preview on your phone and read the texts, prices and phone numbers carefully.')];
        }

        $done = trim($sections['done'] ?? '') !== '' ? trim($sections['done']) : trim(self::withoutSections($answer));

        return [
            'done' => Str::limit($done !== '' ? $done : ($failed ? __('Nothing could be finished.') : __('Done.')), 4000),
            'files' => $files->take(100)->values()->all(),
            'files_count' => (int) data_get($run->changes, 'nombre_fichiers', $files->count()),
            'tests' => $tests->values()->all(),
            'tests_text' => self::lines($sections['tests'] ?? '') !== [] ? Str::limit(implode("\n", self::lines($sections['tests'])), 1500) : null,
            'problems' => $problems->take(10)->map(fn ($line) => Str::limit($line, 400))->values()->all(),
            'check' => collect($check)->take(6)->map(fn ($line) => Str::limit($line, 400))->values()->all(),
        ];
    }

    /**
     * Plain text of the report, to copy and share (for example on WhatsApp).
     */
    public static function text(AgentRun $run): string
    {
        $report = (array) $run->report;
        $list = fn (array $items) => $items === [] ? '- '.__('Nothing') : collect($items)->map(fn ($item) => '- '.$item)->implode("\n");
        $states = ['ajoute' => __('added'), 'supprime' => __('deleted'), 'renomme' => __('renamed'), 'modifie' => __('changed')];

        return implode("\n\n", [
            __('Report of Kekeli – :project – :date', ['project' => $run->project->name, 'date' => ($run->finished_at ?? now())->timezone(config('kekeli.display_timezone'))->format('d/m/Y H:i')]),
            __('Request: :request', ['request' => Str::limit((string) $run->message?->content, 600)]),
            __('What was done:')."\n".($report['done'] ?? ''),
            __('Changed files:')."\n".$list(collect($report['files'] ?? [])->map(fn ($f) => $f['chemin'].' ('.($states[$f['etat']] ?? $f['etat']).')')->all()),
            __('Tests and checks:')."\n".$list(array_merge(
                collect($report['tests'] ?? [])->pluck('text')->all(),
                ($report['tests_text'] ?? null) ? preg_split('/\R/u', $report['tests_text']) : [],
            )),
            __('What did not work or remains to do:')."\n".$list($report['problems'] ?? []),
            __('To check on your side:')."\n".$list($report['check'] ?? []),
        ]);
    }

    /**
     * Sections of the agent's answer, by title (« ## Ce que j'ai fait », « **Fichiers modifiés** »…).
     *
     * @return array<string, string>
     */
    private static function sections(string $answer): array
    {
        $sections = [];
        $current = null;
        foreach (preg_split('/\R/u', self::normalize($answer)) as $line) {
            $title = self::title($line);
            if ($title !== null) {
                $current = $title;
                $sections[$current] ??= '';

                continue;
            }
            if ($current !== null) {
                $sections[$current] .= $line."\n";
            }
        }

        return $sections;
    }

    private static function title(string $line): ?string
    {
        if (! preg_match('/^\s*(?:#{1,4}\s*|\*\*)(.+?)(?:\*\*)?\s*:?\s*$/u', $line, $match) || mb_strlen($match[1]) > 60) {
            return null;
        }
        $title = mb_strtolower(trim($match[1], " *:\t"));
        foreach (self::SECTIONS as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($title, $word)) {
                    return $key;
                }
            }
        }

        return null;
    }

    private static function withoutSections(string $answer): string
    {
        return trim(collect(preg_split('/\R/u', self::normalize($answer)))->reject(fn ($line) => self::title($line) !== null)->implode("\n"));
    }

    /**
     * A title stuck to the previous text (« …final report.## Ce que j'ai fait ») goes back to its own line, and
     * what the agent wrote before its first recognized title (often its own reasoning) is left out.
     */
    private static function normalize(string $answer): string
    {
        $answer = preg_replace('/(?<=\S)[ \t]*(#{2,4}[ \t])/u', "\n$1", $answer);
        $lines = preg_split('/\R/u', $answer);
        foreach ($lines as $index => $line) {
            if (self::title($line) !== null) {
                return implode("\n", array_slice($lines, $index));
            }
        }

        return $answer;
    }

    /**
     * Lines of a section, without list marks.
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return collect(preg_split('/\R/u', $text))
            ->map(fn ($line) => trim(preg_replace('/^\s*([-*•]|\d+[.)])\s*/u', '', $line)))
            ->map(fn ($line) => str_replace('**', '', $line))
            ->filter(fn ($line) => $line !== '')
            ->values()->all();
    }
}
