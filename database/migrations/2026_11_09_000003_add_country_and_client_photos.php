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
        // Pays du client : Kekeli y adapte les images, la monnaie et le format des numéros de téléphone.
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('plan');
        });

        // Photos envoyées par le client depuis son téléphone, utilisées en priorité par l'agent.
        Schema::create('project_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('path');
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        // Images créées pendant une demande, comptées dans l'énergie du client.
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->unsignedInteger('images_created')->default(0)->after('computer_cost_usd');
            $table->decimal('image_cost_usd', 12, 6)->default(0)->after('images_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', fn (Blueprint $table) => $table->dropColumn(['images_created', 'image_cost_usd']));
        Schema::dropIfExists('project_photos');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
