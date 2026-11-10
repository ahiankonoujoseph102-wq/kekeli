<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Web applications (sites with a database): technology, starting template, and the test administrator of
     * the preview (an invented account that only exists in the test database of the virtual computer).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('app_stack', 20)->nullable()->after('kind');
            $table->string('app_template', 60)->nullable()->after('app_stack');
            $table->string('app_admin_email')->nullable()->after('app_template');
            $table->text('app_admin_password')->nullable()->after('app_admin_email');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['app_stack', 'app_template', 'app_admin_email', 'app_admin_password']);
        });
    }
};
