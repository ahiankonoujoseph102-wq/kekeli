<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents et présentations (12 novembre 2026) : un projet « document » (Word et PDF) ou « présentation »
 * (PowerPoint et PDF), ses versions (chaque modification en fait une nouvelle, les anciennes restent téléchargeables)
 * et les fichiers joints par le client (Word, PDF, PowerPoint) à corriger, résumer, traduire ou transformer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('document_type', 20)->nullable()->after('is_automation');
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            $table->json('document_options')->nullable();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 160);
            $table->text('request')->nullable();
            $table->json('files');
            $table->json('previews');
            $table->unsignedInteger('pages')->default(0);
            $table->longText('structure')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'number']);
        });

        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('format', 10);
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
        Schema::dropIfExists('document_versions');
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn('document_options'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('document_type'));
    }
};
