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
    | « gemini,openrouter,workersai ». Une entrée peut aussi préciser le modèle :
    | « gemini/gemini-3.1-flash-lite ». Quand un fournisseur atteint sa limite,
    | Kekeli passe automatiquement au suivant. Un fournisseur sans clé dans le
    | Runner est ignoré. Les limites du jour servent seulement à l'affichage dans
    | l'administration (estimations, à vérifier dans la console de chaque fournisseur).
    |
    */

    'providers_order' => env('KEKELI_FOURNISSEURS', 'gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai'),

    // Modèle qui répond aux questions, par formule (« fournisseur/modèle »), modifiable dans Administration → Réglages.
    // Choisi le 12 novembre 2026 après la comparaison réelle de docs/plan.md, partie 26.15. Les fournisseurs gratuits
    // restent derrière lui, en secours.
    'answer_models' => [
        'gratuite' => 'openai/gpt-6-luna',
        'pro' => 'openai/gpt-6.1-terra',
        'expert' => 'openai/gpt-6.1-terra',
        'agence' => 'openai/gpt-6.1-terra',
    ],

    // Modèle qui construit les sites et les applications dans l'ordinateur virtuel, par formule (« fournisseur/modèle »,
    // vide = fournisseurs gratuits seulement), modifiable dans Administration → Réglages. Choisi le 12 novembre 2026
    // après la construction réelle de la même application par plusieurs modèles (docs/plan.md, partie 27).
    'build_models' => [
        'gratuite' => 'openai/gpt-6.1-terra',
        'pro' => 'openai/gpt-6.1-sol',
        'expert' => 'openai/gpt-6.1-sol',
        'agence' => 'openai/gpt-6.1-sol',
    ],

    // Générateur des images et des affiches, par formule (« fournisseur/modèle »), modifiable dans Administration →
    // Réglages. Choisi le 13 novembre 2026 après l'essai comparatif de docs/plan.md, partie 31 : le meilleur en qualité
    // pour les formules payantes (il dessine toute l'affiche, puis Kekeli relit chaque texte), le meilleur abordable pour
    // la Gratuite (le fond seul, le texte posé par le code). Plafond OpenAI du jour atteint : Cloudflare prend le relais.
    'image_models' => [
        'gratuite' => 'cloudflare/flux-1-schnell',
        'pro' => 'openai/gpt-image-2.6-flare',
        'expert' => 'openai/gpt-image-2.6-flare',
        'agence' => 'openai/gpt-image-2.6-flare',
    ],
    // Modèle qui relit les textes d'une affiche dessinée par le générateur (vision), et générateur de secours.
    'image_check_model' => 'openai/gpt-6.1-terra',
    'image_fallback_model' => 'cloudflare/flux-1-schnell',
    // Prix officiels des générateurs payants (developers.openai.com/api/docs/pricing, lus le 13 novembre 2026), en
    // dollars par million de tokens : texte de la consigne, image donnée (modification), image produite.
    'image_prices' => [
        'openai/gpt-image-2.6-flare' => ['text' => 5.00, 'image_input' => 8.00, 'output' => 30.00],
        'openai/gpt-image-2.6-sunburst' => ['text' => 5.00, 'image_input' => 8.00, 'output' => 30.00],
    ],

    // Fournisseurs que le CLIENT peut brancher avec SA propre clé pour son agent ou son automatisation (choix guidé dans
    // « Variables secrètes » et LISEZ-MOI). Adresses compatibles OpenAI et conditions lues sur les sites officiels le
    // 12 novembre 2026 (docs/plan.md, partie 29) ; « free_data » : phrase montrée quand une offre gratuite peut
    // utiliser les messages pour améliorer les modèles. Les fournisseurs de Kekeli lui-même ne sont jamais montrés.
    'client_model_providers' => json_decode((string) file_get_contents(__DIR__.'/../runner/src/fournisseurs-client.json'), true)['fournisseurs'] ?? [],

    'providers' => [
        'gemini' => [
            'label' => 'Google AI Studio (Gemini)',
            'model' => env('KEKELI_MODELE_GEMINI', 'gemini-3.5-flash-lite'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_GEMINI', 500),
            'quota_timezone' => 'America/Los_Angeles',
        ],
        // Groq : offre gratuite, 1 000 requêtes par jour mais 8 000 tokens par minute (mesuré le 10 novembre 2026).
        'groq' => [
            'label' => 'Groq',
            'model' => env('KEKELI_MODELE_GROQ', 'openai/gpt-oss-120b'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_GROQ', 1000),
            'quota_timezone' => 'UTC',
        ],
        // OpenRouter : UNIQUEMENT des modèles gratuits (nom qui finit par « :free ») ; tout autre modèle est refusé
        // par le site (ProviderList) et par le Runner (modeleAutorise), même par erreur de réglage.
        // Chaque modèle suit les conditions de l'entreprise qui le fait tourner (article 5.1 d'OpenRouter) :
        // Gemma 4 est servi par Google AI Studio (usage commercial permis). Les versions gratuites de Nemotron
        // (NVIDIA, conditions d'essai : « not in production ») et de North (Cohere, interdit de « commercially
        // exploit ») ne sont pas utilisées pour des clients (docs/plan.md, partie 22).
        'openrouter' => [
            'label' => 'OpenRouter',
            'model' => env('KEKELI_MODELE_OPENROUTER', 'google/gemma-4-31b-it:free'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_OPENROUTER', 50),
            'quota_timezone' => 'UTC',
        ],
        // Mistral : plus d'offre gratuite pour l'API (11 novembre 2026). Gardé dans le code, hors de la liste.
        'mistral' => [
            'label' => 'Mistral',
            'model' => env('KEKELI_MODELE_MISTRAL', 'devstral-latest'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_MISTRAL'),
            'quota_timezone' => 'UTC',
        ],
        // Cloudflare Workers AI : même clé que la création d'images (CLOUDFLARE_API_TOKEN et CLOUDFLARE_ACCOUNT_ID),
        // 10 000 « neurones » gratuits par jour partagés avec les images, remise à zéro à minuit UTC. Seulement les
        // modèles gratuits de la liste (ProviderList::FREE_MODELS) ; le compte reste en offre gratuite (aucune facture).
        // OpenAI : clé payante, seulement pour répondre aux questions et pour la dictée (jamais pour l'agent ni dans
        // l'ordinateur virtuel), avec un plafond de dépense par jour (réglage « openai_daily_cap_usd »).
        'openai' => [
            'label' => 'OpenAI',
            'model' => 'gpt-6-luna',
            'daily_limit' => null,
            'quota_timezone' => 'UTC',
        ],
        'workersai' => [
            'label' => 'Cloudflare Workers AI',
            'model' => env('KEKELI_MODELE_WORKERSAI', '@cf/zai-org/glm-4.7-flash'),
            'daily_limit' => env('KEKELI_LIMITE_JOUR_WORKERSAI'),
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
            'api.openverse.org', 'live.staticflickr.com', 'commons.wikimedia.org', 'upload.wikimedia.org', 'thumb.wikimedia.org',
            'fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.bunny.net',
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

    // Modèle gratuit de l'essai des agents (le même que le Runner, variable KEKELI_MODELE_ESSAI_AGENT).
    'agent_trial_model' => env('KEKELI_MODELE_ESSAI_AGENT', env('KEKELI_MODELE_GEMINI', 'gemini-3.5-flash-lite')),

    'settings' => [
        // Minutes sans demande avant que l'ordinateur se mette en pause (10 : décision du 9 novembre 2026).
        'pause_minutes' => 10,
        // Formule gratuite : 5 minutes (décision du 12 novembre 2026) ; l'aperçu se rallume tout seul quand le client revient.
        'free_pause_minutes' => 5,

        // Nombre maximum de demandes que Kekeli traite en même temps (tous les utilisateurs ensemble) ; les suivantes
        // attendent leur tour, sans rien perdre (décision du 9 novembre 2026). L'offre « Hobby » d'E2B permet
        // 10 ordinateurs allumés en même temps. 0 = pas de limite.
        'max_concurrent_runs' => 10,

        // Clients payants d'abord (décision du 10 novembre 2026) : quand le quota gratuit des fournisseurs d'IA du
        // jour atteint ce pourcentage, les demandes gratuites n'utilisent plus qu'une partie des places.
        'paid_priority_quota_percent' => 85,
        'free_places_when_quota_low_percent' => 50,

        // Mode développeur : nombre de corrections automatiques quand la vérification de GitHub échoue sur une
        // pull request de Kekeli (chacune est comptée dans l'énergie du client). Ensuite, Kekeli demande au client.
        'ci_fix_attempts_max' => 2,

        // Jours en pause avant l'effacement de l'ordinateur (le projet reste sauvegardé dans le site).
        'erase_paused_after_days' => 7,

        // Taux de change : 1 € = 655,957 FCFA (fixe) ; 1 $ = 1 € / 1,1165 le 6 novembre 2026 (Banque centrale européenne).
        'usd_to_fcfa' => 590,

        // Ordinateur E2B : taille mesurée de l'ordinateur « opencode » (2 processeurs, 4 Go) et prix par seconde de marche (rien n'est facturé en pause).
        'computer_vcpu' => 2,
        'computer_ram_gib' => 4,
        'e2b_usd_per_vcpu_second' => 0.000014,
        'e2b_usd_per_gib_second' => 0.0000045,

        // Plafond de dépense chez OpenAI par jour (réponses et dictée ensemble, jour de facturation UTC), décision du
        // 12 novembre 2026. Une fois atteint, Kekeli répond avec les fournisseurs gratuits, sans rien dire au client.
        // 0 = OpenAI jamais utilisé.
        'openai_daily_cap_usd' => 1.0,
        // Essai gratuit d'un agent IA dans l'aperçu (modèle gratuit de Kekeli, par le relais du Runner), tant que le
        // client n'a pas mis sa propre clé dans les Variables secrètes : messages par jour et par client (12 novembre 2026).
        'agent_trial_messages_per_day' => 10,
        // Connecteurs (MCP) actifs à la fois dans une discussion pour la formule Gratuite (13 novembre 2026) ; les
        // formules payantes n'ont pas de limite.
        'free_active_connectors_max' => 2,
        // Versions gardées par projet (13 novembre 2026) : les plus anciennes sont effacées au-delà, jamais la version
        // actuelle ni une version remise par une restauration. Une version pèse environ la taille de la sauvegarde.
        'versions_kept_free' => 30,
        'versions_kept_paid' => 200,
        // Dictée vocale quand le navigateur ne la propose pas : transcription d'OpenAI (gpt-transcribe, prix officiel
        // lu le 12 novembre 2026), comptée dans l'énergie du client.
        'transcription_model' => 'gpt-transcribe',
        'transcription_usd_per_minute' => 0.0045,

        // Prix des modèles en dollars par million de tokens : entrée, sortie (réflexion comprise), lecture du cache.
        'model_prices' => [
            'gemini/gemini-3.5-flash-lite' => ['label' => 'Gemini 3.5 Flash-Lite', 'input' => 0.30, 'output' => 2.50, 'cache' => 0.03],
            'gemini/gemini-3.1-flash-lite' => ['label' => 'Gemini 3.1 Flash-Lite', 'input' => 0.25, 'output' => 1.50, 'cache' => 0.025],
            'gemini/gemini-3.8-flash' => ['label' => 'Gemini 3.8 Flash', 'input' => 0.75, 'output' => 3.75, 'cache' => 0.075],
            'gemini/gemini-3.6-flash' => ['label' => 'Gemini 3.6 Flash', 'input' => 0.75, 'output' => 3.75, 'cache' => 0.075],
            'gemini/gemini-3-flash-preview' => ['label' => 'Gemini 3 Flash Preview', 'input' => 0.50, 'output' => 3.00, 'cache' => 0.05],
            // Gemma 4 : seulement en offre gratuite (pas d'offre payante chez Google), donc sans prix.
            'gemini/gemma-4-26b-a4b-it' => ['label' => 'Gemma 4 26B', 'input' => 0, 'output' => 0, 'cache' => 0],
            'gemini/gemini-4-argon' => ['label' => 'Gemini 4 Argon', 'input' => 4.00, 'output' => 20.00, 'cache' => 0.20],
            // Cloudflare Workers AI : prix de l'offre payante (0,011 $ pour 1 000 neurones), convertis par million de tokens.
            'workersai/@cf/zai-org/glm-4.7-flash' => ['label' => 'GLM-4.7 Flash (Cloudflare)', 'input' => 0.0605, 'output' => 0.40, 'cache' => 0.0605],
            'workersai/@cf/zai-org/glm-5.3-flash' => ['label' => 'GLM-5.3 Flash (Cloudflare)', 'input' => 0.15, 'output' => 0.50, 'cache' => 0.03],
            'workersai/@cf/qwen/qwen3-30b-a3b-fp8' => ['label' => 'Qwen3 30B (Cloudflare)', 'input' => 0.051, 'output' => 0.335, 'cache' => 0.051],
            'workersai/@cf/openai/gpt-oss-120b' => ['label' => 'gpt-oss-120b (Cloudflare)', 'input' => 0.35, 'output' => 0.75, 'cache' => 0.35],
            'workersai/@cf/openai/gpt-oss-20b' => ['label' => 'gpt-oss-20b (Cloudflare)', 'input' => 0.20, 'output' => 0.30, 'cache' => 0.20],
            'workersai/@cf/meta/llama-4-scout-17b-16e-instruct' => ['label' => 'Llama 4 Scout (Cloudflare)', 'input' => 0.27, 'output' => 0.85, 'cache' => 0.27],
            'workersai/@cf/moonshotai/kimi-k2.5' => ['label' => 'Kimi K2.5 (Cloudflare)', 'input' => 0.60, 'output' => 3.00, 'cache' => 0.10],
            'anthropic/claude-haiku-5-5' => ['label' => 'Claude Haiku 5.5', 'input' => 0.10, 'output' => 0.50, 'cache' => 0.01],
            'anthropic/claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5', 'input' => 2.00, 'output' => 10.00, 'cache' => 0.10],
            'anthropic/claude-opus-5-5' => ['label' => 'Claude Opus 5.5', 'input' => 4.00, 'output' => 20.00, 'cache' => 0.20],
            // OpenAI (prix officiels lus le 12 novembre 2026, developers.openai.com/api/docs/pricing).
            'openai/gpt-6-luna' => ['label' => 'GPT-6 Luna', 'input' => 0.10, 'output' => 0.50, 'cache' => 0.01],
            'openai/gpt-6.1-terra' => ['label' => 'GPT-6.1 Terra', 'input' => 1.00, 'output' => 5.00, 'cache' => 0.05],
            'openai/gpt-6.1-sol' => ['label' => 'GPT-6.1 Sol', 'input' => 2.00, 'output' => 10.00, 'cache' => 0.10],
        ],

        // Formules (décision du 12 novembre 2026, docs/plan.md, partie 25) : énergie en FCFA de coût réel par période.
        // « period » : « day » = recharge chaque jour à minuit (heure de Lomé), « month » = énergie pour chaque mois de
        // l'abonnement (30 jours à partir du paiement), « hours » = toutes les « window_hours » heures (ancien réglage).
        // « price_usd » : prix affiché aux clients hors zone FCFA (le paiement reste en FCFA par Mobile Money).
        'plans' => [
            'gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'price_usd' => 0, 'energy_fcfa' => 30, 'period' => 'day', 'window_hours' => 0, 'web_searches_per_day' => 3],
            'pro' => ['name' => 'Pro', 'price_fcfa' => 7500, 'price_usd' => 13, 'energy_fcfa' => 4000, 'period' => 'month', 'window_hours' => 0, 'web_searches_per_day' => 20],
            'expert' => ['name' => 'Expert', 'price_fcfa' => 20000, 'price_usd' => 34, 'energy_fcfa' => 11000, 'period' => 'month', 'window_hours' => 0, 'web_searches_per_day' => 40],
            'agence' => ['name' => 'Agence', 'price_fcfa' => 50000, 'price_usd' => 85, 'energy_fcfa' => 28000, 'period' => 'month', 'window_hours' => 0, 'web_searches_per_day' => 80],
        ],
        // Anciennes formules (avant le 12 novembre 2026) et formule qui les remplace : les abonnés passent à la plus proche
        // sans rien perdre (migration 2026_11_12_000001 ; un paiement en attente est converti au moment de sa validation).
        'replaced_plans' => ['decouverte' => 'pro', 'entreprise' => 'expert'],

        // Équivalences affichées sur la page « Formules » (estimations de docs/plan.md, partie 25) : énergie d'un site
        // vitrine complet et d'un site avec base de données complet (création et environ 5 retouches, mélange Claude
        // Haiku 5.5 et Sonnet 5.5, estimation prudente), et d'un site vitrine pour la formule gratuite (mesuré avec Gemini).
        'site_estimate_fcfa' => 275,
        'webapp_estimate_fcfa' => 550,
        'free_site_estimate_fcfa' => 55,
        // Prix publiés sur la page « Formules » avec l'accord du porteur du projet (9 novembre 2026).
        'plans_published' => true,

        // Paiement par Mobile Money avec capture d'écran, validé dans l'administration (Administration → Paiements).
        // Les numéros, les titulaires et le numéro WhatsApp ne sont JAMAIS écrits dans le code : ils se règlent
        // seulement dans Administration → Paiements (enregistrés dans la base). Au Togo, toujours « Mixx by Yas ».
        'payment_countries' => [],
        // Autres pays : un bouton « Écrire sur WhatsApp » (numéro réglé dans Administration → Paiements).
        'payment_whatsapp' => '',
        // Une formule payée est active ce nombre de jours ; un rappel s'affiche ce nombre de jours avant la fin.
        'plan_duration_days' => 30,
        'plan_reminder_days' => 3,

        // Prix d'une image créée (Cloudflare Workers AI, FLUX.1 schnell, 1 024 × 768 en 4 étapes : environ 53 « neurones »
        // à 0,011 $ les 1 000 au-delà des 10 000 gratuits par jour, soit environ 0,0006 $). Estimation arrondie à 0,001 $,
        // comptée dans l'énergie du client même pendant l'offre gratuite (décision du 9 novembre 2026).
        'image_price_usd' => 0.001,

        // Nombre maximum d'images créées par jour, pour tous les utilisateurs ensemble (remis à zéro à minuit UTC,
        // comme l'offre gratuite de Cloudflare). Une image de 1 024 × 1 024 en 4 étapes coûte environ 58 « neurones »
        // sur les 10 000 gratuits par jour, soit environ 170 images (estimation) : 150 garde une marge.
        'images_max_per_day' => 150,

        // Recherches sur Internet (outil « rechercher_web ») : plafond mensuel pour tous les clients ensemble, réglé
        // sous les offres gratuites (Tavily : environ 1 000 recherches par mois ; Exa en secours), et prix d'une
        // recherche compté dans l'énergie. 0,008 $ : prix d'un crédit Tavily payant (estimation), compté même pendant
        // l'offre gratuite, comme les images. Le nombre par jour et par client est donné par chaque formule.
        'web_search_monthly_cap' => 900,
        'web_search_price_usd' => 0.008,

        // Plafond total par jour pour l'ensemble des utilisateurs gratuits (FCFA d'énergie, remis à zéro à minuit, heure de Lomé).
        // 1 000 FCFA au lancement (décision du 12 novembre 2026), à augmenter avec les ventes.
        'free_daily_cap_fcfa' => 1000,

        // Coût de Kekeli sur Laravel Cloud (ses applications et sa base de données) à partir duquel l'administrateur
        // reçoit une alerte (à 80 % puis à 100 %). Lu chaque jour, sans jamais rien changer sur Laravel Cloud.
        'hosting_alert_usd' => 10,
    ],

    // Modèle qui regarde les photos libres avant de les choisir (filigrane, marques, visages, pays du client).
    // Un modèle différent de celui de l'agent, pour ne pas consommer son quota gratuit du jour.
    'vision_model' => env('KEKELI_MODELE_VISION', 'gemini-3.1-flash-lite'),

    /*
    |--------------------------------------------------------------------------
    | Pays des clients
    |--------------------------------------------------------------------------
    |
    | Kekeli adapte les images, la monnaie et le format des numéros de téléphone
    | au pays du client. « anglais » sert aux recherches de photos libres.
    |
    */

    'default_country' => 'TG',

    // Tous les pays du monde (resources/data/pays-du-monde.php, noms en français, par ordre alphabétique), avec des
    // détails en plus pour les pays les plus fréquents ci-dessous (région, ville principale, format des numéros).
    'countries' => array_replace(require resource_path('data/pays-du-monde.php'), [
        'TG' => ['name' => 'Togo', 'region' => "Afrique de l'Ouest", 'city' => 'Lomé', 'english' => 'Togo, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+228 90 12 34 56'],
        'BJ' => ['name' => 'Bénin', 'region' => "Afrique de l'Ouest", 'city' => 'Cotonou', 'english' => 'Benin, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+229 01 97 12 34 56'],
        'CI' => ['name' => "Côte d'Ivoire", 'region' => "Afrique de l'Ouest", 'city' => 'Abidjan', 'english' => 'Ivory Coast, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+225 07 12 34 56 78'],
        'BF' => ['name' => 'Burkina Faso', 'region' => "Afrique de l'Ouest", 'city' => 'Ouagadougou', 'english' => 'Burkina Faso, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+226 70 12 34 56'],
        'SN' => ['name' => 'Sénégal', 'region' => "Afrique de l'Ouest", 'city' => 'Dakar', 'english' => 'Senegal, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+221 77 123 45 67'],
        'ML' => ['name' => 'Mali', 'region' => "Afrique de l'Ouest", 'city' => 'Bamako', 'english' => 'Mali, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+223 70 12 34 56'],
        'NE' => ['name' => 'Niger', 'region' => "Afrique de l'Ouest", 'city' => 'Niamey', 'english' => 'Niger, West Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+227 90 12 34 56'],
        'GN' => ['name' => 'Guinée', 'region' => "Afrique de l'Ouest", 'city' => 'Conakry', 'english' => 'Guinea, West Africa', 'currency' => 'franc guinéen (GNF)', 'phone' => '+224 620 12 34 56'],
        'GH' => ['name' => 'Ghana', 'region' => "Afrique de l'Ouest", 'city' => 'Accra', 'english' => 'Ghana, West Africa', 'currency' => 'cedi (GHS)', 'phone' => '+233 24 123 4567'],
        'NG' => ['name' => 'Nigeria', 'region' => "Afrique de l'Ouest", 'city' => 'Lagos', 'english' => 'Nigeria, West Africa', 'currency' => 'naira (NGN)', 'phone' => '+234 803 123 4567'],
        'CM' => ['name' => 'Cameroun', 'region' => 'Afrique centrale', 'city' => 'Douala', 'english' => 'Cameroon, Central Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+237 6 71 23 45 67'],
        'GA' => ['name' => 'Gabon', 'region' => 'Afrique centrale', 'city' => 'Libreville', 'english' => 'Gabon, Central Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+241 06 12 34 56'],
        'CG' => ['name' => 'Congo', 'region' => 'Afrique centrale', 'city' => 'Brazzaville', 'english' => 'Republic of the Congo, Central Africa', 'currency' => 'franc CFA (FCFA)', 'phone' => '+242 06 123 4567'],
        'CD' => ['name' => 'République démocratique du Congo', 'region' => 'Afrique centrale', 'city' => 'Kinshasa', 'english' => 'Democratic Republic of the Congo, Central Africa', 'currency' => 'franc congolais (CDF)', 'phone' => '+243 81 234 5678'],
        'MA' => ['name' => 'Maroc', 'region' => 'Afrique du Nord', 'city' => 'Casablanca', 'english' => 'Morocco, North Africa', 'currency' => 'dirham (MAD)', 'phone' => '+212 6 12 34 56 78'],
        'FR' => ['name' => 'France', 'region' => 'Europe', 'city' => 'Paris', 'english' => 'France, Europe', 'currency' => 'euro (€)', 'phone' => '+33 6 12 34 56 78'],
        'CA' => ['name' => 'Canada', 'region' => 'Amérique du Nord', 'city' => 'Montréal', 'english' => 'Canada, North America', 'currency' => 'dollar canadien ($ CA)', 'phone' => '+1 514 555 0123'],
    ]),

    // Pays où Kekeli n'est pas encore disponible pendant la bêta : l'offre gratuite de Gemini y est interdite
    // (Union européenne et ses régions ultrapériphériques, Espace économique européen, Royaume-Uni, Suisse).
    // Ils verront « Bientôt disponible dans votre pays » et seront ouverts avec la clé Claude, à la fin du projet.
    // Pour tout ouvrir : KEKELI_PAYS_BIENTOT=aucun.
    'coming_soon_countries' => env('KEKELI_PAYS_BIENTOT') === 'aucun' ? [] : [
        // Union européenne
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // Régions de l'Union européenne situées hors d'Europe et îles d'Åland
        'GP', 'MQ', 'GF', 'RE', 'YT', 'MF', 'AX',
        // Espace économique européen, Royaume-Uni et Suisse
        'NO', 'IS', 'LI', 'GB', 'CH',
    ],

    // Photos que le client peut envoyer pour ses projets (depuis son téléphone).
    'client_photos' => [
        'max_per_project' => 20,
        'max_kilobytes' => 8192,
        'directory' => 'photos-clients',
    ],

    // Les heures montrées aux utilisateurs sont toujours celles de Lomé.
    'display_timezone' => 'Africa/Lome',

    // Lecture du coût de Kekeli sur Laravel Cloud (API officielle « usage »). La clé se crée dans Laravel Cloud
    // (Organisation → Réglages → API tokens) avec la seule permission de lire l'usage ; elle va dans la variable
    // LARAVEL_CLOUD_API_TOKEN, jamais dans le code. Sans clé, rien n'est lu.
    'hosting' => [
        'api_url' => env('LARAVEL_CLOUD_API_URL', 'https://cloud.laravel.com/api'),
        'token' => env('LARAVEL_CLOUD_API_TOKEN'),
        // Noms des applications et de la base de données de Kekeli dans Laravel Cloud (jamais ceux des autres sites).
        'applications' => array_filter(explode(',', (string) env('KEKELI_CLOUD_APPLICATIONS', 'kekeli,kekeli-runner'))),
        'databases' => array_filter(explode(',', (string) env('KEKELI_CLOUD_BASES', 'kekeli'))),
    ],

    // Seuil d'alerte de l'administration, en pourcentage de la limite du jour.
    'alert_threshold' => 80,

    /*
    |--------------------------------------------------------------------------
    | Connecteurs des outils du client (plus tard)
    |--------------------------------------------------------------------------
    |
    | Une entrée par connecteur : « 'gmail' => ['name' => 'Gmail', 'enabled' => true] ». Seuls ceux qui
    | fonctionnent vraiment sont « enabled » ; tant qu'il n'y en a aucun, l'onglet « Connecteurs » des
    | réglages reste caché (app/Support/Connectors.php). Aujourd'hui : aucun.
    |
    */

    // Catalogue des connecteurs (MCP) de la catégorie (1) de l'étude du 13 novembre 2026 (docs/plan.md, partie 26.16) :
    // serveurs officiels où Kekeli s'inscrit tout seul (OAuth avec inscription automatique) ou sans connexion.
    // Aucun compte ni application n'est à créer par le porteur du projet. Adresses relues sur les sites officiels.
    'connectors' => [
        'notion' => ['name' => 'Notion', 'url' => 'https://mcp.notion.com/mcp', 'auth' => 'oauth', 'icon' => 'document-text', 'description' => 'Search, read and create pages in your Notion.'],
        'airtable' => ['name' => 'Airtable', 'url' => 'https://mcp.airtable.com/mcp', 'auth' => 'oauth', 'icon' => 'table-cells', 'description' => 'Read and update the records of your Airtable bases.'],
        'atlassian' => ['name' => 'Jira et Confluence', 'url' => 'https://mcp.atlassian.com/v2/mcp', 'auth' => 'oauth', 'icon' => 'clipboard-document-list', 'description' => 'Your Jira tickets and your Confluence pages.'],
        'linear' => ['name' => 'Linear', 'url' => 'https://mcp.linear.app/mcp', 'auth' => 'oauth', 'icon' => 'check-circle', 'description' => 'Your Linear tickets and projects.'],
        'miro' => ['name' => 'Miro', 'url' => 'https://mcp.miro.com/', 'auth' => 'oauth', 'icon' => 'squares-2x2', 'description' => 'Read your Miro boards and create diagrams.'],
        'huggingface' => ['name' => 'Hugging Face', 'url' => 'https://huggingface.co/mcp', 'auth' => 'aucune', 'icon' => 'cpu-chip', 'description' => 'AI models, datasets and papers. No account needed.'],
        'deepwiki' => ['name' => 'DeepWiki', 'url' => 'https://mcp.deepwiki.com/mcp', 'auth' => 'aucune', 'icon' => 'book-open', 'description' => 'The documentation of the public repositories of GitHub. No account needed.'],
    ],

];
