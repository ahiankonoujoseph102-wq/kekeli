<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kekeli Motion (18 novembre 2026) : une animation posée dans un site ou une application du client. Le Runner dépose
 * son script dans le projet (dossier « animations ») avant chaque travail ; le site garde le lien vers la version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_animations', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_animations');
    }
};
