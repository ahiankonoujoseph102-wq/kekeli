<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A file joined by the client with « + » (Word, PDF or PowerPoint), to correct, summarize, translate or transform. It
 * is read only in the isolated virtual computer, never on the Runner nor on the site.
 */
class ProjectAttachment extends Model
{
    protected $fillable = ['message_id', 'name', 'format', 'size', 'path'];

    /** Formats accepted, with their MIME types. */
    public const FORMATS = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pdf' => 'application/pdf',
    ];

    public const LABELS = ['docx' => 'Word', 'pptx' => 'PowerPoint', 'pdf' => 'PDF'];

    /** Largest file joined (kilobytes). */
    public const MAX_KILOBYTES = 20480;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function store(Project $project, UploadedFile $upload, ?Message $message = null): self
    {
        $format = strtolower($upload->getClientOriginalExtension());
        $name = Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '', $upload->getClientOriginalName()) ?: 'fichier.'.$format, 150, '');
        $path = $upload->storeAs('pieces-jointes/'.$project->id, Str::random(24).'.'.$format, ProjectPhoto::disk());

        return $project->attachments()->create(['name' => $name, 'format' => $format, 'size' => $upload->getSize(), 'path' => $path, 'message_id' => $message?->id]);
    }

    public function label(): string
    {
        return self::LABELS[$this->format] ?? strtoupper($this->format);
    }

    public function deleteWithFile(): void
    {
        Storage::disk(ProjectPhoto::disk())->delete($this->path);
        $this->delete();
    }
}
