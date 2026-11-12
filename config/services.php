<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // GitHub App de Kekeli (gratuite) : le client relie son compte et choisit les dépôts que Kekeli peut voir.
    // Les trois valeurs se placent dans les variables d'environnement, jamais dans le code.
    'github' => [
        // Nom court de l'application (dans son adresse github.com/apps/<nom>).
        'app_slug' => env('GITHUB_APP_SLUG'),
        // « Client ID » et « Client secret » de la GitHub App.
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        // Adresse de l'interface de GitHub (à ne changer que pour un essai local).
        'api_url' => env('KEKELI_GITHUB_API', 'https://api.github.com'),
    ],

];
