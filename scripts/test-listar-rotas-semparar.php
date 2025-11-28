<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\SemPararSoapService;

try {
    echo "Listando rotas cadastradas no SemParar...\n\n";
    
    $service = new SemPararSoapService();
    $result = $service->listarRotas();
    $service->disconnect();
    
    if ($result['success']) {
        echo "Total de rotas encontradas: " . count($result['rotas']) . "\n\n";
        
        foreach ($result['rotas'] as $index => $rota) {
            echo ($index + 1) . ". " . $rota . "\n";
        }
    } else {
        echo "Erro ao listar rotas\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
