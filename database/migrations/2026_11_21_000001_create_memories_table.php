<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mémoire de Kekeli, comme Claude et ChatGPT : les souvenirs de chaque client (chiffrés, jamais partagés, effacés avec
 * son compte), l'interrupteur de la mémoire, le logo enregistré, et la ligne « Mémoire mise à jour » d'un message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('category', 20)->default('preference');
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('memory_enabled')->default(true);
            $table->string('memory_logo_path')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->text('memory_updates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('memory_updates');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['memory_enabled', 'memory_logo_path']);
        });
        Schema::dropIfExists('memories');
    }
};
