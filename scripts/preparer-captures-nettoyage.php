<?php

// Données d'essai inventées pour les captures du nettoyage (jamais de vraies données de clients, jamais de vrai numéro).
//   php artisan migrate:fresh --force
//   php artisan tinker --execute="require 'scripts/preparer-captures-nettoyage.php';"
// Le compte rendu est construit avec le code actuel (RunReport::build) : les captures AVANT et APRÈS montrent donc
// chacune ce que le client voyait réellement avec la même réponse de Kekeli.

use App\Agent\ProjectBackup;
use App\Agent\RunReport;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;

$motDePasse = Hash::make('motdepasse-solide-2026');
$compte = function (string $email, string $nom, array $attributs = []) use ($motDePasse): User {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $nom, 'password' => $motDePasse, 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'plan_expires_at' => null, ...$attributs])->save();
    $user->projects()->delete();

    return $user;
};

$sauvegarde = function (Project $project, array $fichiers): void {
    $dossier = sys_get_temp_dir().'/kekeli-captures-nettoyage-'.bin2hex(random_bytes(3));
    foreach ($fichiers as $chemin => $contenu) {
        @mkdir(dirname($dossier.'/'.$chemin), 0777, true);
        file_put_contents($dossier.'/'.$chemin, $contenu);
    }
    exec('tar -czf '.escapeshellarg($dossier.'.tar.gz').' -C '.escapeshellarg($dossier).' .');
    $backups = app(ProjectBackup::class);
    $backups->disk()->put($backups->path($project->id), file_get_contents($dossier.'.tar.gz'));
    exec('rm -rf '.escapeshellarg($dossier).' '.escapeshellarg($dossier.'.tar.gz'));
    $project->forceFill(['backup_at' => now()->subMinutes(20)])->save();
};

// Une demande terminée : les étapes telles que le Runner les envoie, puis la réponse de Kekeli et son compte rendu.
$demande = function (Project $project, string $texte, array $etapes, string $reponse, array $changes = [], ?string $diff = null): AgentRun {
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $texte]);
    $run = $project->agentRuns()->create(['message_id' => $message->id]);
    foreach ($etapes as [$kind, $target]) {
        $run->steps()->create(['kind' => $kind, 'target' => $target]);
    }
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subMinutes(25), 'sent_at' => now()->subMinutes(25), 'finished_at' => now()->subMinutes(20), 'changes' => $changes ?: null, 'diff' => $diff])->save();
    $reponseMessage = $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
    $run->forceFill(['report' => RunReport::build($run->fresh(), $reponse)])->save();

    return $run;
};

// Numéros et titulaires inventés (les vrais se règlent seulement dans Administration → Paiements).
app(Settings::class)->set('payment_countries', [
    'TG' => ['holder' => 'Titulaire Togo (exemple)', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]],
]);
app(Settings::class)->set('payment_whatsapp', '+228 90 00 00 09');

// 1. Site vitrine terminé, aperçu en pause : la réponse de Kekeli contient les défauts relevés par le porteur du projet.
$ama = $compte('ama-nettoyage@exemple.tg', 'Ama Mensah');
$palmier = $ama->projects()->create(['name' => 'Restaurant Le Palmier', 'description' => 'Un site pour mon restaurant à Lomé, avec le menu, les prix en FCFA et un bouton WhatsApp pour réserver une table.']);
$nbsp = "\u{00A0}";
$demande(
    $palmier,
    'Un site pour mon restaurant à Lomé, avec le menu, les prix en FCFA et un bouton WhatsApp pour réserver une table.',
    [
        ['narration', 'Je prépare la page d\'accueil du restaurant.'],
        ['fichier_ecrit', 'index.html'],
        ['commande', 'cp /home/user/.kekeli/base.css css/base.css'],
        ['narration', 'J\'ajoute le menu avec les prix en FCFA.'],
        ['fichier_ecrit', 'menu.html'],
        ['fichier_modifie', 'index.html'],
        ['narration', 'J\'ajoute le menu avec les prix en FCFA.'],
        ['photos', 'restaurant africain plats'],
        ['icones', 'phone, map-pin, clock'],
        ['narration', 'J\'ajoute les horaires, le plan et le bouton WhatsApp.'],
        ['fichier_modifie', 'index.html'],
        ['commande', 'curl -s -o /dev/null -w "%{http_code}" http://localhost:4100/'],
        ['typographie', '12 espaces insécables ajoutées'],
        ['qualite_ok', '2 pages'],
        ['licences_ok', null],
        ['sauvegarde', null],
    ],
    "## Ce que j'ai fait\nJ'ai créé le site du restaurant Le Palmier{$nbsp}: une page d'accueil, le menu avec les prix en FCFA, les horaires et un plan. 🍽️\nJ'ai ajouté un bouton WhatsApp pour réserver une table{$nbsp}!\nJ'ai ajouté un bouton WhatsApp pour réserver une table{$nbsp}!\n## Fichiers modifiés\n- index.html\n- menu.html\n- css/base.css\n## Tests et vérifications\n- La page d'accueil répond (code 200).\n- php artisan test : aucun test pour un site simple.\n## Ce qui n'a pas marché ou reste à faire\nRien.\n## À vérifier de votre côté\n- Vérifiez les prix et le numéro WhatsApp.\n- Ouvrez l'aperçu sur votre téléphone.",
    ['fichiers' => [['chemin' => 'index.html', 'etat' => 'ajoute', 'ajouts' => 6, 'suppressions' => 0, 'binaire' => false], ['chemin' => 'menu.html', 'etat' => 'ajoute', 'ajouts' => 4, 'suppressions' => 0, 'binaire' => false], ['chemin' => 'css/base.css', 'etat' => 'ajoute', 'ajouts' => 3, 'suppressions' => 0, 'binaire' => false]], 'nombre_fichiers' => 3],
    "diff --git a/index.html b/index.html\nnew file mode 100644\n--- /dev/null\n+++ b/index.html\n@@ -0,0 +1,6 @@\n+<!doctype html>\n+<html lang=\"fr\">\n+<title>Restaurant Le Palmier</title>\n+<h1>Restaurant Le Palmier</h1>\n+<p>Cuisine togolaise au cœur de Lomé.</p>\n+<a href=\"https://wa.me/22890000000\">Réserver sur WhatsApp</a>\n".
    "diff --git a/menu.html b/menu.html\nnew file mode 100644\n--- /dev/null\n+++ b/menu.html\n@@ -0,0 +1,4 @@\n+<h1>Le menu</h1>\n+<p>Poulet braisé : 3 500 FCFA</p>\n+<p>Fufu sauce arachide : 2 500 FCFA</p>\n+<p>Jus de bissap : 500 FCFA</p>\n".
    "diff --git a/css/base.css b/css/base.css\nnew file mode 100644\n--- /dev/null\n+++ b/css/base.css\n@@ -0,0 +1,3 @@\n+body { font-family: system-ui, sans-serif; }\n+.bouton { background: #b45309; color: white; }\n+.conteneur { max-width: 60rem; margin: auto; }\n",
);
$sauvegarde($palmier, [
    'index.html' => "<!doctype html>\n<html lang=\"fr\">\n<title>Restaurant Le Palmier</title>\n<h1>Restaurant Le Palmier</h1>\n<p>Cuisine togolaise au cœur de Lomé.</p>\n<a href=\"https://wa.me/22890000000\">Réserver sur WhatsApp</a>\n",
    'menu.html' => "<h1>Le menu</h1>\n<p>Poulet braisé : 3 500 FCFA</p>\n<p>Fufu sauce arachide : 2 500 FCFA</p>\n<p>Jus de bissap : 500 FCFA</p>\n",
    'css/base.css' => "body { font-family: system-ui, sans-serif; }\n.bouton { background: #b45309; color: white; }\n.conteneur { max-width: 60rem; margin: auto; }\n",
]);
// Aperçu mis en pause il y a quelques minutes (adresse inventée).
$palmier->forceFill(['preview_url' => 'https://4100-essai-inventee.e2b.app/', 'preview_expires_at' => now()->subMinutes(5), 'preview_waking_at' => null])->save();

// 2. Site avec base de données terminé (pressing).
$kofi = $compte('kofi-nettoyage@exemple.tg', 'Kofi Agbeko');
$pressing = $kofi->projects()->make(['name' => 'Pressing Éclat', 'description' => 'Un site pour mon pressing où les clients suivent leurs commandes.']);
$pressing->forceFill(['kind' => Project::KIND_WEBAPP, 'app_stack' => 'laravel', 'app_template' => 'socle', 'app_admin_email' => 'admin@essai.kekeli', 'app_admin_password' => 'essai-inventé-1234'])->save();
$demande(
    $pressing,
    'Un site pour mon pressing où les clients créent un compte et suivent leurs commandes, avec un espace administrateur.',
    [
        ['application_deposee', null],
        ['narration', 'Je regarde comment le site de départ est organisé.'],
        ['fichier_lu', 'routes/web.php'],
        ['narration', 'J\'ajoute les commandes de pressing pour chaque client.'],
        ['fichier_ecrit', 'database/migrations/2026_11_11_000000_create_commandes_table.php'],
        ['commande', 'php artisan migrate'],
        ['fichier_ecrit', 'app/Models/Commande.php'],
        ['narration', 'Je lance les tests pour vérifier que tout fonctionne.'],
        ['commande', 'php artisan test'],
        ['tests_ok', '18 tests'],
        ['securite_ok', null],
        ['application_ok', 'code 200'],
        ['sauvegarde', null],
    ],
    "## Ce que j'ai fait\nVotre pressing a maintenant son site : les clients créent un compte, déposent une commande et suivent son état. ✅\nL'espace administrateur montre toutes les commandes.\n## Fichiers modifiés\n- app/Models/Commande.php\n- database/migrations/2026_11_11_000000_create_commandes_table.php\n## Tests et vérifications\n- php artisan test : 18 tests réussis.\n- Le site répond (code 200).\n## Ce qui n'a pas marché ou reste à faire\nRien.\n## À vérifier de votre côté\n- Connectez-vous avec le compte administrateur d'essai.",
    ['fichiers' => [['chemin' => 'app/Models/Commande.php', 'etat' => 'ajoute', 'ajouts' => 12, 'suppressions' => 0, 'binaire' => false], ['chemin' => 'database/migrations/2026_11_11_000000_create_commandes_table.php', 'etat' => 'ajoute', 'ajouts' => 20, 'suppressions' => 0, 'binaire' => false]], 'nombre_fichiers' => 2],
);
$sauvegarde($pressing, ['README.md' => "# Pressing Éclat\n", 'app/Models/Commande.php' => "<?php\n\nnamespace App\\Models;\n\nclass Commande {}\n"]);
$pressing->forceFill(['preview_url' => 'https://4100-essai-pressing.e2b.app/', 'preview_expires_at' => now()->subMinutes(5)])->save();

// 3. Demande en attente : tous les fournisseurs sont occupés.
$yao = $compte('yao-nettoyage@exemple.tg', 'Yao Dossou');
$ecole = $yao->projects()->create(['name' => 'École Les Petits Génies']);
$m = $ecole->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Un site pour mon école primaire à Lomé, avec les inscriptions et les horaires.']);
$ecole->agentRuns()->create(['message_id' => $m->id])->forceFill(['status' => AgentRun::STATUS_WAITING, 'resume_at' => now()->addMinutes(40), 'sent_at' => now()->subMinutes(2)])->save();

// 4. Site publié.
$efua = $compte('efua-nettoyage@exemple.tg', 'Efua Lawson');
$salon = $efua->projects()->create(['name' => 'Salon Belle Tresse']);
$demande($salon, 'Un site pour mon salon de coiffure, avec les prix et les horaires.', [['narration', 'Je prépare la page d\'accueil du salon.'], ['fichier_ecrit', 'index.html'], ['qualite_ok', '1 page'], ['sauvegarde', null]], "## Ce que j'ai fait\nLe site du salon Belle Tresse est prêt : les coiffures, les prix et les horaires.\n## Fichiers modifiés\n- index.html\n## Tests et vérifications\nAucun test lancé.\n## Ce qui n'a pas marché ou reste à faire\nRien.\n## À vérifier de votre côté\n- Relisez les prix.", ['fichiers' => [['chemin' => 'index.html', 'etat' => 'ajoute', 'ajouts' => 1, 'suppressions' => 0, 'binaire' => false]], 'nombre_fichiers' => 1]);
$sauvegarde($salon, ['index.html' => "<h1>Salon Belle Tresse</h1>\n"]);
$salon->forceFill(['published_url' => 'https://'.$salon->pagesName().'.pages.dev', 'publish_status' => Project::PUBLISH_DONE, 'published_at' => now()->subDay()])->save();

echo "Données d'essai prêtes.\n";
