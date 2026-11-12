<?php

namespace App\Agent;

use App\Models\AgentRun;
use App\Models\AgentStep;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The last message of Kekeli after a request, like Lovable or Claude Code: a few simple sentences that say what was
 * done for the client, without jargon (no file name, command, status code, emoji or non-breaking space), and one polite
 * sentence when something could not be finished. The technical details (files, tests, checks) are kept for the « Code »
 * view and the folded actions. Built from what Kekeli already knows: it costs no energy.
 */
class RunReport
{
    /** Sections of the agent's answer, recognized by their title (older answers had five titles). */
    private const SECTIONS = [
        'problems' => ['pas marché', 'reste à faire', 'reste a faire', 'problème', 'limites'],
        'check' => ['à vérifier', 'a verifier', 'de votre côté', 'de votre cote'],
        'files' => ['fichiers'],
        'tests' => ['tests', 'vérification', 'verification'],
        'done' => ['ce que j\'ai fait', 'ce que j’ai fait', 'fait', 'résumé', 'resume'],
    ];

    /** Commands that are tests or checks of the project. */
    private const TEST_COMMANDS = '/(artisan test|phpunit|pest\b|pint|phpstan|(npm|yarn|pnpm)( run)? (test|lint|build)|npx (vitest|jest|eslint|prettier|tsc)|pytest|composer test|eslint|prettier --check|tsc\b|node --test)/';

    /** A sentence the client should not read: file names, commands, addresses, status codes, code. */
    private const TECHNICAL = '/(`|\b[\w.\/-]+\.(html?|css|s[ac]ss|m?js|jsx|tsx?|php|json|md|ya?ml|env|sql|py|lock|xml|txt|svg|png|jpe?g|webp)\b|\b(php|artisan|npm|npx|composer|node|git|curl|pint|phpunit|vitest|pytest|migrate|localhost|http_code)\b|https?:\/\/|\bcode (http )?[1-5]\d\d\b|\b(HTTP|DOM|CSS|HTML|JSON|API|SQL|CLI|URL)\b|\/home\/|<\/?[a-z]+[ >])/iu';

    /**
     * @return array{done: string, note: ?string, files: list<array{chemin: string, etat: string}>, files_count: int, tests: list<array{text: string, ok: ?bool}>, problems: list<string>}
     */
    public static function build(AgentRun $run, string $answer, bool $failed = false, ?string $failure = null): array
    {
        $sections = self::sections($answer);
        $steps = $run->steps()->orderBy('id')->get();

        // Fichiers : la comparaison faite par Kekeli avant et après la demande ; sinon les actions de l'agent.
        // Ils ne s'affichent que dans la vue « Code ».
        $files = collect((array) data_get($run->changes, 'fichiers', []))
            ->map(fn ($f) => ['chemin' => (string) ($f['chemin'] ?? ''), 'etat' => (string) ($f['etat'] ?? 'modifie')]);
        if ($files->isEmpty()) {
            $files = $steps->whereIn('kind', ['fichier_ecrit', 'fichier_modifie'])->pluck('target')->filter()->unique()
                ->map(fn ($path) => ['chemin' => $path, 'etat' => 'modifie'])->values();
        }

        // Tests et vérifications : gardés pour l'administration et le mode développeur, jamais dans le message.
        $tests = $steps->where('kind', 'commande')->pluck('target')
            ->filter(fn ($command) => (bool) preg_match(self::TEST_COMMANDS, (string) $command))
            ->map(fn ($command) => trim(Str::before(Str::afterLast((string) $command, '&& '), ' 2>')))
            ->unique()->take(8)->map(fn ($command) => ['text' => $command, 'ok' => null])->values();
        foreach ($steps as $step) {
            if (in_array($step->kind, ['qualite_ok', 'licences_ok', 'typographie', 'tests_ok', 'securite_ok', 'application_ok', 'qualite_probleme', 'licences_probleme', 'tests_echec', 'securite_probleme', 'application_erreur'], true)) {
                $tests->push(['text' => __(AgentStep::LABELS[$step->kind]), 'ok' => $step->tone() === 'danger' ? false : true]);
            }
        }

        // Ce qui n'a pas marché : gardé tel quel pour l'administration ; le client lit une seule phrase polie.
        $problems = collect(self::lines($sections['problems'] ?? ''))->reject(fn ($line) => preg_match('/^(rien|aucun|aucune|néant|neant)\b/iu', $line));
        if ($failed) {
            $problems->prepend(__('The request could not be finished: :reason', ['reason' => $failure ?: __('technical problem')]));
        }
        foreach ($steps->whereIn('kind', ['installation_echec', 'tests_echec', 'securite_probleme', 'application_erreur', 'echec_sauvegarde']) as $step) {
            $problems->push(__(AgentStep::LABELS[$step->kind]).($step->target ? ' : '.$step->target : '.'));
        }

        $done = trim($sections['done'] ?? '') !== '' ? $sections['done'] : self::withoutSections($answer);
        $done = self::text($done);
        // Une demande qui n'est pas allée au bout : ce que Kekeli avait déjà fait, d'après ses phrases de travail.
        if ($done === '' && $failed && ($said = $steps->where('kind', 'narration')->pluck('target')->filter())->isNotEmpty()) {
            $done = self::plain(__('Before stopping, Kekeli had done this:').' '.$said->take(6)->implode(' '));
        }
        if ($done === '') {
            $done = $failed ? __('Kekeli could not finish this request.') : __('It is done: your site is up to date.');
        }

        $note = match (true) {
            $failed => __('Sorry, part of the work could not be finished. You can ask Kekeli to try again.'),
            $problems->isNotEmpty() => __('A few points could not be finished. If you wish, Kekeli can look at them again.'),
            default => null,
        };

        return [
            'done' => $done,
            'note' => $note,
            'files' => $files->take(100)->values()->all(),
            'files_count' => (int) data_get($run->changes, 'nombre_fichiers', $files->count()),
            'tests' => $tests->values()->all(),
            'problems' => $problems->take(10)->map(fn ($line) => Str::limit($line, 400))->values()->all(),
        ];
    }

    /**
     * Text for the client, in simple words: no emoji, no non-breaking space, no markdown, no technical sentence,
     * no line written twice. Shared by the final message and the sentences Kekeli says while it works.
     */
    public static function plain(?string $text, int $limit = 700): string
    {
        $text = self::clean((string) $text);
        $sentences = collect(preg_split('/\R+/u', $text))
            ->map(fn ($line) => trim(preg_replace('/^\s*([-*•>]|\d+[.)])\s+/u', '', $line)))
            ->filter()
            // Une phrase par élément : « Le menu est prêt. Les prix sont en FCFA. » donne deux phrases.
            ->flatMap(fn ($line) => preg_split('/(?<=[.!?…])\s+(?=\p{Lu})/u', $line))
            ->map(fn ($sentence) => trim($sentence))
            ->filter(fn ($sentence) => $sentence !== '' && ! preg_match(self::TECHNICAL, $sentence))
            ->unique(fn ($sentence) => mb_strtolower(rtrim($sentence, ' .!')))
            ->values();

        return Str::limit($sentences->implode(' '), $limit);
    }

    /**
     * The final message for the client, keeping its shape: paragraphs separated by an empty line and list items
     * starting with « - ». Same cleaning as plain(): no emoji, no markdown, no technical sentence, no line twice.
     */
    public static function text(?string $text, int $limit = 1200, bool $filter = true): string
    {
        $out = [];
        $seen = [];
        $length = 0;
        foreach (preg_split('/\R/u', self::clean((string) $text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($out && end($out) !== '') {
                    $out[] = '';
                }

                continue;
            }
            $item = (bool) preg_match('/^([-*•]|\d+[.)])\s+/u', $line);
            $line = trim(preg_replace('/^([-*•>]|\d+[.)])\s+/u', '', $line));
            $sentences = collect(preg_split('/(?<=[.!?…])\s+(?=\p{Lu})/u', $line))
                ->map(fn ($sentence) => trim($sentence))
                ->filter(fn ($sentence) => $sentence !== '' && ! ($filter && preg_match(self::TECHNICAL, $sentence)));
            $line = $sentences->implode(' ');
            $key = mb_strtolower(rtrim($line, ' .!'));
            if ($line === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($length + mb_strlen($line) > $limit) {
                break;
            }
            $length += mb_strlen($line);
            // Une ligne de texte qui suit une liste commence un nouveau paragraphe.
            if (! $item && $out && str_starts_with((string) end($out), '- ')) {
                $out[] = '';
            }
            $out[] = $item ? '- '.$line : $line;
        }

        return trim(implode("\n", $out));
    }

    /**
     * The final message cut into blocks for a safe display (escaped by Blade, never raw HTML): paragraphs and lists.
     * An answer to a question keeps all its sentences ($filter false): only the shape is read.
     *
     * @return list<array{type: 'paragraph', lines: list<string>}|array{type: 'list', items: list<string>}>
     */
    public static function blocks(?string $text, bool $filter = true): array
    {
        $blocks = [];
        foreach (preg_split('/\R/u', self::text($text, $filter ? 1200 : 6000, $filter)) as $line) {
            $last = array_key_last($blocks);
            if ($line === '') {
                $blocks[] = ['type' => 'break'];
            } elseif (str_starts_with($line, '- ')) {
                if ($last !== null && $blocks[$last]['type'] === 'list') {
                    $blocks[$last]['items'][] = substr($line, 2);
                } else {
                    $blocks[] = ['type' => 'list', 'items' => [substr($line, 2)]];
                }
            } elseif ($last !== null && $blocks[$last]['type'] === 'paragraph') {
                $blocks[$last]['lines'][] = $line;
            } else {
                $blocks[] = ['type' => 'paragraph', 'lines' => [$line]];
            }
        }

        return array_values(array_filter($blocks, fn ($block) => $block['type'] !== 'break'));
    }

    /**
     * A line of text for the page, escaped first, with French typography: a non-breaking space before ? ! : ; » and
     * after «, so that a sign is never alone at the start of a line. Only the display changes, never the stored text.
     */
    public static function display(string $line): HtmlString
    {
        $html = e($line);
        $html = preg_replace('/ ([?!:;»])/u', '&nbsp;$1', $html);
        $html = preg_replace('/« /u', '«&nbsp;', $html);

        return new HtmlString($html);
    }

    /**
     * Spaces and signs a client should never see: non-breaking spaces become ordinary spaces, emoji and markdown go.
     */
    public static function clean(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}", "\u{2060}", "\u{200B}"], ' ', $text);
        $text = str_replace(['&nbsp;', '&#160;', '&#8239;'], ' ', $text);
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', '', $text);
        $text = preg_replace('/^\s*#{1,6}\s*/mu', '', $text);
        $text = str_replace(['**', '__'], '', $text);

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $text));
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
