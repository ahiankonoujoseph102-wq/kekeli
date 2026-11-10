<?php

// Données d'essai inventées pour les captures de l'étape 8 (jamais de vraies données de clients).
//   php artisan tinker --execute="require 'scripts/preparer-captures-etape-8.php';"

use App\Agent\ProjectBackup;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$motDePasse = Hash::make('motdepasse-solide-2026');
$compte = function (string $email, string $nom, array $attributs = []) use ($motDePasse): User {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $nom, 'password' => $motDePasse, 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null, 'github_token' => null, 'github_login' => null, ...$attributs])->save();
    $user->payments()->delete();
    $user->projects()->delete();

    return $user;
};

$sauvegarde = function (Project $project): void {
    $dossier = sys_get_temp_dir().'/kekeli-captures-8-'.bin2hex(random_bytes(3));
    mkdir($dossier.'/images', 0777, true);
    file_put_contents($dossier.'/index.html', '<h1>'.e($project->name).'</h1>');
    file_put_contents($dossier.'/images/accueil.jpg', 'octets');
    exec('tar -czf '.escapeshellarg($dossier.'.tar.gz').' -C '.escapeshellarg($dossier).' .');
    $backups = app(ProjectBackup::class);
    $backups->disk()->put($backups->path($project->id), file_get_contents($dossier.'.tar.gz'));
    exec('rm -rf '.escapeshellarg($dossier).' '.escapeshellarg($dossier.'.tar.gz'));
    $project->forceFill(['backup_at' => now()->subHour()])->save();
};

// Numéros et titulaires inventés (les vrais se règlent seulement dans Administration → Paiements, jamais dans le dépôt).
app(Settings::class)->set('payment_countries', [
    'TG' => ['holder' => 'Titulaire Togo (exemple)', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]],
    'BJ' => ['holder' => 'Titulaire Bénin (exemple)', 'methods' => [['name' => 'MTN', 'number' => '+229 90 00 00 01'], ['name' => 'Moov', 'number' => '+229 90 00 00 02'], ['name' => 'Celtiis', 'number' => '+229 90 00 00 03']]],
    'BF' => ['holder' => 'Titulaire Burkina Faso (exemple)', 'methods' => [['name' => 'Orange Money', 'number' => '+226 70 00 00 01'], ['name' => 'Wave', 'number' => '+226 70 00 00 02']]],
    'CI' => ['holder' => 'Titulaire Côte d\'Ivoire (exemple)', 'methods' => [['name' => 'Orange Money', 'number' => '+225 07 00 00 00 01'], ['name' => 'MTN Mobile Money', 'number' => '+225 05 00 00 00 01'], ['name' => 'Wave', 'number' => '+225 07 00 00 00 02']]],
]);
app(Settings::class)->set('payment_whatsapp', '+228 90 00 00 09');

$admin = $compte('admin-etape8@exemple.tg', 'Administrateur Kekeli', ['is_admin' => true]);

// Cliente gratuite au Togo, avec un site construit.
$ama = $compte('ama-etape8@exemple.tg', 'Ama Mensah');
$palmier = $ama->projects()->create(['name' => 'Restaurant Le Palmier', 'description' => 'Site du restaurant, avec le menu et les horaires.']);
$sauvegarde($palmier);
$palmier->forceFill(['published_url' => 'https://'.$palmier->pagesName().'.pages.dev', 'publish_status' => Project::PUBLISH_DONE, 'published_at' => now()->subDay()])->save();

// Client Pro (fin dans 2 jours), site publié, nom de domaine en attente.
$kofi = $compte('kofi-etape8@exemple.tg', 'Kofi Agbeko', ['plan' => 'pro', 'plan_expires_at' => now()->addDays(2)->setTime(18, 30)]);
$boutique = $kofi->projects()->create(['name' => 'Boutique Kofi Couture', 'description' => 'Boutique de couture à Lomé.']);
$sauvegarde($boutique);
$boutique->forceFill([
    'published_url' => 'https://'.$boutique->pagesName().'.pages.dev', 'publish_status' => Project::PUBLISH_DONE, 'published_at' => now()->subDay(),
    'custom_domain' => 'www.kofi-couture.tg', 'custom_domain_status' => 'pending', 'custom_domain_message' => 'CNAME record not set',
])->save();

// Cliente en France : bientôt disponible.
$claire = $compte('claire-etape8@exemple.fr', 'Claire Martin', ['country' => 'FR']);
$claire->projects()->create(['name' => 'Boulangerie du Marché']);

// Cliente au Bénin, qui paie la formule Pro : capture d'écran inventée.
$fifa = $compte('fifa-etape8@exemple.bj', 'Fifamè Houngbo', ['country' => 'BJ']);

// File d'attente : une seule place pour l'essai, déjà prise ; une demande attend avant celle de Yao.
app(Settings::class)->set('max_concurrent_runs', 1);
$autre = $compte('autre-etape8@exemple.tg', 'Autre client');
foreach ([AgentRun::STATUS_RUNNING, AgentRun::STATUS_QUEUED] as $i => $statut) {
    $p = $autre->projects()->create(['name' => 'Projet '.($i + 1)]);
    $m = $p->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon site']);
    $p->agentRuns()->create(['message_id' => $m->id])->forceFill(['status' => $statut, 'sent_at' => $i === 0 ? now() : null])->save();
}
$yao = $compte('yao-etape8@exemple.tg', 'Yao Dossou');
$ecole = $yao->projects()->create(['name' => 'École Les Petits Génies']);
$m = $ecole->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée le site de mon école primaire à Lomé, avec les inscriptions et les horaires.']);
$ecole->agentRuns()->create(['message_id' => $m->id]);

echo json_encode(['palmier' => $palmier->id, 'boutique' => $boutique->id, 'boulangerie' => $claire->projects()->value('id'), 'ecole' => $ecole->id]);
