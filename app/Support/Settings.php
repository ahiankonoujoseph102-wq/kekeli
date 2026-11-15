<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Settings that the administrator can change without touching the code.
 * A setting never changed in the administration keeps its default from config/kekeli.php (« settings »).
 */
class Settings
{
    private const CACHE_KEY = 'kekeli.settings';

    /**
     * The settings read once per request (chantier « clics instantanés », 15 novembre 2026): a page reads about 160
     * settings, and each reading of the cache was a query to the database (the cache lives in the database).
     *
     * @var array<string, mixed>|null
     */
    private ?array $stored = null;

    public function get(string $key): mixed
    {
        $stored = $this->stored ??= Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $stored) ? $stored[$key] : config('kekeli.settings.'.$key);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->forget();
    }

    /**
     * Back to the default value of the configuration.
     */
    public function reset(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
        $this->forget();
    }

    /** Forgets the settings read, here and in the shared cache. */
    public function forget(): void
    {
        $this->stored = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }
}
