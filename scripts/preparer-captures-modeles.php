<?php

// Données pour les captures « Nouveaux modèles : test, choix automatique, Standard et Pro » (docs/captures/modeles.md).
// À lancer APRÈS scripts/preparer-captures-design.php (mêmes comptes inventés). Les deux réponses viennent d'un vrai
// essai (docs/captures/modeles/reponses-standard-pro.json) ; les comptes Coarena montrés à l'administration sont
// INVENTÉS (noms de variables, empreintes de compte, quotas) : jamais une vraie clé ni un vrai compte.
//   php artisan tinker --execute="require 'scripts/preparer-captures-modeles.php';"

use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;

$settings = app(Settings::class);
$reponses = json_decode(file_get_contents(base_path('docs/captures/modeles/reponses-standard-pro.json')), true);

$afi = User::where('email', 'afi-design@exemple.tg')->firstOrFail();
$kofi = User::where('email', 'kofi-design@exemple.tg')->firstOrFail();
$settings->set('plans_published', true);

$discussion = function (User $user, string $mode) use ($reponses) {
    $user->projects()->where('name', $reponses[$mode]['titre'])->get()->each->delete();
    $p = $user->projects()->make(['name' => $reponses[$mode]['titre']]);
    $p->forceFill(['kind' => Project::KIND_CHAT])->save();
    $m = $p->messages()->create(['role' => Message::ROLE_USER, 'content' => $reponses['message']]);
    $run = $p->agentRuns()->create(['message_id' => $m->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    $p->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponses[$mode]['reponse'], 'agent_run_id' => $run->id]);
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1, 'report' => ['done' => $reponses[$mode]['reponse'], 'note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
    $p->forceFill(['updated_at' => now()])->saveQuietly();

    return $p;
};
$discussion($kofi, 'standard');
$discussion($afi, 'pro');

// Administration : comptes Coarena INVENTÉS (le vrai contrôle se fait avec « Tester » sur le vrai site).
$comptes = [];
foreach (range(1, 4) as $i) {
    $comptes[] = [
        'nom' => $i === 1 ? 'COARENA_API_KEY' : "COARENA_API_KEY_$i",
        'valide' => true,
        'compte' => 'compte-'.substr(hash('sha256', "invente-$i"), 0, 10),
        'cles_du_compte' => 1,
        'statut' => 'active',
        'expire_le' => '2027-02-12T13:12:11.000Z',
        'quota' => ['usd_par_jour' => 5, 'utilise_usd' => [4.97, 1.32, 0.0, 0.0][$i - 1], 'reste_usd' => [0.03, 3.68, 5, 5][$i - 1], 'demandes_par_minute' => 30, 'en_meme_temps' => 2],
        'modeles' => array_fill(0, 20, 'modele'),
        'essai' => ['modele' => 'gpt-6-luna', 'ok' => true, 'duree_ms' => [946, 1384, 2351, 1132][$i - 1], 'erreur' => null],
        'erreur' => null,
        'controle_le' => now()->subMinutes(3)->toIso8601String(),
    ];
}
$settings->set('coarena_key_checks', $comptes);
$settings->set('coarena_multi_accounts', true);
$settings->set('model_providers', ['coarena' => true, 'openai' => true, 'gratuits' => true]);

// Consommation du jour inventée.
$jour = CarbonImmutable::now('UTC')->toDateString();
ProviderUsage::query()->where('provider', 'coarena')->where('day', $jour)->delete();
foreach ([['gpt-6-luna', 142, 2, 310000, 52000], ['gpt-6-1-terra', 38, 0, 260000, 41000], ['gpt-6-1-sol', 21, 1, 410000, 88000], ['glm-5-3-flash', 17, 0, 60000, 9000]] as [$modele, $demandes, $refus, $entree, $sortie]) {
    (new ProviderUsage)->forceFill(['day' => $jour, 'provider' => 'coarena', 'model' => $modele, 'requests' => $demandes, 'rate_limited' => $refus, 'input_tokens' => $entree, 'output_tokens' => $sortie])->save();
}

echo "Captures des modèles prêtes.\n";
