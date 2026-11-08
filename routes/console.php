<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Chaque nuit : effacement des ordinateurs restés en pause trop longtemps (les projets restent sauvegardés).
Schedule::command('kekeli:effacer-ordinateurs-en-pause')->dailyAt('03:17');
