<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | IMPORTANTE: Quando supports_credentials = true, allowed_origins NÃO pode ser '*'
    | Em desenvolvimento, use as origens específicas do Vite e Laravel
    | Em produção, configure CORS_ALLOWED_ORIGINS no .env
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Configure via .env com CORS_ALLOWED_ORIGINS (separado por vírgula)
    | Exemplo desenvolvimento: CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:5174,http://localhost:8002
    | Exemplo produção: CORS_ALLOWED_ORIGINS=https://seu-servidor.com.br
    |
    | Se não configurado, usa valores padrão para desenvolvimento local
    |
    */
    'allowed_origins' => array_filter(
        env('CORS_ALLOWED_ORIGINS')
            ? explode(',', env('CORS_ALLOWED_ORIGINS'))
            : [
                'http://localhost:5173',
                'http://localhost:5174',
                'http://localhost:5175',
                'http://localhost:5176',
                'http://localhost:8002',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:8002',
            ]
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
