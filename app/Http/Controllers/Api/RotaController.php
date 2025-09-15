<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RotaController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista rotas do Progress para autocomplete
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'string|max:255'
        ]);

        $search = $request->get('search', '');
        
        $result = $this->progressService->getRotas($search);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rotas obtidas com sucesso',
            'data' => $result['data']
        ]);
    }
}