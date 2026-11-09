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
        // Publication du projet sur Cloudflare Pages (adresse gratuite en pages.dev).
        Schema::table('projects', function (Blueprint $table) {
            $table->string('pages_name', 60)->nullable()->after('computer_erased_at');
            $table->string('publish_status', 20)->nullable()->after('pages_name');
            $table->string('published_url')->nullable()->after('publish_status');
            $table->timestamp('published_at')->nullable()->after('published_url');
            $table->string('publish_error', 500)->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['pages_name', 'publish_status', 'published_url', 'published_at', 'publish_error']));
    }
};
