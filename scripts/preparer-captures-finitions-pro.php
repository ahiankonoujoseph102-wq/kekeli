<?php

// Données inventées pour les captures « Finitions professionnelles de la discussion et de la recherche ».
// Aucune requête au modèle ni à la recherche : les réponses sont écrites ici, comme le Runner les renvoie.
//   KEKELI_CAPTURES=apres php artisan tinker --execute="require 'scripts/preparer-captures-finitions-pro.php';"
// Avec KEKELI_CAPTURES=avant, les mêmes discussions sont préparées comme elles étaient avant ces finitions (titre
// coupé, phrase « Je n'ai pas pu vérifier… »), pour la version précédente du site.

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$avant = getenv('KEKELI_CAPTURES') === 'avant';

$user = User::where('email', 'afi-finitions@exemple.tg')->first() ?? new User(['email' => 'afi-finitions@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

$rapport = fn (string $texte, array $plus = []) => $plus + ['done' => $texte, 'note' => null, 'question' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []];

/** Une discussion, avec ses échanges : [message du client, réponse, compléments du compte rendu, recherche faite]. */
$discussion = function (string $nom, array $echanges, int $minutes) use ($user, $rapport) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT])->save();
    foreach ($echanges as [$question, $reponse, $plus, $recherche]) {
        $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
        $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        if ($reponse === null) {
            continue; // Kekeli est en train de répondre.
        }
        if ($recherche) {
            $run->steps()->create(['kind' => 'recherche_web', 'target' => $recherche]);
        }
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => $recherche ? 2 : 1, 'web_searches' => $recherche ? 1 : 0, 'report' => $rapport($reponse, $plus)])->save();
    }
    $projet->forceFill(['updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};

$sources = [
    ['title' => 'Togo : le prix du ciment fixé à 4 050 FCFA le sac', 'url' => 'https://www.togofirst.com/fr/gouvernance-economique/prix-du-ciment', 'date' => 'juin 2026'],
    ['title' => 'Prix des matériaux de construction', 'url' => 'https://commerce.gouv.tg/prix-des-materiaux'],
    ['title' => 'Ciment : ce qui change pour les revendeurs', 'url' => 'https://www.republicoftogo.com/toutes-les-rubriques/economie/ciment', 'date' => 'mars 2026'],
];
$recherches = [['query' => 'prix sac de ciment Lomé Togo 2026', 'sites' => ['togofirst.com', 'commerce.gouv.tg', 'republicoftogo.com', 'cimtogo.tg', 'journaldutogo.com']]];

$ciment = $discussion($avant ? 'Quel est le prix du sac' : 'Prix du ciment à Lomé', [[
    'quel est le prix du sac de ciment à Lomé en ce moment',
    "Selon un article de juin 2026, le sac de ciment de 50 kg est vendu environ 4 050 FCFA à Lomé [1]. Ce prix est encadré par l'État [1, 2].\n\nEn mars 2026, les revendeurs signalaient de petits écarts selon le quartier et le transport [3]. La page du ministère ne donne pas de date [2] : le prix a pu changer, il vaut mieux le vérifier chez votre revendeur au moment de l'achat.",
    ['sources' => $sources, 'searches' => $recherches],
    'prix sac de ciment Lomé Togo 2026',
]], 2);

// Limite de recherches du jour atteinte : avant, Kekeli le disait ; maintenant, il répond normalement.
$change = $discussion($avant ? 'Quel est le taux de change' : 'Taux du dollar en FCFA', [[
    "quel est le taux de change du dollar en franc CFA aujourd'hui",
    $avant
        ? "Je n'ai pas pu vérifier le taux du jour sur Internet. En général, 1 $ vaut environ 600 FCFA, mais ce taux change chaque jour."
        : "En 2025, 1 $ valait environ 600 FCFA. Ce taux change chaque jour : vérifiez-le auprès de votre banque ou d'un bureau de change au moment de votre opération.",
    [],
    null,
]], 9);
$change->agentRuns()->first()->forceFill(['web_searches' => 3])->save();

$restaurant = $discussion($avant ? "J'ai un restaurant à Lomé" : 'Restaurant Le Palmier à Lomé', [[
    "j'ai un restaurant à Lomé qui s'appelle Le Palmier",
    "Un restaurant gagne à montrer son menu, ses horaires et son adresse sur Internet.\n\nVoulez-vous que je crée le site de votre restaurant ?",
    ['offer' => 'creer_site'],
    null,
]], 15);

$conseils = $discussion($avant ? 'Donne moi des conseils pour attirer' : 'Conseils pour attirer des clients', [
    ['donne moi des conseils pour attirer plus de clients dans mon salon de coiffure', "Voici 3 pistes simples :\n\n- Publiez chaque semaine des photos de vos coiffures.\n- Offrez une petite réduction au client qui en amène un autre.\n- Affichez clairement vos prix et vos horaires.", [], null],
    ['écris moi un long texte de présentation pour mon salon', null, [], null],
], 1);

// Un site vitrine, pour la page du projet et un travail arrêté.
$site = $user->projects()->create(['name' => 'Boutique Ayélé']);
$message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'crée un site pour ma boutique de pagnes à Lomé']);
$run = $site->agentRuns()->create(['message_id' => $message->id]);
$run->steps()->create(['kind' => 'narration', 'target' => "Je prépare la page d'accueil avec vos pagnes."]);
$run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 12, 'report' => ['done' => "Votre site est prêt. Il présente votre boutique, vos pagnes et vos horaires.\n\nVos clients peuvent vous écrire sur WhatsApp en un geste.", 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
$site->forceFill(['updated_at' => now()->subMinutes(30)])->save();

echo json_encode(['email' => $user->email, 'ciment' => $ciment->id, 'change' => $change->id, 'restaurant' => $restaurant->id, 'conseils' => $conseils->id, 'site' => $site->id]), "\n";
