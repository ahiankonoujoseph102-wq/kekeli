<?php

// Données inventées pour les captures du mode équipe (phase 1) : un administrateur d'essai, une discussion avec le plan
// d'une application (les deux boutons), et une application construite en mode équipe avec ses missions.
//   php artisan tinker --execute="require 'scripts/preparer-captures-equipe.php';"

use App\Agent\Tools\CreateApplication;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'equipe-admin@exemple.tg')->first() ?? new User(['email' => 'equipe-admin@exemple.tg']);
$admin->forceFill(['name' => 'Kossi Admin', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'light', 'is_admin' => true])->save();
$admin->projects()->get()->each->delete();

$plan = "## Pages\n- Accueil, catalogue des formations, détail d'une formation, inscription et connexion, « Mes formations », paiement, tableau de bord administrateur\n\n## Données\n- Formations, chapitres, inscriptions, paiements\n\n## Rôles\n- Membre, administrateur\n\nTechnologie : Laravel (PHP), simple et solide, facile à héberger";

$chat = $admin->projects()->create(['name' => 'Plateforme Savoir Plus']);
$chat->forceFill(['kind' => Project::KIND_CHAT])->save();
$question = $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une plateforme professionnelle de formation en ligne avec inscription, connexion, espace membre, catalogue de formations, paiement et tableau de bord administrateur.']);
$offer = $chat->agentRuns()->make(['message_id' => $question->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
$offer->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'report' => ['done' => $plan, 'note' => null, 'question' => true, 'markdown' => true, 'offer' => CreateApplication::OFFER, 'plan' => $plan, 'pile' => 'auto', 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
$chat->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $plan, 'agent_run_id' => $offer->id]);

$app = $admin->projects()->create(['name' => 'Savoir Plus']);
$app->forceFill(['kind' => Project::KIND_WEBAPP, 'is_application' => true, 'app_stack' => 'laravel', 'app_template' => 'socle'])->save();
$build = $app->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Construire ce plan']);
$run = $app->agentRuns()->make(['message_id' => $build->id]);
$run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subMinutes(6), 'team' => true])->save();
$run->steps()->create(['kind' => 'narration', 'target' => 'Mission 4 sur 6 : Espace membre et accès aux chapitres.']);
$missions = [
    ['m1', 'base-de-donnees', 'Formations, chapitres, inscriptions et paiements', 'verifiee', 64, 14],
    ['m2', 'frontend', 'Page d’accueil et catalogue', 'verifiee', 112, 19],
    ['m3', 'backend', 'Inscription et paiement d’essai', 'verifiee', 138, 22],
    ['m4', 'backend', 'Espace membre et accès aux chapitres', 'en_cours', 0, 0],
    ['m5', 'frontend', 'Tableau de bord administrateur', 'en_attente', 0, 0],
    ['m6', 'testeur', 'Vérification complète', 'en_attente', 0, 0],
];
foreach ($missions as $i => [$code, $role, $title, $status, $seconds, $requests]) {
    $run->missions()->create(['project_id' => $app->id, 'position' => $i + 1, 'code' => $code, 'role' => $role, 'title' => $title, 'objective' => $title, 'criteria' => ['un test le vérifie'], 'files' => [], 'depends_on' => [], 'status' => $status, 'seconds' => $seconds, 'requests' => $requests]);
}

echo $chat->id, ' ', $app->id, "\n";
