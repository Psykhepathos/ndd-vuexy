<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompraViagemController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\MotoristaController;
use App\Http\Controllers\Api\OSRMController;
use App\Http\Controllers\Api\PacoteController;
use App\Http\Controllers\Api\PracaPedagioController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\RotaController;
use App\Http\Controllers\Api\SemPararController;
use App\Http\Controllers\Api\SemPararRotaController;
use App\Http\Controllers\Api\TransporteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rotas de autenticação (públicas) com rate limiting por IP
// Nota: Aumentado para 10/min pois escritórios compartilham IP
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1'); // 10 tentativas por minuto por IP
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1'); // 5 registros por minuto por IP

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
    // SECURITY: test-connection é público para monitoramento, mas com rate limit
    Route::get('transportes/test-connection', [TransporteController::class, 'testConnection'])
        ->middleware('throttle:10,1');  // 10 requests per minute

    // Expensive operations - strict rate limiting (públicas por ora)
    Route::get('transportes/statistics', [TransporteController::class, 'statistics'])
        ->middleware('throttle:10,1');  // 10 requests per minute
    Route::get('transportes/schema', [TransporteController::class, 'schema'])
        ->middleware('throttle:10,1');  // 10 requests per minute

    // Standard CRUD operations - moderate rate limiting (públicas por ora)
    Route::apiResource('transportes', TransporteController::class)
        ->only(['index', 'show'])
        ->middleware('throttle:60,1');  // 60 requests per minute

    // SECURITY: Custom query endpoint - admin-only with auth
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('transportes/query', [TransporteController::class, 'query'])
            ->middleware('throttle:5,1');   // 5 requests per minute (admin-only custom queries)
    });

    // Rotas para PacoteController (JDBC Progress)
    Route::get('pacotes/statistics', [PacoteController::class, 'statistics']);
    Route::get('pacotes/autocomplete', [PacoteController::class, 'autocomplete']);
    Route::post('pacotes/itinerario', [PacoteController::class, 'itinerario']);
    Route::apiResource('pacotes', PacoteController::class)->only(['index', 'show']);

    // Rotas para RotaController (JDBC Progress) - para autocomplete
    Route::get('rotas', [RotaController::class, 'index']);
    
    // Rotas para proxy de roteamento OSRM (100% gratuito, contorna CORS)
    Route::prefix('routing')->group(function () {
        Route::get('test', [\App\Http\Controllers\Api\RoutingController::class, 'testConnection']);
        Route::post('route', [\App\Http\Controllers\Api\RoutingController::class, 'getRoute']);
        // Route::post('calculate', ...) - DEPRECATED - Google Directions removido
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

    // Rotas para gestão de rotas SemParar
    Route::prefix('semparar-rotas')->group(function () {
        // Rotas específicas primeiro para evitar conflitos
        Route::get('municipios', [SemPararRotaController::class, 'municipios']);
        Route::get('estados', [SemPararRotaController::class, 'estados']);

        // Rotas CRUD
        Route::get('/', [SemPararRotaController::class, 'index']);
        Route::post('/', [SemPararRotaController::class, 'store']);
        Route::get('/{id}', [SemPararRotaController::class, 'show']);
        Route::get('/{id}/municipios', [SemPararRotaController::class, 'showWithMunicipios']);
        Route::put('/{id}', [SemPararRotaController::class, 'update']);
        Route::put('/{id}/municipios', [SemPararRotaController::class, 'updateMunicipios']);
        Route::delete('/{id}', [SemPararRotaController::class, 'destroy']);
    });

    // Rotas para geocoding (conversão IBGE → lat/lon com cache)
    Route::prefix('geocoding')->group(function () {
        Route::post('ibge', [GeocodingController::class, 'getCoordenadasByIbge']);
        Route::post('lote', [GeocodingController::class, 'getCoordenadasLote']);
    });

    // Rotas para gestão de praças de pedágio (ANTT)
    Route::prefix('pracas-pedagio')->group(function () {
        // Rotas específicas primeiro para evitar conflitos com /{id}

        // Estatísticas (público)
        Route::get('estatisticas', [PracaPedagioController::class, 'estatisticas'])
            ->middleware('throttle:30,1');  // 30 requests per minute

        // Buscar praças próximas a coordenadas (público)
        Route::post('proximidade', [PracaPedagioController::class, 'proximidade'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Importar CSV (público por ora, considerar auth futuramente)
        Route::post('importar', [PracaPedagioController::class, 'importar'])
            ->middleware('throttle:5,1');   // 5 requests per minute (operação pesada)

        // Limpar todas as praças (público por ora, considerar auth futuramente)
        Route::delete('limpar', [PracaPedagioController::class, 'limpar'])
            ->middleware('throttle:2,1');   // 2 requests per minute (operação crítica)

        // Listagem com filtros e paginação (público)
        Route::get('/', [PracaPedagioController::class, 'index'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Obter praça específica (público) - SEMPRE POR ÚLTIMO
        Route::get('/{id}', [PracaPedagioController::class, 'show'])
            ->middleware('throttle:60,1');  // 60 requests per minute
    });

    // Rotas para MapService unificado (FASE 1 - Backend Foundation)
    Route::prefix('map')->group(function () {
        // Route calculation with automatic provider selection
        Route::post('route', [MapController::class, 'calculateRoute'])
            ->middleware('throttle:100,1');  // 100 requests per minute

        // Batch geocoding
        Route::post('geocode-batch', [MapController::class, 'geocodeBatch'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Point clustering
        Route::post('cluster-points', [MapController::class, 'clusterPoints'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Cache management
        Route::get('cache-stats', [MapController::class, 'cacheStats'])
            ->middleware('throttle:30,1');  // 30 requests per minute
        Route::post('clear-expired-cache', [MapController::class, 'clearExpiredCache'])
            ->middleware('throttle:5,1');   // 5 requests per minute (admin operation)

        // Provider information
        Route::get('providers', [MapController::class, 'providers'])
            ->middleware('throttle:30,1');  // 30 requests per minute
    });

    // Proxy OSRM (roteamento gratuito)
    Route::post('osrm/route', [OSRMController::class, 'getRoute']);

    // Rotas para SemParar SOAP API (FASE 1A + 1B)
    Route::prefix('semparar')->group(function () {
        // FASE 1A - Core
        Route::get('test-connection', [SemPararController::class, 'testConnection'])
            ->middleware('throttle:10,1');  // 10 requests per minute
        Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // FASE 1B - Routing
        Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
            ->middleware('throttle:20,1');  // 20 requests per minute
        Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
            ->middleware('throttle:20,1');  // 20 requests per minute
        Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // FASE 2A - Purchase
        Route::post('comprar-viagem', [SemPararController::class, 'comprarViagem'])
            ->middleware('throttle:10,1');  // 10 requests per minute (sensitive operation)

        // FASE 2C - Receipt
        Route::post('obter-recibo', [SemPararController::class, 'obterRecibo'])
            ->middleware('throttle:60,1');  // 60 requests per minute
        Route::post('gerar-recibo', [SemPararController::class, 'gerarRecibo'])
            ->middleware('throttle:20,1');  // 20 requests per minute (sends WhatsApp/Email)

        // FASE 3A - Query & Management
        Route::post('consultar-viagens', [SemPararController::class, 'consultarViagens'])
            ->middleware('throttle:60,1');  // 60 requests per minute
        Route::post('cancelar-viagem', [SemPararController::class, 'cancelarViagem'])
            ->middleware('throttle:20,1');  // 20 requests per minute (cancels trip)
        Route::post('reemitir-viagem', [SemPararController::class, 'reemitirViagem'])
            ->middleware('throttle:20,1');  // 20 requests per minute (reissues trip)

        // Debug endpoints (only available in APP_DEBUG=true)
        Route::get('debug/token', [SemPararController::class, 'debugToken']);
        Route::post('debug/clear-cache', [SemPararController::class, 'clearCache']);
    });

    // ⚠️ Compra de Viagem SemParar - MODO DE TESTE ATIVO ⚠️
    // IMPORTANTE: Todas as chamadas estão em modo simulação para evitar compras acidentais
    Route::prefix('compra-viagem')->group(function () {
        Route::get('initialize', [CompraViagemController::class, 'initialize']);
        Route::get('statistics', [CompraViagemController::class, 'statistics']);
        Route::get('health', [CompraViagemController::class, 'health']);

        // LISTAGEM: Busca viagens do Progress (tabela PUB.sPararViagem)
        Route::post('viagens', [CompraViagemController::class, 'listarViagens']);

        // FASE 2: Validação de pacote
        Route::post('validar-pacote', [CompraViagemController::class, 'validarPacote']);

        // FASE 3: Validação de placa/veículo
        Route::post('validar-placa', [CompraViagemController::class, 'validarPlaca']);

        // FASE 4: Seleção de rota
        Route::get('rotas', [CompraViagemController::class, 'listarRotas']);
        Route::post('validar-rota', [CompraViagemController::class, 'validarRota']);

        // FASE 5: Verificação de preço
        Route::post('verificar-preco', [CompraViagemController::class, 'verificarPreco']);

        // FASE 6: Compra de viagem
        Route::post('comprar', [CompraViagemController::class, 'comprarViagem']);

        // DEBUG: Análise completa do fluxo
        Route::post('debug-flow', [\App\Http\Controllers\Api\DebugSemPararController::class, 'debugFlow']);

        // TODO: Adicionar rotas das próximas fases aqui
        // Route::post('gerar-recibo', [CompraViagemController::class, 'gerarRecibo']);
    });
});