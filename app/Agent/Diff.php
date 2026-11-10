<?php

namespace App\Agent;

/**
 * Differences of a request (« git diff »), cut into files and lines, to show simply what changed.
 */
class Diff
{
    /**
     * @return array<string, list<array{type: string, text: string}>> Path → lines (« add », « remove », « same », « gap »).
     */
    public static function files(?string $diff): array
    {
        $files = [];
        $current = null;
        foreach (preg_split('/\R/u', (string) $diff) as $line) {
            if (preg_match('#^diff --git a/(.+?) b/(.+)$#', $line, $match)) {
                $current = $match[2];
                $files[$current] = [];

                continue;
            }
            if ($current === null || preg_match('/^(index |--- |\+\+\+ |new file mode|deleted file mode|similarity index|rename (from|to)|old mode|new mode)/', $line)) {
                continue;
            }
            if (str_starts_with($line, 'Binary files')) {
                $files[$current][] = ['type' => 'binary', 'text' => ''];

                continue;
            }
            $files[$current][] = match (true) {
                str_starts_with($line, '@@') => ['type' => 'gap', 'text' => '…'],
                str_starts_with($line, '+') => ['type' => 'add', 'text' => substr($line, 1)],
                str_starts_with($line, '-') => ['type' => 'remove', 'text' => substr($line, 1)],
                str_starts_with($line, '\\') => ['type' => 'gap', 'text' => ''],
                default => ['type' => 'same', 'text' => substr($line, 1)],
            };
        }

        return $files;
    }
}
