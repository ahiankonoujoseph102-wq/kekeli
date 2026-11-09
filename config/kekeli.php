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
        'system' => ['models.dev', 'models.opencode.ai', 'registry.npmjs.org', 'pypi.org', 'files.pythonhosted.org'],
        'free' => [
            'unsplash.com', 'images.unsplash.com', 'api.unsplash.com',
            'www.pexels.com', 'images.pexels.com', 'videos.pexels.com', 'api.pexels.com',
            'pixabay.com', 'cdn.pixabay.com',
            'api.openverse.org', 'commons.wikimedia.org', 'upload.wikimedia.org',
            'fonts.googleapis.com', 'fonts.gstatic.com',
            'cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Réglages modifiables dans l'administration (valeurs par défaut)
    |--------------------------------------------------------------------------
    |
    | Ces valeurs servent tant que l'administrateur ne les a pas changées dans
    | la page « Réglages ». Prix relevés le 8 novembre 2026 dans les pages
    | officielles (Google AI Studio, Anthropic, E2B) ; ils peuvent changer.
    |
    */

    'settings' => [
        // Minutes sans demande avant que l'ordinateur se mette en pause (10 : décision du 9 novembre 2026).
        'pause_minutes' => 10,

        // Jours en pause avant l'effacement de l'ordinateur (le projet reste sauvegardé dans le site).
        'erase_paused_after_days' => 7,

        // Taux de change : 1 € = 655,957 FCFA (fixe) ; 1 $ = 1 € / 1,1165 le 6 novembre 2026 (Banque centrale européenne).
        'usd_to_fcfa' => 590,

        // Ordinateur E2B : taille mesurée de l'ordinateur « opencode » (2 processeurs, 4 Go) et prix par seconde de marche (rien n'est facturé en pause).
        'computer_vcpu' => 2,
        'computer_ram_gib' => 4,
        'e2b_usd_per_vcpu_second' => 0.000014,
        'e2b_usd_per_gib_second' => 0.0000045,

        // Prix des modèles en dollars par million de tokens : entrée, sortie (réflexion comprise), lecture du cache.
        'model_prices' => [
            'gemini/gemini-3.5-flash-lite' => ['label' => 'Gemini 3.5 Flash-Lite', 'input' => 0.30, 'output' => 2.50, 'cache' => 0.03],
            'gemini/gemini-3.1-flash-lite' => ['label' => 'Gemini 3.1 Flash-Lite', 'input' => 0.25, 'output' => 1.50, 'cache' => 0.025],
            'gemini/gemini-3.8-flash' => ['label' => 'Gemini 3.8 Flash', 'input' => 0.75, 'output' => 3.75, 'cache' => 0.075],
            'gemini/gemini-4-argon' => ['label' => 'Gemini 4 Argon', 'input' => 4.00, 'output' => 20.00, 'cache' => 0.20],
            'anthropic/claude-haiku-5-5' => ['label' => 'Claude Haiku 5.5', 'input' => 0.10, 'output' => 0.50, 'cache' => 0.01],
            'anthropic/claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5', 'input' => 2.00, 'output' => 10.00, 'cache' => 0.20],
            'anthropic/claude-opus-5-5' => ['label' => 'Claude Opus 5.5', 'input' => 4.00, 'output' => 20.00, 'cache' => 0.20],
        ],

        // Formules : énergie en FCFA de coût réel par période ; une période de 0 heure veut dire « par jour » (minuit, heure de Lomé).
        // Décision du 9 novembre 2026 : recharge chaque jour à minuit pour toutes les formules ; prix payants provisoires,
        // à revoir après le choix du modèle (étape 6) et jamais publiés sans l'accord du porteur du projet.
        'plans' => [
            'gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'energy_fcfa' => 30, 'window_hours' => 0],
            'decouverte' => ['name' => 'Découverte', 'price_fcfa' => 2500, 'energy_fcfa' => 40, 'window_hours' => 0],
            'pro' => ['name' => 'Pro', 'price_fcfa' => 7500, 'energy_fcfa' => 130, 'window_hours' => 0],
            'entreprise' => ['name' => 'Entreprise', 'price_fcfa' => 25000, 'energy_fcfa' => 450, 'window_hours' => 0],
        ],
        'plans_published' => false,

        // Plafond total par jour pour l'ensemble des utilisateurs gratuits (FCFA d'énergie, remis à zéro à minuit, heure de Lomé).
        'free_daily_cap_fcfa' => 1500,
    ],

    // Les heures montrées aux utilisateurs sont toujours celles de Lomé.
    'display_timezone' => 'Africa/Lome',

    // Seuil d'alerte de l'administration, en pourcentage de la limite du jour.
    'alert_threshold' => 80,

];
