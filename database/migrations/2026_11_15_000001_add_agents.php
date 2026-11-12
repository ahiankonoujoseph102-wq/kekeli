<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agents IA (12 novembre 2026) : un projet « webapp » avec is_agent (technologie langgraph ou crewai dans app_stack,
 * technologie imposée par le client dans app_service), et les messages de l'essai gratuit dans l'aperçu, comptés par
 * client et par jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_agent')->default(false)->after('is_application');
        });

        Schema::create('agent_trial_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_trial_messages');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('is_agent');
        });
    }
};
