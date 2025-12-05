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

    'host' => env('PROGRESS_HOST', '192.168.80.113'),
    'port' => env('PROGRESS_PORT', '13361'),
    'database' => env('PROGRESS_DATABASE', 'tambasa'),
    'username' => env('PROGRESS_USERNAME', 'sysprogress'),
    'password' => env('PROGRESS_PASSWORD', 'sysprogress'),

    'jdbc_url' => env('PROGRESS_JDBC_URL', 'jdbc:datadirect:openedge://192.168.80.113:13361;databaseName=tambasa;trustStore='),
    'driver_path' => env('PROGRESS_DRIVER_PATH', 'c:/Progress/OpenEdge/java/openedge.jar'),

];
