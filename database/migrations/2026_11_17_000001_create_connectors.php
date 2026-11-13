<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connecteurs (MCP), comme Claude et ChatGPT : les serveurs MCP reliés par chaque client (catalogue ou personnalisé),
 * leurs jetons chiffrés, les outils toujours autorisés, et le journal des actions (avec les demandes d'accord).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // La clé du catalogue (« notion »…), ou null pour un connecteur personnalisé.
            $table->string('catalog_key', 40)->nullable();
            $table->string('name', 60);
            $table->string('url', 500);
            // « aucune » (serveur sans connexion) ou « oauth ».
            $table->string('auth', 10)->default('oauth');
            // Inscription de Kekeli auprès du serveur d'autorisation, jetons : chiffrés, jamais affichés.
            $table->text('client')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('resource', 500)->nullable();
            // Outils pour lesquels le client a choisi « Toujours autoriser ».
            $table->json('always_allowed')->nullable();
            // Actif par défaut dans les nouvelles discussions.
            $table->boolean('active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'url']);
        });

        Schema::create('connector_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_connector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('agent_run_id')->nullable()->index();
            $table->string('connector_name', 60);
            $table->string('tool', 128);
            $table->boolean('writes')->default(false);
            // « fait », « erreur », « en_attente » (accord demandé), « autorise », « refuse ».
            $table->string('status', 12);
            // Le résumé montré au client (action, détails) ; les arguments exacts seulement pendant l'attente, chiffrés.
            $table->json('summary')->nullable();
            $table->text('arguments')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // Les connecteurs actifs dans ce projet (null : ceux que le client a marqués actifs par défaut).
            $table->json('connectors')->nullable();
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            // La reprise après « Autoriser » : l'action acceptée par le client.
            $table->unsignedBigInteger('connector_action_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('connector_action_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('connectors'));
        Schema::dropIfExists('connector_actions');
        Schema::dropIfExists('user_connectors');
    }
};
