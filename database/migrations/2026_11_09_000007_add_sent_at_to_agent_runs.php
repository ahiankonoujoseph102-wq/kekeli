<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moment where a request was given to the Runner: it then takes one of the places of Kekeli
     * (10 requests at the same time at most, the next ones wait for their turn).
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('started_at');
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropIndex(['status', 'sent_at']);
            $table->dropColumn('sent_at');
        });
    }
};
