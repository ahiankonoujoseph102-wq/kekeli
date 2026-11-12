<?php

namespace App\Github;

use App\Support\SecretScanner;

/**
 * What Kekeli never sends to GitHub: the .env files (only .env.example is kept), the test databases, the folders of
 * dependencies, and any file in which a key is found. The files are those of the last backup of the project.
 */
class GithubFiles
{
    /**
     * @param  array<string, string>  $files
     * @return array{files: array<string, string>, skipped: list<string>}
     */
    public static function clean(array $files): array
    {
        $kept = [];
        $skipped = [];
        foreach ($files as $path => $content) {
            if (self::forbidden($path) || SecretScanner::redact($content)[1]) {
                $skipped[] = $path;

                continue;
            }
            $kept[$path] = $content;
        }

        return ['files' => $kept, 'skipped' => $skipped];
    }

    public static function forbidden(string $path): bool
    {
        $name = basename($path);
        if (preg_match('#(^|/)(node_modules|vendor|\.venv|venv|__pycache__|\.next|\.git)/#', $path)) {
            return true;
        }
        if (str_starts_with($name, '.env') && $name !== '.env.example') {
            return true;
        }

        // Bases d'essai locales (SQLite, PGlite) et fichiers de clés.
        return (bool) preg_match('/\.(sqlite3?|db|sqlite-journal|pem|key|p12)$/i', $name) || str_contains($path, 'pglite') || $name === 'secrets.json';
    }
}
