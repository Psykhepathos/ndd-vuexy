<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PasswordResetRequestController;
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
use App\Http\Controllers\Api\MotoristaEmpresaController;
use App\Http\Controllers\Api\VeiculoCacheController;
use Illuminate\Support\Facades\Route;

// Rotas de autenticação (públicas) com rate limiting por IP
// Nota: Aumentado para 10/min pois escritórios compartilham IP
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1'); // 10 tentativas por minuto por IP
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1'); // 5 registros por minuto por IP

// Rotas públicas para primeiro acesso e reset de senha
Route::post('auth/setup-password', [AuthController::class, 'setupPassword'])
    ->middleware('throttle:10,1'); // 10 tentativas por minuto por IP
Route::post('auth/verify-setup-token', [AuthController::class, 'verifySetupToken'])
    ->middleware('throttle:20,1'); // 20 verificações por minuto por IP
Route::post('password-reset-request', [PasswordResetRequestController::class, 'requestReset'])
    ->middleware('throttle:5,1'); // 5 solicitações por minuto por IP

// Rotas protegidas por autenticação
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // CORREÇÃO #6: Rate limiting para prevenir token enumeration
    Route::get('auth/user', [AuthController::class, 'user'])
        ->middleware('throttle:60,1');  // 60 req/min

    // Rotas de gerenciamento de usuários (com verificação de permissões)
    Route::prefix('users')->group(function () {
        Route::get('statistics', [UserController::class, 'statistics'])
            ->middleware(['throttle:30,1', 'permission:usuarios.view']);
        Route::get('roles', [UserController::class, 'roles'])
            ->middleware(['throttle:60,1', 'permission:usuarios.view']);
        Route::get('permissions', [UserController::class, 'permissions'])
            ->middleware(['throttle:60,1', 'permission:perfis.view']);
        Route::get('audit-logs', [UserController::class, 'allAuditLogs'])
            ->middleware(['throttle:30,1', 'permission:auditoria.view']);
        Route::get('/', [UserController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:usuarios.view']);
        Route::post('/', [UserController::class, 'store'])
            ->middleware(['throttle:30,1', 'permission:usuarios.create']);
        Route::get('{user}', [UserController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:usuarios.view']);
        Route::put('{user}', [UserController::class, 'update'])
            ->middleware(['throttle:30,1', 'permission:usuarios.edit']);
        Route::delete('{user}', [UserController::class, 'destroy'])
            ->middleware(['throttle:30,1', 'permission:usuarios.delete']);
        Route::post('{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware(['throttle:10,1', 'permission:usuarios.reset_password']);
        Route::post('{user}/resend-setup-email', [UserController::class, 'resendSetupEmail'])
            ->middleware(['throttle:5,1', 'permission:usuarios.edit']);
        Route::get('{user}/audit-logs', [UserController::class, 'auditLogs'])
            ->middleware(['throttle:30,1', 'permission:auditoria.view']);
    });

    // Rota para troca de senha obrigatória
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:10,1');

    // Rotas para gerenciamento de perfis (roles) - com verificação de permissões
    Route::prefix('roles')->group(function () {
        Route::get('statistics', [RoleController::class, 'statistics'])
            ->middleware(['throttle:30,1', 'permission:perfis.view']);
        Route::get('permissions', [RoleController::class, 'permissions'])
            ->middleware(['throttle:60,1', 'permission:perfis.view']);
        Route::get('/', [RoleController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:perfis.view']);
        Route::post('/', [RoleController::class, 'store'])
            ->middleware(['throttle:30,1', 'permission:perfis.create']);
        Route::get('{role}', [RoleController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:perfis.view']);
        Route::put('{role}', [RoleController::class, 'update'])
            ->middleware(['throttle:30,1', 'permission:perfis.edit']);
        Route::delete('{role}', [RoleController::class, 'destroy'])
            ->middleware(['throttle:30,1', 'permission:perfis.delete']);
        Route::post('{role}/sync-permissions', [RoleController::class, 'syncPermissions'])
            ->middleware(['throttle:30,1', 'permission:perfis.manage_permissions']);
    });

    // Rotas para gerenciamento de solicitações de reset de senha
    Route::prefix('password-reset-requests')->group(function () {
        Route::get('statistics', [PasswordResetRequestController::class, 'statistics'])
            ->middleware(['throttle:30,1', 'permission:usuarios.reset_password']);
        Route::get('/', [PasswordResetRequestController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:usuarios.reset_password']);
        Route::post('{passwordResetRequest}/approve', [PasswordResetRequestController::class, 'approve'])
            ->middleware(['throttle:30,1', 'permission:usuarios.reset_password']);
        Route::post('{passwordResetRequest}/reject', [PasswordResetRequestController::class, 'reject'])
            ->middleware(['throttle:30,1', 'permission:usuarios.reset_password']);
    });
});

Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // Rotas para MotoristaController
    Route::apiResource('motoristas', MotoristaController::class)
        ->middleware('permission:motoristas.view');
    Route::get('motoristas/progress/{codigo}', [MotoristaController::class, 'findByProgressCode'])
        ->middleware('permission:motoristas.view_details');

    // Rotas para ProgressController (JDBC Progress)
    Route::prefix('progress')->group(function () {
        // Test connection - permissão de configurações
        Route::get('test-connection', [ProgressController::class, 'testConnection'])
            ->middleware(['throttle:10,1', 'permission:configuracoes.test_connections']);

        Route::get('transportes', [ProgressController::class, 'getTransportes'])
            ->middleware(['throttle:60,1', 'permission:transportadores.view']);

        Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById'])
            ->middleware(['throttle:60,1', 'permission:transportadores.view_details']);

        // Custom query - APENAS admins (permissão especial)
        Route::post('query', [ProgressController::class, 'executeCustomQuery'])
            ->middleware(['throttle:5,1', 'permission:configuracoes.edit']);
    });

    // Rotas para TransporteController (JDBC Progress)
    Route::get('transportes/test-connection', [TransporteController::class, 'testConnection'])
        ->middleware(['throttle:10,1', 'permission:configuracoes.test_connections']);

    Route::get('transportes/statistics', [TransporteController::class, 'statistics'])
        ->middleware(['throttle:10,1', 'permission:transportadores.view']);
    Route::get('transportes/schema', [TransporteController::class, 'schema'])
        ->middleware(['throttle:10,1', 'permission:configuracoes.view']);

    // CRUD transportadores
    Route::get('transportes', [TransporteController::class, 'index'])
        ->middleware(['throttle:60,1', 'permission:transportadores.view']);
    Route::get('transportes/{id}', [TransporteController::class, 'show'])
        ->middleware(['throttle:60,1', 'permission:transportadores.view_details']);

    // Custom query - admin only
    Route::post('transportes/query', [TransporteController::class, 'query'])
        ->middleware(['throttle:5,1', 'permission:configuracoes.edit']);

    // Rotas para PacoteController (JDBC Progress)
    Route::get('pacotes/statistics', [PacoteController::class, 'statistics'])
        ->middleware(['throttle:30,1', 'permission:pacotes.view']);
    Route::get('pacotes/autocomplete', [PacoteController::class, 'autocomplete'])
        ->middleware(['throttle:60,1', 'permission:pacotes.view']);
    Route::post('pacotes/itinerario', [PacoteController::class, 'itinerario'])
        ->middleware(['throttle:60,1', 'permission:pacotes.view_itinerary']);
    Route::get('pacotes', [PacoteController::class, 'index'])
        ->middleware(['throttle:60,1', 'permission:pacotes.view']);
    Route::get('pacotes/{id}', [PacoteController::class, 'show'])
        ->middleware(['throttle:60,1', 'permission:pacotes.view_details']);

    // Rotas para RotaController (JDBC Progress) - para autocomplete
    Route::get('rotas', [RotaController::class, 'index'])
        ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
    
    // Rotas para proxy de roteamento OSRM (100% gratuito, contorna CORS)
    Route::prefix('routing')->group(function () {
        Route::get('test', [\App\Http\Controllers\Api\RoutingController::class, 'testConnection'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.test_connections']);
        Route::post('route', [\App\Http\Controllers\Api\RoutingController::class, 'getRoute'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
    });

    // Rotas para monitoramento Google Maps
    Route::prefix('google-maps')->group(function () {
        Route::get('quota', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'getUsageStats'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.view']);
        Route::post('reset-counters', [\App\Http\Controllers\Api\GoogleMapsQuotaController::class, 'resetCounters'])
            ->middleware(['throttle:5,1', 'permission:configuracoes.edit']);
    });

    // Rotas para cache de rotas
    Route::prefix('route-cache')->group(function () {
        Route::post('find', [\App\Http\Controllers\Api\RouteCacheController::class, 'findRoute'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::post('save', [\App\Http\Controllers\Api\RouteCacheController::class, 'saveRoute'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.edit']);
        Route::get('stats', [\App\Http\Controllers\Api\RouteCacheController::class, 'getStats'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.view']);
        Route::delete('clear-expired', [\App\Http\Controllers\Api\RouteCacheController::class, 'clearExpired'])
            ->middleware(['throttle:5,1', 'permission:configuracoes.edit']);
    });

    // Rotas para gestão de rotas SemParar (Rotas Padrão)
    Route::prefix('semparar-rotas')->group(function () {
        // Rotas específicas primeiro para evitar conflitos
        Route::get('municipios', [SemPararRotaController::class, 'municipios'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::get('estados', [SemPararRotaController::class, 'estados'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

        // Rotas GET
        Route::get('/', [SemPararRotaController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::get('/{id}', [SemPararRotaController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::get('/{id}/municipios', [SemPararRotaController::class, 'showWithMunicipios'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

        // Rotas de modificação
        Route::post('/', [SemPararRotaController::class, 'store'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.create']);
        Route::put('/{id}', [SemPararRotaController::class, 'update'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.edit']);
        Route::put('/{id}/municipios', [SemPararRotaController::class, 'updateMunicipios'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.manage_municipios']);
        Route::delete('/{id}', [SemPararRotaController::class, 'destroy'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.delete']);
    });

    // Rotas para geocoding (conversão IBGE → lat/lon com cache)
    Route::prefix('geocoding')->group(function () {
        Route::post('ibge', [GeocodingController::class, 'getCoordenadasByIbge'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::post('lote', [GeocodingController::class, 'getCoordenadasLote'])
            ->middleware(['throttle:30,1', 'permission:rotas_padrao.view']);
    });

    // Rotas para gestão de praças de pedágio (ANTT)
    Route::prefix('pracas-pedagio')->group(function () {
        // Rotas específicas primeiro para evitar conflitos com /{id}

        // Estatísticas
        Route::get('estatisticas', [PracaPedagioController::class, 'estatisticas'])
            ->middleware(['throttle:30,1', 'permission:pracas_pedagio.view']);

        // Buscar praças próximas a coordenadas
        Route::post('proximidade', [PracaPedagioController::class, 'proximidade'])
            ->middleware(['throttle:60,1', 'permission:pracas_pedagio.view']);

        // Importar CSV - admin only
        Route::post('importar', [PracaPedagioController::class, 'importar'])
            ->middleware(['throttle:5,1', 'permission:pracas_pedagio.import']);

        // Limpar todas as praças - admin only
        Route::delete('limpar', [PracaPedagioController::class, 'limpar'])
            ->middleware(['throttle:2,1', 'permission:configuracoes.edit']);

        // Listagem com filtros e paginação
        Route::get('/', [PracaPedagioController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:pracas_pedagio.view']);

        // Obter praça específica - SEMPRE POR ÚLTIMO
        Route::get('/{id}', [PracaPedagioController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:pracas_pedagio.view']);
    });

    // Rotas para MapService unificado (FASE 1 - Backend Foundation)
    Route::prefix('map')->group(function () {
        // Route calculation with automatic provider selection
        Route::post('route', [MapController::class, 'calculateRoute'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

        // Batch geocoding
        Route::post('geocode-batch', [MapController::class, 'geocodeBatch'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

        // Point clustering
        Route::post('cluster-points', [MapController::class, 'clusterPoints'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

        // Cache management
        Route::get('cache-stats', [MapController::class, 'cacheStats'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.view']);
        Route::post('clear-expired-cache', [MapController::class, 'clearExpiredCache'])
            ->middleware(['throttle:5,1', 'permission:configuracoes.edit']);

        // Provider information
        Route::get('providers', [MapController::class, 'providers'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.view']);
    });

    // Proxy OSRM (roteamento gratuito)
    Route::post('osrm/route', [OSRMController::class, 'getRoute'])
        ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);

    // Rotas para SemParar SOAP API (FASE 1A + 1B - consultas/simulações)
    Route::prefix('semparar')->group(function () {
        // FASE 1A - Core
        Route::get('test-connection', [SemPararController::class, 'testConnection'])
            ->middleware(['throttle:10,1', 'permission:configuracoes.test_connections']);
        Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
            ->middleware(['throttle:60,1', 'permission:veiculos.validate_semparar']);

        // FASE 1B - Routing
        Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
            ->middleware(['throttle:20,1', 'permission:compra_viagem.view']);
        Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
            ->middleware(['throttle:20,1', 'permission:compra_viagem.create']);
        Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view']);

        // Debug endpoints (only available in APP_DEBUG=true, protected in controller)
        Route::get('debug/token', [SemPararController::class, 'debugToken'])
            ->middleware('permission:configuracoes.edit');
        Route::post('debug/clear-cache', [SemPararController::class, 'clearCache'])
            ->middleware('permission:configuracoes.edit');

        // FASE 2A - Purchase (CRÍTICO - operação financeira)
        Route::post('comprar-viagem', [SemPararController::class, 'comprarViagem'])
            ->middleware(['throttle:10,1', 'permission:compra_viagem.create']);

        // FASE 2C - Receipt
        Route::post('obter-recibo', [SemPararController::class, 'obterRecibo'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view_receipt']);
        Route::post('gerar-recibo', [SemPararController::class, 'gerarRecibo'])
            ->middleware(['throttle:10,1', 'permission:compra_viagem.generate_pdf']);

        // FASE 3A - Query & Management
        Route::post('consultar-viagens', [SemPararController::class, 'consultarViagens'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view']);
        Route::post('cancelar-viagem', [SemPararController::class, 'cancelarViagem'])
            ->middleware(['throttle:20,1', 'permission:compra_viagem.cancel']);
        Route::post('reemitir-viagem', [SemPararController::class, 'reemitirViagem'])
            ->middleware(['throttle:20,1', 'permission:compra_viagem.create']);
    });

    // Rotas para NDD Cargo API (Roteirizador e Vale Pedágio)
    // Integração com protocolo CrossTalk sobre SOAP 1.1
    // @see docs/integracoes/ndd-cargo/README.md
    Route::prefix('ndd-cargo')->group(function () {
        // Info e health check
        Route::get('info', [NddCargoController::class, 'info'])
            ->middleware(['throttle:120,1', 'permission:vpo_emissao.view']);
        Route::get('test-connection', [NddCargoController::class, 'testConnection'])
            ->middleware(['throttle:5,1', 'permission:configuracoes.test_connections']);

        // Consultas de roteirizador
        Route::post('roteirizador', [NddCargoController::class, 'consultarRoteirizador'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.validate']);
        Route::post('rota-simples', [NddCargoController::class, 'consultarRotaSimples'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.validate']);

        // Consulta de resultado assíncrono
        Route::get('resultado/{guid}', [NddCargoController::class, 'consultarResultado'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.view']);
    });

    // Rotas para VPO (Vale Pedágio Obrigatório) Data Sync
    // Sistema de sincronização Progress → ANTT → Cache Local
    // @see docs/integracoes/ndd-cargo/VPO_DATA_SYNC.md
    Route::prefix('vpo')->group(function () {
        // Health check e info
        Route::get('test-connection', [VpoController::class, 'testConnection'])
            ->middleware(['throttle:10,1', 'permission:configuracoes.test_connections']);

        // Sincronização de dados (operações de escrita)
        Route::post('sync/transportador', [VpoController::class, 'syncTransportador'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.validate']);

        Route::post('sync/batch', [VpoController::class, 'syncBatch'])
            ->middleware(['throttle:10,1', 'permission:vpo_emissao.validate']);

        // Consultas ao cache (operações de leitura)
        Route::get('transportadores', [VpoController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.view']);

        Route::get('transportadores/{codtrn}', [VpoController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.view']);

        // Atualização de campos faltantes (preenchidos pelo usuário)
        Route::put('transportadores/{codtrn}', [VpoController::class, 'update'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.validate']);

        // Operações de manutenção
        Route::delete('transportadores/{codtrn}', [VpoController::class, 'destroy'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.edit']);

        Route::post('transportadores/{codtrn}/recalcular-qualidade', [VpoController::class, 'recalcularQualidade'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.validate']);

        // Estatísticas
        Route::get('statistics', [VpoController::class, 'statistics'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.view']);

        // Calcular praças de pedágio para rota (IBGE → CEP → NDD Cargo)
        Route::post('calcular-pracas', [VpoController::class, 'calcularPracas'])
            ->middleware(['throttle:20,1', 'permission:vpo_emissao.validate']);

        // Logs de Emissão VPO (Auditoria)
        Route::prefix('emissao/logs')->group(function () {
            // Estatísticas (ANTES de rotas com parâmetros)
            Route::get('statistics', [VpoController::class, 'estatisticasLogs'])
                ->middleware(['throttle:60,1', 'permission:auditoria.view']);

            // Buscar por UUID
            Route::get('uuid/{uuid}', [VpoController::class, 'buscarLogPorUuid'])
                ->middleware(['throttle:60,1', 'permission:auditoria.view']);

            // Listar logs com filtros
            Route::get('/', [VpoController::class, 'listarLogs'])
                ->middleware(['throttle:60,1', 'permission:auditoria.view']);

            // Detalhe de um log
            Route::get('{id}', [VpoController::class, 'detalheLog'])
                ->middleware(['throttle:60,1', 'permission:auditoria.view']);
        });

        // Iniciar emissão VPO (com log)
        Route::post('emissao/iniciar', [VpoController::class, 'iniciarEmissao'])
            ->middleware(['throttle:20,1', 'permission:vpo_emissao.create']);

        // Motoristas de Empresas (CNPJ) - Cache para dados complementares VPO
        // @see docs/integracoes/ndd-cargo/MOTORISTA_EMPRESA_CACHE.md (futuro)
        Route::prefix('motoristas')->group(function () {
            // Verificar se transportador é empresa e tem motoristas
            Route::get('{codtrn}/verificar', [MotoristaEmpresaController::class, 'verificar'])
                ->middleware(['throttle:60,1', 'permission:motoristas.view']);

            // Listar motoristas completos (prontos para VPO)
            Route::get('{codtrn}/completos', [MotoristaEmpresaController::class, 'completos'])
                ->middleware(['throttle:60,1', 'permission:motoristas.view']);

            // Listar todos motoristas de um transportador
            Route::get('{codtrn}', [MotoristaEmpresaController::class, 'index'])
                ->middleware(['throttle:60,1', 'permission:motoristas.view']);

            // Buscar motorista específico
            Route::get('{codtrn}/{codmot}', [MotoristaEmpresaController::class, 'show'])
                ->middleware(['throttle:60,1', 'permission:motoristas.view_details']);

            // Salvar/atualizar dados de motorista
            Route::post('{codtrn}/{codmot}', [MotoristaEmpresaController::class, 'store'])
                ->middleware(['throttle:30,1', 'permission:vpo_emissao.validate']);
        });
    });

    // Rotas para VPO Emissão (Vale Pedágio via NDD Cargo)
    // Sistema de emissão assíncrona de Vale Pedágio com integração NDD Cargo
    // @see docs/integracoes/ndd-cargo/VPO_EMISSAO_WIZARD.md (futuro)
    Route::prefix('vpo/emissao')->group(function () {
        // Rotas específicas ANTES das rotas com parâmetros {uuid}

        // Iniciar emissão
        Route::post('iniciar', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'iniciar'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.create']);

        // Validação e preview
        Route::post('validar-pacote', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'validarPacote'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.validate']);

        Route::post('preview-waypoints', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'previewWaypoints'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.validate']);

        // Listar rotas disponíveis
        Route::get('pacote/{codpac}/rotas', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'rotasDisponiveis'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.view']);

        // Estatísticas (ANTES de {uuid} para não ser capturado)
        Route::get('statistics', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'statistics'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.view']);

        // Histórico de emissões
        Route::get('/', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:vpo_emissao.view']);

        // Rotas com parâmetros {uuid} - DEVEM VIR POR ÚLTIMO

        // Consultar resultado (polling)
        Route::get('{uuid}', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'consultar'])
            ->middleware(['throttle:120,1', 'permission:vpo_emissao.view']);

        // Cancelar emissão
        Route::post('{uuid}/cancelar', [\App\Http\Controllers\Api\VpoEmissaoController::class, 'cancelar'])
            ->middleware(['throttle:30,1', 'permission:vpo_emissao.create']);
    });

    // Compra de Viagem SemParar
    Route::prefix('compra-viagem')->group(function () {
        // Inicialização e health check
        Route::get('initialize', [CompraViagemController::class, 'initialize'])
            ->middleware(['throttle:120,1', 'permission:compra_viagem.view']);
        Route::get('health', [CompraViagemController::class, 'health'])
            ->middleware(['throttle:120,1', 'permission:configuracoes.test_connections']);

        // Estatísticas
        Route::get('statistics', [CompraViagemController::class, 'statistics'])
            ->middleware(['throttle:10,1', 'permission:compra_viagem.view']);

        // Listagem de viagens
        Route::post('viagens', [CompraViagemController::class, 'listarViagens'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view']);

        // Validação de pacote
        Route::post('validar-pacote', [CompraViagemController::class, 'validarPacote'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view']);

        // Validação de placa/veículo
        Route::post('validar-placa', [CompraViagemController::class, 'validarPlaca'])
            ->middleware(['throttle:60,1', 'permission:veiculos.validate_semparar']);

        // Seleção de rota
        Route::get('rotas', [CompraViagemController::class, 'listarRotas'])
            ->middleware(['throttle:60,1', 'permission:rotas_padrao.view']);
        Route::post('validar-rota', [CompraViagemController::class, 'validarRota'])
            ->middleware(['throttle:60,1', 'permission:compra_viagem.view']);

        // Verificação de preço (chamada SOAP externa)
        Route::post('verificar-preco', [CompraViagemController::class, 'verificarPreco'])
            ->middleware(['throttle:30,1', 'permission:compra_viagem.view']);

        // Compra de viagem (CRÍTICO - Operação financeira)
        Route::post('comprar', [CompraViagemController::class, 'comprarViagem'])
            ->middleware(['throttle:10,1', 'permission:compra_viagem.create']);

        // Debug: Análise completa do fluxo
        Route::post('debug-flow', [\App\Http\Controllers\Api\DebugSemPararController::class, 'debugFlow'])
            ->middleware(['throttle:10,1', 'permission:configuracoes.edit']);
    });

    // Rotas para cache de veículos SemParar
    // Permite reutilização de dados de veículos validados e edição pelo usuário
    Route::prefix('veiculos-cache')->group(function () {
        // Listar veículos com filtros
        Route::get('/', [VeiculoCacheController::class, 'index'])
            ->middleware(['throttle:60,1', 'permission:veiculos.view']);

        // Buscar veículos de um transportador
        Route::get('transportador/{codtrn}', [VeiculoCacheController::class, 'byTransportador'])
            ->middleware(['throttle:60,1', 'permission:veiculos.view']);

        // Buscar veículo por placa (usa parâmetro, então deve vir depois de transportador)
        Route::get('{placa}', [VeiculoCacheController::class, 'show'])
            ->middleware(['throttle:60,1', 'permission:veiculos.view_details']);

        // Criar/Atualizar veículo manualmente
        Route::post('/', [VeiculoCacheController::class, 'store'])
            ->middleware(['throttle:30,1', 'permission:veiculos.validate_semparar']);

        // Atualizar veículo existente
        Route::put('{id}', [VeiculoCacheController::class, 'update'])
            ->middleware(['throttle:30,1', 'permission:veiculos.validate_semparar']);

        // Remover do cache
        Route::delete('{id}', [VeiculoCacheController::class, 'destroy'])
            ->middleware(['throttle:30,1', 'permission:configuracoes.edit']);

        // Revalidar veículo no SemParar
        Route::post('{id}/revalidar', [VeiculoCacheController::class, 'revalidar'])
            ->middleware(['throttle:30,1', 'permission:veiculos.validate_semparar']);

        // Vincular veículo a transportador
        Route::post('{id}/vincular', [VeiculoCacheController::class, 'vincular'])
            ->middleware(['throttle:30,1', 'permission:veiculos.validate_semparar']);
    });
});
