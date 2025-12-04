<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GeocodingController extends Controller
{
    protected GeocodingService $geocodingService;

    public function __construct(GeocodingService $geocodingService)
    {
        $this->geocodingService = $geocodingService;
    }

    /**
     * Busca coordenadas de um município pelo código IBGE
     */
    public function getCoordenadasByIbge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codigo_ibge' => [
                    'required',
                    'string',
                    'size:7',
                    'regex:/^\d{7}$/'
                ],
                'nome_municipio' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/u'
                ],
                'uf' => [
                    'required',
                    'string',
                    'size:2',
                    'regex:/^[A-Z]{2}$/'
                ]
            ], [
                'codigo_ibge.regex' => 'Código IBGE deve conter apenas 7 dígitos',
                'nome_municipio.regex' => 'Nome do município contém caracteres inválidos',
                'uf.regex' => 'UF deve ser 2 letras maiúsculas (ex: SP, RJ)'
            ]);

            // LGPD Art. 46 - Log de acesso a dados de localização
            Log::info('Coordenadas por IBGE acessadas', [
                'codigo_ibge' => $validated['codigo_ibge'],
                'nome_municipio' => $validated['nome_municipio'],
                'uf' => $validated['uf'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            $coordenadas = $this->geocodingService->getCoordenadasByIbge(
                $validated['codigo_ibge'],
                $validated['nome_municipio'],
                $validated['uf']
            );

            if (!$coordenadas) {
                Log::warning('Município não encontrado no geocoding', [
                    'codigo_ibge' => $validated['codigo_ibge'],
                    'nome_municipio' => $validated['nome_municipio'],
                    'uf' => $validated['uf'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível obter coordenadas para este município',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Coordenadas obtidas com sucesso',
                'data' => $coordenadas
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Erro ao buscar coordenadas', [
                'error_id' => $errorId,
                'codigo_ibge' => $validated['codigo_ibge'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }

    /**
     * Busca coordenadas em lote para múltiplos municípios
     */
    public function getCoordenadasLote(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'municipios' => 'required|array|min:1|max:100',  // CRÍTICO: Previne DoS
                'municipios.*.cdibge' => [
                    'required',
                    'string',
                    'size:7',
                    'regex:/^\d{7}$/'
                ],
                'municipios.*.desmun' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/u'
                ],
                'municipios.*.desest' => [
                    'required',
                    'string',
                    'size:2',
                    'regex:/^[A-Z]{2}$/'
                ],
                'municipios.*.cod_mun' => 'nullable|integer|min:1',
                'municipios.*.cod_est' => 'nullable|integer|min:1|max:99'
            ], [
                'municipios.max' => 'Máximo de 100 municípios por requisição',
                'municipios.*.cdibge.regex' => 'Código IBGE deve conter apenas 7 dígitos',
                'municipios.*.desmun.regex' => 'Nome do município contém caracteres inválidos',
                'municipios.*.desest.regex' => 'UF deve ser 2 letras maiúsculas (ex: SP, RJ)'
            ]);

            // Log de volume alto (possível abuso)
            if (count($validated['municipios']) > 50) {
                Log::warning('Requisição de geocoding com alto volume', [
                    'count' => count($validated['municipios']),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toIso8601String()
                ]);
            }

            // LGPD Art. 46 - Log de acesso a dados de localização em lote
            Log::info('Coordenadas em lote acessadas', [
                'total_municipios' => count($validated['municipios']),
                'municipios_codigos' => array_column($validated['municipios'], 'cdibge'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            $resultado = $this->geocodingService->getCoordenadasLote($validated['municipios']);

            return response()->json([
                'success' => true,
                'message' => 'Coordenadas obtidas com sucesso',
                'data' => $resultado
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Erro ao buscar coordenadas em lote', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'total_municipios' => count($validated['municipios'] ?? []),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }
}
