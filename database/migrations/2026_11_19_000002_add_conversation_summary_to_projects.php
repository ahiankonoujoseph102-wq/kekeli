<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Résumé automatique des longues discussions (13 novembre 2026), invisible pour le client : le résumé du début de la
// discussion et le dernier message qu'il couvre.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('conversation_summary')->nullable();
            $table->unsignedBigInteger('summary_until_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['conversation_summary', 'summary_until_message_id']));
    }
};
