<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A file joined by the client with « + »: a Word, PDF or PowerPoint document to correct, summarize, translate or
 * transform (read only in the isolated virtual computer, never on the Runner nor on the site), or a video or a sound for
 * a video (cut, assembled, its voice transcribed in the isolated video computer).
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

    public const LABELS = ['docx' => 'Word', 'pptx' => 'PowerPoint', 'pdf' => 'PDF', 'mp4' => 'Vidéo', 'mov' => 'Vidéo', 'webm' => 'Vidéo', 'mp3' => 'Audio', 'm4a' => 'Audio', 'wav' => 'Audio', 'ogg' => 'Audio'];

    /** The documents (read in the isolated computer) and the media of a video (videos and sounds of the client). */
    public const DOCUMENTS = ['docx', 'pdf', 'pptx'];

    public const VIDEOS = ['mp4', 'mov', 'webm'];

    public const SOUNDS = ['mp3', 'm4a', 'wav', 'ogg'];

    /** Largest file joined (kilobytes): a document, then a video or a sound. */
    public const MAX_KILOBYTES = 20480;

    public const MAX_MEDIA_KILOBYTES = 51200;

    /** All the extensions accepted by « Ajouter un fichier ». */
    public static function extensions(): array
    {
        return [...self::DOCUMENTS, ...self::VIDEOS, ...self::SOUNDS];
    }

    /** A document (Word, PDF, PowerPoint) stays under 20 MB; a video or a sound under 50 MB. */
    public static function documentSizeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value instanceof UploadedFile && in_array(strtolower($value->getClientOriginalExtension()), self::DOCUMENTS, true) && $value->getSize() > self::MAX_KILOBYTES * 1024) {
                $fail(__('validation.max.file', ['attribute' => __('file'), 'max' => self::MAX_KILOBYTES]));
            }
        };
    }

    public function isMedia(): bool
    {
        return in_array($this->format, [...self::VIDEOS, ...self::SOUNDS], true);
    }

    /** « video » or « audio » for a media, null for a document. */
    public function mediaType(): ?string
    {
        return in_array($this->format, self::VIDEOS, true) ? 'video' : (in_array($this->format, self::SOUNDS, true) ? 'audio' : null);
    }

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
