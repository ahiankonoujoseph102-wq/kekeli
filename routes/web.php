<?php

use App\Http\Controllers\AnswerStreamController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\DictationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GithubController;
use App\Http\Controllers\MemoryLogoController;
use App\Http\Controllers\PaymentScreenshotController;
use App\Http\Controllers\ProjectCodeController;
use App\Http\Controllers\ProjectPhotoController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RunnerAgentTrialController;
use App\Http\Controllers\RunnerBackupController;
use App\Http\Controllers\RunnerDocumentController;
use App\Http\Controllers\RunnerEventController;
use App\Http\Controllers\RunnerFileController;
use App\Http\Controllers\RunnerFreellmapiBackupController;
use App\Http\Controllers\RunnerGithubReadController;
use App\Http\Controllers\RunnerKnowledgeController;
use App\Http\Controllers\RunnerPhotoController;
use App\Http\Controllers\RunnerProjectEventController;
use App\Http\Controllers\RunnerRepositoryController;
use App\Http\Controllers\RunnerWakeController;
use App\Http\Middleware\VerifyRunnerSignature;
use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// L'accueil : pour un visiteur, la grande question ; pour un client connecté, la page « Nouveau », avec la même barre de
// navigation que toutes les pages connectées.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('projects.index') : view('welcome');
})->name('home');

// Anciennes adresses de la galerie de modèles, retirée le 11 novembre 2026 : tout commence par l'accueil.
Route::redirect('modeles', '/', 301);
Route::get('modeles/{chemin}', fn () => redirect('/', 301))->where('chemin', '.*');

// Pages juridiques, ouvertes à tous.
Route::view('conditions-d-utilisation', 'legal.conditions')->name('legal.terms');
Route::view('confidentialite', 'legal.confidentialite')->name('legal.privacy');
// Page du site installable quand le réseau manque (gardée par public/sw.js) : aucune donnée de compte.
Route::view('hors-connexion', 'offline')->name('offline');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'projets');

    Volt::route('projets', 'projects.index')->name('projects.index');
    // « Projets » (sites, applications, agents, automatisations) et « Bibliothèque » (documents, présentations, images,
    // vidéos), comme les grands outils (14 novembre 2026) ; « Récents » ne garde que les discussions.
    Volt::route('mes-projets', 'projects.all')->name('projects.all');
    Volt::route('bibliotheque', 'library')->name('library');
    // Base de connaissances (18 novembre 2026) : des espaces de documents, et des conversations qui y répondent.
    Volt::route('connaissances', 'knowledge.index')->name('knowledge.index');
    Volt::route('connaissances/{space}', 'knowledge.show')->name('knowledge.show');
    Volt::route('projets/ouvrir-un-depot', 'projects.open-repository')->name('projects.open-repository');
    // Anciennes adresses du formulaire « Nouveau site » : tout commence par « Que voulez-vous créer aujourd'hui ? »
    // dans « Mes projets » depuis le 11 novembre 2026.
    Route::redirect('projets/nouveau-site', '/projets');
    Route::redirect('projets/nouvelle-application', '/projets');
    Volt::route('projets/{project}', 'projects.show')->name('projects.show');
    // Plus de page « Comptes rendus » à part (11 novembre 2026) : les comptes rendus sont dans la discussion.
    Route::get('projets/{project}/comptes-rendus', function (Project $project) {
        abort_unless($project->user_id === auth()->id() || auth()->user()->can('admin'), 404);

        return redirect()->route('projects.show', $project);
    })->name('projects.reports');
    // Plus de guide de mise en ligne (12 novembre 2026) : comme Lovable et Bolt.new, le code se télécharge ou part
    // sur GitHub depuis la fenêtre du projet ; l'ancienne adresse renvoie vers le projet.
    Route::get('projets/{project}/mise-en-ligne', function (Project $project) {
        abort_unless($project->user_id === auth()->id() || auth()->user()->can('admin'), 404);

        return redirect()->route('projects.show', $project);
    });
    Route::get('projets/{project}/photos/{photo}', [ProjectPhotoController::class, 'show'])->name('projects.photos.show');
    Volt::route('formules', 'plans')->name('plans');
    Volt::route('formules/{plan}/payer', 'payments.create')->name('payments.create');
    Route::get('projets/{project}/code.zip', [ProjectCodeController::class, 'zip'])->name('projects.code.zip');
    // La réponse de Kekeli mot par mot, ouverte par la page dès l'envoi (sans file d'attente), et les durées vues par
    // le téléphone (seulement des nombres).
    Route::post('projets/{project}/reponses/{run}', AnswerStreamController::class)->middleware('throttle:60,1')->name('projects.answers.stream');
    // Le Runner réveillé pendant que le client écrit (il s'endort sur Laravel Cloud quand personne ne s'en sert).
    Route::post('reveil', RunnerWakeController::class)->middleware('throttle:10,1')->name('runner.wake');
    Route::post('projets/{project}/reponses/{run}/chronos', [AnswerStreamController::class, 'chronos'])->middleware('throttle:60,1')->name('projects.answers.chronos');
    // Documents et présentations : chaque version reste téléchargeable, dans chacun de ses formats.
    Route::get('projets/{project}/versions/{number}/telecharger/{format}', [DocumentController::class, 'download'])->whereNumber('number')->whereIn('format', ['docx', 'pptx', 'pdf', 'png', 'mp4', 'srt'])->name('projects.versions.download');
    Route::get('projets/{project}/versions/{number}/video', [DocumentController::class, 'video'])->whereNumber('number')->name('projects.versions.video');
    Route::get('projets/{project}/versions/{number}/pages/{page}', [DocumentController::class, 'page'])->whereNumber(['number', 'page'])->name('projects.versions.page');
    // Dictée vocale quand le navigateur ne sait pas écrire la voix lui-même (transcription d'OpenAI, comptée dans l'énergie).
    Route::post('dictee', [DictationController::class, 'store'])->middleware('throttle:20,1')->name('dictation.store');
    Route::get('github/connexion', [GithubController::class, 'connect'])->name('github.connect');
    Route::get('github/retour', [GithubController::class, 'callback'])->name('github.callback');
    Route::post('github/confirmer', [GithubController::class, 'confirm'])->name('github.confirm');
    Route::post('github/deconnexion', [GithubController::class, 'disconnect'])->name('github.disconnect');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('administration')->group(function () {
    Route::get('recus/{receipt}', [ReceiptController::class, 'admin'])->name('admin.receipts.show');
    Volt::route('emails', 'admin.emails')->name('admin.emails');
    Volt::route('fournisseurs', 'admin.providers')->name('admin.providers');
    Volt::route('modeles', 'admin.models')->name('admin.models');
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

// Sauvegarde chiffrée de la base de FreeLLMAPI (compteurs de quotas), envoyée et relue par le Runner avec son jeton.
Route::get('runner/freellmapi/sauvegarde', [RunnerFreellmapiBackupController::class, 'show'])->name('runner.freellmapi.backup.show');
Route::put('runner/freellmapi/sauvegarde', [RunnerFreellmapiBackupController::class, 'store'])->name('runner.freellmapi.backup.store');

// Un fichier du projet lu par le premier répondant (question sur le code) : adresse signée pour 10 minutes.
Route::get('runner/projets/{project}/fichier', [RunnerFileController::class, 'show'])->name('runner.file.show');
// Lecture d'un dépôt GitHub relié par le client (16 novembre 2026) : adresse signée, lecture seule.
Route::get('runner/comptes/{user}/github', [RunnerGithubReadController::class, 'show'])->name('runner.github.read');

// Base de connaissances : un document pris par le Runner pour être lu dans un ordinateur isolé (adresse signée de
// 10 minutes et signature du Runner).
Route::get('runner/base/documents/{document}', [RunnerKnowledgeController::class, 'show'])
    ->middleware(['signed', VerifyRunnerSignature::class])
    ->name('runner.knowledge.file');

// Événements d'un projet sans demande à l'agent, et sauvegardes des fichiers, signés eux aussi.
Route::middleware(VerifyRunnerSignature::class)->prefix('runner/projets/{project}')->group(function () {
    Route::post('evenements', RunnerProjectEventController::class)->name('runner.project-events');
    Route::post('essai-agent', RunnerAgentTrialController::class)->name('runner.agent-trial');
    Route::post('sauvegarde', [RunnerBackupController::class, 'store'])->name('runner.backup.store');
    Route::get('sauvegarde', [RunnerBackupController::class, 'show'])->name('runner.backup.show');
    Route::get('photos/{photo}', [RunnerPhotoController::class, 'show'])->name('runner.photos.show');
    Route::get('logo-memoire', [RunnerPhotoController::class, 'memoryLogo'])->name('runner.memory.logo');
    Route::get('depot', [RunnerRepositoryController::class, 'archive'])->name('runner.repository.archive');
    Route::post('documents/{run}', [RunnerDocumentController::class, 'store'])->name('runner.documents.store');
    Route::get('versions/{version}/fond', [RunnerDocumentController::class, 'background'])->name('runner.versions.background');
    Route::get('versions/{version}/medias/{name}', [RunnerDocumentController::class, 'media'])->name('runner.versions.media');
    Route::get('versions-du-projet/{number}', [RunnerBackupController::class, 'version'])->whereNumber('number')->name('runner.project-versions.archive');
    Route::get('pieces-jointes/{attachment}', [RunnerDocumentController::class, 'attachment'])->name('runner.attachments.show');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/formule', 'settings.plan')->name('settings.plan');
    // Connecteurs (MCP) : la page n'existe que lorsque les connecteurs fonctionnent vraiment (Runner réglé, GitHub App).
    Volt::route('settings/connecteurs', 'settings.connectors')->name('settings.connectors');
    Volt::route('settings/memoire', 'settings.memory')->name('settings.memory');
    Route::get('memoire/logo', MemoryLogoController::class)->name('memory.logo');
    Route::get('recus/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('connecteurs/retour', [ConnectorController::class, 'callback'])->name('connectors.callback');
    Route::post('connecteurs/personnalise', [ConnectorController::class, 'store'])->middleware('throttle:10,1')->name('connectors.store');
    Route::get('connecteurs/{key}/connecter', [ConnectorController::class, 'connect'])->where('key', '[a-z0-9-]+')->middleware('throttle:20,1')->name('connectors.connect');
    Route::delete('connecteurs/{connector}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
});

require __DIR__.'/auth.php';
