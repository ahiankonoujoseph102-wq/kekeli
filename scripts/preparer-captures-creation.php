<?php

// Captures « Créer comme Lovable et Claude » (15 novembre 2026) : états inventés, sans aucune requête au modèle.
//   php artisan tinker --execute="require 'scripts/preparer-captures-creation.php';"
// Affiche les identifiants des projets (variables du script scripts/captures-creation.js).

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$compte = function (string $email, string $name) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $name, 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'appearance' => 'system', 'welcome_bonus_fcfa' => 500, 'welcome_bonus_at' => now()->subDay()])->save();
    $user->projects()->get()->each->delete();

    return $user;
};
$rapport = fn (string $texte, array $plus = []) => $plus + ['done' => $texte, 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []];
$ids = [];

// 1. Les questions avant une création, avec leurs choix (dernier message de la discussion).
$afi = $compte('afi-creation@exemple.tg', 'Afi Mensah');
$chat = $afi->projects()->make(['name' => 'Site de formations en IA']);
$chat->forceFill(['kind' => Project::KIND_CHAT])->save();
$message = $chat->messages()->create(['role' => Message::ROLE_USER, 'content' => 'pouvez-vous me créer un site web pour vendre mes formations en intelligence artificielle']);
$run = $chat->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
$questions = [
    ['question' => 'Quel nom souhaitez-vous donner à votre activité ?', 'choices' => ['Académie IA Togo', 'Formations IA Lomé']],
    ['question' => 'Quelles formations voulez-vous mettre en avant ?', 'choices' => ['Initiation à l’IA', 'IA pour entreprises', 'Création d’agents IA']],
    ['question' => 'Comment les clients peuvent-ils vous contacter ?', 'choices' => ['WhatsApp à Lomé', 'Téléphone à Lomé', 'E-mail et en ligne']],
];
$texte = "Pour créer un site qui vous ressemble, j’ai besoin de quelques précisions :\n\n".implode("\n", array_map(fn ($q, $i) => ($i + 1).'. '.$q['question'], $questions, array_keys($questions)))."\n\nTouchez vos choix, écrivez vos réponses, ou laissez-moi choisir pour vous.";
$chat->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $texte, 'agent_run_id' => $run->id]);
$run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'simple_chat' => true, 'report' => $rapport($texte, ['question' => true, 'markdown' => true, 'questions' => ['tool' => 'creer_site', 'items' => $questions]])])->save();
$ids['QUESTIONS'] = $chat->id;

// 2. Les étapes visibles pendant le travail (dépliées une par une).
$site = $afi->projects()->create(['name' => 'Clean Express']);
$message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée un site pour mon pressing Clean Express à Bè (Lomé) : 500 FCFA la chemise, 1 500 FCFA le costume, livraison à domicile, WhatsApp, couleurs bleu et blanc.']);
$run = $site->agentRuns()->create(['message_id' => $message->id]);
$run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(70), 'sent_at' => now()->subSeconds(72)])->save();
foreach ([
    ['narration', 'Je prépare un site clair pour présenter vos services de pressing.'],
    ['fichier_lu', 'dossier du projet'],
    ['narration', 'Je crée d’abord la page d’accueil avec vos prix et le bouton WhatsApp.'],
    ['fichier_ecrit', 'index.html'],
    ['narration', 'J’ajoute la livraison à domicile, les horaires et les moyens de paiement.'],
    ['photos', 'pressing west africa'],
    ['narration', 'Je vérifie l’affichage sur téléphone.'],
] as [$kind, $target]) {
    $run->steps()->create(['kind' => $kind, 'target' => $target]);
}
$ids['ETAPES'] = $site->id;

// 3. Une création qui dépasse l'énergie et se termine proprement (formule Gratuite, bonus déjà utilisé).
$kossi = $compte('kossi-depasse@exemple.tg', 'Kossi Agbéko');
$kossi->forceFill(['welcome_bonus_fcfa' => 500, 'welcome_bonus_at' => now()->subDays(3)])->save();
$old = $kossi->projects()->create(['name' => 'Premier essai']);
$oldMessage = $old->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Un premier site']);
$old->agentRuns()->create(['message_id' => $oldMessage->id])->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now()->subDays(2), 'model_cost_usd' => 0.9, 'created_at' => now()->subDays(2)])->save();
$restaurant = $kossi->projects()->create(['name' => 'Chez Tante Akpéné']);
$message = $restaurant->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée le site de mon restaurant Chez Tante Akpéné à Bè, avec le menu, les prix et un bouton WhatsApp.']);
$run = $restaurant->agentRuns()->create(['message_id' => $message->id]);
foreach ([['narration', 'Je crée la page d’accueil et le menu avec vos prix.'], ['fichier_ecrit', 'index.html'], ['fichier_ecrit', 'menu.html'], ['fin_garde_fou', ''], ['sauvegarde', '86 Ko']] as [$kind, $target]) {
    $run->steps()->create(['kind' => $kind, 'target' => $target]);
}
$fin = "Votre site est prêt pour l’essentiel.\n- Accueil : le restaurant, le quartier et le bouton WhatsApp\n- Menu : vos plats avec leurs prix en FCFA\nJ’ai choisi les couleurs orange et crème. La page Contact reste à faire : demandez-la quand votre énergie sera rechargée.";
$restaurant->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $fin, 'agent_run_id' => $run->id]);
$run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subMinutes(4), 'finished_at' => now(), 'model_cost_usd' => 0.25, 'computer_seconds' => 230, 'stop_reason' => AgentRun::STOP_GUARD,
    'report' => $rapport($fin, ['next' => ['Ajouter la page Contact', 'Changer les couleurs', 'Mettre en ligne'], 'guard' => true, 'files' => [['chemin' => 'index.html', 'etat' => 'ajoute'], ['chemin' => 'menu.html', 'etat' => 'ajoute']], 'files_count' => 2])])->save();
$ids['DEPASSE'] = $restaurant->id;

foreach ($ids as $cle => $id) {
    echo "{$cle}={$id}\n";
}
