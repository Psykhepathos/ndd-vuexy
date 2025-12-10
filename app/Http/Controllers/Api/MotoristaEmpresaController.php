<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Vpo\MotoristaEmpresaCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para gerenciar motoristas de empresas (CNPJ)
 *
 * Endpoints para listar, visualizar e salvar dados de motoristas
 * que serão usados na emissão de VPO para empresas.
 */
class MotoristaEmpresaController extends Controller
{
    protected MotoristaEmpresaCacheService $cacheService;

    public function __construct(MotoristaEmpresaCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Lista motoristas de um transportador (empresa)
     *
     * GET /api/vpo/motoristas/{codtrn}
     */
    public function index(int $codtrn): JsonResponse
    {
        // Validar se é empresa
        if (!$this->cacheService->isEmpresa($codtrn)) {
            return response()->json([
                'success' => false,
                'error' => 'Transportador não é uma empresa (CNPJ). Use o fluxo de autônomo.',
                'tipo' => 'autonomo'
            ], 400);
        }

        // Verificar se tem motoristas no Progress
        if (!$this->cacheService->temMotoristasProgress($codtrn)) {
            return response()->json([
                'success' => false,
                'error' => 'Empresa não possui motoristas cadastrados no sistema.',
                'tipo' => 'sem_motoristas'
            ], 404);
        }

        $result = $this->cacheService->listarMotoristasEmpresa($codtrn);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Busca um motorista específico
     *
     * GET /api/vpo/motoristas/{codtrn}/{codmot}
     */
    public function show(int $codtrn, int $codmot): JsonResponse
    {
        $result = $this->cacheService->getMotorista($codtrn, $codmot);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Salva/atualiza dados de um motorista
     *
     * POST /api/vpo/motoristas/{codtrn}/{codmot}
     *
     * Body:
     * {
     *   "cpf": "12345678901",
     *   "rntrc": "12345678",
     *   "nommae": "MARIA DA SILVA",
     *   "data_nascimento": "1980-01-15",
     *   "cnh": "1234567890",
     *   "categoria_cnh": "E",
     *   "validade_cnh": "2025-12-31",
     *   "endereco_logradouro": "RUA EXEMPLO",
     *   "endereco_numero": "123",
     *   "endereco_bairro": "CENTRO",
     *   "endereco_cidade": "SAO PAULO",
     *   "endereco_uf": "SP",
     *   "endereco_cep": "01234567"
     * }
     */
    public function store(Request $request, int $codtrn, int $codmot): JsonResponse
    {
        // Validação dos dados
        $validator = Validator::make($request->all(), [
            'cpf' => 'nullable|string|max:14',
            'rntrc' => 'nullable|string|max:20',
            'nommae' => 'nullable|string|max:100',
            'data_nascimento' => 'nullable|date',
            'cnh' => 'nullable|string|max:20',
            'categoria_cnh' => 'nullable|string|max:5',
            'validade_cnh' => 'nullable|date',
            'endereco_logradouro' => 'nullable|string|max:200',
            'endereco_numero' => 'nullable|string|max:20',
            'endereco_bairro' => 'nullable|string|max:100',
            'endereco_cidade' => 'nullable|string|max:100',
            'endereco_uf' => 'nullable|string|max:2',
            'endereco_cep' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Dados inválidos',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        $result = $this->cacheService->salvarMotorista($codtrn, $codmot, $request->all());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Lista motoristas completos (prontos para VPO)
     *
     * GET /api/vpo/motoristas/{codtrn}/completos
     */
    public function completos(int $codtrn): JsonResponse
    {
        $motoristas = $this->cacheService->listarMotoristasCompletosParaVpo($codtrn);

        return response()->json([
            'success' => true,
            'data' => $motoristas,
            'total' => count($motoristas)
        ]);
    }

    /**
     * Verifica se transportador é empresa e tem motoristas
     *
     * GET /api/vpo/motoristas/{codtrn}/verificar
     */
    public function verificar(int $codtrn): JsonResponse
    {
        $isEmpresa = $this->cacheService->isEmpresa($codtrn);
        $temMotoristas = $this->cacheService->temMotoristasProgress($codtrn);

        return response()->json([
            'success' => true,
            'codtrn' => $codtrn,
            'is_empresa' => $isEmpresa,
            'tem_motoristas' => $temMotoristas,
            'requer_selecao_motorista' => $isEmpresa && $temMotoristas,
            'mensagem' => $this->getMensagemVerificacao($isEmpresa, $temMotoristas)
        ]);
    }

    /**
     * Retorna mensagem de verificação
     */
    private function getMensagemVerificacao(bool $isEmpresa, bool $temMotoristas): string
    {
        if (!$isEmpresa) {
            return 'Transportador é autônomo (CPF). Fluxo direto de VPO.';
        }

        if (!$temMotoristas) {
            return 'Empresa não possui motoristas cadastrados. Necessário cadastrar motoristas primeiro.';
        }

        return 'Empresa com motoristas. Necessário selecionar motorista para VPO.';
    }
}
