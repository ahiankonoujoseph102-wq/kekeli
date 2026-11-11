<?php

// Données d'essai inventées pour les captures « publier et récupérer son site » (jamais de vraies données de clients).
//   php artisan migrate:fresh --force
//   php artisan tinker --execute="require 'scripts/preparer-captures-nettoyage.php';"
//   (captures AVANT)
//   php artisan tinker --execute="require 'scripts/preparer-captures-publier.php';"
//   (captures APRÈS)
// La réponse ci-dessous est celle donnée par Kekeli (Gemini 3.5 Flash-Lite, offre gratuite) lors de l'essai réel du
// 12 novembre 2026, pour le même site inventé.

use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\User;

$pressing = User::where('email', 'kofi-nettoyage@exemple.tg')->firstOrFail()->projects()->firstOrFail();
$pressing->agentRuns()->where('purpose', AgentRun::PURPOSE_QUESTION)->delete();

$question = 'Où héberger mon site et combien ça coûte ?';
$reponse = "Pour héberger votre site Pressing Éclat qui utilise une base de données avec PHP et MySQL, voici deux choix honnêtes :\n\n"
    ."- Un hébergement mutualisé chez un hébergeur au Togo ou chez Hostinger. Le coût est en général entre 3 et 10 dollars par mois, soit environ 1 800 à 6 000 francs CFA par mois.\n"
    ."- Laravel Cloud, qui propose un paiement à l'usage, soit quelques dollars par mois pour un petit site.\n\n"
    ."Pensez à vérifier ces prix sur le site de l'hébergeur.\n\n"
    ."Kekeli n'héberge pas les sites avec base de données pour l'instant. Votre code vous appartient, vous pouvez le télécharger depuis l'onglet Code. La personne qui hébergera votre site trouvera toutes les explications nécessaires dans le fichier README.";

$message = $pressing->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
$run = $pressing->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
$run->forceFill([
    'status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subSeconds(3), 'finished_at' => now(),
    'provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'requests' => 1, 'input_tokens' => 784, 'output_tokens' => 181,
    'report' => ['done' => RunReport::clean($reponse), 'note' => null, 'question' => true, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []],
])->save();
$pressing->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);

echo "Question d'essai prête.\n";
