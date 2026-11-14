<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    // Une seule page pour se connecter ou créer un compte : l'ancienne adresse d'inscription y mène.
    Route::redirect('register', '/login')->name('register');

    // Le bouton de l'e-mail de connexion (lien à usage unique, 10 minutes).
    Route::get('connexion/lien/{token}', LoginLinkController::class)
        ->middleware('throttle:20,1')
        ->name('login.link');

    // « Continuer avec Google » (seulement quand GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET sont réglés).
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/retour', [GoogleController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::post('logout', Logout::class)
    ->name('logout');
