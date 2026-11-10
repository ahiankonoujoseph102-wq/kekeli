<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel web application: ZIP file ready for shared hosting (cPanel, Hostinger), with its libraries.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('package_status', 20)->nullable();
            $table->unsignedInteger('package_size')->nullable();
            $table->timestamp('package_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['package_status', 'package_size', 'package_at']);
        });
    }
};
