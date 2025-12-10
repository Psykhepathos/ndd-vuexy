<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracaPedagio;
use App\Services\NddCargo\NddCargoService;
use App\Services\NddCargo\DTOs\ConsultarRoteirizadorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para integração com API NDD Cargo
 *
 * Disponibiliza endpoints REST para:
 * - Consultar roteirizador (cálculo de rotas e praças de pedágio)
 * - Consultar resultados assíncronos
 * - Testar conectividade
 *
 * Rate Limiting:
 * - Consultas: 60 req/min
 * - Testes: 10 req/min
 *
 * @see docs/integracoes/ndd-cargo/README.md
 */
class NddCargoController extends Controller
{
    /**
     * @var NddCargoService
     */
    private NddCargoService $nddCargoService;

    public function __construct(NddCargoService $nddCargoService)
    {
        $this->nddCargoService = $nddCargoService;
    }

    /**
     * Consulta roteirizador completo
     *
     * POST /api/ndd-cargo/roteirizador
     *
     * Body:
     * {
     *   "cnpj_empresa": "17359233000188",
     *   "cnpj_contratante": "17359233000188",
     *   "categoria_pedagio": 7,
     *   "pontos_parada": {
     *     "origem": "01310100",
     *     "destino": "20040020"
     *   },
     *   "tipo_rota_padrao": 1,
     *   "evitar_pedagogios": false,
     *   "priorizar_rodovias": false,
     *   "tipo_rota": 1,
     *   "tipo_veiculo": 5,
     *   "retornar_trecho": false
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRoteirizador(Request $request): JsonResponse
    {
        try {
            // Validação
            $validator = Validator::make($request->all(), [
                'cnpj_empresa' => 'required|string|size:14',
                'cnpj_contratante' => 'required|string|size:14',
                'categoria_pedagio' => 'integer|min:1|max:7',
                'pontos_parada' => 'required|array|max:100',  // Limite 100 pontos (proteção DoS)
                'pontos_parada.origem' => 'required|string|size:8',
                'pontos_parada.destino' => 'required|string|size:8',
                'pontos_parada.*' => 'string|size:8',  // Validar todos os elementos
                'tipo_rota_padrao' => 'integer|min:1|max:3',
                'evitar_pedagogios' => 'boolean',
                'priorizar_rodovias' => 'boolean',
                'tipo_rota' => 'integer|min:1|max:3',
                'tipo_veiculo' => 'integer|min:1|max:10',
                'retornar_trecho' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Criar DTO
            $dto = ConsultarRoteirizadorRequest::fromArray($request->all());

            // Consultar roteirizador
            $response = $this->nddCargoService->consultarRoteirizador($dto);

            // Retornar resposta
            if ($response->sucesso) {
                return response()->json([
                    'success' => true,
                    'data' => $response->toArray()
                ]);
            } else {
                // Status 202 = Aceito para processamento (retorna 202 HTTP)
                if ($response->status === 202) {
                    return response()->json([
                        'success' => false,
                        'message' => $response->mensagem,
                        'status' => $response->status,
                        'guid' => $response->guid,
                        'consultar_em' => url("/api/ndd-cargo/resultado/{$response->guid}")
                    ], 202);
                }

                // Outros erros
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarRoteirizador', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Consulta rota simples (apenas CEPs origem/destino)
     *
     * POST /api/ndd-cargo/rota-simples
     *
     * Body:
     * {
     *   "cep_origem": "01310100",
     *   "cep_destino": "20040020",
     *   "categoria_pedagio": 7
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRotaSimples(Request $request): JsonResponse
    {
        try {
            // Validação
            $validator = Validator::make($request->all(), [
                'cep_origem' => 'required|string|size:8',
                'cep_destino' => 'required|string|size:8',
                'categoria_pedagio' => 'integer|min:1|max:7',
            ], [
                'cep_origem.size' => 'CEP de origem deve conter exatamente 8 dígitos',
                'cep_destino.size' => 'CEP de destino deve conter exatamente 8 dígitos',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Consultar
            $response = $this->nddCargoService->consultarRotaSimples(
                cepOrigem: $request->input('cep_origem'),
                cepDestino: $request->input('cep_destino'),
                categoriaPedagio: $request->input('categoria_pedagio', 7)
            );

            // Retornar resposta
            if ($response->sucesso) {
                return response()->json([
                    'success' => true,
                    'data' => $response->toArray()
                ]);
            } else {
                // Status 202 = Aceito para processamento (retorna 202 HTTP)
                if ($response->status === 202) {
                    return response()->json([
                        'success' => false,
                        'message' => $response->mensagem,
                        'status' => $response->status,
                        'guid' => $response->guid,
                        'consultar_em' => url("/api/ndd-cargo/resultado/{$response->guid}")
                    ], 202);
                }

                // Outros erros
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarRotaSimples', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Consulta resultado de operação assíncrona
     *
     * GET /api/ndd-cargo/resultado/{guid}
     *
     * @param string $guid UUID da transação original
     * @return JsonResponse
     */
    public function consultarResultado(string $guid): JsonResponse
    {
        try {
            // Validar GUID
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'GUID inválido'
                ], 422);
            }

            // Consultar resultado
            $response = $this->nddCargoService->consultarResultado($guid);

            // Retornar resposta
            if ($response->sucesso) {
                // Converter resposta para array
                $responseData = $response->toArray();

                // Enriquecer praças de pedágio com coordenadas da tabela ANTT
                if (!empty($responseData['pracas_pedagio'])) {
                    $responseData['pracas_pedagio'] = $this->enriquecerPracasComCoordenadas($responseData['pracas_pedagio']);

                    Log::info('consultarResultado: Praças enriquecidas', [
                        'guid' => $guid,
                        'total_pracas' => count($responseData['pracas_pedagio']),
                        'com_coordenadas' => count(array_filter($responseData['pracas_pedagio'], fn($p) => !empty($p['lat']) && !empty($p['lon'])))
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], $response->status === -2 ? 404 : 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarResultado', [
                'guid' => $guid,
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Enriquece praças de pedágio com coordenadas da tabela ANTT
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
     * Busca praça de pedágio na tabela ANTT usando várias estratégias
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
     * Normaliza nome de praça para busca
     */
    private function normalizarNomePraca(string $nome): string
    {
        $nome = preg_replace('/^(praca|praça|prç|pc|pça)\s*/i', '', $nome);
        $nome = preg_replace('/\s+(I{1,3}|IV|V|VI{0,3}|\d+)\s*$/i', '', $nome);
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        return $nome;
    }

    /**
     * Normaliza nome de rodovia para busca
     */
    private function normalizarRodovia(string $rodovia): string
    {
        if (preg_match('/(BR|SP|MG|RJ|PR|RS|SC|BA|GO|MT|MS|PE|CE|MA|PA|AM|PI|RN|PB|SE|AL|ES|RO|AC|AP|RR|TO|DF)[\s\-]*(\d+)/i', $rodovia, $matches)) {
            return strtoupper($matches[1]) . '-' . $matches[2];
        }
        return trim($rodovia);
    }

    /**
     * Testa conexão com API NDD Cargo
     *
     * GET /api/ndd-cargo/test-connection
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->nddCargoService->testConnection();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Erro no teste de conexão NDD Cargo', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Retorna informações sobre a integração NDD Cargo
     *
     * GET /api/ndd-cargo/info
     *
     * @return JsonResponse
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'NDD Cargo API Integration',
                'version' => '1.0.0',
                'environment' => config('nddcargo.environment'),
                'endpoint' => config('nddcargo.endpoint_url'),
                'versao_layout' => config('nddcargo.versao_layout'),
                'documentation' => [
                    'overview' => url('/docs/integracoes/ndd-cargo/README.md'),
                    'index' => url('/docs/integracoes/ndd-cargo/INDEX.md'),
                    'python_analysis' => url('/docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md'),
                ],
                'endpoints' => [
                    'test_connection' => url('/api/ndd-cargo/test-connection'),
                    'info' => url('/api/ndd-cargo/info'),
                    'roteirizador' => url('/api/ndd-cargo/roteirizador'),
                    'rota_simples' => url('/api/ndd-cargo/rota-simples'),
                    'resultado' => url('/api/ndd-cargo/resultado/{guid}'),
                ],
            ]
        ]);
    }
}
