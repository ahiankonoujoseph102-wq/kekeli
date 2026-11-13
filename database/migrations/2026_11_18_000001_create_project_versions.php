<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versions et retour en arrière des sites, applications, agents et automatisations, comme Lovable et Bolt.new :
 * chaque travail réussi crée une version (une archive figée et nettoyée des fichiers du projet) ; restaurer une
 * version crée une nouvelle version, rien n'est jamais effacé (sauf les plus anciennes au-delà de la limite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 120);
            // « travail » (un travail réussi de Kekeli) ou « restauration ».
            $table->string('kind', 12)->default('travail');
            $table->ulid('agent_run_id')->nullable();
            // La version remise par une restauration.
            $table->unsignedInteger('restored_from')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('files_count')->default(0);
            $table->timestamps();
            $table->unique(['project_id', 'number']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // La version montrée dans l'aperçu sans rien changer (« Vous regardez la version 3 »), ou null.
            $table->unsignedInteger('viewing_version')->nullable();
            // Une remise de version en cours dans l'ordinateur (voir, revenir, restaurer).
            $table->timestamp('version_busy_at')->nullable();
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            // Le titre court de la version créée par ce travail, donné par le premier répondant.
            $table->string('version_title', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('version_title'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['viewing_version', 'version_busy_at']));
        Schema::dropIfExists('project_versions');
    }
};
