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
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('backup_size')->nullable()->after('preview_expires_at');
            $table->timestamp('backup_at')->nullable()->after('backup_size');
            $table->timestamp('preview_waking_at')->nullable()->after('backup_at');
        });

        // Sites ajoutés par l'utilisateur à la liste des sites autorisés ; un retrait est gardé dans l'historique.
        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 253);
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'removed_at']);
        });

        // Demandes de permission de l'agent avant une action risquée.
        Schema::create('agent_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('request_id', 100);
            $table->string('tool', 40);
            $table->text('target')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_run_id', 'request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_permissions');
        Schema::dropIfExists('project_sites');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['backup_size', 'backup_at', 'preview_waking_at']);
        });
    }
};
