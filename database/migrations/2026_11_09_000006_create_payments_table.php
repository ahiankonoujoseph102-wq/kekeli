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
        // Paiements par Mobile Money : le client envoie la capture d'écran, l'administrateur valide.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->unsignedInteger('amount_fcfa');
            $table->string('country', 2);
            $table->string('screenshot_path');
            $table->string('status', 20)->default('pending');
            $table->string('refusal_reason', 300)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Fin de la formule payée (30 jours après la validation) ; ensuite, retour à la formule gratuite.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('plan_expires_at')->nullable()->after('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('plan_expires_at'));
        Schema::dropIfExists('payments');
    }
};
