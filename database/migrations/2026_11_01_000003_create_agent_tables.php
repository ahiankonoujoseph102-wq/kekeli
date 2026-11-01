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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('provider', 30)->nullable();
            $table->string('model', 120)->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->text('target')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_usages', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('provider', 30);
            $table->string('model', 120);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('rate_limited')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('cached_tokens')->default(0);
            $table->timestamps();

            $table->unique(['day', 'provider', 'model']);
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
        Schema::dropIfExists('provider_usages');
        Schema::dropIfExists('agent_steps');
        Schema::dropIfExists('agent_runs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
