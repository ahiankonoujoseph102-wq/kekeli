<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apparence choisie par le client (Système, Clair ou Sombre), gardée pour son compte. Par défaut : « system », Kekeli
 * suit le réglage du téléphone ou de l'ordinateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('appearance', 10)->default('system')->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
