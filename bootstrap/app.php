<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

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
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Para requisições API não autenticadas, retornar JSON 401 ao invés de redirecionar
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Não redirecionar - deixar o exception handler lidar
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tratar AuthenticationException para retornar JSON em requisições API
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Não autenticado.',
                ], 401);
            }
        });
    })->create();
