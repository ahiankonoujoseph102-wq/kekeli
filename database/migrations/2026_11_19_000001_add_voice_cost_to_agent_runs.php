<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Vidéos (13 novembre 2026) : le coût de la voix off et des sous-titres de la voix du client, compté dans l'énergie.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->decimal('voice_cost_usd', 12, 6)->default(0)->after('web_search_cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('voice_cost_usd'));
    }
};
