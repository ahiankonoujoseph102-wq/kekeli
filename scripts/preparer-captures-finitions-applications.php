<?php

// Données inventées pour les captures « avant / après » des finitions de la pull request 41 (applications).
// Aucune requête au modèle : les étapes et les phrases sont écrites ici, comme le Runner les envoie.
//   KEKELI_CAPTURES=apres php artisan tinker --execute="require 'scripts/preparer-captures-finitions-applications.php';"
// Avec KEKELI_CAPTURES=avant (dans une copie de la version précédente), les mêmes données sont préparées comme elles
// arrivaient avant ces finitions (titre coupé, phrase « Je découvre votre application actuelle… »).

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$avant = getenv('KEKELI_CAPTURES') === 'avant';
$motDePasse = Hash::make('motdepasse-solide-2026');

$compte = function (string $email, string $formule) use ($motDePasse) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => 'Afi Mensah', 'password' => $motDePasse, 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => $formule, 'plan_expires_at' => $formule === 'gratuite' ? null : now()->addDays(20), 'appearance' => 'system'])->save();
    $user->projects()->get()->each->delete();

    return $user;
};

// 1. Une application en construction : actions repliées, phrases de travail, titre.
$user = $compte('afi-finitions-applications@exemple.tg', 'pro');
$demande = 'je veux une application de rendez-vous pour mon Salon de coiffure Belle Afi à Lomé';
$projet = $user->projects()->make(['name' => $avant ? 'Je veux une application de rendez-vous' : 'Salon de coiffure Belle Afi']);
$projet->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();
$message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
$run = $projet->agentRuns()->create(['message_id' => $message->id]);
$etapes = [
    ['narration', $avant ? 'Je découvre votre application actuelle pour ajouter les rendez-vous sans toucher aux comptes existants.' : 'Je prépare les pages de votre application de rendez-vous.'],
    ['recherche', '/home/user/projet'],
    ['fichier_lu', $avant ? '/home/user/projet/' : '/home/user/projet'],
    ['fichier_lu', '/home/user/projet/routes/web.php'],
    ['fichier_ecrit', '/home/user/projet/database/migrations/2026_11_12_000001_create_rendez_vous_table.php'],
    ['commande', 'php artisan migrate && touch /home/user/.kekeli/relancer'],
    ['fichier_modifie', '/home/user/projet/resources/views/accueil.blade.php'],
    ['commande', 'touch /home/user/.kekeli/relancer'],
    ['commande', 'php artisan test'],
];
foreach ($etapes as [$genre, $cible]) {
    $run->steps()->create(['kind' => $genre, 'target' => $cible]);
}
$run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 20, 'report' => ['done' => "Votre application de rendez-vous est prête.\n\nVos clientes créent un compte, choisissent une prestation et un horaire ; vous voyez et confirmez les rendez-vous dans l'administration.", 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
$projet->forceFill(['updated_at' => now()->subMinute()])->save();

// 2. Énergie épuisée pendant une construction (formule Gratuite).
$gratuit = $compte('kofi-finitions-applications@exemple.tg', 'gratuite');
$arrete = $gratuit->projects()->make(['name' => 'Boutique Ayélé']);
$arrete->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();
$m = $arrete->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la gestion du stock des pagnes']);
$r = $arrete->agentRuns()->create(['message_id' => $m->id]);
$r->steps()->create(['kind' => 'narration', 'target' => "J'ajoute la page du stock des pagnes."]);
$r->steps()->create(['kind' => 'fichier_ecrit', 'target' => '/home/user/projet/app/Models/Stock.php']);
$r->forceFill(['status' => AgentRun::STATUS_STOPPED, 'stop_reason' => 'energie', 'stop_requested_at' => now(), 'finished_at' => now(), 'model_cost_usd' => 0.2, 'sent_at' => now()])->save();

echo json_encode(['application' => $projet->id, 'energie' => $arrete->id]), "\n";
