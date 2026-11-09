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
        // Travail arrêté en cours de route (énergie épuisée) : la demande pourra reprendre avec « Continuer ».
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->timestamp('stop_requested_at')->nullable()->after('resume_at');
            $table->string('stop_reason', 30)->nullable()->after('stop_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn(['stop_requested_at', 'stop_reason']));
    }
};
