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

    /*
    |--------------------------------------------------------------------------
    | Aperçu en direct
    |--------------------------------------------------------------------------
    |
    | Le Runner envoie le lien de l'aperçu de chaque projet. Seuls les liens
    | HTTPS vers ces domaines (ceux des ordinateurs virtuels) sont acceptés.
    |
    */

    'preview_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('KEKELI_DOMAINES_APERCU', 'e2b.app,e2b.dev'))))),

    // L'aperçu affiche « Kekeli se met en pause… » pendant ce nombre de secondes avant la pause.
    'preview_pause_warning' => 90,

    /*
    |--------------------------------------------------------------------------
    | Sauvegarde des projets
    |--------------------------------------------------------------------------
    |
    | Après chaque demande, le Runner envoie une copie compressée des fichiers
    | du projet. Elle est remise dans un nouvel ordinateur si l'ancien ne peut
    | pas être réveillé. Une fois le site hébergé, KEKELI_DISQUE_SAUVEGARDES
    | pourra désigner un stockage objet (par exemple celui de Laravel Cloud).
    |
    */

    'backups' => [
        'disk' => env('KEKELI_DISQUE_SAUVEGARDES', 'local'),
        'directory' => 'sauvegardes',
        'max_parts' => 13,
        'part_size' => 4 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sites toujours autorisés
    |--------------------------------------------------------------------------
    |
    | Même liste que dans runner/src/reseau.js (un test vérifie qu'elles restent
    | identiques). L'utilisateur peut ajouter d'autres sites, projet par projet.
    |
    */

    'default_sites' => [
        'system' => ['models.dev', 'registry.npmjs.org', 'pypi.org', 'files.pythonhosted.org'],
        'free' => [
            'unsplash.com', 'images.unsplash.com', 'api.unsplash.com',
            'www.pexels.com', 'images.pexels.com', 'videos.pexels.com', 'api.pexels.com',
            'pixabay.com', 'cdn.pixabay.com',
            'api.openverse.org', 'commons.wikimedia.org', 'upload.wikimedia.org',
            'fonts.googleapis.com', 'fonts.gstatic.com',
            'cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com',
        ],
    ],

    // Les heures montrées aux utilisateurs sont toujours celles de Lomé.
    'display_timezone' => 'Africa/Lome',

    // Seuil d'alerte de l'administration, en pourcentage de la limite du jour.
    'alert_threshold' => 80,

];
