<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'auth/*',
        ]);
        
        $middleware->api([
            \App\Http\Middleware\CorsMiddleware::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SecurityHeaders::class,  // SECURITY: Add security headers to all API responses
        ]);

        // Middleware de proteção para Google Maps (apenas em rotas específicas)
        $middleware->alias([
            'google.quota' => \App\Http\Middleware\GoogleMapsQuotaProtection::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
