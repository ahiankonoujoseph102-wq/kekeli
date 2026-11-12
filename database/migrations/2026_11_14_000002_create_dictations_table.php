<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice dictations written by OpenAI when the browser of the client cannot do it itself: their cost is counted in the
 * client's energy. The recording itself is never kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model', 60);
            $table->unsignedInteger('seconds')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictations');
    }
};
