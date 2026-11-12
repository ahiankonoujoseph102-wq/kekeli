<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The cost of the requests of each provider and day, to keep the daily ceiling of OpenAI (paid key) set in
 * Administration → Réglages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_usages', function (Blueprint $table) {
            $table->decimal('cost_usd', 12, 6)->default(0)->after('cached_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('provider_usages', fn (Blueprint $table) => $table->dropColumn('cost_usd'));
    }
};
