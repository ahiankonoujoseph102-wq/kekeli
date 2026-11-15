<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche sur Internet comme Claude (15 novembre 2026) : une question du client compte pour UNE recherche du jour
 * (« web_searches »), même si Kekeli envoie plusieurs requêtes aux fournisseurs (le nom exact, une variante, la
 * lecture des meilleures pages). Ces requêtes sont comptées à part (« web_search_requests ») pour le plafond du mois,
 * réglé pour rester dans les offres gratuites de Tavily et d'Exa. Les anciennes demandes : une requête par recherche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->unsignedInteger('web_search_requests')->default(0);
        });
        DB::table('agent_runs')->where('web_searches', '>', 0)->update(['web_search_requests' => DB::raw('web_searches')]);
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('web_search_requests');
        });
    }
};
