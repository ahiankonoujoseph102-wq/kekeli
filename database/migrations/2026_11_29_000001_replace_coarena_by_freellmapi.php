<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Coarena est remplacé par FreeLLMAPI (décision du porteur du projet du 15 novembre 2026, docs/plan.md, partie 26.38).
 * Les réglages propres à Coarena sont retirés (basculement entre comptes, clés éteintes, contrôles des clés, modèles
 * Coarena par tâche et mode économie) ; l'interrupteur « coarena » est retiré des fournisseurs, FreeLLMAPI y est ajouté
 * sans toucher aux interrupteurs OpenAI et offres gratuites. L'historique (consommation, demandes) n'est jamais touché,
 * et les clés Coarena restent dans l'environnement du Runner (elles ne sont simplement plus lues).
 * Sans danger si elle est relancée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', ['coarena_multi_accounts', 'coarena_disabled_keys', 'coarena_key_checks', 'model_routes', 'economy_below_percent'])->delete();

        $stored = DB::table('settings')->where('key', 'model_providers')->value('value');
        if ($stored !== null) {
            $switches = (array) json_decode((string) $stored, true);
            unset($switches['coarena']);
            $switches['freellmapi'] ??= true;
            DB::table('settings')->where('key', 'model_providers')->update(['value' => json_encode($switches)]);
        }

        app(Settings::class)->forget();
    }

    public function down(): void
    {
        // Rien à défaire : Coarena n'est plus dans le code.
    }
};
