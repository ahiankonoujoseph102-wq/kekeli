<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une « discussion simple » (décision du porteur du projet du 15 novembre 2026) : une réponse écrite qui n'a utilisé
 * aucun outil (ni création, ni modification, ni recherche sur Internet, ni connecteur). Son coût reste enregistré et
 * compte dans le plafond global des comptes gratuits, mais il ne retire rien à l'énergie du client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->boolean('simple_chat')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('simple_chat');
        });
    }
};
