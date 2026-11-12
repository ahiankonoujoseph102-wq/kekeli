<?php

namespace App\Agent;

use App\Models\Project;
use RuntimeException;
use ZipArchive;

/**
 * The files of a project, read in its last backup (the same files as the published site): to download them in a
 * ZIP file or to send them to the client's GitHub account. Hidden files and technical folders are left out.
 */
class ProjectFiles
{
    private const IGNORED = ['node_modules', '.git', '.kekeli'];

    public const MAX_FILES = 2000;

    public const MAX_BYTES = 100 * 1024 * 1024;

    public function __construct(private ProjectBackup $backups) {}

    /**
     * @return array<string, string> Path (with « / ») → content.
     */
    public function read(Project $project, ?bool $hidden = null): array
    {
        if (! $this->backups->exists($project)) {
            throw new RuntimeException('Le projet n\'a pas encore de sauvegarde.');
        }

        $tar = gzdecode($this->backups->disk()->get($this->backups->path($project->id)));
        if ($tar === false) {
            throw new RuntimeException('La sauvegarde est illisible.');
        }

        $files = [];
        $total = 0;
        foreach ($this->entries($tar) as $path => $content) {
            $path = preg_replace('#^(\./)+#', '', str_replace('\\', '/', $path));
            $parts = explode('/', $path);

            // Un dépôt GitHub et une application web gardent leurs fichiers cachés (.gitignore, .env.example,
            // .htaccess…) ; un site, non. Jamais de fichier de secrets (.env) ni de base de données d'essai.
            $keepHidden = $hidden ?? ($project->isRepository() || $project->isWebApp());
            if ($path === '' || collect($parts)->contains(fn ($part) => $part === '' || $part === '..' || (! $keepHidden && str_starts_with($part, '.')) || in_array($part, self::IGNORED, true))) {
                continue;
            }
            if (self::isSecretOrTestData(end($parts))) {
                continue;
            }

            $total += strlen($content);
            if (count($files) >= ($project->isRepository() || $project->isWebApp() ? self::MAX_FILES * 10 : self::MAX_FILES) || $total > self::MAX_BYTES) {
                throw new RuntimeException('Le projet est trop gros pour être envoyé.');
            }

            $files[$path] = $content;
        }

        ksort($files);

        return $files;
    }

    /**
     * Files never given to the client's GitHub or ZIP: secrets (.env, .env.local…) and test databases.
     */
    public static function isSecretOrTestData(string $name): bool
    {
        return (bool) preg_match('/^\.env(\..+)?$/', $name) && $name !== '.env.example'
            || (bool) preg_match('/\.(sqlite|sqlite3|sqlite-journal|db)$/', $name);
    }

    /**
     * Regular files of a « tar » archive (formats ustar, GNU and pax, the ones made by the « tar » command).
     *
     * @return \Generator<string, string>
     */
    private function entries(string $tar): \Generator
    {
        $offset = 0;
        $length = strlen($tar);
        $longName = null;

        while ($offset + 512 <= $length) {
            $header = substr($tar, $offset, 512);
            if (trim($header, "\0") === '') {
                break;
            }

            $name = rtrim(substr($header, 0, 100), "\0");
            $size = octdec(trim(substr($header, 124, 12), "\0 ") ?: '0');
            $type = $header[156];
            $prefix = rtrim(substr($header, 345, 155), "\0");
            if (substr($header, 257, 5) === 'ustar' && $prefix !== '') {
                $name = $prefix.'/'.$name;
            }

            $content = substr($tar, $offset + 512, (int) $size);
            $offset += 512 + (int) (ceil($size / 512) * 512);

            if ($type === 'L') {
                $longName = rtrim($content, "\0");

                continue;
            }
            if ($type === 'x') {
                if (preg_match('/^\d+ path=(.*)$/m', $content, $match)) {
                    $longName = $match[1];
                }

                continue;
            }

            if ($longName !== null) {
                $name = $longName;
                $longName = null;
            }

            if ($type === '0' || $type === "\0") {
                yield $name => $content;
            }
        }
    }

    /**
     * A ZIP file with the code of the project, written in a temporary file (deleted after the download).
     */
    public function zip(Project $project): string
    {
        $files = $this->read($project);
        $path = tempnam(sys_get_temp_dir(), 'kekeli-zip-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Le fichier ZIP n\'a pas pu être créé.');
        }

        // Le code d'une application arrive toujours avec son LISEZ-MOI en français (installer, variables, lancer).
        if ($project->isWebApp() && ! isset($files['LISEZ-MOI.md'])) {
            $files['LISEZ-MOI.md'] = self::readMe($project);
        }

        $folder = $project->pagesName();
        foreach ($files as $name => $content) {
            $zip->addFromString($folder.'/'.$name, $content);
        }
        $zip->close();

        return $path;
    }

    /**
     * LISEZ-MOI.md in French for a project made before Kekeli wrote one (the agent keeps it up to date afterwards).
     */
    public static function readMe(Project $project): string
    {
        $laravel = $project->app_stack !== 'nextjs';

        return implode("\n", [
            '# '.$project->name,
            '',
            'Application web faite avec Kekeli. Son code vous appartient : vous pouvez l\'héberger où vous voulez.',
            '',
            '## Installer',
            '```bash',
            $laravel ? "composer install\ncp .env.example .env\nphp artisan key:generate" : "npm install\ncp .env.example .env",
            '```',
            '',
            '## Variables à remplir',
            'Ouvrez le fichier `.env` et remplissez chaque ligne vide (chaque variable est expliquée dans `.env.example`). Ne mettez jamais ces valeurs dans le code ni sur GitHub.',
            '',
            '## Lancer',
            '```bash',
            $laravel ? "php artisan migrate\nphp artisan serve" : 'npm run dev',
            '```',
            '',
            '## Tests',
            '```bash',
            $laravel ? 'php artisan test' : 'npm test',
            '```',
            '',
        ]);
    }
}
