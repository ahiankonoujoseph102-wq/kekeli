<?php

// Données inventées pour les captures « Nouveau design de Kekeli, partout » (docs/captures/nouveau-design.md).
// Aucune requête au modèle ni au Runner : les réponses, les fichiers et les reçus sont écrits ici, à partir de
// fichiers déjà produits pour d'anciennes captures (docs/captures/…). La même préparation sert pour « avant » et
// « après » (les deux sites lisent la même base).
//   php artisan tinker --execute="require 'scripts/preparer-captures-design.php';"

use App\Billing\Receipts;
use App\Models\AgentRun;
use App\Models\DocumentVersion;
use App\Models\EmailLog;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

$settings = app(Settings::class);
$settings->set('payment_countries', ['TG' => ['holder' => 'Titulaire inventé', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]]]);
$settings->set('payment_whatsapp', '');

$compte = function (string $email, string $nom, string $formule, bool $admin = false) {
    $user = User::where('email', $email)->first() ?? new User(['email' => $email]);
    $user->forceFill(['name' => $nom, 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => $formule, 'plan_expires_at' => $formule === 'gratuite' ? null : now()->addDays(20), 'appearance' => 'system', 'is_admin' => $admin, 'welcomed_at' => now()])->save();
    $user->projects()->get()->each->delete();

    return $user;
};

$afi = $compte('afi-design@exemple.tg', 'Afi Mensah', 'pro');
$kofi = $compte('kofi-design@exemple.tg', 'Kofi Agbeko', 'gratuite');
$admin = $compte('admin-design@exemple.tg', 'Administrateur d’essai', 'agence', true);
$vide = $compte('esi-design@exemple.tg', 'Esi Amouzou', 'gratuite');

$projet = function (User $user, string $nom, array $attributs, int $minutes) {
    $p = $user->projects()->make(['name' => $nom]);
    $p->forceFill($attributs)->save();
    $p->forceFill(['updated_at' => now()->subMinutes($minutes)])->saveQuietly();

    return $p;
};

/** Une question et sa réponse (Markdown), comme le Runner les renvoie. */
$echange = function (Project $p, string $question, ?string $reponse, ?array $reflexion = null, array $sources = []) {
    $message = $p->messages()->create(['role' => Message::ROLE_USER, 'content' => $question]);
    $run = $p->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
    if ($reponse === null) {
        $run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(5)])->save();

        return $run;
    }
    $p->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $reponse, 'agent_run_id' => $run->id]);
    $rapport = ['done' => $reponse, 'note' => null, 'question' => true, 'markdown' => true, 'sources' => $sources, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []];
    if ($reflexion) {
        $rapport['thinking'] = $reflexion;
    }
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now(), 'requests' => 1, 'report' => $rapport])->save();

    return $run;
};

// 1. Une discussion avec une réponse riche (titres, tableau, liste, recommandation) et la réflexion repliée.
$boutique = $projet($afi, 'Ouvrir une boutique de pagnes', ['kind' => Project::KIND_CHAT], 2);
$echange($boutique, 'je veux ouvrir une boutique de pagnes à Lomé, combien il faut pour commencer ?', <<<'MD'
Pour une boutique de pagnes à Lomé, le plus sûr est de **commencer petit, avec un stock choisi et une vente en ligne dès le premier jour**.

## Le budget de départ

| Poste | Montant estimé | Remarque |
|---|---|---|
| Stock de départ (40 pièces) | 400 000 FCFA | Wax et pagnes locaux |
| Loyer (3 mois d'avance) | 150 000 FCFA | Près d'un marché |
| **Total** | **550 000 FCFA** | À vérifier sur place |

## Les 3 points qui font la différence

1. **Le choix du stock** : peu de modèles, mais ceux qui partent vite.
2. **Les photos** : nettes, à la lumière du jour, pour WhatsApp.
3. **La fidélité** : un groupe WhatsApp pour vos clientes.

Je vous conseille de garder au moins **100 000 FCFA** de côté pour réassortir les modèles qui marchent.
MD, ['summary' => 'Le client veut un budget de départ concret. Je compare le stock et le loyer, puis je donne une recommandation en FCFA.', 'seconds' => 6]);
$echange($boutique, 'et pour le code d’un bouton WhatsApp sur mon site ?', "Un simple lien suffit, avec l'indicatif du pays et sans « + » :\n\n```html\n<a href=\"https://wa.me/22890000001\">Écrire sur WhatsApp</a>\n```\n\nRemplacez le numéro par le vôtre.");

// 2. Une discussion pendant que Kekeli réfléchit (texte qui scintille, minuteur).
$encours = $projet($afi, 'Prix du ciment à Lomé', ['kind' => Project::KIND_CHAT], 1);
$echange($encours, 'quel est le prix du sac de ciment à Lomé en ce moment ?', null);

// 3. Un site terminé, avec ses actions repliées et le dernier message de Kekeli.
$site = $projet($afi, 'Restaurant Le Palmier', ['kind' => Project::KIND_SITE], 30);
$message = $site->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Un site pour mon restaurant Le Palmier à Lomé, avec le menu, les horaires et un bouton WhatsApp.']);
$run = $site->agentRuns()->create(['message_id' => $message->id]);
foreach ([['fichier_ecrit', 'index.html'], ['fichier_ecrit', 'menu.html'], ['fichier_ecrit', 'style.css'], ['commande', 'npm test'], ['licences_ok', null]] as [$kind, $target]) {
    $run->steps()->create(['kind' => $kind, 'target' => $target]);
}
$fin = "Votre site du restaurant Le Palmier est prêt.\n\n- La page d'accueil présente le restaurant et ses horaires.\n- Le menu montre vos plats avec leurs prix en FCFA.\n- Le bouton WhatsApp ouvre directement la discussion avec vous.";
$site->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => $fin, 'agent_run_id' => $run->id]);
$run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subMinutes(34), 'finished_at' => now()->subMinutes(30), 'requests' => 12, 'report' => ['done' => $fin, 'note' => null, 'files' => ['index.html', 'menu.html', 'style.css'], 'files_count' => 3, 'tests' => [], 'problems' => []]])->save();

// 4. Un site arrêté parce que l'énergie est épuisée (compte Gratuite).
$kofi->forceFill(['plan' => 'gratuite'])->save();
$arret = $projet($kofi, 'Boutique de Kofi', ['kind' => Project::KIND_SITE], 5);
$m = $arret->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute une page avec mes produits et leurs prix.']);
$r = $arret->agentRuns()->create(['message_id' => $m->id]);
$r->steps()->create(['kind' => 'fichier_modifie', 'target' => 'index.html']);
$r->forceFill(['status' => AgentRun::STATUS_STOPPED, 'stop_reason' => 'energie', 'started_at' => now()->subMinutes(6), 'finished_at' => now()->subMinutes(5), 'model_cost_usd' => 0.2])->save();

// 5. Des fichiers : document, présentation, affiche et vidéo, à partir de fichiers déjà produits.
$disk = Storage::disk(ProjectPhoto::disk());
$fichier = function (Project $p, string $type, string $demande, string $titre, array $fichiers, array $apercus, int $minutes) use ($disk) {
    $p->forceFill(['kind' => Project::KIND_DOCUMENT, 'document_type' => $type])->save();
    $message = $p->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
    $run = $p->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_DOCUMENT]);
    $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'started_at' => now()->subMinutes($minutes + 1), 'finished_at' => now()->subMinutes($minutes), 'requests' => 2, 'report' => ['done' => '', 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();
    $dossier = DocumentVersion::folder($p, $run->id);
    $noms = [];
    foreach ($fichiers as $format => $source) {
        $disk->put("$dossier/$format-".basename($source), file_get_contents(base_path($source)));
        $noms[$format] = "$format-".basename($source);
    }
    $vues = [];
    foreach ($apercus as $i => $source) {
        $disk->put("$dossier/apercu-".($i + 1).'.jpg', file_get_contents(base_path($source)));
        $vues[] = 'apercu-'.($i + 1).'.jpg';
    }
    $p->documentVersions()->create(['agent_run_id' => $run->id, 'number' => 1, 'title' => $titre, 'request' => $demande, 'files' => $noms, 'previews' => $vues, 'pages' => count($vues), 'structure' => ['titre' => $titre]]);
    $p->forceFill(['updated_at' => now()->subMinutes($minutes)])->saveQuietly();
};
$d = 'docs/captures/documents/fichiers/';
$fichier($projet($afi, 'Devis du salon de coiffure', [], 60), 'document', 'Fais-moi un devis pour une coiffure de mariage à 25 000 FCFA.', 'Devis du salon de coiffure', ['docx' => $d.'devis.docx', 'pdf' => $d.'devis.pdf'], [$d.'devis-page-1.jpg'], 60);
$fichier($projet($afi, 'Présentation de la boutique', [], 90), 'presentation', 'Une présentation de 8 diapositives pour présenter ma boutique de pagnes à une banque.', 'Présentation de la boutique', ['pptx' => $d.'presentation.pptx', 'pdf' => $d.'presentation.pdf'], array_map(fn ($i) => $d."presentation-page-$i.jpg", range(1, 3)), 90);
$fichier($projet($afi, 'Affiche de la soirée', [], 120), 'image', 'Une affiche pour la soirée du restaurant samedi à 20 h, entrée 5 000 FCFA.', 'Affiche de la soirée', ['png' => 'docs/captures/connecteurs/affiche/pro/v1/apercu-1.jpg'], ['docs/captures/connecteurs/affiche/pro/v1/apercu-1.jpg'], 120);
$fichier($projet($afi, 'Publicité du restaurant', [], 150), 'video', 'Une publicité de 25 secondes pour mon restaurant, format vertical.', 'Publicité du restaurant', ['mp4' => 'docs/captures/optimisations/apres-publicite-pro.mp4'], [], 150);

// 6. Mémoire, paiements, reçus et journal des e-mails.
$afi->memories()->delete();
foreach (['Ma boutique s’appelle Ama Pagnes, au grand marché de Lomé.', 'Mes couleurs : vert et or.', 'Paiement par Mixx by Yas et Flooz.'] as $souvenir) {
    $afi->memories()->create(['content' => $souvenir, 'category' => 'entreprise']);
}

Payment::query()->whereIn('user_id', [$afi->id, $kofi->id])->get()->each(fn ($payment) => $payment->forceDelete());
$disk = Storage::disk(Payment::disk());
$disk->put('paiements/design/capture.png', file_get_contents(base_path('public/logo.png')));
$attente = Payment::query()->forceCreate(['user_id' => $kofi->id, 'plan' => 'pro', 'country' => 'TG', 'amount_fcfa' => 7500, 'screenshot_path' => 'paiements/design/capture.png', 'status' => Payment::STATUS_PENDING]);
$valide = Payment::query()->forceCreate(['user_id' => $afi->id, 'plan' => 'pro', 'country' => 'TG', 'amount_fcfa' => 7500, 'screenshot_path' => 'paiements/design/capture.png', 'status' => Payment::STATUS_VALIDATED, 'method' => 'Flooz', 'reference' => 'FL251114.0930.K7Q2', 'reviewed_at' => now()->subDays(10), 'reviewed_by' => $admin->id, 'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(20)]);
Receipt::query()->where('client_email', $afi->email)->get()->each(fn ($recu) => Receipt::query()->whereKey($recu->id)->delete());
if (! Receipt::query()->where('payment_id', $valide->id)->exists()) {
    app(Receipts::class)->issueFor($valide->fresh());
}

EmailLog::query()->delete();
foreach ([['login_code', 'sent', true], ['welcome', 'sent', false], ['payment_to_check', 'sent', false], ['receipt', 'sent', true], ['plan_ending', 'deferred', false]] as $i => [$type, $statut, $priorite]) {
    EmailLog::query()->forceCreate(['type' => $type, 'user_id' => $afi->id, 'recipient_masked' => 'a•••@e•••.tg', 'priority' => $priorite, 'status' => $statut, 'attempts' => 1, 'sent_at' => $statut === 'sent' ? now()->subMinutes(10 * $i) : null]);
}

echo "Comptes : afi-design@exemple.tg (Pro), kofi-design@exemple.tg (Gratuite, énergie épuisée), esi-design@exemple.tg (vide), admin-design@exemple.tg (administrateur). Mot de passe : motdepasse-solide-2026\n";
