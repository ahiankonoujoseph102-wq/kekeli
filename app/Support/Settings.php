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

    public function get(string $key): mixed
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $stored) ? $stored[$key] : config('kekeli.settings.'.$key);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Back to the default value of the configuration.
     */
    public function reset(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
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
