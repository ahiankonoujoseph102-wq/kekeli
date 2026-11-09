<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Chaque nuit : effacement des ordinateurs restés en pause trop longtemps (les projets restent sauvegardés).
Schedule::command('kekeli:effacer-ordinateurs-en-pause')->dailyAt('03:17');

// Chaque jour : retour à la formule gratuite quand les 30 jours d'une formule payée sont finis. Une seule fois par
// jour pour laisser le site et sa base de données s'endormir (l'énergie tient déjà compte de la date de fin).
Schedule::command('kekeli:expirer-formules')->dailyAt('00:07')->timezone('Africa/Lome');
