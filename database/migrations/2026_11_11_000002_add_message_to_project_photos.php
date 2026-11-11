<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos are now joined in the message field, like Lovable or Claude Code: each photo remembers the message
     * that sent it, and the discussion shows it in that message. Older photos have no message.
     */
    public function up(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_id');
        });
    }
};
