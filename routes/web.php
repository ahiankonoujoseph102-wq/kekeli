<?php

use App\Http\Controllers\GithubController;
use App\Http\Controllers\PaymentScreenshotController;
use App\Http\Controllers\ProjectCodeController;
use App\Http\Controllers\ProjectPhotoController;
use App\Http\Controllers\RunnerBackupController;
use App\Http\Controllers\RunnerEventController;
use App\Http\Controllers\RunnerPhotoController;
use App\Http\Controllers\RunnerProjectEventController;
use App\Http\Middleware\VerifyRunnerSignature;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Pages juridiques, ouvertes à tous.
Route::view('conditions-d-utilisation', 'legal.conditions')->name('legal.terms');
Route::view('confidentialite', 'legal.confidentialite')->name('legal.privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'projets');

    Volt::route('projets', 'projects.index')->name('projects.index');
    Volt::route('projets/{project}', 'projects.show')->name('projects.show');
    Route::get('projets/{project}/photos/{photo}', [ProjectPhotoController::class, 'show'])->name('projects.photos.show');
    Volt::route('formules', 'plans')->name('plans');
    Volt::route('formules/{plan}/payer', 'payments.create')->name('payments.create');
    Route::get('projets/{project}/code.zip', [ProjectCodeController::class, 'zip'])->name('projects.code.zip');
    Route::get('github/connexion', [GithubController::class, 'connect'])->name('github.connect');
    Route::get('github/retour', [GithubController::class, 'callback'])->name('github.callback');
    Route::post('github/deconnexion', [GithubController::class, 'disconnect'])->name('github.disconnect');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('administration')->group(function () {
    Volt::route('fournisseurs', 'admin.providers')->name('admin.providers');
    Volt::route('projets', 'admin.projects')->name('admin.projects');
    Volt::route('consommation', 'admin.consumption')->name('admin.consumption');
    Volt::route('reglages', 'admin.settings')->name('admin.settings');
    Volt::route('paiements', 'admin.payments')->name('admin.payments');
    Route::get('paiements/{payment}/capture', [PaymentScreenshotController::class, 'show'])->name('admin.payments.screenshot');
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
    Route::get('photos/{photo}', [RunnerPhotoController::class, 'show'])->name('runner.photos.show');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
