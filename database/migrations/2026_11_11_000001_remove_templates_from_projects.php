<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gallery of site templates is removed (11 novembre 2026): every site starts from « Que voulez-vous créer
     * aujourd'hui ? ». The instructions given to the agent with a request (agent_runs.instructions) are kept.
     */
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['template', 'template_answers', 'template_step']));
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('template', 60)->nullable();
            $table->json('template_answers')->nullable();
            $table->unsignedTinyInteger('template_step')->nullable();
        });
    }
};
