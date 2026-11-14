<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-mails avec Brevo (journal des envois), reçus d'abonnement (numérotés sans doublon, jamais modifiés), connexion par
 * code envoyé par e-mail et par Google, comme Claude et ChatGPT.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Journal des envois : le type, le destinataire masqué, la réussite ou l'échec (jamais le contenu).
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_masked', 120);
            $table->boolean('priority')->default(false);
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('deferred_until')->nullable();
            $table->timestamps();
            $table->index(['status', 'sent_at']);
        });

        // Reçus : un numéro unique par année (KEK-2026-000123), les informations figées au moment du paiement.
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('kind', 20)->default('receipt');
            $table->foreignId('cancels_receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('plan_name', 60);
            $table->timestamp('paid_at');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->integer('amount_fcfa');
            $table->integer('amount_usd')->nullable();
            $table->string('method', 60)->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('reason', 300)->nullable();
            $table->json('issuer');
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['year', 'sequence']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 60)->nullable();
            $table->string('reference', 80)->nullable();
        });

        // Codes de connexion : seulement l'empreinte du code et du lien, valables 10 minutes, à usage unique.
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code_hash');
            $table->string('token_hash', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamp('plan_reminded_at')->nullable();
            $table->timestamp('welcomed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar_url', 'plan_reminded_at', 'welcomed_at']);
        });
        Schema::dropIfExists('login_codes');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['method', 'reference']);
        });
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('email_logs');
    }
};
