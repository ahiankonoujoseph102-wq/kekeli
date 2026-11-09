<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Réglages modifiables dans l'administration (les valeurs par défaut sont dans config/kekeli.php).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });

        // Consommation de chaque demande : requêtes, tokens, coût du modèle et temps d'ordinateur.
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->unsignedInteger('requests')->default(0)->after('model');
            $table->unsignedBigInteger('input_tokens')->default(0)->after('requests');
            $table->unsignedBigInteger('output_tokens')->default(0)->after('input_tokens');
            $table->unsignedBigInteger('reasoning_tokens')->default(0)->after('output_tokens');
            $table->unsignedBigInteger('cached_tokens')->default(0)->after('reasoning_tokens');
            $table->decimal('model_cost_usd', 12, 6)->default(0)->after('cached_tokens');
            $table->unsignedInteger('computer_seconds')->default(0)->after('model_cost_usd');
            $table->decimal('computer_cost_usd', 12, 6)->default(0)->after('computer_seconds');
        });

        // Formule de chaque utilisateur et début de sa période d'énergie (formules payantes).
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 30)->default('gratuite')->after('is_admin');
            $table->timestamp('energy_window_started_at')->nullable()->after('plan');
        });

        // Ordinateur effacé après une longue pause (le projet reste sauvegardé dans le site).
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('computer_erased_at')->nullable()->after('preview_waking_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('computer_erased_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['plan', 'energy_window_started_at']));
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn([
            'requests', 'input_tokens', 'output_tokens', 'reasoning_tokens', 'cached_tokens',
            'model_cost_usd', 'computer_seconds', 'computer_cost_usd',
        ]));
        Schema::dropIfExists('settings');
    }
};
