<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProgressService;

class TestProgressConnection extends Command
{
    protected $signature = 'progress:test';
    protected $description = 'Testa a conexão JDBC com o Progress e busca transportes';

    public function handle()
    {
        $this->info('=== TESTE CONEXÃO JDBC PROGRESS ===');
        $this->newLine();

        $progressService = new ProgressService();

        // Teste 1: Conexão
        $this->info('1. Testando conexão...');
        $connectionResult = $progressService->testConnection();

        if ($connectionResult['success']) {
            $this->info('✅ Conexão estabelecida!');
            $this->line('Dados: ' . json_encode($connectionResult['data'], JSON_PRETTY_PRINT));
        } else {
            $this->error('❌ Falha na conexão: ' . $connectionResult['error']);
            return 1;
        }

        $this->newLine();

        // Teste 2: Buscar 100 transportes
        $this->info('2. Buscando 100 primeiros transportes...');
        $transportesResult = $progressService->getTransportes(['limit' => 100]);

        if ($transportesResult['success']) {
            $this->info('✅ Consulta realizada com sucesso!');
            $this->info('Total de registros: ' . ($transportesResult['data']['total'] ?? 0));

            if (!empty($transportesResult['data']['transportes'])) {
                $this->newLine();
                $this->info('Primeiros 5 transportes:');
                $firstFive = array_slice($transportesResult['data']['transportes'], 0, 5);
                foreach ($firstFive as $transporte) {
                    $this->line('- Código: ' . ($transporte['codtrn'] ?? 'N/A') . 
                               ' | Nome: ' . ($transporte['nomtrn'] ?? 'N/A'));
                }
            }
        } else {
            $this->error('❌ Falha na consulta: ' . $transportesResult['error']);
        }

        $this->newLine();
        $this->info('=== FIM DO TESTE ===');

        return 0;
    }
}