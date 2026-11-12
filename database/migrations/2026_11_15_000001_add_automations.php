<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatisations (12 novembre 2026) : un projet « webapp » marqué is_automation (code JavaScript et fichier GitHub
 * Actions planifié, qui tourne sur le dépôt GitHub du client, jamais chez Kekeli).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_automation')->default(false)->after('is_agent');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('is_automation'));
    }
};
