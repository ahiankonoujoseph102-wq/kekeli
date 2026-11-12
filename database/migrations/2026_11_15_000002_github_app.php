<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GitHub App de Kekeli (12 novembre 2026) : le jeton de l'utilisateur expire après 8 heures et se renouvelle avec un
 * jeton de renouvellement (6 mois), tous deux chiffrés ; l'installation dit quels dépôts Kekeli peut voir. Chaque projet
 * relié choisit comment Kekeli envoie son travail : pull request (par défaut, comme Claude Code) ou branche principale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('github_refresh_token')->nullable()->after('github_token');
            $table->timestamp('github_token_expires_at')->nullable()->after('github_refresh_token');
            $table->unsignedBigInteger('github_installation_id')->nullable()->after('github_token_expires_at');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->string('github_push_mode', 20)->default('pull_request')->after('github_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['github_refresh_token', 'github_token_expires_at', 'github_installation_id']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('github_push_mode'));
    }
};
