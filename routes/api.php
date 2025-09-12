<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MotoristaController;
use App\Http\Controllers\Api\PacoteController;
use App\Http\Controllers\Api\ProgressController;
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
    Route::apiResource('pacotes', PacoteController::class)->only(['index', 'show']);
});