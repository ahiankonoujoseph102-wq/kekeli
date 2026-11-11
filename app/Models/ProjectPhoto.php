<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A photo sent by the client from their phone (a dish, an article, the shop…), used first by the agent.
 * The file is kept in the private storage of the site and copied into the project's computer.
 */
class ProjectPhoto extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'path',
        'size',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function disk(): string
    {
        return (string) config('kekeli.backups.disk', 'local');
    }

    /**
     * Keep a photo chosen on the phone for a project, under a simple name (for example « plat-du-jour.jpg »).
     */
    public static function store(Project $project, UploadedFile $upload): self
    {
        $directory = config('kekeli.client_photos.directory');
        $extension = strtolower($upload->getClientOriginalExtension() ?: (string) $upload->extension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $base = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'photo';
        $name = Str::limit($base, 60, '').'.'.$extension;

        for ($i = 2; $project->photos()->where('name', $name)->exists(); $i++) {
            $name = Str::limit($base, 55, '').'-'.$i.'.'.$extension;
        }

        $path = $upload->storeAs($directory.'/'.$project->id, Str::random(20).'.'.$extension, self::disk());

        return $project->photos()->create(['name' => $name, 'path' => $path, 'size' => $upload->getSize()]);
    }

    /**
     * Remove the file with the photo.
     */
    public function deleteWithFile(): void
    {
        Storage::disk(self::disk())->delete($this->path);
        $this->delete();
    }
}
