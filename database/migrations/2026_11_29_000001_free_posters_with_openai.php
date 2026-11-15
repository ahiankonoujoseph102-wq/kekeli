<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Affiches et images de la formule Gratuite : GPT Image 2.6 « flare » d'abord, comme les formules payantes (banc du
 * 15 novembre 2026, docs/captures/fonctions.md : aucun modèle gratuit n'atteint 8 sur 10), FLUX.1 schnell gratuit en
 * secours. Seule l'ancienne valeur de départ (« cloudflare/flux-1-schnell ») est remplacée ; un choix fait à la main
 * dans Administration → Réglages n'est jamais changé. Sans danger si elle est relancée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'plans')->value('value');
        $plans = $stored !== null ? json_decode((string) $stored, true) : null;
        if (! is_array($plans) || ! is_array($plans['gratuite'] ?? null) || ($plans['gratuite']['image_model'] ?? null) !== 'cloudflare/flux-1-schnell') {
            return;
        }
        $plans['gratuite']['image_model'] = 'openai/gpt-image-2.6-flare';
        DB::table('settings')->where('key', 'plans')->update(['value' => json_encode($plans)]);
        app(Settings::class)->forget();
    }

    public function down(): void
    {
        // Rien à défaire : l'ancienne valeur n'est jamais remise toute seule.
    }
};
