<?php

// Captures « Recherches et réponses aussi bonnes que Claude et ChatGPT » (15 novembre 2026) : les VRAIES réponses du
// banc d'essai (runner/mesures/banc-recherche-avant.json et banc-recherche-apres.json) et de l'essai du fondateur
// (runner/mesures/fondateur.json), rangées telles quelles dans des discussions d'un compte inventé. Aucune requête au
// modèle ni à la recherche ici.
//   DB_DATABASE=/home/user/kekeli-recherche.sqlite php artisan tinker --execute="require 'scripts/preparer-captures-recherche-claude.php';"
// Affiche les identifiants des discussions (variables du script de captures).

use App\Agent\Markdown;
use App\Jobs\RespondToMessage;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$lire = fn (string $nom) => json_decode(file_get_contents(base_path("runner/mesures/{$nom}")), true);
$avant = collect($lire('banc-recherche-avant.json')['resultats'])->keyBy('numero');
$apres = collect($lire('banc-recherche-apres.json')['resultats'])->keyBy('numero');
// Joseph Ahiankonou et Claudel Noubissie : refaits avec la liste de toutes les requêtes envoyées (banc « captures »).
$captures = collect($lire('banc-recherche-captures.json')['resultats'])->keyBy('numero');
$fondateur = collect($lire('fondateur.json')['resultats'])->keyBy('question');

$user = User::where('email', 'afi-recherche@exemple.tg')->first() ?? new User(['email' => 'afi-recherche@exemple.tg']);
$user->forceFill(['name' => 'Afi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();

/** Une discussion avec une question et la réponse réelle (texte, sources citées, recherches faites). */
$discussion = function (string $nom, string $question, string $reponse, array $sources, array $requetes, int $minutes) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT])->save();
    $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
    $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    $texte = Markdown::prepare($reponse);
    $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $texte, 'agent_run_id' => $run->id]);
    $sites = collect($sources)->map(fn ($s) => preg_replace('/^www\./', '', (string) parse_url($s['url'], PHP_URL_HOST)))->unique()->values()->all();
    $searches = collect($requetes)->map(fn ($q) => ['query' => $q, 'sites' => $sites])->all();
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 2, 'web_searches' => $requetes ? 1 : 0,
        'report' => ['done' => $texte, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => RespondToMessage::sources($sources), 'searches' => $searches, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
    $projet->forceFill(['updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};

$depuis = fn (array $r) => [$r['reponse'], $r['liste_sources'], $r['requetes_envoyees'] ?? $r['recherches_demandees']];
$ids = [];
foreach ([
    'JOSEPH_AVANT' => ['Joseph Ahiankonou (avant)', $avant[1]],
    'JOSEPH_APRES' => ['Qui est Joseph Ahiankonou', $captures[1]],
    'NOUBISSIE_AVANT' => ['Claudel Noubissie (avant)', $avant[3]],
    'NOUBISSIE_APRES' => ['Qui est Claudel Noubissie', $captures[3]],
    'TOGOCOM' => ['Activités de Togocom', $apres[6]],
] as $cle => [$nom, $r]) {
    [$texte, $sources, $requetes] = $depuis($r);
    $ids[$cle] = $discussion($nom, $r['question'], $texte, $sources, $requetes, count($ids) + 1)->id;
}
foreach (['AGENTS' => ['Apprendre à créer des agents IA', 'je veux apprendre à créer des agents IA'], 'FONDATEUR' => ['Fondateur de Kekeli', 'qui est ton fondateur']] as $cle => [$nom, $question]) {
    $ids[$cle] = $discussion($nom, $question, $fondateur[$question]['reponse'], [], [], count($ids) + 1)->id;
}
foreach ($ids as $cle => $id) {
    echo "{$cle}={$id}\n";
}
