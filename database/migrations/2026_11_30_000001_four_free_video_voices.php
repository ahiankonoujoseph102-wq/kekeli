<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voix off des vidéos (15 novembre 2026) : quatre voix gratuites de Gemini au lieu de deux (chacune a son propre quota
 * gratuit). Seule l'ancienne liste par défaut enregistrée est remplacée ; une liste choisie à la main dans
 * Administration → Réglages n'est jamais changée. Sans danger si elle est relancée.
 */
return new class extends Migration
{
    private const OLD = [
        ['modele' => 'gemini-3.8-flash-tts', 'voix' => 'Kore'],
        ['modele' => 'gemini-3.8-flash-lite-tts', 'voix' => 'Kore'],
    ];

    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'video_free_voices')->value('value');
        if ($stored !== null && json_decode((string) $stored, true) === self::OLD) {
            DB::table('settings')->where('key', 'video_free_voices')->update(['value' => json_encode(config('kekeli.settings.video_free_voices'))]);
            app(Settings::class)->forget();
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'ancienne valeur n'est jamais remise toute seule.
    }
};
