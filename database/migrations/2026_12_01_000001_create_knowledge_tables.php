<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de connaissances (Kekeli Knowledge, 18 novembre 2026) : des espaces (Entreprise, Formation, Comptabilité…),
 * chacun avec ses documents, leurs passages (texte, page, vecteur) et ses conversations (des discussions reliées à
 * l'espace). Les vecteurs sont gardés en texte (base 64 de nombres à virgule sur 4 octets) : la même table marche avec
 * PostgreSQL et SQLite, sans extension à installer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_spaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('knowledge_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            // « fichier » (importé depuis le téléphone), « drive » (lien Google Drive public) ou « youtube » (vidéo publique).
            $table->string('source', 20)->default('fichier');
            $table->string('source_url', 500)->nullable();
            $table->string('format', 10);
            $table->unsignedBigInteger('size');
            $table->string('path');
            // Empreinte SHA-256 du fichier : jamais deux fois le même fichier dans un espace, et jamais d'indexation refaite.
            $table->char('hash', 64);
            $table->string('status', 20)->default('pending');
            $table->string('error', 40)->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('characters')->default(0);
            $table->string('embedding_model', 80)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['knowledge_space_id', 'hash']);
            $table->index(['user_id', 'hash']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('knowledge_space_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('page')->nullable();
            // Le moment d'une vidéo où commence le passage (en secondes), pour le citer et l'ouvrir à cet endroit.
            $table->unsignedInteger('seconds')->nullable();
            $table->text('content');
            $table->longText('vector')->nullable();
            $table->string('embedding_model', 80)->nullable();
            $table->timestamps();
            $table->index(['knowledge_space_id', 'knowledge_document_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUlid('knowledge_space_id')->nullable()->constrained()->cascadeOnDelete();
            // Les documents choisis pour la conversation (null : tous les documents de l'espace).
            $table->json('knowledge_documents')->nullable();
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            // Les passages envoyés au modèle pour ce message (document, page, extrait), pour afficher les sources.
            $table->json('knowledge')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('knowledge');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('knowledge_space_id');
            $table->dropColumn('knowledge_documents');
        });
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_spaces');
    }
};
