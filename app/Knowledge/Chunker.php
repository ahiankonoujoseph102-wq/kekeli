<?php

namespace App\Knowledge;

/**
 * Cuts the text of a document into passages of about 1 000 characters, like the RAG tools: a passage never mixes two
 * pages (its page is cited), starts again at each title of a Word document (and keeps that title in front, which helps
 * the search), never cuts a sentence when it can, and repeats the end of the previous passage (overlap) so that an
 * idea split in two stays findable.
 */
class Chunker
{
    public const TARGET = 1000;

    public const MAX = 1500;

    public const OVERLAP = 150;

    /** A last passage shorter than this joins the previous one (same page). */
    public const MIN = 200;

    /**
     * @param  list<array{page: ?int, text: string, title?: bool}>  $units  pages, slides or paragraphs, in order
     * @return list<array{page: ?int, content: string}>
     */
    public static function split(array $units): array
    {
        $chunks = [];
        $buffer = '';
        $page = null;
        $heading = null;
        $overlap = '';
        $section = false;

        $flush = function () use (&$chunks, &$buffer, &$page, &$heading, &$overlap, &$section) {
            $text = trim($buffer);
            if ($text !== '' && $text !== trim($overlap)) {
                $content = $heading !== null && ! str_contains($text, $heading) ? $heading."\n".$text : $text;
                // « section » : le passage commence une partie titrée ; il ne rejoint jamais le passage précédent.
                $chunks[] = ['page' => $page, 'content' => $content, 'section' => $section];
                $overlap = self::tail($text);
            }
            $buffer = '';
            $section = false;
        };

        foreach ($units as $unit) {
            $text = self::clean((string) ($unit['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $unitPage = isset($unit['page']) && (int) $unit['page'] > 0 ? (int) $unit['page'] : null;
            if ($unitPage !== $page) {
                $flush();
                $page = $unitPage;
                $overlap = '';
            }
            if (! empty($unit['title'])) {
                // Un titre juste sous un autre titre (« Guide », puis « Congés ») : les deux forment le titre de la partie.
                if ($heading !== null && trim($buffer) === $heading) {
                    $heading = mb_substr($heading."\n".$text, 0, 300);
                    $buffer = $heading;

                    continue;
                }
                $flush();
                $heading = mb_substr($text, 0, 200);
                $overlap = '';
                $buffer = $text;
                $section = true;

                continue;
            }
            foreach (self::segments($text) as $segment) {
                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($segment) + 1 > self::TARGET) {
                    $flush();
                    $buffer = $overlap !== '' ? $overlap.' ' : '';
                }
                $buffer .= ($buffer === '' || str_ends_with($buffer, ' ') ? '' : "\n").$segment;
            }
        }
        $flush();

        // Un tout petit dernier passage rejoint le précédent de la même page.
        $merged = [];
        foreach ($chunks as $chunk) {
            $last = array_key_last($merged);
            if ($last !== null && ! $chunk['section'] && $merged[$last]['page'] === $chunk['page'] && mb_strlen($chunk['content']) < self::MIN && mb_strlen($merged[$last]['content']) + mb_strlen($chunk['content']) <= self::MAX) {
                $merged[$last]['content'] .= "\n".$chunk['content'];

                continue;
            }
            $merged[] = $chunk;
        }

        return array_map(fn (array $chunk) => ['page' => $chunk['page'], 'content' => $chunk['content']], $merged);
    }

    /** Paragraphs, then sentences, then words for what is still too long. @return list<string> */
    private static function segments(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n\s*\n|\n/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (mb_strlen($paragraph) <= self::TARGET) {
                $out[] = $paragraph;

                continue;
            }
            foreach (preg_split('/(?<=[.!?…;:])\s+/u', $paragraph) ?: [] as $sentence) {
                while (mb_strlen($sentence) > self::TARGET) {
                    $cut = mb_strrpos(mb_substr($sentence, 0, self::TARGET), ' ') ?: self::TARGET;
                    $out[] = trim(mb_substr($sentence, 0, $cut));
                    $sentence = trim(mb_substr($sentence, $cut));
                }
                if ($sentence !== '') {
                    $out[] = $sentence;
                }
            }
        }

        return $out;
    }

    /** The end of a passage repeated at the start of the next one: its last whole sentences, about 150 characters. */
    private static function tail(string $text): string
    {
        if (mb_strlen($text) <= self::OVERLAP) {
            return '';
        }
        $end = mb_substr($text, -self::OVERLAP);
        $start = preg_match('/[.!?…]\s+(.*)$/us', $end, $match) ? $match[1] : (($space = mb_strpos($end, ' ')) !== false ? mb_substr($end, $space + 1) : $end);

        return trim($start);
    }

    private static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\0"], ["\n", "\n", ' ', ''], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text);
    }
}
