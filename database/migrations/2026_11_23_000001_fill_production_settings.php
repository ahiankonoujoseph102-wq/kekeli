<?php

use App\Billing\ProductionDefaults;
use Illuminate\Database\Migrations\Migration;

/*
 * Remplit Administration → Paiements et Administration → Réglages d'une base de production neuve (numéros Mobile Money
 * du Togo, du Burkina Faso, du Bénin et de la Côte d'Ivoire, WhatsApp, plafonds, émetteur des reçus, téléphones),
 * avec l'accord du porteur du projet du 14 novembre 2026. Seul ce qui est vide ou absent est rempli : une valeur déjà
 * saisie dans l'administration n'est jamais changée, et la relancer n'ajoute rien en double (App\Billing\ProductionDefaults).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pendant les tests, chaque base est recréée : les tests appellent eux-mêmes ProductionDefaults quand il le faut.
        if (app()->runningUnitTests()) {
            return;
        }

        app(ProductionDefaults::class)->apply();
    }

    public function down(): void
    {
        // Rien à défaire : ces réglages appartiennent ensuite à l'administration.
    }
};
