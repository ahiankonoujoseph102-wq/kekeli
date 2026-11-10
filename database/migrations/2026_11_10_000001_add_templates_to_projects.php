<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects created from a template of the gallery: the template, the client's answers to the simple questions
     * (asked one at a time), and the extra instructions given to the agent with a request.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('template', 60)->nullable();
            $table->json('template_answers')->nullable();
            // Question en cours (0 = la première) ; vide quand toutes les réponses sont données.
            $table->unsignedTinyInteger('template_step')->nullable();
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            $table->text('instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['template', 'template_answers', 'template_step']));
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('instructions'));
    }
};
