<?php

// Suite de l'essai du relecteur : une modification qui change plusieurs prix d'un site existant (modèle gratuit),
// puis les nouveaux prix absents et les anciens prix restés sur le site.
//   PROJET=<identifiant> php artisan tinker --execute="require 'scripts/essai-relecteur-modif.php';"

use App\Agent\ProjectBackup;
use App\Agent\RunStarter;
use App\Models\Message;
use App\Models\Project;
use App\Projects\TarArchive;
use App\Support\Settings;

$demande = 'Nouveaux tarifs : maternelle 88 000 FCFA, CP 98 000 FCFA, CE1 et CE2 108 000 FCFA, CM1 et CM2 118 000 FCFA. Le transport passe à 11 000 FCFA par mois. Nouveau numéro du secrétariat : +228 70 99 88 77.';
$anciens = ['85 000', '95 000', '105 000', '115 000', '10 000', '70 44 55 66'];
$project = Project::findOrFail(getenv('PROJET'));
$settings = app(Settings::class);
$plans = $settings->get('plans');
$avant = $plans['pro']['build_model'] ?? null;
$plans['pro']['build_model'] = '';
$settings->set('plans', $plans);
try {
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
    $run = app(RunStarter::class)->startWith($project, $message);
    $debut = time();
    while ($run->fresh()->isActive() && time() - $debut < 1800) {
        sleep(10);
    }
    $run->refresh();
    $html = '';
    foreach (TarArchive::entries((string) gzdecode((string) app(ProjectBackup::class)->disk()->get(app(ProjectBackup::class)->path($project->id)))) as $path => [$content]) {
        if (str_ends_with($path, '.html')) {
            $html .= ' '.$content;
        }
    }
    $texte = preg_replace('/\s+/', ' ', str_replace(['&nbsp;', '&#160;', "\u{202F}", "\u{00A0}"], ' ', strip_tags($html)));
    $chiffres = preg_replace('/[\s.\-()]/u', '', $texte);
    preg_match_all('/\d{1,3}(?: \d{3})* FCFA|\+228 [\d ]+\d/u', $demande, $faits);
    $absents = array_values(array_filter($faits[0], fn ($f) => ! str_contains($chiffres, preg_replace('/\D/', '', $f))));
    $restes = array_values(array_filter($anciens, fn ($a) => str_contains($texte, $a)));
    echo json_encode(['etat' => $run->status, 'secondes' => time() - $debut, 'faits' => count($faits[0]), 'absents' => $absents, 'anciens_restes' => $restes, 'corrections' => $run->steps()->where('kind', 'verification_correction')->count(), 'tokens_entree' => $run->input_tokens], JSON_UNESCAPED_UNICODE)."\n";
} finally {
    $plans['pro']['build_model'] = $avant;
    $settings->set('plans', $plans);
}
