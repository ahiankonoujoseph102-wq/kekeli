<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mode des modèles choisi par le client : « standard » (par défaut, et toujours pour la formule Gratuite) ou « pro »
// (formules payantes, « + » → « Mode Pro ») ; docs/plan.md, partie 26.26.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('model_mode', 16)->default('standard')->after('appearance');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('model_mode'));
    }
};
