<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bonus de bienvenue et plafond des comptes gratuits (décisions du porteur du projet du 15 novembre 2026).
 * - Chaque compte reçoit une seule fois un bonus d'énergie (500 FCFA au départ, réglable) : les comptes déjà créés
 *   le reçoivent maintenant ; relancée, la migration ne le donne jamais une deuxième fois (« welcome_bonus_at »).
 * - Plafond total par jour des comptes gratuits : 1 000 → 3 000 FCFA. Seule l'ancienne valeur enregistrée (1 000) est
 *   enlevée (la nouvelle valeur de config/kekeli.php s'applique) ; une valeur choisie à la main n'est jamais changée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('welcome_bonus_fcfa', 10, 2)->default(0);
            $table->timestamp('welcome_bonus_at')->nullable();
        });

        $this->fill();
    }

    /**
     * The data part, safe to run again: the bonus for accounts that never had it, and the old free ceiling removed.
     */
    public function fill(): void
    {
        $stored = DB::table('settings')->where('key', 'welcome_bonus_fcfa')->value('value');
        $bonus = $stored === null ? (float) config('kekeli.settings.welcome_bonus_fcfa', 0) : (float) json_decode((string) $stored, true);
        if ($bonus > 0) {
            DB::table('users')->whereNull('welcome_bonus_at')->update(['welcome_bonus_fcfa' => $bonus, 'welcome_bonus_at' => now()]);
        }

        if ((float) json_decode((string) DB::table('settings')->where('key', 'free_daily_cap_fcfa')->value('value'), true) === 1000.0) {
            DB::table('settings')->where('key', 'free_daily_cap_fcfa')->delete();
        }
        // L'estimation d'un site vitrine gratuit (75 FCFA) laisse la place à la mesure réelle (60 FCFA, config/kekeli.php).
        if ((float) json_decode((string) DB::table('settings')->where('key', 'free_site_estimate_fcfa')->value('value'), true) === 75.0) {
            DB::table('settings')->where('key', 'free_site_estimate_fcfa')->delete();
        }
        Cache::forget('kekeli.settings');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['welcome_bonus_fcfa', 'welcome_bonus_at']);
        });
    }
};
