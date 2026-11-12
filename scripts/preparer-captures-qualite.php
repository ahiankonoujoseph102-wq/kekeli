<?php

// Données inventées pour les captures « Qualité des réponses comme Claude ». Aucune requête au modèle : les réponses
// sont écrites ici, comme le Runner les renvoie (texte en Markdown, résumé de la réflexion).
//   php artisan tinker --execute="require 'scripts/preparer-captures-qualite.php';"
// La même préparation sert pour « avant » (version précédente du site) et « après ».

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'afi-qualite@exemple.tg')->first() ?? new User(['email' => 'afi-qualite@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

/** Une discussion : [message du client, réponse (null : Kekeli répond encore), réflexion]. */
$discussion = function (string $nom, array $echanges, int $minutes) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT])->save();
    foreach ($echanges as [$question, $reponse, $reflexion]) {
        $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
        $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        if ($reponse === null) {
            $run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(4)])->save();

            continue;
        }
        $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
        $rapport = ['done' => $reponse, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []];
        if ($reflexion) {
            $rapport['thinking'] = $reflexion;
        }
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1, 'report' => $rapport])->save();
    }
    $projet->forceFill(['updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};

$riche = <<<'MD'
Pour une boutique de pagnes à Lomé, le plus sûr est de **commencer petit, avec un stock choisi et une vente en ligne dès le premier jour**.

## Le budget de départ

| Poste | Montant estimé | Remarque |
|---|---|---|
| Stock de départ (40 pièces) | 400 000 FCFA | Wax et pagnes locaux, en 3 gammes de prix |
| Loyer du local (3 mois d'avance) | 150 000 FCFA | Un petit local près d'un marché |
| Aménagement et présentoirs | 80 000 FCFA | Étagères, miroir, éclairage |
| **Total** | **630 000 FCFA** | Ordre de grandeur, à vérifier sur place |

## Les 3 points qui font la différence

1. **Le choix du stock** : peu de modèles, mais ceux qui partent vite. Notez chaque semaine ce qui se vend.
2. **Les photos** : des photos nettes, à la lumière du jour, pour WhatsApp et Facebook.
3. **La fidélité** : un carnet ou un groupe WhatsApp pour prévenir vos clientes des nouveautés.

## Ma recommandation

Commencez avec **un stock de 300 000 à 400 000 FCFA** et gardez au moins 100 000 FCFA de côté pour réassortir les modèles qui marchent. Une boutique qui manque de ses meilleurs pagnes perd ses clientes.
MD;

$boutique = $discussion('Ouvrir une boutique de pagnes', [[
    'je veux ouvrir une boutique de pagnes à Lomé combien il faut pour commencer et comment réussir',
    $riche,
    ['summary' => "Le client veut un budget de départ et des conseils concrets. Je compare le stock, le loyer et l'aménagement, puis je donne une recommandation chiffrée en FCFA.", 'seconds' => 6],
]], 2);

$calcul = $discussion('Remise de 15 %', [[
    'combien font 15 % de 40 000 FCFA',
    "**6 000 FCFA.**\n\n15 % de 40 000 FCFA, c'est 40 000 × 0,15 = 6 000 FCFA. Le prix après la remise est donc de **34 000 FCFA**.",
    null,
]], 9);

$code = $discussion('Lien WhatsApp sur une page', [[
    'comment mettre un bouton whatsapp sur ma page web',
    "Il suffit d'un lien qui ouvre WhatsApp avec votre numéro, écrit avec l'indicatif du pays et sans le signe « + » :\n\n```html\n<a href=\"https://wa.me/22890123456\">Écrire sur WhatsApp</a>\n```\n\nRemplacez **22890123456** par votre numéro. Sur un téléphone, le lien ouvre directement la discussion.",
    null,
]], 15);

$encours = $discussion('Plan pour une formation', [[
    'aide moi à préparer le plan d’une formation de 3 jours sur Excel pour des commerçants',
    null,
    null,
]], 1);

echo json_encode(['email' => $user->email, 'boutique' => $boutique->id, 'calcul' => $calcul->id, 'code' => $code->id, 'encours' => $encours->id]), "\n";
