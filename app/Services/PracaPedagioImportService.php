<?php

namespace App\Services;

use App\Models\PracaPedagio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PracaPedagioImportService
{
    /**
     * Importar CSV da ANTT
     */
    public function importarCSV(string $filePath): array
    {
        $imported = 0;
        $errors = [];
        $startTime = microtime(true);

        try {
            // Detectar encoding (Windows-1252 -> UTF-8)
            $content = file_get_contents($filePath);
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');

            // Salvar temporariamente
            $tempPath = sys_get_temp_dir() . '/pracas_utf8.csv';
            file_put_contents($tempPath, $content);

            // Abrir CSV
            $handle = fopen($tempPath, 'r');
            if (!$handle) {
                throw new \Exception('Não foi possível abrir o arquivo CSV');
            }

            // Ler header
            $header = fgetcsv($handle, 0, ';');

            // Processar linhas
            DB::beginTransaction();

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                try {
                    $data = array_combine($header, $row);

                    PracaPedagio::updateOrCreate(
                        [
                            'praca' => $data['praca_de_pedagio'],
                            'rodovia' => $data['rodovia'],
                            'km' => $this->parseDecimal($data['km_m'])
                        ],
                        [
                            'concessionaria' => $data['concessionaria'],
                            'uf' => $data['uf'],
                            'municipio' => $data['municipio'],
                            'ano_pnv' => $data['ano_do_pnv_snv'] ?: null,
                            'tipo_pista' => $data['tipo_pista'],
                            'sentido' => $data['sentido'],
                            'situacao' => $data['situacao'],
                            'data_inativacao' => $data['data_da_inativacao'] ?: null,
                            'latitude' => $this->parseDecimal($data['latitude']),
                            'longitude' => $this->parseDecimal($data['longitude']),
                            'fonte' => 'ANTT',
                            'data_importacao' => now()
                        ]
                    );

                    $imported++;

                } catch (\Exception $e) {
                    // CORREÇÃO BUG #71: Não incluir 'data' no erro (pode vazar informações sensíveis)
                    $errors[] = [
                        'line' => $imported + 2, // +2 = header + 1-indexed
                        'error' => $e->getMessage()
                    ];
                    Log::warning('Erro ao importar praça', [
                        'line' => $imported + 2,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            fclose($handle);
            unlink($tempPath);

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Importação de praças concluída', [
                'imported' => $imported,
                'errors' => count($errors),
                'duration' => $duration . 's'
            ]);

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'duration' => $duration
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erro fatal na importação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'imported' => $imported,
                'errors' => $errors
            ];
        }
    }

    /**
     * Converter string com vírgula para decimal
     */
    private function parseDecimal(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    /**
     * Limpar todas as praças (CUIDADO!)
     * CORREÇÃO BUG #72: Exigir confirmation code para prevenir truncate acidental
     * CORREÇÃO BUG #73: Logging LGPD completo com contexto de usuário
     */
    public function limparTudo(string $confirmationCode = '', ?array $userContext = null): bool
    {
        // Validação de confirmation code
        $expectedCode = 'DELETE_ALL_PRACAS_' . date('Y-m-d');
        if ($confirmationCode !== $expectedCode) {
            Log::warning('Tentativa de limpar praças sem confirmation code válido', [
                'expected_code' => $expectedCode,
                'provided_code' => $confirmationCode,
                'user_id' => $userContext['user_id'] ?? null,
                'ip' => $userContext['ip'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);
            throw new \Exception("Confirmation code inválido. Use: {$expectedCode}");
        }

        try {
            $count = PracaPedagio::count();
            PracaPedagio::truncate();

            // CORREÇÃO BUG #73: Logging LGPD completo incluindo quem executou
            Log::warning('Todas as praças foram removidas do banco', [
                'total_removidas' => $count,
                'admin_id' => $userContext['user_id'] ?? null,
                'admin_email' => $userContext['user_email'] ?? null,
                'ip' => $userContext['ip'] ?? null,
                'user_agent' => $userContext['user_agent'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao limpar praças', [
                'error' => $e->getMessage(),
                'user_id' => $userContext['user_id'] ?? null,
                'ip' => $userContext['ip'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);
            return false;
        }
    }

    /**
     * Estatísticas do banco
     */
    public function getEstatisticas(): array
    {
        return [
            'total' => PracaPedagio::count(),
            'ativas' => PracaPedagio::ativas()->count(),
            'inativas' => PracaPedagio::where('situacao', 'Inativo')->count(),
            'por_uf' => PracaPedagio::select('uf', DB::raw('count(*) as total'))
                ->groupBy('uf')
                ->orderBy('total', 'desc')
                ->get(),
            'por_concessionaria' => PracaPedagio::select('concessionaria', DB::raw('count(*) as total'))
                ->groupBy('concessionaria')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
        ];
    }
}
