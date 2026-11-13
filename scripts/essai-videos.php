<?php

// Essai réel d'une vidéo de bout en bout (13 novembre 2026) : le vrai premier répondant choisit l'outil « creer_video »,
// le vrai Runner écrit le scénario, crée la voix off et rend la vidéo dans un vrai ordinateur E2B, puis le site reçoit
// le MP4 et en fait une version. Compte d'essai inventé (formule Pro), données inventées.
//   Site, « php artisan queue:work » et Runner lancés avec le même KEKELI_RUNNER_SECRET, puis :
//   php artisan tinker --execute="require 'scripts/essai-videos.php';"   (variable MODIFIER=1 : une nouvelle version)

use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\WebApps\NewSite;
use Illuminate\Support\Facades\Hash;

$journal = fn (string $texte) => print '['.now()->timezone('Africa/Lome')->format('H:i:s').'] '.$texte."\n";
$user = User::where('email', 'essai-videos@exemple.tg')->first() ?? new User(['email' => 'essai-videos@exemple.tg']);
$user->forceFill(['name' => 'Ama Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();

$attendre = function (callable $condition, int $secondes, string $quoi) use ($journal) {
    $debut = time();
    while (! $condition()) {
        if (time() - $debut > $secondes) {
            throw new RuntimeException("Délai dépassé : $quoi");
        }
        sleep(5);
    }
    $journal("$quoi (".(time() - $debut).' s)');
};

if (getenv('MODIFIER')) {
    $project = $user->projects()->where('kind', Project::KIND_DOCUMENT)->where('document_type', 'video')->latest('created_at')->firstOrFail();
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Mets le prix en plus gros et enlève les sous-titres.']);
    $avant = $project->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->count();
    RespondToMessage::dispatch($project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]));
} else {
    $user->projects()->where('kind', Project::KIND_DOCUMENT)->where('document_type', 'video')->get()->each->delete();
    $project = app(NewSite::class)->create($user, 'Fais une publicité TikTok verticale de 25 secondes pour ma boutique de pagnes « Ama Pagnes » au Grand Marché de Lomé. Promotion : -20 % sur les pagnes wax cette semaine, le pagne complet à 12 000 FCFA au lieu de 15 000 FCFA. Livraison partout à Lomé. Paiement Mixx by Yas et Flooz. Commandes sur WhatsApp au +228 90 12 34 56. Avec une voix off, des sous-titres et une musique entraînante.');
    $avant = 0;
}

$attendre(fn () => $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->count() > $avant, 300, 'Outil « creer_video » choisi');
$run = $project->fresh()->agentRuns()->where('purpose', AgentRun::PURPOSE_DOCUMENT)->reorder('created_at', 'desc')->first();
$journal('Options : '.json_encode($run->document_options, JSON_UNESCAPED_UNICODE));
$attendre(fn () => ! $run->fresh()->isActive(), 1800, 'Vidéo : '.$run->fresh()->status);
$run->refresh();
$version = $project->fresh()->documentVersions()->reorder('number', 'desc')->first();
$journal('État : '.$run->status.' ; version '.$version?->number.' ; fichiers '.json_encode($version?->files));
$journal('Vérifications : '.json_encode(data_get($version?->structure, 'verifications'), JSON_UNESCAPED_UNICODE));
$journal(sprintf('Coût : modèle %.4f $, voix %.4f $, images %.4f $, ordinateur %.4f $ (%d s)', $run->model_cost_usd, $run->voice_cost_usd, $run->image_cost_usd, $run->computer_cost_usd, $run->computer_seconds));
echo json_encode(['projet' => $project->id, 'version' => $version?->number])."\n";
