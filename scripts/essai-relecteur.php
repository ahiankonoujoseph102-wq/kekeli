<?php

// Essai réel du relecteur des sites (13 novembre 2026) : un site vitrine construit avec le modèle GRATUIT (aucune
// dépense) à partir d'une demande pleine de faits précis (prix, numéro, e-mail), puis la liste des faits absents du site.
// Lancer deux fois : Runner démarré avec KEKELI_RELECTEUR=non (avant), puis sans (après).
//   php artisan tinker --execute="require 'scripts/essai-relecteur.php';"

use App\Agent\ProjectBackup;
use App\Agent\RunStarter;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Projects\TarArchive;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$demande = getenv('DEMANDE') ?: "Crée un site vitrine d'une seule page index.html pour le maquis « Chez Ama » à Bè, Lomé. Menu : riz sauce arachide 1 500 FCFA, attiéké poisson braisé 2 500 FCFA, fufu sauce graine 2 000 FCFA, jus de bissap 500 FCFA. Réservations au +228 90 12 34 56, e-mail chezama@exemple.tg. Paiement Mixx by Yas et Flooz.";
$user = User::where('email', 'essai-relecteur@exemple.tg')->first() ?? new User(['email' => 'essai-relecteur@exemple.tg']);
$user->forceFill(['name' => 'Ama Koffi', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20)])->save();
$settings = app(Settings::class);
$plans = $settings->get('plans');
$avant = $plans['pro']['build_model'] ?? null;
$plans['pro']['build_model'] = '';
$settings->set('plans', $plans);

try {
    $project = $user->projects()->make(['name' => 'Chez Ama']);
    $project->forceFill(['kind' => Project::KIND_SITE])->save();
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
    $run = app(RunStarter::class)->startWith($project, $message);
    $debut = time();
    while ($run->fresh()->isActive() && time() - $debut < 1800) {
        sleep(10);
    }
    $run->refresh();
    $files = [];
    foreach (TarArchive::entries((string) gzdecode((string) app(ProjectBackup::class)->disk()->get(app(ProjectBackup::class)->path($project->id)))) as $path => [$content]) {
        if (str_ends_with($path, '.html')) {
            $files[] = $content;
        }
    }
    $texte = preg_replace('/\s+/', ' ', str_replace(['&nbsp;', '&#160;'], ' ', strip_tags(implode(' ', $files))));
    $chiffres = preg_replace('/[\s.\-()]/u', '', $texte);
    preg_match_all('/\d{1,3}(?: \d{3})* FCFA|\+228 [\d ]+\d|[\w.]+@\w+(?:\.\w+)+/u', $demande, $faits);
    $absents = array_values(array_filter($faits[0], fn ($f) => str_contains($f, '@') ? ! str_contains($texte, $f) : ! str_contains($chiffres, preg_replace('/\D/', '', $f))));
    $corrections = $run->steps()->where('kind', 'verification_correction')->count();
    echo json_encode(['projet' => $project->id, 'etat' => $run->status, 'secondes' => time() - $debut, 'faits' => count($faits[0]), 'absents' => $absents, 'corrections' => $corrections, 'tokens_entree' => $run->input_tokens, 'tokens_sortie' => $run->output_tokens, 'modele' => $run->model], JSON_UNESCAPED_UNICODE)."\n";
} finally {
    $plans['pro']['build_model'] = $avant;
    $settings->set('plans', $plans);
}
