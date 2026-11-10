<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Developer mode: an existing GitHub repository of the client, opened in the virtual computer, with the branch
     * of Kekeli, the pull request and the state of GitHub's automatic checks.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('kind', 20)->default('site');
            $table->string('repository')->nullable();
            $table->string('repository_base_branch')->nullable();
            $table->string('repository_base_sha', 40)->nullable();
            // « write » : le client peut écrire dans le dépôt ; « read » : dépôt public ouvert en lecture seule.
            $table->string('repository_access', 10)->nullable();
            $table->string('work_branch')->nullable();
            // Dernier état reçu du Runner : type de projet reconnu, commits et fichiers modifiés.
            $table->json('repository_state')->nullable();
            $table->string('push_status', 20)->nullable();
            $table->text('push_error')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('pushed_sha', 40)->nullable();
            $table->unsignedInteger('pr_number')->nullable();
            $table->string('pr_url')->nullable();
            $table->string('pr_status', 20)->nullable();
            $table->string('ci_status', 20)->nullable();
            $table->text('ci_summary')->nullable();
            $table->unsignedTinyInteger('ci_fix_attempts')->default(0);
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            // « ci_fix » : demande lancée par Kekeli pour corriger une vérification automatique de GitHub qui a échoué.
            $table->string('purpose', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn([
            'kind', 'repository', 'repository_base_branch', 'repository_base_sha', 'repository_access', 'work_branch', 'repository_state',
            'push_status', 'push_error', 'pushed_at', 'pushed_sha', 'pr_number', 'pr_url', 'pr_status', 'ci_status', 'ci_summary', 'ci_fix_attempts',
        ]));
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('purpose'));
    }
};
