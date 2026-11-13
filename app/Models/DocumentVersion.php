<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One version of a document or a presentation: its files (Word, PowerPoint, PDF), the images of its pages for the
 * preview, and the structure written by the model (to make the next version). Older versions stay downloadable.
 */
class DocumentVersion extends Model
{
    protected $fillable = ['agent_run_id', 'number', 'title', 'request', 'files', 'previews', 'pages', 'structure'];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'previews' => 'array',
            'pages' => 'integer',
            'number' => 'integer',
            'structure' => 'array',
        ];
    }

    /** Formats that can be downloaded, in the order shown to the client. */
    public const FORMATS = ['docx' => 'Word', 'pptx' => 'PowerPoint', 'png' => 'PNG', 'pdf' => 'PDF'];

    /** Names of the files the Runner can send for a version: never a path. */
    public const FILE_NAMES = '/^(document\.(docx|pdf)|presentation\.(pptx|pdf)|image\.(png|pdf)|fond\.(png|jpg)|source\.pdf|apercu-\d{1,3}\.jpg)$/';

    /**
     * The format of an image, as the client says it: « Carré (Facebook, Instagram) », « A4 (à imprimer) ».
     */
    public static function imageFormatLabel(?string $format): string
    {
        return match ($format) {
            'vertical' => __('Vertical (WhatsApp status, TikTok)'),
            'paysage' => __('Landscape (banner)'),
            'a4' => __('A4 (to print)'),
            'a3' => __('A3 (to print)'),
            default => __('Square (Facebook, Instagram)'),
        };
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Folder of the files of this version on the disk of the backups (one folder per request of the client).
     */
    public static function folder(Project $project, string $runId): string
    {
        return 'documents/'.$project->id.'/'.$runId;
    }

    public function path(string $name): string
    {
        return self::folder($this->project, (string) $this->agent_run_id).'/'.$name;
    }

    /**
     * @return array<string, string> format => label, only those of this version
     */
    public function formats(): array
    {
        return array_intersect_key(self::FORMATS, (array) $this->files);
    }

    /**
     * Name of the downloaded file: « lettre-de-motivation-v2.docx ».
     */
    public function downloadName(string $format): string
    {
        $base = Str::slug($this->title ?: $this->project->name) ?: 'document';

        return $base.($this->number > 1 ? '-v'.$this->number : '').'.'.$format;
    }
}
