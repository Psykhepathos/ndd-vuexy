<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OSRMController extends Controller
{
    /**
     * Proxy para API do OSRM (Open Source Routing Machine)
     * Resolve problemas de CORS ao fazer requisição do backend
     */
    public function getRoute(Request $request)
    {
        try {
            // Validar parâmetros
            $request->validate([
                'coordinates' => 'required|string',
            ]);

            $coordinates = $request->input('coordinates');

            // Construir URL do OSRM
            $url = "https://router.project-osrm.org/route/v1/driving/{$coordinates}";
            $url .= "?overview=full&geometries=geojson";

            Log::info("OSRM Proxy: Requisitando rota", ['url' => $url]);

            // Fazer requisição para OSRM
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                Log::info("OSRM Proxy: Rota obtida com sucesso", [
                    'code' => $data['code'] ?? 'unknown',
                    'routes' => count($data['routes'] ?? [])
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                Log::error("OSRM Proxy: Erro na resposta", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'OSRM retornou erro: ' . $response->status()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("OSRM Proxy: Exceção", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
