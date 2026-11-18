<?php

namespace App\Knowledge;

use App\Models\KnowledgeDocument;
use App\Models\ProjectPhoto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The links of the knowledge base (18 novembre 2026): a public Google Drive file (« Tous les utilisateurs disposant
 * du lien ») or a public YouTube video. Only the identifier is taken from the link: the addresses called are always
 * built by Kekeli on Google's own hosts, never an address written by the client.
 */
class KnowledgeLinks
{
    /** Google Docs, Sheets and Slides are exported in a format Kekeli reads. */
    private const EXPORTS = [
        'document' => ['https://docs.google.com/document/d/%s/export?format=docx', 'docx'],
        'spreadsheets' => ['https://docs.google.com/spreadsheets/d/%s/export?format=csv', 'csv'],
        'presentation' => ['https://docs.google.com/presentation/d/%s/export/pptx', 'pptx'],
    ];

    /** MIME types of the files of Drive that Kekeli can read. */
    private const TYPES = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt', 'text/markdown' => 'md', 'text/csv' => 'csv', 'application/json' => 'json', 'text/html' => 'html',
    ];

    /**
     * What a link is: a YouTube video, a Drive file (or a Google Docs, Sheets, Slides), a Drive folder, or nothing known.
     *
     * @return array{type: string, id?: string, kind?: string, url?: string}|null
     */
    public static function parse(string $link): ?array
    {
        $link = trim($link);
        if (! preg_match('#^https?://#i', $link) || mb_strlen($link) > 500) {
            return null;
        }
        $host = Str::lower((string) parse_url($link, PHP_URL_HOST));
        $path = (string) parse_url($link, PHP_URL_PATH);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            $id = match (true) {
                $host === 'youtu.be' => trim($path, '/'),
                str_starts_with($path, '/shorts/'), str_starts_with($path, '/live/'), str_starts_with($path, '/embed/') => explode('/', trim($path, '/'))[1] ?? '',
                default => (string) ($query['v'] ?? ''),
            };

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['type' => KnowledgeDocument::SOURCE_YOUTUBE, 'id' => $id, 'url' => 'https://www.youtube.com/watch?v='.$id] : null;
        }

        if (in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            if (preg_match('#/drive/(?:u/\d+/)?folders/#', $path)) {
                return ['type' => 'dossier'];
            }
            if (preg_match('#^/(document|spreadsheets|presentation)/d/([A-Za-z0-9_-]{20,})#', $path, $match)) {
                return ['type' => KnowledgeDocument::SOURCE_DRIVE, 'kind' => $match[1], 'id' => $match[2], 'url' => sprintf('https://docs.google.com/%s/d/%s', $match[1], $match[2])];
            }
            $id = preg_match('#/file/d/([A-Za-z0-9_-]{20,})#', $path, $match) ? $match[1] : (string) ($query['id'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]{20,}$/', $id)) {
                return ['type' => KnowledgeDocument::SOURCE_DRIVE, 'kind' => 'file', 'id' => $id, 'url' => 'https://drive.google.com/file/d/'.$id];
            }
        }

        return null;
    }

    /** The title of a public YouTube video (null: private, removed or unknown). */
    public function youtubeTitle(string $url): ?string
    {
        $response = rescue(fn () => Http::timeout(15)->get('https://www.youtube.com/oembed', ['format' => 'json', 'url' => $url]), null, report: false);

        return $response?->successful() ? Str::limit(trim((string) $response->json('title')), 150, '') ?: null : null;
    }

    /**
     * Downloads a public Drive file into the storage of the space, at most $maxKilobytes. The document gets its real
     * name, format and size.
     *
     * @throws KnowledgeException « drive_prive », « format », « trop_grand »
     * @throws \RuntimeException when Google cannot be reached (the task tries again)
     */
    public function downloadDrive(KnowledgeDocument $document, int $maxKilobytes): void
    {
        $link = self::parse((string) $document->source_url);
        if (($link['type'] ?? null) !== KnowledgeDocument::SOURCE_DRIVE) {
            throw new KnowledgeException('format');
        }
        [$address, $format] = isset(self::EXPORTS[$link['kind']])
            ? [sprintf(self::EXPORTS[$link['kind']][0], $link['id']), self::EXPORTS[$link['kind']][1]]
            : ['https://drive.usercontent.google.com/download?id='.$link['id'].'&export=download&confirm=t', null];

        $temporary = tempnam(sys_get_temp_dir(), 'kekeli-drive-');
        try {
            $response = Http::timeout(300)->withOptions(['sink' => $temporary, 'allow_redirects' => ['max' => 5]])->get($address);
            if ($response->status() >= 500) {
                throw new \RuntimeException('Google Drive ne répond pas pour le moment.');
            }
            $type = Str::lower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            // Un fichier privé ou introuvable : Google renvoie sa page de connexion (HTML) ou une erreur.
            // Un vrai fichier arrive toujours en pièce jointe ; une page HTML sans pièce jointe est la page de connexion.
            $attachment = str_contains(Str::lower((string) $response->header('Content-Disposition')), 'attachment');
            if (! $response->successful() || ($type === 'text/html' && ! $attachment)) {
                throw new KnowledgeException('drive_prive');
            }
            $size = (int) filesize($temporary);
            if ($size > $maxKilobytes * 1024) {
                throw new KnowledgeException('trop_grand');
            }
            $name = self::fileName((string) $response->header('Content-Disposition'));
            $format ??= self::TYPES[$type] ?? Str::lower(pathinfo((string) $name, PATHINFO_EXTENSION));
            if (! in_array($format, KnowledgeDocument::extensions(), true)) {
                throw new KnowledgeException('format');
            }
            $path = 'connaissances/'.$document->knowledge_space_id.'/'.Str::random(32).'.'.$format;
            Storage::disk(ProjectPhoto::disk())->put($path, fopen($temporary, 'r'));
            if (filled($document->path) && $document->path !== $path) {
                Storage::disk(ProjectPhoto::disk())->delete($document->path);
            }
            $document->forceFill([
                'name' => Str::limit($name ?: $document->name, 150, ''),
                'format' => $format,
                'size' => $size,
                'path' => $path,
            ])->save();
        } finally {
            @unlink($temporary);
        }
    }

    /** The name of the file given by Google (« filename* » in UTF-8 first). */
    private static function fileName(string $disposition): ?string
    {
        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $disposition, $match)) {
            $name = rawurldecode($match[1]);
        } elseif (preg_match('/filename="?([^";]+)"?/i', $disposition, $match)) {
            $name = $match[1];
        } else {
            return null;
        }
        $name = trim(preg_replace('/[^\pL\pN ._()\'-]/u', '', $name) ?? '');

        return $name !== '' ? $name : null;
    }
}
