<?php

// Captures « Montage vidéo et motion design » (13 novembre 2026) : avec le compte de l'essai réel (scripts/essai-videos.php),
// prépare deux écrans de plus, sans rien envoyer au Runner :
//   1. une discussion où Kekeli propose le plan d'une vidéo de 50 secondes (le vrai outil « creer_video », avec son plan) ;
//   2. une vidéo en cours de fabrication (« Rendu… 60 % »), pour montrer la progression et « Arrêter ».
// La vidéo de l'essai réel (versions 1 et 2) sert pour le lecteur, les versions et la carte du fichier.
//   php artisan tinker --execute="require 'scripts/preparer-captures-videos.php';"

use App\Agent\Tools\CreateVideo;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;

$user = User::where('email', 'essai-videos@exemple.tg')->firstOrFail();
$user->projects()->whereIn('name', ['Promotion Lomé IA School', 'Introduction Tech avec Kossi'])->get()->each->delete();

// 1. Le plan d'une vidéo longue, proposé par le vrai outil.
$plan = $user->projects()->make(['name' => 'Promotion Lomé IA School']);
$plan->forceFill(['kind' => Project::KIND_CHAT])->save();
$message = $plan->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une vidéo promotionnelle animée de 50 secondes, au format horizontal, pour mon école Lomé IA School à Adidogomé : 3 formations, cours le soir et le samedi, 25 000 FCFA par mois.']);
$repondant = $plan->agentRuns()->make(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
$repondant->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now(), 'finished_at' => now()])->save();
app(CreateVideo::class)->handle($repondant, [
    'duree' => '50',
    'format' => 'paysage',
    'nom' => 'Promotion Lomé IA School',
    'plan' => "## Scènes\n1. Accroche : « L'intelligence artificielle, c'est pour vous » (4 s)\n2. Lomé IA School à Adidogomé (7 s)\n3. Intelligence artificielle pour débutants (7 s)\n4. Création d'agents IA (7 s)\n5. Automatisations pour les entreprises (7 s)\n6. Petits groupes, le soir et le samedi (7 s)\n7. 25 000 FCFA par mois, Mixx by Yas ou Flooz (5 s)\n8. Fin : inscrivez-vous (6 s)\n## Texte affiché\nTitres courts, prix dans une pastille\n## Voix off\nOui, chaleureuse, avec sous-titres\n## Musique\nDynamique, libre de droits\n## Format\nHorizontal (16:9), pour YouTube et les écrans",
]);

// 2. Une vidéo en cours de fabrication : les étapes, puis le rendu à 60 %.
$enCours = $user->projects()->make(['name' => 'Introduction Tech avec Kossi']);
$enCours->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'video'])->save();
$demande = $enCours->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une introduction de 10 secondes pour ma chaîne YouTube « Tech avec Kossi », animée, avec une musique entraînante.']);
$run = $enCours->agentRuns()->make(['message_id' => $demande->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
$run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(80), 'sent_at' => now()->subSeconds(80), 'document_options' => ['type' => 'video', 'action' => 'creer', 'formats' => [], 'fichier' => null, 'recherche' => '', 'format' => 'paysage', 'duree' => 10]])->save();
foreach (['J’écris le script de la vidéo.', 'Je prépare les images.', 'Je choisis la musique.', 'Je monte la vidéo.'] as $phrase) {
    $run->steps()->create(['kind' => 'narration', 'target' => $phrase]);
}
$run->steps()->create(['kind' => 'rendu', 'target' => '60']);

$video = $user->projects()->where('kind', Project::KIND_DOCUMENT)->where('document_type', 'video')->whereHas('documentVersions')->latest('created_at')->firstOrFail();
echo json_encode(['plan' => $plan->id, 'en_cours' => $enCours->id, 'video' => $video->id])."\n";
