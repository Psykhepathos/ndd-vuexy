<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GoogleMapsQuotaController extends Controller
{
    /**
     * Obter estatísticas de uso da API do Google Maps
     */
    public function getUsageStats()
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $dailyRequests = Cache::get("google_maps_requests_{$today}", 0);
        $monthlyCost = Cache::get("google_maps_cost_{$thisMonth}", 0.0);
        
        $dailyLimit = env('GOOGLE_MAPS_DAILY_LIMIT', 1000);
        $monthlyBudget = env('GOOGLE_MAPS_MONTHLY_BUDGET', 1.00);
        
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
            'protection_enabled' => env('GOOGLE_MAPS_PROTECTION_ENABLED', true),
            'api_status' => $this->getApiStatus()
        ]);
    }
    
    /**
     * Resetar contadores (apenas para emergência)
     */
    public function resetCounters(Request $request)
    {
        $type = $request->input('type'); // 'daily' ou 'monthly'
        
        if ($type === 'daily') {
            $key = 'google_maps_requests_' . date('Y-m-d');
            Cache::forget($key);
            return response()->json(['message' => 'Contador diário resetado']);
        }
        
        if ($type === 'monthly') {
            $key = 'google_maps_cost_' . date('Y-m');
            Cache::forget($key);
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
        
        $dailyLimit = env('GOOGLE_MAPS_DAILY_LIMIT', 1000);
        $monthlyBudget = env('GOOGLE_MAPS_MONTHLY_BUDGET', 1.00);
        
        if ($dailyRequests >= $dailyLimit) {
            return 'daily_limit_exceeded';
        }
        
        if ($monthlyCost >= $monthlyBudget) {
            return 'monthly_budget_exceeded';
        }
        
        return 'active';
    }
}