<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
     * Remove the file with the photo.
     */
    public function deleteWithFile(): void
    {
        Storage::disk(self::disk())->delete($this->path);
        $this->delete();
    }
}
