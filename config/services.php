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

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'daily_limit' => env('GOOGLE_MAPS_DAILY_LIMIT', 1000),
        'monthly_budget' => env('GOOGLE_MAPS_MONTHLY_BUDGET', 1.00),
        'protection_enabled' => env('GOOGLE_MAPS_PROTECTION_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Flask Service (PDF Generation + WhatsApp)
    |--------------------------------------------------------------------------
    */
    'python_flask' => [
        'url' => env('PYTHON_FLASK_URL'),  // Obrigatório se usar geração de PDF
        'timeout' => env('PYTHON_FLASK_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | OSRM Routing Servers (public, free)
    |--------------------------------------------------------------------------
    | Ordem de fallback para servidores OSRM gratuitos
    */
    'osrm' => [
        'servers' => [
            env('OSRM_SERVER_PRIMARY', 'https://router.project-osrm.org'),
            env('OSRM_SERVER_SECONDARY', 'https://routing.openstreetmap.de/routed-car'),
            env('OSRM_SERVER_FALLBACK', 'http://router.project-osrm.org'),
        ],
        'timeout' => env('OSRM_TIMEOUT', 15),
    ],

];
