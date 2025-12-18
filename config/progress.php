<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Progress Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Progress OpenEdge database connection via JDBC
    |
    */

    // Credenciais obrigatórias - definir no .env
    'host' => env('PROGRESS_HOST'),
    'port' => env('PROGRESS_PORT', '13361'),
    'database' => env('PROGRESS_DATABASE'),
    'username' => env('PROGRESS_USERNAME'),
    'password' => env('PROGRESS_PASSWORD'),

    // JDBC URL - definir no .env
    'jdbc_url' => env('PROGRESS_JDBC_URL'),
    'driver_path' => env('PROGRESS_DRIVER_PATH'),

];
