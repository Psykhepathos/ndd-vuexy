<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleMapsQuotaController extends Controller
{
    /**
     * Obter estatísticas de uso da API do Google Maps
     * CORREÇÃO BUG #44: Agora requer autenticação (dados sensíveis)
     */
    public function getUsageStats()
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');

        $dailyRequests = Cache::get("google_maps_requests_{$today}", 0);
        $monthlyCost = Cache::get("google_maps_cost_{$thisMonth}", 0.0);

        // CORREÇÃO BUG #45: Usar config() em vez de env() no runtime
        $dailyLimit = config('services.google_maps.daily_limit', 1000);
        $monthlyBudget = config('services.google_maps.monthly_budget', 1.00);
        
        return response()->json([
            'today' => [
                'requests' => $dailyRequests,
                'limit' => $dailyLimit,
                'percentage' => round(($dailyRequests / $dailyLimit) * 100, 2),
                'status' => $dailyRequests >= $dailyLimit ? 'blocked' : 'active'
            ],
            'this_month' => [
                'cost' => $monthlyCost,
                'budget' => $monthlyBudget,
                'percentage' => round(($monthlyCost / $monthlyBudget) * 100, 2),
                'status' => $monthlyCost >= $monthlyBudget ? 'blocked' : 'active'
            ],
            // CORREÇÃO BUG #45: Usar config() em vez de env() no runtime
            'protection_enabled' => config('services.google_maps.protection_enabled', true),
            'api_status' => $this->getApiStatus()
        ]);
    }
    
    /**
     * Resetar contadores (apenas para emergência)
     * CORREÇÃO BUG #46: Agora requer autenticação admin
     * CORREÇÃO BUG #47: Logging de quem resetou (LGPD)
     */
    public function resetCounters(Request $request)
    {
        // CORREÇÃO BUG #46: Verificar se usuário é admin
        if (!$request->user() || $request->user()->role !== 'admin') {
            Log::warning('Tentativa de reset de quota sem permissão', [
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Acesso negado. Apenas administradores podem resetar contadores.'
            ], 403);
        }

        $type = $request->input('type'); // 'daily' ou 'monthly'
        
        if ($type === 'daily') {
            $key = 'google_maps_requests_' . date('Y-m-d');
            Cache::forget($key);

            // CORREÇÃO BUG #47: Logging de reset (LGPD + auditoria)
            Log::warning('Contador de quota Google Maps resetado', [
                'type' => 'daily',
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json(['message' => 'Contador diário resetado']);
        }

        if ($type === 'monthly') {
            $key = 'google_maps_cost_' . date('Y-m');
            Cache::forget($key);

            // CORREÇÃO BUG #47: Logging de reset (LGPD + auditoria)
            Log::warning('Contador de quota Google Maps resetado', [
                'type' => 'monthly',
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json(['message' => 'Contador mensal resetado']);
        }
        
        return response()->json(['error' => 'Tipo inválido'], 400);
    }
    
    /**
     * Verificar status da API
     */
    private function getApiStatus()
    {
        $dailyKey = 'google_maps_requests_' . date('Y-m-d');
        $monthlyKey = 'google_maps_cost_' . date('Y-m');

        $dailyRequests = Cache::get($dailyKey, 0);
        $monthlyCost = Cache::get($monthlyKey, 0.0);

        // CORREÇÃO BUG #45: Usar config() em vez de env() no runtime
        $dailyLimit = config('services.google_maps.daily_limit', 1000);
        $monthlyBudget = config('services.google_maps.monthly_budget', 1.00);
        
        if ($dailyRequests >= $dailyLimit) {
            return 'daily_limit_exceeded';
        }
        
        if ($monthlyCost >= $monthlyBudget) {
            return 'monthly_budget_exceeded';
        }
        
        return 'active';
    }
}