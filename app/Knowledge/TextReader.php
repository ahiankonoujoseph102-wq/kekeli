<?php

namespace App\Knowledge;

/**
 * The plain texts (TXT, Markdown, CSV, JSON, HTML) are read by the site itself: no program runs on them, only text
 * functions. Word, PDF and PowerPoint are read in the isolated computer of the Runner (RunnerClient::knowledgeExtract).
 */
class TextReader
{
    /** @return list<array{page: ?int, text: string, title?: bool}> */
    public static function read(string $bytes, string $format): array
    {
        $text = self::utf8($bytes);
        if ($format === 'html') {
            $text = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $text) ?? $text;
            $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)[^>]*>#i', "\n", $text) ?? $text;
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($format === 'json') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $text = (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if ($format === 'md') {
            // Un titre collé à son texte devient un bloc à part.
            $text = preg_replace('/^(#{1,6}\s+.+)$/mu', "\n$1\n", $text) ?? $text;
        }

        $units = [];
        foreach (preg_split('/\n\s*\n/u', $text) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            // Un titre Markdown (« ## Congés ») commence un nouveau passage.
            if ($format === 'md' && preg_match('/^(#{1,6})\s+(.+)$/u', $block, $match) && ! str_contains($block, "\n")) {
                $units[] = ['page' => null, 'text' => trim($match[2]), 'title' => true];

                continue;
            }
            $units[] = ['page' => null, 'text' => $block];
        }

        return $units;
    }

    /** Any text becomes valid UTF-8 (a file saved in Windows-1252 keeps its accents). */
    private static function utf8(string $bytes): string
    {
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes) ?? $bytes;
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        return mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }
}
