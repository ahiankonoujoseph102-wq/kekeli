<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The code of the client: sending to GitHub (every formula), own domain name (paid formulas).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_login')->nullable();
            // Clé d'accès donnée par GitHub, chiffrée avec la clé de l'application ; jamais affichée.
            $table->text('github_token')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('github_repo')->nullable();
            $table->string('github_status', 20)->nullable();
            $table->text('github_error')->nullable();
            $table->timestamp('github_exported_at')->nullable();
            $table->string('custom_domain')->nullable();
            $table->string('custom_domain_status', 20)->nullable();
            $table->text('custom_domain_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['github_login', 'github_token']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn([
            'github_repo', 'github_status', 'github_error', 'github_exported_at', 'custom_domain', 'custom_domain_status', 'custom_domain_message',
        ]));
    }
};
