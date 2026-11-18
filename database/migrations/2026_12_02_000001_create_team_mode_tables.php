<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode équipe, phase 1 (18 novembre 2026, docs/multi-agents-etude.md) : une demande construite par l'équipe d'agents
 * (« agent_runs.team »), et ses missions avec leur résultat, leurs faits et leurs mesures (requêtes, tokens, durée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->boolean('team')->default(false);
        });

        Schema::create('project_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 10);
            $table->string('role', 20);
            $table->string('title', 80);
            $table->text('objective');
            $table->json('criteria');
            $table->json('files');
            $table->json('depends_on');
            $table->string('status', 20)->default('en_attente');
            $table->string('problem', 300)->nullable();
            $table->string('summary', 600)->nullable();
            $table->json('facts')->nullable();
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);
            $table->timestamps();
            $table->unique(['agent_run_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_missions');
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('team');
        });
    }
};
