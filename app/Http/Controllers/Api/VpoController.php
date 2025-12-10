<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Vpo\VpoDataSyncService;
use App\Services\Vpo\VpoEmissaoLogService;
use App\Services\GeocodingService;
use App\Services\NddCargo\NddCargoService;
use App\Services\NddCargo\DTOs\ConsultarRoteirizadorRequest;
use App\Models\VpoTransportadorCache;
use App\Models\VpoEmissaoLog;
use App\Models\PracaPedagio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VpoController extends Controller
{
    protected VpoDataSyncService $syncService;
    protected VpoEmissaoLogService $logService;
    protected GeocodingService $geocodingService;
    protected NddCargoService $nddCargoService;

    public function __construct(
        VpoDataSyncService $syncService,
        VpoEmissaoLogService $logService,
        GeocodingService $geocodingService,
        NddCargoService $nddCargoService
    ) {
        $this->syncService = $syncService;
        $this->logService = $logService;
        $this->geocodingService = $geocodingService;
        $this->nddCargoService = $nddCargoService;
    }

    /**
     * GET /api/vpo/test-connection
     */
    public function testConnection(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'services' => [
                'progress' => true,
                'antt_opendata' => true,
                'database_local' => VpoTransportadorCache::count() . ' registros em cache'
            ],
            'message' => 'Todos os serviços operacionais'
        ]);
    }

    /**
     * POST /api/vpo/sync/transportador
     */
    public function syncTransportador(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codtrn' => 'required|integer',
            'codmot' => 'nullable|integer',
            'placa' => 'nullable|string|max:10',
            'force_antt_update' => 'boolean'
        ]);

        $result = $this->syncService->syncTransportador(
            $validated['codtrn'],
            $validated['codmot'] ?? null,
            $validated['placa'] ?? null,
            $validated['force_antt_update'] ?? false
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/vpo/sync/batch
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codtrn_list' => 'required|array|min:1|max:100',
            'codtrn_list.*' => 'integer',
            'force_antt_update' => 'boolean'
        ]);

        $result = $this->syncService->syncBatch(
            $validated['codtrn_list'],
            $validated['force_antt_update'] ?? false
        );

        return response()->json($result);
    }

    /**
     * GET /api/vpo/transportadores
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $query = VpoTransportadorCache::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('antt_nome', 'like', "%{$search}%")
                  ->orWhere('antt_rntrc', 'like', "%{$search}%")
                  ->orWhere('placa', 'like', "%{$search}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('antt_status', $status);
        }

        if ($qualidadeMinima = $request->get('qualidade_minima')) {
            $query->qualidadeMinima((int) $qualidadeMinima);
        }

        if ($request->boolean('apenas_validos')) {
            $query->rntrcValido();
        }

        $query->orderByDesc('score_qualidade')
              ->orderByDesc('ultima_sync_progress');

        $transportadores = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transportadores->items(),
            'pagination' => [
                'total' => $transportadores->total(),
                'per_page' => $transportadores->perPage(),
                'current_page' => $transportadores->currentPage(),
                'last_page' => $transportadores->lastPage(),
            ]
        ]);
    }

    /**
     * GET /api/vpo/transportadores/{codtrn}
     */
    public function show(int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache. Execute sincronização primeiro.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transportador,
            'vpo_data' => $transportador->toVpoArray(),
            'meta' => [
                'needs_update' => $transportador->isStale(),
                'rntrc_valido' => $transportador->isRntrcValido(),
                'needs_antt_update' => $transportador->needsAnttUpdate()
            ]
        ]);
    }

    /**
     * GET /api/vpo/statistics
     */
    public function statistics(): JsonResponse
    {
        $total = VpoTransportadorCache::count();
        $ativos = VpoTransportadorCache::ativos()->count();
        $rntrcValidos = VpoTransportadorCache::rntrcValido()->count();
        $qualidadeAlta = VpoTransportadorCache::qualidadeMinima(80)->count();

        $avgQualidade = VpoTransportadorCache::avg('score_qualidade');

        $porStatus = VpoTransportadorCache::select('antt_status', DB::raw('COUNT(*) as total'))
            ->groupBy('antt_status')
            ->get()
            ->pluck('total', 'antt_status');

        $porFonte = VpoTransportadorCache::select('antt_fonte', DB::raw('COUNT(*) as total'))
            ->whereNotNull('antt_fonte')
            ->groupBy('antt_fonte')
            ->get()
            ->pluck('total', 'antt_fonte');

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $total,
                'ativos' => $ativos,
                'rntrc_validos' => $rntrcValidos,
                'qualidade_alta' => $qualidadeAlta,
                'qualidade_media' => round($avgQualidade, 2),
                'por_status' => $porStatus,
                'por_fonte_antt' => $porFonte,
            ]
        ]);
    }

    /**
     * DELETE /api/vpo/transportadores/{codtrn}
     */
    public function destroy(int $codtrn): JsonResponse
    {
        $deleted = VpoTransportadorCache::byCodtrn($codtrn)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transportador removido do cache'
        ]);
    }

    /**
     * POST /api/vpo/transportadores/{codtrn}/recalcular-qualidade
     */
    public function recalcularQualidade(int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado'
            ], 404);
        }

        $score = $transportador->calculateQualityScore();

        return response()->json([
            'success' => true,
            'score' => $score,
            'campos_faltantes' => $transportador->campos_faltantes,
            'avisos' => $transportador->avisos
        ]);
    }

    /**
     * POST /api/vpo/calcular-pracas
     *
     * Calcula praças de pedágio para uma lista de municípios usando NDD Cargo.
     * Converte municípios (IBGE) → CEPs (ViaCEP) → NDD Cargo roteirizador.
     *
     * Body:
     * {
     *   "municipios": [
     *     {"desMun": "SAO PAULO", "desEst": "SP"},
     *     {"desMun": "RIO DE JANEIRO", "desEst": "RJ"}
     *   ],
     *   "categoria_pedagio": 7,
     *   "tipo_veiculo": 5
     * }
     */
    public function calcularPracas(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'municipios' => 'required|array|min:2|max:50',
                'municipios.*.desMun' => 'required|string|max:100',
                'municipios.*.desEst' => 'required|string|max:2',
                'categoria_pedagio' => 'nullable|integer|min:1|max:7',
                'tipo_veiculo' => 'nullable|integer|min:1|max:4', // 1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator
            ]);

            $municipios = $validated['municipios'];
            $categoriaPedagio = $validated['categoria_pedagio'] ?? 7;
            // tipoVeiculo: 1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator (NÃO usar 5!)
            $tipoVeiculo = $validated['tipo_veiculo'] ?? 2;

            Log::info('calcularPracas: Iniciando cálculo', [
                'municipios_count' => count($municipios),
                'categoria_pedagio' => $categoriaPedagio
            ]);

            // 1. Converter municípios para CEPs via ViaCEP
            $ceps = [];
            $cepsNaoEncontrados = [];

            foreach ($municipios as $index => $mun) {
                $nome = $mun['desMun'];
                $uf = strtoupper($mun['desEst']);

                $cep = $this->geocodingService->getCepByMunicipio($nome, $uf);

                if ($cep && strlen($cep) === 8) {
                    $ceps[] = [
                        'municipio' => $nome,
                        'uf' => $uf,
                        'cep' => $cep,
                        'index' => $index
                    ];
                    Log::info("CEP encontrado: {$nome}/{$uf} → {$cep}");
                } else {
                    $cepsNaoEncontrados[] = "{$nome}/{$uf}";
                    Log::warning("CEP não encontrado: {$nome}/{$uf}");
                }
            }

            // Verificar se temos pelo menos origem e destino
            if (count($ceps) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível encontrar CEPs suficientes para a rota',
                    'ceps_encontrados' => count($ceps),
                    'ceps_nao_encontrados' => $cepsNaoEncontrados,
                    'detalhe' => 'São necessários pelo menos 2 municípios com CEP válido'
                ], 400);
            }

            // 2. Montar request para NDD Cargo
            $cepOrigem = $ceps[0]['cep'];
            $cepDestino = $ceps[count($ceps) - 1]['cep'];

            Log::info('calcularPracas: Chamando NDD Cargo', [
                'cep_origem' => $cepOrigem,
                'cep_destino' => $cepDestino,
                'ceps_intermediarios' => count($ceps) - 2
            ]);

            // 3. Chamar NDD Cargo roteirizador
            $response = $this->nddCargoService->consultarRotaSimples(
                cepOrigem: $cepOrigem,
                cepDestino: $cepDestino,
                categoriaPedagio: $categoriaPedagio
            );

            // 4. Processar resposta
            if ($response->sucesso) {
                // Converter array de PracaPedagioDTO para arrays simples
                $pracasArray = array_map(
                    fn($praca) => $praca->toArray(),
                    $response->pracasPedagio
                );

                // 4.1. Enriquecer praças com coordenadas da tabela pracas_pedagio
                $pracasEnriquecidas = $this->enriquecerPracasComCoordenadas($pracasArray);

                Log::info('calcularPracas: Sucesso!', [
                    'pracas_count' => count($response->pracasPedagio),
                    'pracas_com_coordenadas' => count(array_filter($pracasEnriquecidas, fn($p) => isset($p['lat']) && $p['lat']))
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'pracas' => $pracasEnriquecidas,
                        'valor_total' => $response->valorTotalPedagios ?? 0,
                        'distancia_km' => $response->distanciaKm ?? 0,
                        'tempo_estimado_min' => $response->tempoMinutos ?? 0,
                        'ceps_utilizados' => [
                            'origem' => $cepOrigem,
                            'destino' => $cepDestino,
                        ],
                        'municipios_processados' => count($ceps),
                        'ceps_nao_encontrados' => $cepsNaoEncontrados,
                    ]
                ]);
            } else {
                // Se retornou 202 (processamento assíncrono)
                if ($response->status === 202 && $response->guid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Processamento em andamento',
                        'status' => 202,
                        'guid' => $response->guid,
                        'consultar_em' => url("/api/ndd-cargo/resultado/{$response->guid}"),
                        'ceps_utilizados' => [
                            'origem' => $cepOrigem,
                            'destino' => $cepDestino,
                        ]
                    ], 202);
                }

                Log::error('calcularPracas: Erro NDD Cargo', [
                    'mensagem' => $response->mensagem,
                    'status' => $response->status
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem ?? 'Erro ao calcular rota',
                    'status' => $response->status,
                    'ceps_utilizados' => [
                        'origem' => $cepOrigem,
                        'destino' => $cepDestino,
                    ]
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('calcularPracas: Erro interno', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao calcular praças de pedágio',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * PUT /api/vpo/transportadores/{codtrn}
     * Atualiza campos faltantes do cache VPO (preenchidos pelo usuário)
     */
    public function update(Request $request, int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache.'
            ], 404);
        }

        $camposEditaveis = [
            'antt_rntrc', 'antt_validade', 'antt_status',
            'placa', 'veiculo_tipo', 'veiculo_modelo',
            'condutor_rg', 'condutor_nome', 'condutor_sexo', 'condutor_nome_mae', 'condutor_data_nascimento',
            'endereco_rua', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_estado', 'endereco_cep',
            'contato_telefone', 'contato_celular', 'contato_email',
        ];

        $validated = $request->validate([
            'antt_rntrc' => 'nullable|string|max:20',
            'antt_validade' => 'nullable|date',
            'antt_status' => 'nullable|string|max:50',
            'placa' => 'nullable|string|max:10',
            'veiculo_tipo' => 'nullable|string|max:50',
            'veiculo_modelo' => 'nullable|string|max:100',
            'condutor_rg' => 'nullable|string|max:20',
            'condutor_nome' => 'nullable|string|max:200',
            'condutor_sexo' => 'nullable|string|max:1|in:M,F',
            'condutor_nome_mae' => 'nullable|string|max:200',
            'condutor_data_nascimento' => 'nullable|date',
            'endereco_rua' => 'nullable|string|max:200',
            'endereco_numero' => 'nullable|string|max:20',
            'endereco_bairro' => 'nullable|string|max:100',
            'endereco_cidade' => 'nullable|string|max:100',
            'endereco_estado' => 'nullable|string|max:2',
            'endereco_cep' => 'nullable|string|max:10',
            'contato_telefone' => 'nullable|string|max:20',
            'contato_celular' => 'nullable|string|max:20',
            'contato_email' => 'nullable|email|max:200',
        ]);

        $dadosParaAtualizar = [];
        foreach ($validated as $campo => $valor) {
            if (in_array($campo, $camposEditaveis) && $valor !== null) {
                $dadosParaAtualizar[$campo] = $valor;
            }
        }

        if (empty($dadosParaAtualizar)) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum campo válido para atualizar'
            ], 400);
        }

        $dadosParaAtualizar['editado_manualmente'] = true;
        $dadosParaAtualizar['data_edicao_manual'] = now();

        $transportador->update($dadosParaAtualizar);
        $transportador->refresh();
        $score = $transportador->calculateQualityScore();

        return response()->json([
            'success' => true,
            'message' => 'Dados atualizados com sucesso',
            'data' => [
                'codtrn' => $transportador->codtrn,
                'campos_atualizados' => array_keys($dadosParaAtualizar),
                'score_qualidade' => $score,
                'campos_faltantes' => $transportador->campos_faltantes,
            ]
        ]);
    }

    // =========================================================================
    // LOGS DE EMISSÃO VPO
    // =========================================================================

    /**
     * GET /api/vpo/emissao/logs
     *
     * Lista logs de emissão VPO com paginação e filtros
     *
     * Query params:
     * - page: int (default 1)
     * - per_page: int (default 15, max 100)
     * - status: string (iniciado|calculando|aguardando|sucesso|erro|cancelado)
     * - codtrn: int
     * - codpac: int
     * - placa: string
     * - search: string (busca em transportador, placa, rota, uuid)
     * - data_inicio: string (Y-m-d)
     * - data_fim: string (Y-m-d)
     */
    public function listarLogs(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status',
            'codtrn',
            'codpac',
            'placa',
            'search',
            'data_inicio',
            'data_fim',
        ]);

        $perPage = min($request->input('per_page', 15), 100);

        $logs = $this->logService->listar($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    /**
     * GET /api/vpo/emissao/logs/{id}
     *
     * Retorna detalhes completos de um log de emissão
     */
    public function detalheLog(int $id): JsonResponse
    {
        $log = $this->logService->buscarPorId($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * GET /api/vpo/emissao/logs/uuid/{uuid}
     *
     * Busca log por UUID
     */
    public function buscarLogPorUuid(string $uuid): JsonResponse
    {
        $log = $this->logService->buscarPorUuid($uuid);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * GET /api/vpo/emissao/logs/statistics
     *
     * Retorna estatísticas dos logs de emissão
     *
     * Query params:
     * - data_inicio: string (Y-m-d)
     * - data_fim: string (Y-m-d)
     */
    public function estatisticasLogs(Request $request): JsonResponse
    {
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        $stats = $this->logService->estatisticas($dataInicio, $dataFim);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * POST /api/vpo/emissao/iniciar
     *
     * Inicia uma nova emissão VPO e cria o log
     *
     * Body:
     * {
     *   "codpac": 12345,
     *   "rota_id": 204,
     *   "pracas_pedagio": [...],
     *   "valor_total": 150.00,
     *   "km_total": 500,
     *   "data_inicio": "2025-12-10",
     *   "data_fim": "2025-12-15",
     *   "codmot": null,
     *   "placa": "ABC1234",
     *   "eixos": 6
     * }
     */
    public function iniciarEmissao(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codpac' => 'required|integer',
                'codtrn' => 'required|integer',
                'rota_id' => 'nullable|integer',
                'pracas_pedagio' => 'nullable|array',
                'valor_total' => 'nullable|numeric',
                'km_total' => 'nullable|numeric',
                'data_inicio' => 'nullable|date',
                'data_fim' => 'nullable|date',
                'codmot' => 'nullable|integer',
                'placa' => 'nullable|string|max:10',
                'eixos' => 'nullable|integer|min:2|max:9',
                // Dados completos do formulário
                'transportador_nome' => 'nullable|string',
                'transportador_cpf_cnpj' => 'nullable|string',
                'transportador_autonomo' => 'nullable|boolean',
                'transportador_rntrc' => 'nullable|string',
                'motorista_nome' => 'nullable|string',
                'motorista_cpf' => 'nullable|string',
                'veiculo_modelo' => 'nullable|string',
                'rota_nome' => 'nullable|string',
                'rota_municipios' => 'nullable|array',
                // NDD Cargo data
                'roteirizador_guid' => 'nullable|string',
                'roteirizador_request' => 'nullable|array',
                'roteirizador_response' => 'nullable|array',
                'emissao_guid' => 'nullable|string',
                'emissao_request' => 'nullable|array',
                'emissao_response' => 'nullable|array',
                'ndd_codigo_retorno' => 'nullable|string',
                'ndd_mensagem_retorno' => 'nullable|string',
                'ndd_protocolo' => 'nullable|string',
            ]);

            // Preparar dados do log
            $eixos = $validated['eixos'] ?? 6;
            $logData = [
                'codpac' => $validated['codpac'],
                'codtrn' => $validated['codtrn'],
                'transportador_nome' => $validated['transportador_nome'] ?? null,
                'transportador_cpf_cnpj' => $validated['transportador_cpf_cnpj'] ?? null,
                'transportador_autonomo' => $validated['transportador_autonomo'] ?? false,
                'transportador_rntrc' => $validated['transportador_rntrc'] ?? null,
                'codmot' => $validated['codmot'] ?? null,
                'motorista_nome' => $validated['motorista_nome'] ?? null,
                'motorista_cpf' => $validated['motorista_cpf'] ?? null,
                'veiculo_placa' => $validated['placa'] ?? null,
                'veiculo_modelo' => $validated['veiculo_modelo'] ?? null,
                'veiculo_eixos' => $eixos,
                'categoria_pedagio' => $this->calcularCategoriaPedagio($eixos),
                'rota_id' => $validated['rota_id'] ?? null,
                'rota_nome' => $validated['rota_nome'] ?? null,
                'rota_municipios' => $validated['rota_municipios'] ?? null,
                'rota_municipios_count' => count($validated['rota_municipios'] ?? []),
                'pracas_pedagio' => $validated['pracas_pedagio'] ?? [],
                'pracas_count' => count($validated['pracas_pedagio'] ?? []),
                'valor_total_pedagios' => $validated['valor_total'] ?? 0,
                'distancia_km' => $validated['km_total'] ?? null,
                'data_inicio' => $validated['data_inicio'] ?? null,
                'data_fim' => $validated['data_fim'] ?? null,
                // NDD Cargo data
                'roteirizador_guid' => $validated['roteirizador_guid'] ?? null,
                'roteirizador_request' => $validated['roteirizador_request'] ?? null,
                'roteirizador_response' => $validated['roteirizador_response'] ?? null,
                'emissao_guid' => $validated['emissao_guid'] ?? null,
                'emissao_request' => $validated['emissao_request'] ?? null,
                'emissao_response' => $validated['emissao_response'] ?? null,
                'ndd_codigo_retorno' => $validated['ndd_codigo_retorno'] ?? null,
                'ndd_mensagem_retorno' => $validated['ndd_mensagem_retorno'] ?? null,
                'ndd_protocolo' => $validated['ndd_protocolo'] ?? null,
            ];

            // Criar log
            $log = $this->logService->iniciarLog($logData, $request);

            // Aqui seria chamada a emissão real via NDD Cargo
            // Por enquanto, apenas retornamos o log criado
            // TODO: Implementar chamada real de emissão

            return response()->json([
                'success' => true,
                'message' => 'Emissão VPO iniciada',
                'data' => [
                    'log_id' => $log->id,
                    'uuid' => $log->uuid,
                    'status' => $log->status,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao iniciar emissão VPO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao iniciar emissão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula categoria de pedágio baseada no número de eixos
     */
    private function calcularCategoriaPedagio(int $eixos): int
    {
        // 5=Caminhão leve (2 eixos), 6=Caminhão médio (3-5 eixos), 7=Caminhão pesado (6+ eixos)
        if ($eixos <= 2) return 5;
        if ($eixos <= 5) return 6;
        return 7;
    }

    /**
     * Enriquece praças de pedágio NDD Cargo com coordenadas da tabela pracas_pedagio.
     *
     * A API NDD Cargo geralmente não retorna coordenadas, então precisamos
     * buscar na tabela de praças ANTT que tem latitude/longitude.
     *
     * Estratégias de matching (em ordem):
     * 1. Nome exato (nome ↔ praca)
     * 2. Nome parcial (LIKE)
     * 3. Rodovia + KM aproximado (±5km)
     * 4. Rodovia + nome parcial
     *
     * @param array $pracasNdd Array de praças vindas do NDD Cargo
     * @return array Praças enriquecidas com lat/lon
     */
    private function enriquecerPracasComCoordenadas(array $pracasNdd): array
    {
        if (empty($pracasNdd)) {
            return [];
        }

        $pracasEnriquecidas = [];

        foreach ($pracasNdd as $praca) {
            $pracaEnriquecida = $praca;

            // Se já tem coordenadas do NDD, apenas valida
            if (!empty($praca['latitude']) && !empty($praca['longitude'])) {
                $pracaEnriquecida['lat'] = (float) $praca['latitude'];
                $pracaEnriquecida['lon'] = (float) $praca['longitude'];
                $pracaEnriquecida['coordenadas_fonte'] = 'ndd_cargo';
                $pracasEnriquecidas[] = $pracaEnriquecida;
                continue;
            }

            // Buscar coordenadas na tabela pracas_pedagio
            $pracaAntt = $this->buscarPracaAntt($praca);

            if ($pracaAntt) {
                $pracaEnriquecida['lat'] = (float) $pracaAntt->latitude;
                $pracaEnriquecida['lon'] = (float) $pracaAntt->longitude;
                $pracaEnriquecida['coordenadas_fonte'] = 'antt_cache';
                $pracaEnriquecida['antt_match'] = [
                    'id' => $pracaAntt->id,
                    'praca' => $pracaAntt->praca,
                    'rodovia' => $pracaAntt->rodovia,
                    'km' => $pracaAntt->km,
                    'municipio' => $pracaAntt->municipio,
                ];

                Log::info('Praça enriquecida com coordenadas', [
                    'ndd_nome' => $praca['nome'] ?? 'N/A',
                    'ndd_rodovia' => $praca['rodovia'] ?? 'N/A',
                    'antt_praca' => $pracaAntt->praca,
                    'lat' => $pracaAntt->latitude,
                    'lon' => $pracaAntt->longitude,
                ]);
            } else {
                // Não encontrou match - deixa sem coordenadas
                $pracaEnriquecida['lat'] = null;
                $pracaEnriquecida['lon'] = null;
                $pracaEnriquecida['coordenadas_fonte'] = null;

                Log::warning('Praça sem coordenadas - match não encontrado', [
                    'ndd_nome' => $praca['nome'] ?? 'N/A',
                    'ndd_rodovia' => $praca['rodovia'] ?? 'N/A',
                    'ndd_km' => $praca['km'] ?? 'N/A',
                ]);
            }

            $pracasEnriquecidas[] = $pracaEnriquecida;
        }

        return $pracasEnriquecidas;
    }

    /**
     * Busca praça de pedágio na tabela ANTT (pracas_pedagio) usando várias estratégias.
     *
     * @param array $pracaNdd Praça do NDD Cargo
     * @return PracaPedagio|null
     */
    private function buscarPracaAntt(array $pracaNdd): ?PracaPedagio
    {
        $nome = $pracaNdd['nome'] ?? '';
        $rodovia = $pracaNdd['rodovia'] ?? '';
        $km = $pracaNdd['km'] ?? null;
        $concessionaria = $pracaNdd['concessionaria'] ?? '';

        // Normalizar dados para busca
        $nomeNormalizado = $this->normalizarNomePraca($nome);
        $rodoviaNormalizada = $this->normalizarRodovia($rodovia);

        // 1. Busca por nome exato
        $praca = PracaPedagio::where(function ($q) use ($nome, $nomeNormalizado) {
            $q->whereRaw('LOWER(praca) = ?', [strtolower($nome)])
              ->orWhereRaw('LOWER(praca) = ?', [strtolower($nomeNormalizado)]);
        })
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->first();

        if ($praca) {
            return $praca;
        }

        // 2. Busca por rodovia + km aproximado (±5km)
        if ($rodoviaNormalizada && $km !== null) {
            $praca = PracaPedagio::where(function ($q) use ($rodoviaNormalizada) {
                $q->whereRaw('LOWER(rodovia) = ?', [strtolower($rodoviaNormalizada)])
                  ->orWhereRaw('LOWER(rodovia) LIKE ?', ['%' . strtolower($rodoviaNormalizada) . '%']);
            })
            ->whereBetween('km', [$km - 5, $km + 5])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByRaw('ABS(km - ?)', [$km])
            ->first();

            if ($praca) {
                return $praca;
            }
        }

        // 3. Busca por nome parcial (LIKE)
        if (strlen($nomeNormalizado) >= 4) {
            $praca = PracaPedagio::whereRaw('LOWER(praca) LIKE ?', ['%' . strtolower($nomeNormalizado) . '%'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->first();

            if ($praca) {
                return $praca;
            }
        }

        // 4. Busca por rodovia + nome parcial
        if ($rodoviaNormalizada && strlen($nomeNormalizado) >= 3) {
            $praca = PracaPedagio::where(function ($q) use ($rodoviaNormalizada) {
                $q->whereRaw('LOWER(rodovia) LIKE ?', ['%' . strtolower($rodoviaNormalizada) . '%']);
            })
            ->where(function ($q) use ($nomeNormalizado) {
                $q->whereRaw('LOWER(praca) LIKE ?', ['%' . strtolower($nomeNormalizado) . '%']);
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first();

            if ($praca) {
                return $praca;
            }
        }

        // 5. Busca por concessionária + rodovia (última tentativa)
        if ($concessionaria && $rodoviaNormalizada) {
            $praca = PracaPedagio::whereRaw('LOWER(concessionaria) LIKE ?', ['%' . strtolower($concessionaria) . '%'])
                ->where(function ($q) use ($rodoviaNormalizada) {
                    $q->whereRaw('LOWER(rodovia) LIKE ?', ['%' . strtolower($rodoviaNormalizada) . '%']);
                })
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->first();

            if ($praca) {
                return $praca;
            }
        }

        return null;
    }

    /**
     * Normaliza nome de praça para busca (remove prefixos comuns, acentos, etc.)
     */
    private function normalizarNomePraca(string $nome): string
    {
        // Remove prefixos comuns
        $prefixos = ['PRAÇA ', 'PRACA ', 'PEDAGIO ', 'PEDÁGIO ', 'P. ', 'PED. '];
        $nomeNormalizado = strtoupper(trim($nome));

        foreach ($prefixos as $prefixo) {
            if (str_starts_with($nomeNormalizado, $prefixo)) {
                $nomeNormalizado = substr($nomeNormalizado, strlen($prefixo));
            }
        }

        // Remove acentos
        $nomeNormalizado = $this->removerAcentos($nomeNormalizado);

        return trim($nomeNormalizado);
    }

    /**
     * Normaliza código de rodovia (BR-116 → BR116, SP 160 → SP160)
     */
    private function normalizarRodovia(string $rodovia): string
    {
        // Remove traços, espaços
        return preg_replace('/[\s\-]+/', '', strtoupper(trim($rodovia)));
    }

    /**
     * Remove acentos de uma string
     */
    private function removerAcentos(string $string): string
    {
        $map = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr($string, $map);
    }
}
