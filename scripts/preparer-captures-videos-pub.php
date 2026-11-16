<?php

// Données inventées pour les captures « Vidéos publicitaires et échecs expliqués comme Claude Code » (15 novembre
// 2026). Aucune requête au modèle : les réponses et les échecs sont écrits ici comme le site les enregistre.
//   php artisan tinker --execute="require 'scripts/preparer-captures-videos-pub.php';"

use App\Agent\Failures;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-videos@exemple.tg')->first() ?? new User(['email' => 'afi-videos@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'free', 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

$discussion = function (string $nom, int $minutes) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT, 'updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};
$question = function (Project $projet, string $texte, array $report, string $status = AgentRun::STATUS_COMPLETED, ?string $error = null) {
    $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $texte]);
    $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    if (filled($report['done'] ?? '')) {
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $report['done'], 'agent_run_id' => $run->id]);
    }
    $run->forceFill(['status' => $status, 'error' => $error, 'finished_at' => now(), 'requests' => 1,
        'report' => $report + ['note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

    return $run;
};

// 1. La publicité : Kekeli demande la voix off (« Voix de femme », « Voix d'homme », « Ma propre voix »).
$pub = $discussion('Publicité Ama Pagnes', 1);
$question($pub, 'Fais-moi une publicité vidéo pour ma boutique Ama Pagnes au grand marché de Lomé : pagnes wax à 12 000 FCFA, commande sur WhatsApp au +228 90 12 34 56.', [
    'done' => "Pour créer une vidéo adaptée, j’ai besoin de quelques précisions :\n\n1. Quelle voix off préférez-vous pour votre publicité ?\n\nTouchez vos choix, écrivez vos réponses, ou laissez-moi choisir pour vous.",
    'questions' => ['tool' => 'creer_video', 'items' => [['question' => 'Quelle voix off préférez-vous pour votre publicité ?', 'choices' => ['Voix de femme', 'Voix d’homme', 'Ma propre voix']]]],
]);

// 2. Une demande impossible : ce que Kekeli ne peut pas faire, pourquoi, et ce qu'il fait à la place.
$appel = $discussion('Prévenir Koffi', 3);
$question($appel, 'Appelle mon client Koffi au 90 00 00 00 pour lui dire que sa commande est prête.', [
    'done' => "Je ne peux pas appeler votre client : je n’ai pas accès au téléphone.\n\nVoici le message prêt à lui envoyer sur WhatsApp ou par SMS :\n\n« Bonjour Koffi, votre commande est prête. Vous pouvez passer la récupérer à la boutique dès aujourd’hui. Merci pour votre confiance. »\n\nSi vous prévenez souvent vos clients, je peux aussi créer une automatisation qui leur envoie ce message toute seule depuis votre compte WhatsApp Business.",
]);

// 3. Tous les modèles très sollicités : l'échec expliqué et « Réessayer ».
$occupe = $discussion('Idées de promotion', 5);
$question($occupe, 'Donne-moi 3 idées de promotion pour la fête de l’indépendance.', ['done' => '', 'note' => Failures::busy(), 'retry' => true], AgentRun::STATUS_FAILED, 'premier répondant : aucun fournisseur disponible');

// 4. Une affiche sans création d'images (quota du jour épuisé) : l'heure du retour et une autre façon de faire.
$affiche = $user->projects()->make(['name' => 'Affiche soldes']);
$affiche->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => 'image', 'updated_at' => now()->subMinutes(7)])->save();
$message = $affiche->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une affiche pour les soldes de ma boutique, -20 % sur tous les pagnes ce samedi.']);
$run = $affiche->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
$run->forceFill(['status' => AgentRun::STATUS_FAILED, 'error' => 'images_indisponibles : quota du jour', 'finished_at' => now(),
    'report' => ['done' => Failures::explain('images_indisponibles', $run), 'note' => null, 'retry' => false, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

// 5. Une construction qui a échoué : la raison, ce qui est gardé, ce qui avait été fait, et « Réessayer ».
$site = $user->projects()->make(['name' => 'Salon Belle Afi']);
$site->forceFill(['kind' => Project::KIND_SITE, 'updated_at' => now()->subMinutes(9)])->save();
$message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute une page de réservation avec les horaires du salon.']);
$run = $site->agentRuns()->create(['message_id' => $message->id]);
$run->steps()->create(['kind' => 'narration', 'target' => 'Je prépare la page de réservation.']);
$run->forceFill(['status' => AgentRun::STATUS_FAILED, 'error' => 'aucun_fournisseur : 429', 'finished_at' => now(),
    'report' => ['done' => Failures::explain('aucun_fournisseur', $run)."\n\nAvant de s'arrêter, Kekeli avait fait ceci : Je prépare la page de réservation.", 'note' => null, 'retry' => true, 'next' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

echo "Prêt : afi-videos@exemple.tg\n";
