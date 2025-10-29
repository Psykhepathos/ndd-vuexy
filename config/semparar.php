<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SemParar API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to SemParar SOAP API (app.viafacil.com.br)
    |
    */

    'wsdl_url' => env('SEMPARAR_WSDL_URL', 'https://app.viafacil.com.br/wsvp/ValePedagio?wsdl'),

    // Separate WSDL for trip history/consultation (obterExtratoCreditos)
    'wsdl_extrato_url' => env('SEMPARAR_WSDL_EXTRATO_URL', 'https://app.viafacil.com.br/vpextrato/ValePedagio?wsdl'),

    'cnpj' => env('SEMPARAR_CNPJ', '2024209702'),

    'user' => env('SEMPARAR_USER', 'CORPORATIVO'),

    'password' => env('SEMPARAR_PASSWORD', 'Tambasa20'),

    'timeout' => env('SEMPARAR_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SOAP Client Options
    |--------------------------------------------------------------------------
    */

    'soap_options' => [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE, // Disable cache during development
        'connection_timeout' => 30,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                // TLS 1.2 + 1.3 support
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            ]
        ])
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */

    'token_cache_ttl' => 3600, // 1 hour (in seconds)

];
