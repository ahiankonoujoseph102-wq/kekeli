<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plafond de dépense OpenAI par jour : 1 $ → 10 $ (accord du porteur du projet du 15 novembre 2026, quand OpenAI passe
 * en premier et Coarena en dernier). Seule l'ancienne valeur enregistrée (1) est remplacée ; une valeur choisie à la
 * main dans Administration → Réglages n'est jamais changée. Sans danger si elle est relancée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'openai_daily_cap_usd')->value('value');
        if ($stored !== null && (float) json_decode((string) $stored, true) === 1.0) {
            DB::table('settings')->where('key', 'openai_daily_cap_usd')->update(['value' => json_encode(10.0)]);
            app(Settings::class)->forget();
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'ancienne valeur n'est jamais remise toute seule.
    }
};
