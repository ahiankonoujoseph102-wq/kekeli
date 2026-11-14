<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Laravel Cloud place un proxy devant le site : on lui fait confiance pour savoir que la visite arrive en HTTPS
        // et pour connaître l'adresse du visiteur (limites d'envoi des codes de connexion, par exemple).
        $middleware->trustProxies(at: '*');
        // Les événements de l'agent arrivent du Runner, protégés par une signature.
        $middleware->validateCsrfTokens(except: ['runner/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
