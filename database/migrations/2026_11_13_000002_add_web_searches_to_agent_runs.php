<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherches sur Internet faites pour une demande (outil « rechercher_web » du premier répondant) et leur coût, compté
 * dans l'énergie du client comme les images créées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->unsignedInteger('web_searches')->default(0)->after('image_cost_usd');
            $table->decimal('web_search_cost_usd', 12, 6)->default(0)->after('web_searches');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn(['web_searches', 'web_search_cost_usd']));
    }
};
