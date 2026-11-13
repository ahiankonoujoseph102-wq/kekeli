<?php

// Essai réel des versions (13 novembre 2026), avec le vrai Runner, de vrais ordinateurs E2B et le vrai agent :
// un petit site puis une petite application, 3 modifications chacun, la version 2 regardée dans l'aperçu, restaurée,
// puis la restauration annulée ; enfin un travail arrêté au milieu. Compte d'essai inventé (formule Pro).
//   Site, Runner et « php artisan queue:work » lancés avec le même KEKELI_RUNNER_SECRET, puis :
//   ESSAI=site php artisan tinker --execute="require 'scripts/essai-versions.php';"   (ou ESSAI=application, ESSAI=arret)

use App\Agent\PreviewToken;
use App\Agent\RunStarter;
use App\Agent\Tools\CreateApplication;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Projects\ProjectVersions;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

$essai = getenv('ESSAI') ?: 'site';
$journal = function (string $texte) {
    echo '['.now()->timezone('Africa/Lome')->format('H:i:s').'] '.$texte."\n";
};

$user = User::where('email', 'essai-versions@exemple.tg')->first() ?? new User(['email' => 'essai-versions@exemple.tg']);
$user->forceFill(['name' => 'Kossi Agbéko', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system'])->save();

// Le modèle de construction de l'essai : gratuit (Gemini) pour le site vitrine, GPT-6.1 Terra pour l'application.
$settings = app(Settings::class);
$plans = $settings->get('plans');
$plans['pro']['build_model'] = $essai === 'site' ? '' : 'openai/gpt-6.1-terra';
$settings->set('plans', $plans);

$attendre = function (callable $condition, int $secondes, string $quoi) use ($journal) {
    $debut = time();
    while (! $condition()) {
        if (time() - $debut > $secondes) {
            throw new RuntimeException("Délai dépassé : $quoi");
        }
        sleep(5);
    }
    $journal("$quoi (".(time() - $debut).' s)');
};

$travail = function (Project $project, string $demande, ?string $titre, ?callable $lancer = null) use ($attendre) {
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $demande]);
    $run = $lancer ? $lancer($project, $message) : app(RunStarter::class)->startWith($project, $message);
    if (! $run) {
        throw new RuntimeException('Travail refusé (énergie ou travail en cours).');
    }
    $run->forceFill(['version_title' => $titre])->save();
    $attendre(fn () => ! $run->fresh()->isActive(), 1800, "Travail « $demande » : ".$run->fresh()->status);

    return $run->fresh();
};

$apercu = function (Project $project, string $chemin = '/') use ($attendre) {
    $attendre(fn () => $project->fresh()->version_busy_at === null, 300, 'Ordinateur prêt');
    sleep(4);
    $url = PreviewToken::url($project->fresh());
    if (! $url) {
        return '(aperçu endormi)';
    }
    $base = preg_replace('#\?.*$#', '', $url);
    $token = parse_url($url, PHP_URL_QUERY);

    return (string) Http::timeout(60)->get(rtrim($base, '/').$chemin.($token ? '?'.$token : ''))->body();
};

$versions = app(ProjectVersions::class);
$cout = fn (Project $p) => round((float) $p->agentRuns()->sum('model_cost_usd'), 4);

if ($essai === 'site' || $essai === 'application') {
    $project = $user->projects()->make(['name' => $essai === 'site' ? 'Maquis Chez Ama' : 'Réservations Chez Ama']);
    $project->forceFill(['kind' => Project::KIND_SITE])->save();
    $marques = ['VERSION-UN', 'VERSION-DEUX', 'VERSION-TROIS', 'VERSION-QUATRE'];

    if ($essai === 'site') {
        $travail($project, "Crée un site vitrine d'une seule page index.html pour le maquis Chez Ama à Lomé. Mets exactement le texte {$marques[0]} dans le pied de page.", null);
    } else {
        $travail($project, "Une petite application de réservation pour le maquis Chez Ama : une page d'accueil publique qui affiche exactement le texte {$marques[0]}, et une page réservations réservée à l'administrateur. Reste très simple.", null, function (Project $p, Message $m) use ($marques) {
            return app(CreateApplication::class)->build($p, $m, "Pages : accueil (affiche le texte {$marques[0]}), connexion, réservations (administrateur). Données : réservation (nom, date, nombre de personnes). Rôle : administrateur. Technologie : Laravel. Très simple, 3 tests au plus.", 'laravel');
        });
    }
    foreach ([1 => 'Ajout du texte de la version 2', 2 => 'Ajout du texte de la version 3', 3 => 'Ajout du texte de la version 4'] as $i => $titre) {
        $travail($project, "Remplace seulement le texte {$marques[$i - 1]} de la page d'accueil par exactement {$marques[$i]}. Ne change rien d'autre.", $titre);
    }
    $project->refresh();
    $journal('Versions : '.$project->versions()->get()->map->label()->implode(' | '));
    $journal('Aperçu actuel : '.(str_contains($apercu($project), $marques[3]) ? $marques[3].' trouvé' : 'texte attendu absent'));

    // Regarder la version 2 dans l'aperçu, sans rien changer.
    $versions->view($project, $project->versions()->where('number', 2)->sole());
    $html = $apercu($project);
    $journal('Aperçu de la version 2 : '.(str_contains($html, $marques[1]) && ! str_contains($html, $marques[3]) ? $marques[1].' trouvé, '.$marques[3].' absent' : 'inattendu'));
    $versions->back($project->fresh());
    $journal('Retour à la version actuelle : '.(str_contains($apercu($project), $marques[3]) ? $marques[3].' trouvé' : 'inattendu'));

    // Restaurer la version 2 : une nouvelle version 5, l'aperçu montre la version 2.
    $cinq = $versions->restore($project->fresh(), $project->versions()->where('number', 2)->sole());
    $journal('Restauration : '.$cinq->label());
    $journal('Aperçu après la restauration : '.(str_contains($apercu($project), $marques[1]) ? $marques[1].' trouvé' : 'inattendu'));

    // Annuler la restauration : restaurer la version 4 (version 6).
    $six = $versions->restore($project->fresh(), $project->versions()->where('number', 4)->sole());
    $journal('Annulation : '.$six->label());
    $journal('Aperçu après l’annulation : '.(str_contains($apercu($project), $marques[3]) ? $marques[3].' trouvé' : 'inattendu'));
    $journal('Sauvegarde après l’annulation : '.(str_contains((string) gzdecode((string) app(App\Agent\ProjectBackup::class)->disk()->get(app(App\Agent\ProjectBackup::class)->path($project->id))), $marques[3]) ? 'à jour' : 'inattendu'));
    $journal('Coût des modèles : '.$cout($project).' $');
    echo json_encode(['projet' => $project->id, 'versions' => $project->versions()->count()])."\n";
}

if ($essai === 'arret') {
    // Un travail arrêté au milieu (comme « Arrêter ») : le projet reste sur sa dernière version.
    $project = $user->projects()->where('name', 'Maquis Chez Ama')->latest('created_at')->firstOrFail();
    $avant = $versions->current($project);
    $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Refais complètement le site avec 5 pages, une galerie et un menu détaillé, et remplace le texte VERSION-QUATRE par VERSION-CASSEE.']);
    $run = app(RunStarter::class)->startWith($project, $message);
    $attendre(fn () => $run->fresh()->steps()->count() >= 6 || ! $run->fresh()->isActive(), 900, 'Travail commencé');
    $run->fresh()->update(['stop_requested_at' => now(), 'stop_reason' => AgentRun::STOP_CLIENT]);
    $attendre(fn () => ! $run->fresh()->isActive(), 600, 'Travail arrêté : '.$run->fresh()->status);
    $html = $apercu($project);
    $journal('Projet resté sur la version '.data_get($run->fresh()->report, 'kept_version').' (version actuelle avant : '.$avant->number.')');
    $journal('Aperçu après l’arrêt : '.(str_contains($html, 'VERSION-QUATRE') && ! str_contains($html, 'VERSION-CASSEE') ? 'VERSION-QUATRE trouvé, rien du travail arrêté' : 'inattendu'));
    $journal('Versions : '.$project->versions()->count());
}
