# Script de teste completo FASE 2A
# Execute: powershell -ExecutionPolicy Bypass -File test-fase2a-completo.ps1

$API_BASE = "http://localhost:8002/api/semparar"
$PLACA = "HNE3C80"
$HOJE = Get-Date -Format "yyyy-MM-dd"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "🧪 TESTE COMPLETO FASE 2A" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# PASSO 1: Roteirizar praças
Write-Host "1️⃣  Roteirizando SP → RJ..." -ForegroundColor Green

$body1 = @{
    pontos = @(
        @{cod_ibge = 3550308; desc = "SAO PAULO - SP"; latitude = -23.5505199; longitude = -46.6333094},
        @{cod_ibge = 3304557; desc = "RIO DE JANEIRO - RJ"; latitude = -22.9068467; longitude = -43.1728965}
    )
    alternativas = $false
} | ConvertTo-Json -Depth 10

$response1 = Invoke-RestMethod -Uri "$API_BASE/roteirizar" -Method Post -Body $body1 -ContentType "application/json"
Write-Host "   ✅ $($response1.data.total) praças encontradas" -ForegroundColor Green

$pracaIds = $response1.data.pracas | ForEach-Object { $_.id }
Write-Host "   Praças: $($pracaIds -join ', ')" -ForegroundColor Gray
Write-Host ""

# PASSO 2: Cadastrar rota temporária
Write-Host "2️⃣  Cadastrando rota temporária..." -ForegroundColor Green

$timestamp = [int][double]::Parse((Get-Date -UFormat %s))
$nomeRotaTeste = "TESTE_FASE2A_$timestamp"

$body2 = @{
    praca_ids = $pracaIds
    nome_rota = $nomeRotaTeste
} | ConvertTo-Json

$response2 = Invoke-RestMethod -Uri "$API_BASE/rota-temporaria" -Method Post -Body $body2 -ContentType "application/json"
$NOME_ROTA = $response2.data.nome_rota_semparar
$COD_ROTA = $response2.data.cod_rota_semparar

Write-Host "   ✅ Rota cadastrada: $NOME_ROTA (código: $COD_ROTA)" -ForegroundColor Green
Write-Host ""

# PASSO 3: Obter custo
Write-Host "3️⃣  Calculando custo para placa $PLACA..." -ForegroundColor Green

$body3 = @{
    nome_rota = $NOME_ROTA
    placa = $PLACA
    eixos = 2
    data_inicio = $HOJE
    data_fim = $HOJE
} | ConvertTo-Json

$response3 = Invoke-RestMethod -Uri "$API_BASE/custo-rota" -Method Post -Body $body3 -ContentType "application/json"
$VALOR = $response3.data.valor

Write-Host "   ✅ Custo calculado: R$ $($VALOR.ToString('F2'))" -ForegroundColor Green
Write-Host ""

# PASSO 4: Comprar viagem (FASE 2A!)
Write-Host "4️⃣  COMPRANDO VIAGEM (FASE 2A)..." -ForegroundColor Yellow
Write-Host "   ⚠️  ATENÇÃO: Esta operação EFETIVA a compra!" -ForegroundColor Red

$confirmation = Read-Host "   Deseja continuar? (s/n)"

if ($confirmation -eq "s" -or $confirmation -eq "S") {
    $body4 = @{
        nome_rota = $NOME_ROTA
        placa = $PLACA
        eixos = 2
        data_inicio = $HOJE
        data_fim = $HOJE
        item_fin1 = "PEDAGIO"
        item_fin2 = ""
        item_fin3 = ""
    } | ConvertTo-Json

    $response4 = Invoke-RestMethod -Uri "$API_BASE/comprar-viagem" -Method Post -Body $body4 -ContentType "application/json"

    Write-Host ""
    Write-Host "==========================================" -ForegroundColor Cyan
    Write-Host "✅ VIAGEM COMPRADA COM SUCESSO!" -ForegroundColor Green
    Write-Host "==========================================" -ForegroundColor Cyan
    Write-Host "Código da Viagem: $($response4.data.cod_viagem)" -ForegroundColor White
    Write-Host "Status: $($response4.data.status)" -ForegroundColor White
    Write-Host "Placa: $PLACA" -ForegroundColor White
    Write-Host "Custo: R$ $($VALOR.ToString('F2'))" -ForegroundColor White
    Write-Host "==========================================" -ForegroundColor Cyan
} else {
    Write-Host "   ❌ Compra cancelada pelo usuário" -ForegroundColor Red
}

Write-Host ""
Write-Host "🏁 Teste concluído!" -ForegroundColor Green
