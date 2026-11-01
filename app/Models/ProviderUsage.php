<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Requests, refusals for reached limits and tokens, per AI provider, model and quota day.
 */
class ProviderUsage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'day',
        'provider',
        'model',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
        ];
    }
}
