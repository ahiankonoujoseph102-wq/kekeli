<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Kekeli (mode équipe) : « php kekeli-pages.php » liste les pages GET sans paramètre ;
// « php kekeli-pages.php /adresse administrateur|visiteur » ouvre une page (un processus par page) et dit si elle plante.
// Rien n'est envoyé ni modifié : seulement des pages GET, dans la base d'essai du projet.
$dossier = getcwd();
require $dossier.'/vendor/autoload.php';
$app = require $dossier.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! isset($argv[1])) {
    $uris = [];
    foreach ($app['router']->getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');
        $page = in_array('GET', $route->methods(), true) && ! str_contains($uri, '{') && ! preg_match('#^/(_|sanctum|livewire|storage|telescope|horizon|up$|deconnexion|logout)#', $uri);
        $classe = ltrim((string) $route->getControllerClass(), '\\');
        if ($classe) {
            try {
                $existe = class_exists($classe);
            } catch (Throwable $e) {
                $existe = false;
            }
            // Un contrôleur supprimé mais encore appelé par une route : la page plantera, Kekeli le dit tout de suite.
            if (! $existe) {
                echo "PANNE {$uri} : le contrôleur {$classe} n'existe plus, mais la route l'appelle encore\n";

                continue;
            }
            // Les pages des bibliothèques (dossier vendor) ne sont pas celles du projet, sauf les pages simples (Route::view) et Volt.
            $fichier = (string) (new ReflectionClass($classe))->getFileName();
            if (str_contains($fichier, '/vendor/') && ! str_starts_with($classe, 'Livewire\\Volt') && $classe !== 'Illuminate\\Routing\\ViewController') {
                continue;
            }
        }
        if ($page) {
            $uris[] = $uri;
        }
    }
    echo implode("\n", array_map(fn ($u) => 'PAGE '.$u, array_slice(array_values(array_unique($uris)), 0, 30)))."\n";
    exit;
}
[$uri, $qui] = [$argv[1], $argv[2] ?? 'visiteur'];
$requete = Request::create($uri, 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
$app->instance('request', $requete);
if ($qui === 'administrateur') {
    $admin = User::query()->where('is_admin', true)->orderBy('id')->first();
    if (! $admin) {
        exit;
    }
    $app['auth']->guard('web')->setUser($admin);
}
try {
    $reponse = $kernel->handle($requete);
    [$code, $erreur] = [$reponse->getStatusCode(), $reponse->exception ?? null];
} catch (Throwable $e) {
    [$code, $erreur] = [500, $e];
}
if ($code >= 500) {
    $detail = $erreur ? get_class($erreur).' : '.$erreur->getMessage().' ('.$erreur->getFile().':'.$erreur->getLine().')' : 'erreur '.$code;
    $detail = str_replace([$dossier.'/vendor/composer/../../', $dossier.'/'], '', $detail);
    echo "PANNE {$uri} ({$qui}) : ".mb_substr($detail, 0, 300)."\n";
}
