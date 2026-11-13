<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Automatisations déclenchées par un événement (13 novembre 2026) : le déclencheur reconnu dans le code écrit par Kekeli
// (heure fixe, événement, vérification régulière), l'événement d'essai et les formulaires branchés.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('automation_trigger')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('automation_trigger'));
    }
};
