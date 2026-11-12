<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications (12 novembre 2026) : une application web installable (projet « webapp » avec is_application), le service
 * que le client a demandé (par exemple Supabase), et les « Variables secrètes » du projet, chiffrées, transmises
 * seulement à l'ordinateur virtuel du projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_application')->default(false)->after('kind');
            $table->string('app_service', 30)->nullable()->after('app_stack');
        });

        Schema::create('project_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->text('value');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_secrets');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['is_application', 'app_service']));
    }
};
