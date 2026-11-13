<?php

// Données inventées pour les captures « Connecteurs (MCP) ». La réponse de DeepWiki est celle de l'essai réel du
// 13 novembre 2026 (runner/scripts/essai-connecteurs.mjs) ; le reste est écrit ici, comme le Runner le renvoie.
// Aucun vrai jeton : celui de Notion est inventé et ne sert jamais.
//   php artisan tinker --execute="require 'scripts/preparer-captures-connecteurs.php';"

use App\Models\AgentRun;
use App\Models\ConnectorAction;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'kossi-connecteurs@exemple.tg')->first() ?? new User(['email' => 'kossi-connecteurs@exemple.tg']);
$user->forceFill(['name' => 'Kossi Agbéko', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null, 'appearance' => 'system'])->save();
$user->projects()->get()->each->delete();
$user->connectors()->delete();
$user->connectorActions()->delete();

$notion = $user->connectors()->create(['catalog_key' => 'notion', 'name' => 'Notion', 'url' => 'https://mcp.notion.com/mcp', 'auth' => 'oauth', 'access_token' => 'jeton-invente-pour-les-captures', 'token_expires_at' => now()->addDays(30), 'connected_at' => now()->subDay(), 'active' => true]);
$huggingface = $user->connectors()->create(['catalog_key' => 'huggingface', 'name' => 'Hugging Face', 'url' => 'https://huggingface.co/mcp', 'auth' => 'aucune', 'connected_at' => now()->subDay(), 'active' => false]);

$discussion = function (string $nom, int $minutes) use ($user) {
    $projet = $user->projects()->make(['name' => $nom]);
    $projet->forceFill(['kind' => Project::KIND_CHAT, 'updated_at' => now()->subMinutes($minutes)])->save();

    return $projet;
};
$reponse = function (Project $projet, string $question, string $texte, array $utilises) {
    $message = $projet->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
    $run = $projet->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    $projet->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $texte, 'agent_run_id' => $run->id]);
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 2, 'provider' => 'openai', 'model' => 'gpt-6-luna', 'report' => ['done' => $texte, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => [], 'connectors' => $utilises, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

    return $run;
};
$journal = function (Project $projet, AgentRun $run, $connecteur, string $outil, string $titre, bool $ecriture, string $statut, array $details = [], array $arguments = []) use ($user) {
    return ConnectorAction::create(['user_id' => $user->id, 'user_connector_id' => $connecteur->id, 'project_id' => $projet->id, 'agent_run_id' => $run->id, 'connector_name' => $connecteur->name, 'tool' => $outil, 'writes' => $ecriture, 'status' => $statut, 'summary' => ['action' => $titre, 'description' => '', 'details' => $details], 'arguments' => $statut === ConnectorAction::PENDING ? $arguments : null]);
};

// 1. Une discussion avec Notion : une lecture faite tout de suite, puis une action qui attend l'accord du client.
$menu = $discussion('Menu de la semaine', 2);
$run = $reponse($menu, 'Que dit ma page Notion sur le menu du restaurant ?', "Dans votre Notion, la page **Menu du restaurant** donne :\n\n- **Riz gras** : 1 500 FCFA\n- **Akoumé et sauce gombo** : 1 200 FCFA\n- **Poisson braisé** : 3 000 FCFA\n\nLa page a été modifiée pour la dernière fois le 10 novembre 2026.", [['name' => 'Notion', 'title' => 'Chercher des pages', 'writes' => false, 'status' => 'fait'], ['name' => 'Notion', 'title' => 'Lire une page', 'writes' => false, 'status' => 'fait']]);
$journal($menu, $run, $notion, 'notion-search', 'Chercher des pages', false, ConnectorAction::DONE, [['nom' => 'query', 'valeur' => 'menu du restaurant']]);
$journal($menu, $run, $notion, 'notion-fetch', 'Lire une page', false, ConnectorAction::DONE, [['nom' => 'id', 'valeur' => 'Menu du restaurant']]);

$message = $menu->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une page « Menu de la semaine » avec le riz gras le lundi et le poisson braisé le vendredi.']);
$attente = $menu->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
$action = $journal($menu, $attente, $notion, 'notion-create-pages', 'Créer des pages', true, ConnectorAction::PENDING, [['nom' => 'title', 'valeur' => 'Menu de la semaine'], ['nom' => 'content', 'valeur' => 'Lundi : riz gras (1 500 FCFA). Vendredi : poisson braisé (3 000 FCFA).'], ['nom' => 'parent', 'valeur' => 'Restaurant Chez Ama']], ['title' => 'Menu de la semaine']);
$attente->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1, 'report' => ['done' => '', 'note' => null, 'question' => true, 'approval' => $action->id, 'connectors' => [], 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
$menu->forceFill(['connectors' => [$notion->id]])->save();

// 2. Une discussion avec Hugging Face (sans compte) : réponse réelle de l'essai du 13 novembre 2026, raccourcie.
$traduction = $discussion('Modèles de traduction', 30);
$run2 = $reponse($traduction, 'Cherche sur Hugging Face 3 modèles populaires pour traduire du français vers l’anglais.', "Sur Hugging Face, ces 3 modèles populaires peuvent traduire du français vers l’anglais :\n\n- **Helsinki-NLP/opus-mt-fr-en** : spécialisé dans cette paire de langues ; environ 309 500 téléchargements.\n- **facebook/nllb-200-distilled-600M** : modèle multilingue ; environ 939 900 téléchargements.\n- **Helsinki-NLP/opus-mt-tc-big-fr-en** : plus grand, plus précis, un peu plus lent.", [['name' => 'Hugging Face', 'title' => 'Hub repo search', 'writes' => false, 'status' => 'fait']]);
$journal($traduction, $run2, $huggingface, 'hub_repo_search', 'Hub repo search', false, ConnectorAction::DONE, [['nom' => 'query', 'valeur' => 'translation french english']]);
$refus = $journal($traduction, $run2, $notion, 'notion-create-pages', 'Créer des pages', true, ConnectorAction::REFUSED);
$refus->forceFill(['created_at' => now()->subMinutes(25)])->save();
$traduction->forceFill(['connectors' => [$huggingface->id]])->save();

echo "Prêt : kossi-connecteurs@exemple.tg\n";
