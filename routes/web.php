<?php

use App\Http\Controllers\RunnerBackupController;
use App\Http\Controllers\RunnerEventController;
use App\Http\Controllers\RunnerProjectEventController;
use App\Http\Middleware\VerifyRunnerSignature;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'projets');

    Volt::route('projets', 'projects.index')->name('projects.index');
    Volt::route('projets/{project}', 'projects.show')->name('projects.show');
    Volt::route('formules', 'plans')->name('plans');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('administration')->group(function () {
    Volt::route('fournisseurs', 'admin.providers')->name('admin.providers');
    Volt::route('projets', 'admin.projects')->name('admin.projects');
    Volt::route('consommation', 'admin.consumption')->name('admin.consumption');
    Volt::route('reglages', 'admin.settings')->name('admin.settings');
});

// Événements de l'agent envoyés par le Runner, protégés par une signature.
Route::post('runner/evenements', RunnerEventController::class)
    ->middleware(VerifyRunnerSignature::class)
    ->name('runner.events');

// Événements d'un projet sans demande à l'agent, et sauvegardes des fichiers, signés eux aussi.
Route::middleware(VerifyRunnerSignature::class)->prefix('runner/projets/{project}')->group(function () {
    Route::post('evenements', RunnerProjectEventController::class)->name('runner.project-events');
    Route::post('sauvegarde', [RunnerBackupController::class, 'store'])->name('runner.backup.store');
    Route::get('sauvegarde', [RunnerBackupController::class, 'show'])->name('runner.backup.show');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
