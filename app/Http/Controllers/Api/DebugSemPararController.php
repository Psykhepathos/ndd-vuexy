<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use App\Services\SemPararSoapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugSemPararController extends Controller
{
    private ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Debug completo do fluxo SemParar
     */
    public function debugFlow(Request $request)
    {
        // CORREÇÃO #1: Bloquear endpoint em produção
        if (!config('app.debug')) {
            Log::warning('Tentativa de acesso ao endpoint de debug em produção bloqueada', [
                'user_id' => $request->user()->id ?? null,
                'user_email' => $request->user()->email ?? null,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Endpoint de debug desabilitado em produção'
            ], 403);
        }

        $codPac = $request->input('codpac');
        $codRota = $request->input('cod_rota');

        // CORREÇÃO #5: Logging de acesso (LGPD Art. 46 compliance)
        Log::warning('Acesso ao endpoint de debug', [
            'user_id' => $request->user()->id ?? null,
            'user_email' => $request->user()->email ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'codpac' => $codPac,
            'cod_rota' => $codRota,
            'timestamp' => now()->toIso8601String()
        ]);

        $debug = [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'inputs' => [
                'codpac' => $codPac,
                'cod_rota' => $codRota
            ],
            'steps' => []
        ];

        try{
            // PASSO 1: Buscar rota Progress
            $debug['steps'][] = [
                'number' => 1,
                'name' => 'Buscar Rota Progress',
                'progress_ref' => 'Rota.cls linha 695-714',
                'status' => 'running'
            ];

            $sqlRota = "SELECT TOP 1 r.sPararRotID, r.desSPararRot, r.flgRetorno, r.flgCD FROM PUB.semPararRot r WHERE r.sPararRotID = " . intval($codRota);
            $resultRota = $this->progressService->executeCustomQuery($sqlRota);

            if (!$resultRota['success'] || empty($resultRota['data']['results'])) {
                throw new \Exception('Rota não encontrada');
            }

            $rota = $resultRota['data']['results'][0];
            $debug['steps'][0]['status'] = 'success';
            $debug['steps'][0]['data'] = $rota;

            // PASSO 2: Buscar municípios da rota
            $debug['steps'][] = [
                'number' => 2,
                'name' => 'Buscar Municípios da Rota',
                'progress_ref' => 'Rota.cls linha 698-713 (loop semPararRotMu)',
                'status' => 'running'
            ];

            $sqlMunicipios = "SELECT m.cdibge, m.desMun, m.desEst FROM PUB.semPararRotMu m WHERE m.sPararRotID = " . intval($codRota) . " ORDER BY m.sPararMuSeq";
            $resultMunicipios = $this->progressService->executeCustomQuery($sqlMunicipios);

            $municipios = $resultMunicipios['data']['results'] ?? [];
            $debug['steps'][1]['status'] = 'success';
            $debug['steps'][1]['data'] = [
                'total' => count($municipios),
                'municipios' => $municipios
            ];

            // PASSO 3: Buscar pacote
            $debug['steps'][] = [
                'number' => 3,
                'name' => 'Buscar Pacote',
                'progress_ref' => 'Rota.cls linha 716',
                'status' => 'running'
            ];

            $sqlPacote = "SELECT TOP 1 codpac, codrot FROM PUB.pacote WHERE codpac = " . intval($codPac);
            $resultPacote = $this->progressService->executeCustomQuery($sqlPacote);

            if (!$resultPacote['success'] || empty($resultPacote['data']['results'])) {
                $debug['steps'][2]['status'] = 'error';
                $debug['steps'][2]['error'] = 'Pacote não encontrado';
            } else {
                $pacote = $resultPacote['data']['results'][0];
                $debug['steps'][2]['status'] = 'success';
                $debug['steps'][2]['data'] = $pacote;
            }

            // PASSO 4: Buscar entregas do pacote (arqrdnt)
            $debug['steps'][] = [
                'number' => 4,
                'name' => 'Buscar Entregas do Pacote (arqrdnt)',
                'progress_ref' => 'Rota.cls linha 719-796 (loop carga/pedido/arqrdnt)',
                'status' => 'running',
                'warning' => 'Query pode ser lenta - verificar índices'
            ];

            // Query SIMPLIFICADA para debug
            $sqlEntregas = "SELECT TOP 10 ped.numseqped, ped.asdped, cli.desend, ard.latitute, ard.longitude, ard.cidade " .
                          "FROM PUB.carga car " .
                          "INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar " .
                          "INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli " .
                          "LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped " .
                          "WHERE car.codpac = " . intval($codPac);

            $startTime = microtime(true);
            $resultEntregas = $this->progressService->executeCustomQuery($sqlEntregas);
            $endTime = microtime(true);

            $entregas = $resultEntregas['data']['results'] ?? [];
            $debug['steps'][3]['status'] = $resultEntregas['success'] ? 'success' : 'error';
            $debug['steps'][3]['data'] = [
                'total' => count($entregas),
                'entregas_sample' => $entregas,
                'query_time' => round($endTime - $startTime, 2) . 's'
            ];

            // ANÁLISE: Comparação Progress vs PHP
            $debug['analysis'] = [
                'progress_flow' => [
                    '1. Loop municipios rota (semPararRotMu) → t-entrega com IBGE, lat=0, lon=0',
                    '2. Loop entregas pacote (carga→pedido→arqrdnt) → t-entrega com GPS real',
                    '3. Se achou município pelo nome → ZERA GPS e mantém IBGE (linha 787-790)',
                    '4. Envia DATASET com mix: municípios (IBGE+0,0) + entregas (GPS+IBGE=0)',
                ],
                'php_current_implementation' => [
                    '1. Busca municípios → adiciona com IBGE, lat=0, lon=0 ✓',
                    '2. Busca entregas via getItinerarioPacote() → TIMEOUT/LENTO ❌',
                    '3. Não está chegando entregas com GPS ❌'
                ],
                'problem_identified' => 'Query de entregas está travando. Progress usa loop FOR EACH otimizado, PHP usa JOIN pesado.',
                'solution' => 'Simplificar query ou usar approach diferente para buscar arqrdnt'
            ];

            return response()->json([
                'success' => true,
                'debug' => $debug
            ]);

        } catch (\Exception $e) {
            // CORREÇÃO #4: Logar stack trace completo, retornar apenas mensagem genérica
            Log::error('Erro no debug flow', [
                'user_id' => $request->user()->id ?? null,
                'codpac' => $codPac,
                'cod_rota' => $codRota,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),  // ✅ Apenas em logs
                'timestamp' => now()->toIso8601String()
            ]);

            $debug['steps'][] = [
                'number' => 999,
                'name' => 'ERRO',
                'status' => 'error',
                'error' => 'Erro interno no processamento'  // ✅ Mensagem genérica
                // ❌ NÃO retornar: trace
            ];

            return response()->json([
                'success' => false,
                'debug' => $debug,
                'error' => 'Erro interno. Contate o suporte com timestamp: ' . now()->toIso8601String()
            ], 500);
        }
    }
}
