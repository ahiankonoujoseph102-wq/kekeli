<?php

// Compte les requêtes à la base et le temps de rendu de chaque page courante pour un compte (chantier « clics
// instantanés », 15 novembre 2026). Aucune donnée réelle : une base locale avec des données inventées.
//
//   DB_DATABASE=/chemin/base.sqlite php scripts/compter-requetes.php adresse@compte [pages…]

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
$user = User::where('email', $email)->firstOrFail();
$project = $user->projects()->latest('updated_at')->first();
$chat = $user->projects()->where('kind', Project::KIND_CHAT)->latest('updated_at')->first();
$pages = [
    'accueil' => '/',
    'nouvelle-discussion' => route('projects.index', absolute: false),
    'discussion' => $chat ? route('projects.show', $chat, false) : null,
    'projet' => $project ? route('projects.show', $project, false) : null,
    'projets' => route('projects.all', absolute: false),
    'bibliotheque' => route('library', absolute: false),
    'formules' => route('plans', absolute: false),
    'reglages' => route('settings.profile', absolute: false),
];
$choisies = array_slice($argv, 2);
foreach ($pages as $nom => $chemin) {
    if (! $chemin || ($choisies && ! in_array($nom, $choisies, true))) {
        continue;
    }
    foreach ([1, 2] as $essai) {
        $requetes = [];
        DB::flushQueryLog();
        DB::listen(function ($q) use (&$requetes) {
            $requetes[] = ['sql' => $q->sql, 'ms' => $q->time];
        });
        $app['auth']->guard('web')->setUser($user);
        $debut = microtime(true);
        $reponse = $kernel->handle($req = Request::create($chemin, 'GET'));
        $ms = round((microtime(true) - $debut) * 1000);
        $kernel->terminate($req, $reponse);
        if ($essai === 2) {
            $parSql = collect($requetes)->groupBy(fn ($r) => preg_replace('/\d+/', 'N', $r['sql']))->map->count()->sortDesc();
            printf("%-20s %d %4d requêtes %5d ms  (plus répétée : %d × %s)\n", $nom, $reponse->getStatusCode(), count($requetes), $ms, $parSql->first(), substr((string) $parSql->keys()->first(), 0, 110));
        }
        $app['events']->forget(QueryExecuted::class);
    }
}
