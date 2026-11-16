<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Modifier » un message, comme Claude et ChatGPT (16 novembre 2026) : la discussion repart du message modifié ; ce
 * qui suivait l'ancien message est masqué (gardé, jamais relu par le modèle, jamais montré).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
