<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clear messages of Kekeli: the report of each request (always in the same order), the list of changed files and
     * the differences (« See the changes »), and the link between Kekeli's answer and its request.
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->json('report')->nullable();
            $table->json('changes')->nullable();
            $table->mediumText('diff')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignUlid('agent_run_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_run_id');
        });
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn(['report', 'changes', 'diff']));
    }
};
