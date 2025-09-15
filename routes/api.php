<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MotoristaController;
use App\Http\Controllers\Api\PacoteController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\RotaController;
use App\Http\Controllers\Api\TransporteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rotas de autenticação (públicas)
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Rotas protegidas por autenticação
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
});

Route::middleware('api')->group(function () {
    Route::apiResource('motoristas', MotoristaController::class);
    Route::get('motoristas/progress/{codigo}', [MotoristaController::class, 'findByProgressCode']);

    Route::prefix('progress')->group(function () {
        Route::get('test-connection', [ProgressController::class, 'testConnection']);
        Route::get('transportes', [ProgressController::class, 'getTransportes']);
        Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById']);
        Route::post('query', [ProgressController::class, 'executeCustomQuery']);
    });
    
    // Rotas para TransporteController (JDBC Progress) - específicas primeiro
    Route::get('transportes/test-connection', [TransporteController::class, 'testConnection']);
    Route::get('transportes/statistics', [TransporteController::class, 'statistics']);
    Route::get('transportes/schema', [TransporteController::class, 'schema']);
    Route::post('transportes/query', [TransporteController::class, 'query']);
    Route::apiResource('transportes', TransporteController::class)->only(['index', 'show']);

    // Rotas para PacoteController (JDBC Progress)
    Route::get('pacotes/statistics', [PacoteController::class, 'statistics']);
    Route::post('pacotes/itinerario', [PacoteController::class, 'itinerario']);
    Route::apiResource('pacotes', PacoteController::class)->only(['index', 'show']);

    // Rotas para RotaController (JDBC Progress) - para autocomplete
    Route::get('rotas', [RotaController::class, 'index']);
    
    // Rotas para proxy de roteamento (contorna CORS)
    Route::prefix('routing')->group(function () {
        Route::get('test', [\App\Http\Controllers\Api\RoutingController::class, 'testConnection']);
        Route::post('route', [\App\Http\Controllers\Api\RoutingController::class, 'getRoute']);
    });
    
    // Rotas para monitoramento Google Maps
    Route::prefix('google-maps')->group(function () {
        Route::get('quota', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'getUsageStats']);
        Route::post('reset-counters', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'resetCounters']);
    });
    
    // Rotas para cache de rotas
    Route::prefix('route-cache')->group(function () {
        Route::post('find', [\App\Http\Controllers\Api\RouteCacheController::class, 'findRoute']);
        Route::post('save', [\App\Http\Controllers\Api\RouteCacheController::class, 'saveRoute']);
        Route::get('stats', [\App\Http\Controllers\Api\RouteCacheController::class, 'getStats']);
        Route::delete('clear-expired', [\App\Http\Controllers\Api\RouteCacheController::class, 'clearExpired']);
    });
});