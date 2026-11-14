<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le temps de chaque étape d'une réponse ou d'un travail (toucher sur « Envoyer », message affiché, tâche reçue,
 * appel au modèle, premier mot, fin), pour mesurer la vitesse de Kekeli. Jamais de texte ni de secret : des nombres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->json('timings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('timings');
        });
    }
};
