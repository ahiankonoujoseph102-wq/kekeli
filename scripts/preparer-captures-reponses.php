<?php

// Données inventées pour les captures « Répondre comme Claude et ChatGPT ». Aucune requête au modèle : les réponses
// sont écrites ici, comme le Runner les renvoie (texte en Markdown).
//   php artisan tinker --execute="require 'scripts/preparer-captures-reponses.php';"
// La même préparation sert pour « avant » (version précédente du site) et « après ».

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-reponses@exemple.tg')->first() ?? new User(['email' => 'afi-reponses@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system', 'is_admin' => true])->save();
$user->projects()->get()->each->delete();

/** Une discussion : liste de [message du client, réponse de Kekeli]. */
$discussion = function (string $nom, array $echanges, int $minutes) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT])->save();
    foreach ($echanges as [$question, $reponse]) {
        $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
        $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1,
            'report' => ['done' => $reponse, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
    }
    $projet->forceFill(['updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};

$longue = <<<'MD'
Pour une tresse à Lomé, comptez **entre 3 000 et 25 000 FCFA** selon le modèle, la longueur et le salon. Les mèches sont souvent en plus.

## Les prix courants

| Modèle | Prix de la pose | Durée |
|---|---|---|
| Nattes collées simples | 3 000 à 5 000 FCFA | 1 à 2 h |
| Tresses avec mèches (mi-dos) | 8 000 à 15 000 FCFA | 4 à 6 h |
| Knotless ou box braids longues | 15 000 à 25 000 FCFA | 6 à 8 h |

Ce sont des ordres de grandeur observés en 2024 : demandez toujours le prix avant de commencer.

## Ce qui fait varier le prix

1. **La longueur** : jusqu'aux fesses, le prix peut doubler.
2. **La finesse** : des tresses fines demandent plus d'heures.
3. **Les mèches** : de 1 500 à 6 000 FCFA le paquet selon la marque ; il en faut 2 à 5.
4. **Le quartier** : un salon de Tokoin ou de Bè est souvent moins cher qu'un salon du centre.

## Mon conseil

Pour un premier essai, prenez des **tresses avec mèches mi-dos** : environ 12 000 FCFA tout compris, et elles tiennent 4 à 6 semaines. Apportez vos mèches pour mieux maîtriser le prix.
MD;

$discussion('Prix d’une tresse à Lomé', [['combien coûte une tresse à Lomé', $longue]], 2);

$discussion('Capitale du Burkina Faso', [['Quelle est la capitale du Burkina Faso ?', 'La capitale du Burkina Faso est **Ouagadougou**.']], 5);

$discussion('Bouton WhatsApp sur une page', [[
    'comment mettre un bouton whatsapp sur ma page web',
    "Il suffit d'un lien qui ouvre WhatsApp avec votre numéro, écrit avec l'indicatif du pays et sans le signe « + » :\n\n```html\n<a href=\"https://wa.me/22890123456\" class=\"bouton-whatsapp\">\n  Écrire sur WhatsApp\n</a>\n```\n\nRemplacez **22890123456** par votre numéro. Sur un téléphone, le lien ouvre directement la discussion ; sur un ordinateur, il ouvre WhatsApp Web.",
]], 8);

// Une longue discussion, pour le bouton « ↓ » (revenir à la fin).
$echanges = [];
foreach (['Comment trouver mes premiers clients ?', 'Et sur Facebook ?', 'Combien dépenser en publicité ?', 'Comment fixer mes prix ?'] as $i => $q) {
    $echanges[] = [$q, "Voici l'essentiel, en trois idées.\n\n1. **Commencez par votre entourage** : famille, voisins et anciens collègues sont vos premiers clients et vos premiers ambassadeurs.\n2. **Soyez régulier** : une publication par jour vaut mieux que dix le même jour.\n3. **Mesurez** : notez d'où vient chaque client pour savoir ce qui marche.\n\nAvec un petit budget, misez sur WhatsApp et le bouche-à-oreille avant de payer de la publicité. Gardez une trace de chaque vente pendant un mois, puis gardez seulement ce qui rapporte."];
}
$discussion('Trouver ses premiers clients', $echanges, 11);

echo "Données des captures prêtes.\n";
