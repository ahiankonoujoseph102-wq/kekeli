<?php

// Données inventées pour les captures « Une formule gratuite généreuse et rapide » (docs/captures/formule-gratuite.md),
// sur une base SQLite neuve (DB_DATABASE=/home/user/kekeli-gratuite.sqlite php artisan migrate:fresh --force) :
//   DB_CONNECTION=sqlite DB_DATABASE=/home/user/kekeli-gratuite.sqlite php artisan tinker --execute="require 'scripts/preparer-captures-formule-gratuite.php';"
// Deux comptes de la formule Gratuite : Kossi a eu 3 discussions simples (énergie intacte) ; Ama a utilisé toutes ses
// discussions du jour (phrase courte et « Voir les formules »). Un administrateur pour Administration → Réglages.

use App\Agent\Energy;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;

$motDePasse = bcrypt('motdepasse-solide-2026');
$compte = function (string $email, string $nom, bool $admin = false) use ($motDePasse): User {
    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill(['name' => $nom, 'password' => $motDePasse, 'country' => 'TG', 'email_verified_at' => now(), 'plan' => 'gratuite', 'is_admin' => $admin, 'terms_accepted_at' => now(), 'welcomed_at' => now()])->save();

    return $user;
};

$discussion = function (User $user, string $titre, array $echanges, bool $limite = false) {
    $projet = $user->projects()->make(['name' => $titre]);
    $projet->forceFill(['kind' => Project::KIND_CHAT])->save();
    foreach ($echanges as $i => [$question, $reponse]) {
        $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
        $dernier = $limite && $i === array_key_last($echanges);
        $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $run->forceFill([
            'status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'provider' => 'coarena', 'model' => 'gpt-6-1-terra',
            'model_cost_usd' => 0.0016, 'simple_chat' => ! $dernier,
            'report' => ['done' => $reponse, 'note' => null, 'question' => true, 'markdown' => true, 'chat_limit' => $dernier, 'sources' => [], 'searches' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
        ])->save();
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
    }

    return $projet;
};

$kossi = $compte('kossi-gratuit@exemple.tg', 'Kossi Essai');
$compte('admin-gratuit@exemple.tg', 'Administrateur Essai', true);
$ama = $compte('ama-gratuit@exemple.tg', 'Ama Essai');

$discussion($kossi, 'Conseils pour ma boutique', [
    ['Salut', 'Bonjour ! Comment puis-je vous aider aujourd’hui ?'],
    ['C’est quoi un nom de domaine ?', 'Un **nom de domaine** est l’adresse de votre site, par exemple `boutique-kossi.com`. Il coûte en général entre 6 000 et 10 000 FCFA par an (à vérifier chez l’hébergeur).'],
    ['Merci, et pour une boutique de pagnes à Lomé, quel nom choisir ?', 'Quelques idées courtes et faciles à retenir : **Pagnes de Kossi**, **Wax Lomé**, **Kossi Tissus**. Choisissez un nom simple à dire au téléphone et vérifiez qu’il est libre en .tg et en .com.'],
]);

// Ama : toutes les discussions du jour utilisées (les 100 de la formule Gratuite : 96 plus anciennes, sans texte,
// rangées dans une discussion à part, puis les 3 de la discussion montrée).
$anciennes = $ama->projects()->make(['name' => 'Questions du matin']);
$anciennes->forceFill(['kind' => Project::KIND_CHAT])->save();
foreach (range(1, 97) as $i) {
    $anciennes->agentRuns()->create(['purpose' => AgentRun::PURPOSE_QUESTION])->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'model_cost_usd' => 0.0016, 'simple_chat' => true, 'report' => ['done' => '', 'question' => true, 'replaced' => true]])->save();
}
$limite = __('You have used all your discussions for today. They come back at :time. You can still create with your energy.', ['time' => app(Energy::class)->simpleChatsResetText()]);
$discussion($ama, 'Questions sur mon salon', [
    ['Bonjour', 'Bonjour ! Comment puis-je vous aider ?'],
    ['Quels horaires pour un salon de coiffure à Lomé ?', 'Beaucoup de salons ouvrent de **8 h à 19 h** du lundi au samedi, avec une ouverture plus courte le dimanche.'],
    ['Et les prix d’une tresse ?', 'Comptez en général entre **3 000 et 15 000 FCFA** selon la longueur et le modèle (à adapter à votre quartier).'],
    ['Merci, une dernière question', $limite],
], true);

echo 'Kossi : '.app(Energy::class)->percentLeft($kossi).' % d’énergie, '.app(Energy::class)->simpleChatsLeft($kossi).' discussions restantes'.PHP_EOL;
echo 'Ama : '.app(Energy::class)->simpleChatsLeft($ama->fresh()).' discussion restante'.PHP_EOL;
