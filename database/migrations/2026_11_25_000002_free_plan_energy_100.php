<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Formule Gratuite : 100 FCFA d'énergie par jour au lieu de 30 (décision du porteur du projet du 15 novembre 2026).
 * Tous les comptes lisent l'énergie de leur formule dans les réglages : il suffit de changer la valeur enregistrée.
 * Une formule jamais enregistrée dans l'administration prend déjà la nouvelle valeur de config/kekeli.php ; une
 * formule enregistrée avec l'ancienne valeur (30) passe à 100 ; une autre valeur, choisie dans l'administration,
 * n'est jamais changée. Relancée, elle ne change rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        // L'équivalence affichée d'un site vitrine gratuit : 55 FCFA (Gemini) devient l'estimation avec GPT-6.1 Terra.
        if ((float) json_decode((string) DB::table('settings')->where('key', 'free_site_estimate_fcfa')->value('value'), true) === 55.0) {
            DB::table('settings')->where('key', 'free_site_estimate_fcfa')->delete();
            Cache::forget('kekeli.settings');
        }

        $value = DB::table('settings')->where('key', 'plans')->value('value');
        $plans = $value === null ? null : json_decode($value, true);
        if (! is_array($plans) || ! isset($plans['gratuite']) || (float) ($plans['gratuite']['energy_fcfa'] ?? 0) !== 30.0) {
            return;
        }

        $plans['gratuite']['energy_fcfa'] = 100;
        DB::table('settings')->where('key', 'plans')->update(['value' => json_encode($plans)]);
        Cache::forget('kekeli.settings');
    }

    public function down(): void
    {
        // Rien à défaire : l'énergie se règle ensuite dans Administration → Réglages.
    }
};
