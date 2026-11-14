<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Laravel Cloud met en pause les tâches planifiées quand le site dort (sans visite) : une tâche prévue une fois par
// jour à heure fixe serait sautée si le site dort à cette heure-là. Chaque tâche passe donc toutes les heures où le
// site est éveillé ; elles ne font jamais rien en double (rappel noté par compte, alerte de coût une fois par mois,
// effacement unique par projet), et elles ne réveillent jamais le site ni la base de données.

// Effacement des ordinateurs restés en pause trop longtemps (les projets restent sauvegardés).
Schedule::command('kekeli:effacer-ordinateurs-en-pause')->hourlyAt(17)->withoutOverlapping();

// Retour à la formule gratuite quand les 30 jours d'une formule payée sont finis, et rappel la veille de la fin
// (l'énergie tient déjà compte de la date de fin, même avant le passage de cette tâche).
Schedule::command('kekeli:expirer-formules')->hourlyAt(7)->withoutOverlapping();

// Lecture du coût de Kekeli sur Laravel Cloud, alerte au-delà du seuil réglé dans l'administration (une fois par mois).
Schedule::command('kekeli:verifier-cout-hebergement')->hourlyAt(11)->withoutOverlapping();
