<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runner
    |--------------------------------------------------------------------------
    |
    | Le Runner est le petit service Node.js qui crée les ordinateurs virtuels
    | et pilote l'agent OpenCode. Le site lui envoie les demandes signées avec
    | le secret partagé KEKELI_RUNNER_SECRET (jamais écrit dans le code).
    |
    */

    'runner' => [
        'url' => env('KEKELI_RUNNER_URL', 'http://127.0.0.1:3100'),
        'secret' => env('KEKELI_RUNNER_SECRET'),
        'timeout' => (int) env('KEKELI_RUNNER_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Liste des fournisseurs d'IA
    |--------------------------------------------------------------------------
    |
    | KEKELI_FOURNISSEURS donne l'ordre de la liste, par exemple
    | « gemini,openrouter,mistral ». Une entrée peut aussi préciser le modèle :
    | « gemini/gemini-3.1-flash-lite ». Quand un fournisseur atteint sa limite,
    | Kekeli passe automatiquement au suivant. Un fournisseur sans clé dans le
    | Runner est ignoré. Les limites du jour servent seulement à l'affichage dans
    | l'administration (estimations, à vérifier dans la console de chaque fournisseur).
    |
    */

    'providers_order' => env('KEKELI_FOURNISSEURS', 'gemini,openrouter,mistral'),

    'providers' => [
        'gemini' => [
            'label' => 'Google AI Studio (Gemini)',
            'model' => env('KEKELI_MODELE_GEMINI', 'gemini-3.5-flash-lite'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_GEMINI', 500),
            'quota_timezone' => 'America/Los_Angeles',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'model' => env('KEKELI_MODELE_OPENROUTER', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_OPENROUTER', 50),
            'quota_timezone' => 'UTC',
        ],
        'mistral' => [
            'label' => 'Mistral',
            'model' => env('KEKELI_MODELE_MISTRAL', 'devstral-latest'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_MISTRAL'),
            'quota_timezone' => 'UTC',
        ],
    ],

    // Les heures montrées aux utilisateurs sont toujours celles de Lomé.
    'display_timezone' => 'Africa/Lome',

    // Seuil d'alerte de l'administration, en pourcentage de la limite du jour.
    'alert_threshold' => 80,

];
