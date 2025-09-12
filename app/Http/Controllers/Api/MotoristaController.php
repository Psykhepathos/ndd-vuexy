<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'NDD API',
    description: 'API do sistema de integração corporativa NDD para vale pedágio e CIOT'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Servidor de desenvolvimento'
)]
class MotoristaController extends Controller
{
    #[OA\Get(
        path: '/motoristas',
        tags: ['Motoristas'],
        summary: 'Lista todos os motoristas',
        description: 'Retorna uma lista paginada de motoristas com filtros opcionais',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por status (ativo/inativo)', schema: new OA\Schema(type: 'string', enum: ['ativo', 'inativo'])),
            new OA\Parameter(name: 'nome', in: 'query', description: 'Filtrar por nome (busca parcial)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'cpf', in: 'query', description: 'Filtrar por CPF (busca parcial)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'codigo_progress', in: 'query', description: 'Filtrar por código Progress', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Itens por página (padrão: 15)', schema: new OA\Schema(type: 'integer', default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de motoristas',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean', example: true),
                        'data' => new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Motorista')),
                        'pagination' => new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                'current_page' => new OA\Property(property: 'current_page', type: 'integer'),
                                'last_page' => new OA\Property(property: 'last_page', type: 'integer'),
                                'per_page' => new OA\Property(property: 'per_page', type: 'integer'),
                                'total' => new OA\Property(property: 'total', type: 'integer')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Erro interno do servidor')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Motorista::query();

            if ($request->has('status')) {
                if ($request->status === 'ativo') {
                    $query->ativo();
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has('nome')) {
                $query->where('nome', 'LIKE', '%' . $request->nome . '%');
            }

            if ($request->has('cpf')) {
                $query->where('cpf', 'LIKE', '%' . $request->cpf . '%');
            }

            if ($request->has('codigo_progress')) {
                $query->porCodigoProgress($request->codigo_progress);
            }

            $perPage = $request->get('per_page', 15);
            $motoristas = $query->orderBy('nome')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $motoristas->getCollection()->map(fn($motorista) => $motorista->toApiArray()),
                'pagination' => [
                    'current_page' => $motoristas->currentPage(),
                    'last_page' => $motoristas->lastPage(),
                    'per_page' => $motoristas->perPage(),
                    'total' => $motoristas->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar motoristas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar lista de motoristas'
            ], 500);
        }
    }

    #[OA\Post(
        path: '/motoristas',
        tags: ['Motoristas'],
        summary: 'Cria um novo motorista',
        description: 'Cria um novo motorista no sistema',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/MotoristaRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Motorista criado com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/Motorista')
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data) && $request->getContent()) {
            $data = json_decode($request->getContent(), true) ?: [];
        }

        $validator = Validator::make($data, [
            'codigo_progress' => 'required|string|unique:motoristas,codigo_progress',
            'nome' => 'required|string|max:255',
            'cpf' => 'required|string|unique:motoristas,cpf',
            'cnh' => 'required|string',
            'vencimento_cnh' => 'nullable|date',
            'telefone' => 'nullable|string',
            'email' => 'nullable|email',
            'status' => 'sometimes|in:ativo,inativo,suspenso'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!isset($data['status'])) {
                $data['status'] = 'ativo';
            }
            $motorista = Motorista::create($data);

            Log::info('Motorista criado', ['id' => $motorista->id, 'nome' => $motorista->nome]);

            return response()->json([
                'success' => true,
                'message' => 'Motorista criado com sucesso',
                'data' => $motorista->toApiArray()
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar motorista'
            ], 500);
        }
    }

    #[OA\Get(
        path: '/motoristas/{id}',
        tags: ['Motoristas'],
        summary: 'Mostra um motorista específico',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do motorista', content: new OA\JsonContent(ref: '#/components/schemas/Motorista')),
            new OA\Response(response: 404, description: 'Motorista não encontrado')
        ]
    )]
    public function show(string $id): JsonResponse
    {
        try {
            $motorista = Motorista::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $motorista->toApiArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Motorista não encontrado'
            ], 404);
        }
    }

    #[OA\Put(
        path: '/motoristas/{id}',
        tags: ['Motoristas'],
        summary: 'Atualiza um motorista',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/MotoristaRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Motorista atualizado', content: new OA\JsonContent(ref: '#/components/schemas/Motorista')),
            new OA\Response(response: 404, description: 'Motorista não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação')
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codigo_progress' => 'sometimes|string|unique:motoristas,codigo_progress,' . $id,
            'nome' => 'sometimes|string|max:255',
            'cpf' => 'sometimes|string|size:11|unique:motoristas,cpf,' . $id,
            'cnh' => 'sometimes|string|max:50',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'data_nascimento' => 'nullable|date',
            'endereco' => 'nullable|string|max:500',
            'cidade' => 'nullable|string|max:100',
            'uf' => 'nullable|string|size:2',
            'cep' => 'nullable|string|max:10',
            'status' => 'boolean',
            'observacoes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $motorista = Motorista::findOrFail($id);
            $oldData = $motorista->toApiArray();
            
            $motorista->update($request->all());
            $motorista->refresh();

            Log::info('Motorista atualizado', ['id' => $motorista->id, 'nome' => $motorista->nome]);

            return response()->json([
                'success' => true,
                'message' => 'Motorista atualizado com sucesso',
                'data' => $motorista->toApiArray()
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar motorista'
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/motoristas/{id}',
        tags: ['Motoristas'],
        summary: 'Remove um motorista',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Motorista removido com sucesso'),
            new OA\Response(response: 404, description: 'Motorista não encontrado')
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        try {
            $motorista = Motorista::findOrFail($id);
            $motoristaData = $motorista->toApiArray();

            $motorista->delete();

            Log::info('Motorista desativado', ['id' => $motorista->id, 'nome' => $motorista->nome]);

            return response()->json([
                'success' => true,
                'message' => 'Motorista removido com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar motorista'
            ], 500);
        }
    }

    public function findByProgressCode(string $codigo): JsonResponse
    {
        try {
            $motorista = Motorista::porCodigoProgress($codigo)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $motorista->toApiArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Motorista não encontrado com este código Progress'
            ], 404);
        }
    }
}