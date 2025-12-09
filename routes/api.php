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
use App\Http\Controllers\Api\NddCargoController;
use App\Http\Controllers\Api\VpoController;
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

    // CORREÇÃO #6: Rate limiting para prevenir token enumeration
    Route::get('auth/user', [AuthController::class, 'user'])
        ->middleware('throttle:60,1');  // 60 req/min
});

Route::middleware('api')->group(function () {
    Route::apiResource('motoristas', MotoristaController::class);
    Route::get('motoristas/progress/{codigo}', [MotoristaController::class, 'findByProgressCode']);

    // CORREÇÃO #1+#2: Rate limiting + Autenticação para ProgressController
    Route::prefix('progress')->group(function () {
        // Endpoints públicos com rate limiting (CORREÇÃO #2: Prevenir DoS)
        Route::get('test-connection', [ProgressController::class, 'testConnection'])
            ->middleware('throttle:10,1');  // 10 req/min - health check

        Route::get('transportes', [ProgressController::class, 'getTransportes'])
            ->middleware('throttle:60,1');  // 60 req/min - listagem

        Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById'])
            ->middleware('throttle:60,1');  // 60 req/min - leitura específica

        // CORREÇÃO #1: Custom query APENAS para usuários autenticados + rate limiting agressivo
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('query', [ProgressController::class, 'executeCustomQuery'])
                ->middleware('throttle:5,1');  // 5 req/min - apenas admins
        });
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
    // CORREÇÃO BUG #44: Proteger endpoints de quota com autenticação
    Route::prefix('google-maps')->group(function () {
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('quota', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'getUsageStats'])
                ->middleware('throttle:30,1');  // 30 requests per minute
            Route::post('reset-counters', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'resetCounters'])
                ->middleware('throttle:5,1');  // 5 requests per minute (admin operation)
        });
    });
    
    // Rotas para cache de rotas
    Route::prefix('route-cache')->group(function () {
        Route::post('find', [\App\Http\Controllers\Api\RouteCacheController::class, 'findRoute']);
        Route::post('save', [\App\Http\Controllers\Api\RouteCacheController::class, 'saveRoute']);
        Route::get('stats', [\App\Http\Controllers\Api\RouteCacheController::class, 'getStats']);

        // CORREÇÃO BUG #50: Endpoint administrativo requer autenticação
        Route::delete('clear-expired', [\App\Http\Controllers\Api\RouteCacheController::class, 'clearExpired'])
            ->middleware('auth:sanctum');
    });

    // Rotas para gestão de rotas SemParar
    Route::prefix('semparar-rotas')->group(function () {
        // Rotas específicas primeiro para evitar conflitos (públicas)
        Route::get('municipios', [SemPararRotaController::class, 'municipios']);
        Route::get('estados', [SemPararRotaController::class, 'estados']);

        // Rotas GET (públicas)
        Route::get('/', [SemPararRotaController::class, 'index']);
        Route::get('/{id}', [SemPararRotaController::class, 'show']);
        Route::get('/{id}/municipios', [SemPararRotaController::class, 'showWithMunicipios']);

        // Rotas de modificação (protegidas - requerem autenticação de admin)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/', [SemPararRotaController::class, 'store']);
            Route::put('/{id}', [SemPararRotaController::class, 'update']);
            Route::put('/{id}/municipios', [SemPararRotaController::class, 'updateMunicipios']);
            Route::delete('/{id}', [SemPararRotaController::class, 'destroy']);
        });
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
        // CORREÇÃO BUG #43: Rate limiting aplicado corretamente
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
        // CORREÇÃO BUG #56: Rate limiting adequado para prevenir abuse
        Route::post('route', [MapController::class, 'calculateRoute'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Batch geocoding
        Route::post('geocode-batch', [MapController::class, 'geocodeBatch'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Point clustering
        Route::post('cluster-points', [MapController::class, 'clusterPoints'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Cache management
        // CORREÇÃO BUG #59: Proteger endpoints de cache com autenticação
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('cache-stats', [MapController::class, 'cacheStats'])
                ->middleware('throttle:30,1');  // 30 requests per minute
            Route::post('clear-expired-cache', [MapController::class, 'clearExpiredCache'])
                ->middleware('throttle:5,1');   // 5 requests per minute (admin operation)
        });

        // Provider information
        Route::get('providers', [MapController::class, 'providers'])
            ->middleware('throttle:30,1');  // 30 requests per minute
    });

    // Proxy OSRM (roteamento gratuito)
    // CORREÇÃO BUG #52: Rate limiting para prevenir abuse
    Route::post('osrm/route', [OSRMController::class, 'getRoute'])
        ->middleware('throttle:60,1');  // 60 requests per minute

    // Rotas PÚBLICAS para SemParar SOAP API (FASE 1A + 1B - consultas/simulações)
    Route::prefix('semparar')->group(function () {
        // FASE 1A - Core (públicas para teste e monitoramento)
        Route::get('test-connection', [SemPararController::class, 'testConnection'])
            ->middleware('throttle:10,1');  // 10 requests per minute
        Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // FASE 1B - Routing (públicas para simulação)
        Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
            ->middleware('throttle:20,1');  // 20 requests per minute
        Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
            ->middleware('throttle:20,1');  // 20 requests per minute
        Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Debug endpoints (only available in APP_DEBUG=true, protected in controller)
        Route::get('debug/token', [SemPararController::class, 'debugToken']);
        Route::post('debug/clear-cache', [SemPararController::class, 'clearCache']);
    });

    // Rotas PÚBLICAS para SemParar SOAP API (FASE 2A + 2C + 3A)
    // NOTA: Rotas públicas pois Progress database não possui segurança user-level
    // Rate limiting já protege contra DoS e abuse de operações críticas
    // Se autenticação for necessária no futuro, atualizar frontend primeiro
    Route::prefix('semparar')->group(function () {
        // FASE 2A - Purchase (CRÍTICO - operação financeira, rate limit 10/min)
        // CORREÇÃO BUG #15: Rate limiting aplicado corretamente
        Route::post('comprar-viagem', [SemPararController::class, 'comprarViagem'])
            ->middleware('throttle:10,1');  // 10 requests per minute (sensitive operation)

        // FASE 2C - Receipt (CRÍTICO - dados sensíveis + envio WhatsApp)
        // CORREÇÃO BUG #15: Rate limiting aplicado corretamente
        Route::post('obter-recibo', [SemPararController::class, 'obterRecibo'])
            ->middleware('throttle:60,1');  // 60 requests per minute
        Route::post('gerar-recibo', [SemPararController::class, 'gerarRecibo'])
            ->middleware('throttle:10,1');  // 10 requests per minute (sends WhatsApp/Email - sensitive)

        // FASE 3A - Query & Management (CRÍTICO - dados sensíveis + operações irreversíveis)
        Route::post('consultar-viagens', [SemPararController::class, 'consultarViagens'])
            ->middleware('throttle:60,1');  // 60 requests per minute
        Route::post('cancelar-viagem', [SemPararController::class, 'cancelarViagem'])
            ->middleware('throttle:20,1');  // 20 requests per minute (cancels trip)
        Route::post('reemitir-viagem', [SemPararController::class, 'reemitirViagem'])
            ->middleware('throttle:20,1');  // 20 requests per minute (reissues trip)
    });

    // Rotas PÚBLICAS para NDD Cargo API (Roteirizador e Vale Pedágio)
    // Integração com protocolo CrossTalk sobre SOAP 1.1
    // @see docs/integracoes/ndd-cargo/README.md
    Route::prefix('ndd-cargo')->group(function () {
        // Info e health check (públicos)
        Route::get('info', [NddCargoController::class, 'info'])
            ->middleware('throttle:120,1');  // 120 requests per minute
        Route::get('test-connection', [NddCargoController::class, 'testConnection'])
            ->middleware('throttle:5,1');  // 5 requests per minute (proteção contra abuso)

        // Consultas de roteirizador (públicas para simulação)
        Route::post('roteirizador', [NddCargoController::class, 'consultarRoteirizador'])
            ->middleware('throttle:60,1');  // 60 requests per minute
        Route::post('rota-simples', [NddCargoController::class, 'consultarRotaSimples'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Consulta de resultado assíncrono
        Route::get('resultado/{guid}', [NddCargoController::class, 'consultarResultado'])
            ->middleware('throttle:60,1');  // 60 requests per minute
    });

    // Rotas PÚBLICAS para VPO (Vale Pedágio Obrigatório) Data Sync
    // Sistema de sincronização Progress → ANTT → Cache Local
    // @see docs/integracoes/ndd-cargo/VPO_DATA_SYNC.md
    Route::prefix('vpo')->group(function () {
        // Health check e info
        Route::get('test-connection', [VpoController::class, 'testConnection'])
            ->middleware('throttle:10,1');  // 10 requests per minute

        // Sincronização de dados (operações de escrita)
        Route::post('sync/transportador', [VpoController::class, 'syncTransportador'])
            ->middleware('throttle:30,1');  // 30 requests per minute (sincronização individual)

        Route::post('sync/batch', [VpoController::class, 'syncBatch'])
            ->middleware('throttle:10,1');  // 10 requests per minute (operação pesada)

        // Consultas ao cache (operações de leitura)
        Route::get('transportadores', [VpoController::class, 'index'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        Route::get('transportadores/{codtrn}', [VpoController::class, 'show'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Atualização de campos faltantes (preenchidos pelo usuário)
        Route::put('transportadores/{codtrn}', [VpoController::class, 'update'])
            ->middleware('throttle:30,1');  // 30 requests per minute

        // Operações de manutenção
        Route::delete('transportadores/{codtrn}', [VpoController::class, 'destroy'])
            ->middleware('throttle:30,1');  // 30 requests per minute (força resync)

        Route::post('transportadores/{codtrn}/recalcular-qualidade', [VpoController::class, 'recalcularQualidade'])
            ->middleware('throttle:30,1');  // 30 requests per minute

        // Estatísticas
        Route::get('statistics', [VpoController::class, 'statistics'])
            ->middleware('throttle:30,1');  // 30 requests per minute
    });

    // Rotas PÚBLICAS para VPO Emissão (Vale Pedágio via NDD Cargo)
    // Sistema de emissão assíncrona de Vale Pedágio com integração NDD Cargo
    // @see docs/integracoes/ndd-cargo/VPO_EMISSAO_WIZARD.md (futuro)
    Route::prefix('vpo/emissao')->group(function () {
        // Rotas específicas ANTES das rotas com parâmetros {uuid}

        // Iniciar emissão
        Route::post('iniciar', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'iniciar'])
            ->middleware('throttle:30,1');  // 30 requests per minute (operação pesada)

        // Validação e preview
        Route::post('validar-pacote', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'validarPacote'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        Route::post('preview-waypoints', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'previewWaypoints'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Listar rotas disponíveis
        Route::get('pacote/{codpac}/rotas', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'rotasDisponiveis'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Estatísticas (ANTES de {uuid} para não ser capturado)
        Route::get('statistics', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'statistics'])
            ->middleware('throttle:30,1');  // 30 requests per minute

        // Histórico de emissões
        Route::get('/', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'index'])
            ->middleware('throttle:60,1');  // 60 requests per minute

        // Rotas com parâmetros {uuid} - DEVEM VIR POR ÚLTIMO

        // Consultar resultado (polling)
        Route::get('{uuid}', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'consultar'])
            ->middleware('throttle:120,1');  // 120 requests per minute (polling frequente)

        // Cancelar emissão
        Route::post('{uuid}/cancelar', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'cancelar'])
            ->middleware('throttle:30,1');  // 30 requests per minute
    });

    // ⚠️ Compra de Viagem SemParar - MODO DE TESTE ATIVO ⚠️
    // IMPORTANTE: Todas as chamadas estão em modo simulação para evitar compras acidentais

    // ENDPOINTS PÚBLICOS (sem autenticação necessária)
    // CORREÇÃO #4: Rate limiting para prevenir DoS
    Route::prefix('compra-viagem')->group(function () {
        Route::get('initialize', [CompraViagemController::class, 'initialize'])
            ->middleware('throttle:120,1');  // 120 req/min - informações leves
        Route::get('health', [CompraViagemController::class, 'health'])
            ->middleware('throttle:120,1');  // 120 req/min - health check
    });

    // ENDPOINTS PÚBLICOS (consistente com pacotes/transportes)
    // NOTA: Rotas públicas pois Progress database não possui segurança user-level
    // Rate limiting já protege contra DoS e brute force
    // Se autenticação for necessária no futuro, atualizar frontend primeiro
    Route::prefix('compra-viagem')->group(function () {
        // ESTATÍSTICAS (operação cara no Progress)
        Route::get('statistics', [CompraViagemController::class, 'statistics'])
            ->middleware('throttle:10,1');  // 10 req/min - query complexa

        // LISTAGEM: Busca viagens do Progress (tabela PUB.sPararViagem)
        Route::post('viagens', [CompraViagemController::class, 'listarViagens'])
            ->middleware('throttle:60,1');  // 60 req/min - operação padrão

        // FASE 2: Validação de pacote
        Route::post('validar-pacote', [CompraViagemController::class, 'validarPacote'])
            ->middleware('throttle:60,1');  // 60 req/min - operação padrão

        // FASE 3: Validação de placa/veículo
        Route::post('validar-placa', [CompraViagemController::class, 'validarPlaca'])
            ->middleware('throttle:60,1');  // 60 req/min - operação padrão

        // FASE 4: Seleção de rota
        Route::get('rotas', [CompraViagemController::class, 'listarRotas'])
            ->middleware('throttle:60,1');  // 60 req/min - autocomplete
        Route::post('validar-rota', [CompraViagemController::class, 'validarRota'])
            ->middleware('throttle:60,1');  // 60 req/min - validação

        // FASE 5: Verificação de preço (chamada SOAP externa)
        Route::post('verificar-preco', [CompraViagemController::class, 'verificarPreco'])
            ->middleware('throttle:30,1');  // 30 req/min - SOAP call

        // FASE 6: Compra de viagem (CRÍTICO - Operação financeira)
        Route::post('comprar', [CompraViagemController::class, 'comprarViagem'])
            ->middleware('throttle:10,1');  // 10 req/min - previne compras duplicadas

        // DEBUG: Análise completa do fluxo
        Route::post('debug-flow', [\App\Http\Controllers\Api\DebugSemPararController::class, 'debugFlow'])
            ->middleware('throttle:10,1');  // 10 req/min - debug apenas

        // TODO: Adicionar rotas das próximas fases aqui
        // Route::post('gerar-recibo', [CompraViagemController::class, 'gerarRecibo']);
    });
});
