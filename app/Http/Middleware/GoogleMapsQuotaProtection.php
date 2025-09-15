<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleMapsQuotaProtection
{
    // Limite diário de requests (ajuste conforme necessário)
    private const DAILY_REQUEST_LIMIT = 1000;
    private const MONTHLY_COST_LIMIT = 1.00; // $1 USD
    
    public function handle(Request $request, Closure $next)
    {
        // Chave para contar requests diários
        $dailyKey = 'google_maps_requests_' . date('Y-m-d');
        $monthlyKey = 'google_maps_cost_' . date('Y-m');
        
        // Verificar requests diários
        $dailyRequests = Cache::get($dailyKey, 0);
        
        if ($dailyRequests >= self::DAILY_REQUEST_LIMIT) {
            Log::warning('Google Maps daily request limit exceeded', [
                'requests' => $dailyRequests,
                'limit' => self::DAILY_REQUEST_LIMIT
            ]);
            
            return response()->json([
                'error' => 'Quota diária do Google Maps excedida',
                'message' => 'Tente novamente amanhã ou use fallback',
                'fallback' => 'usar_linha_reta'
            ], 429);
        }
        
        // Verificar custo mensal estimado
        $monthlyCost = Cache::get($monthlyKey, 0.0);
        
        if ($monthlyCost >= self::MONTHLY_COST_LIMIT) {
            Log::warning('Google Maps monthly cost limit exceeded', [
                'cost' => $monthlyCost,
                'limit' => self::MONTHLY_COST_LIMIT
            ]);
            
            return response()->json([
                'error' => 'Orçamento mensal do Google Maps excedido',
                'message' => 'APIs desabilitadas para proteger orçamento',
                'fallback' => 'usar_linha_reta'
            ], 429);
        }
        
        // Prosseguir com a requisição
        $response = $next($request);
        
        // Incrementar contadores após requisição bem-sucedida
        if ($response->isSuccessful()) {
            // Incrementar requests diários
            Cache::put($dailyKey, $dailyRequests + 1, now()->endOfDay());
            
            // Incrementar custo estimado (aproximadamente $0.005 por request)
            $requestCost = 0.005;
            Cache::put($monthlyKey, $monthlyCost + $requestCost, now()->endOfMonth());
            
            Log::info('Google Maps request tracked', [
                'daily_requests' => $dailyRequests + 1,
                'monthly_cost' => $monthlyCost + $requestCost
            ]);
        }
        
        return $response;
    }
}