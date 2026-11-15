<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sites vitrines et modifications construits d'abord par GPT-6.1 Terra, pour toutes les formules (décision du porteur
 * du projet du 15 novembre 2026), pour qu'un site complet tienne dans les 100 FCFA d'une journée gratuite. Les routes
 * jamais enregistrées prennent la nouvelle valeur de config/kekeli.php ; une route enregistrée avec l'ancienne valeur
 * passe à la nouvelle ; une route changée dans Administration → Modèles n'est jamais touchée.
 */
return new class extends Migration
{
    private const OLD = [
        'site' => ['gpt-6-1-sol', 'kimi-k3-1-pro'],
        'modification' => ['gpt-6-1-sol', 'gpt-6-1-terra'],
    ];

    public function up(): void
    {
        $value = DB::table('settings')->where('key', 'model_routes')->value('value');
        $routes = $value === null ? null : json_decode($value, true);
        if (! is_array($routes)) {
            return;
        }

        $changed = false;
        foreach (self::OLD as $task => $old) {
            if (($routes[$task]['qualite'] ?? null) === $old) {
                $routes[$task]['qualite'] = ['gpt-6-1-terra', 'gpt-6-1-sol'];
                $changed = true;
            }
        }
        if ($changed) {
            DB::table('settings')->where('key', 'model_routes')->update(['value' => json_encode($routes)]);
            app(Settings::class)->forget();
        }
    }

    public function down(): void
    {
        // Rien à défaire : les routes se règlent ensuite dans Administration → Modèles.
    }
};
