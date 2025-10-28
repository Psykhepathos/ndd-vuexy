# Test script for FASE 2B - Progress Database Persistence
# Tests complete workflow: roteirizar → cadastrar → custo → comprar → verify DB

$API_BASE = "http://localhost:8002/api/semparar"
$PROGRESS_API = "http://localhost:8002/api/progress/query"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "TESTE FASE 2B - PERSISTÊNCIA NO PROGRESS" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Test data
$hoje = Get-Date -Format "yyyy-MM-dd"
$amanha = (Get-Date).AddDays(1).ToString("yyyy-MM-dd")

# FASE 2B test data (Progress fields)
$codPac = 3043368  # Package ID from Progress
$codTrn = 5576     # Transporter ID
$sPararRotID = 204 # SemParar route ID
$placa = "ABC1234"

Write-Host "Dados do teste:" -ForegroundColor Yellow
Write-Host "  Pacote: $codPac"
Write-Host "  Transportador: $codTrn"
Write-Host "  Rota SemParar ID: $sPararRotID"
Write-Host "  Placa: $placa"
Write-Host "  Data início: $hoje"
Write-Host "  Data fim: $amanha`n"

# Step 1: Roteirizar
Write-Host "[1/5] Roteirizando municípios..." -ForegroundColor Yellow
$bodyRoteirizar = @{
    pontos = @(
        @{ cod_ibge = 3118601; desc = "CONTAGEM - MG"; latitude = -19.9384589; longitude = -44.0518344 },
        @{ cod_ibge = 3136306; desc = "JOAO PINHEIRO - MG"; latitude = -17.7406406; longitude = -46.1743626 },
        @{ cod_ibge = 3147006; desc = "PARACATU - MG"; latitude = -17.2250251; longitude = -46.8680057 },
        @{ cod_ibge = 3170404; desc = "UNAI - MG"; latitude = -16.3577794; longitude = -46.9062823 }
    )
    alternativas = $false
} | ConvertTo-Json -Depth 10

$responseRoteirizar = Invoke-RestMethod -Uri "$API_BASE/roteirizar" -Method POST -Body $bodyRoteirizar -ContentType "application/json"

if (-not $responseRoteirizar.success) {
    Write-Host "❌ ERRO na roteirização: $($responseRoteirizar.message)" -ForegroundColor Red
    exit 1
}

$pracaIds = $responseRoteirizar.data.pracas | ForEach-Object { $_.id }
Write-Host "✅ Roteirização OK - $($pracaIds.Count) praças encontradas" -ForegroundColor Green
Write-Host "   IDs: $($pracaIds -join ', ')`n"

# Step 2: Cadastrar rota temporária
Write-Host "[2/5] Cadastrando rota temporária..." -ForegroundColor Yellow
$timestamp = [DateTimeOffset]::Now.ToUnixTimeSeconds()
$nomeRota = "TESTE_FASE2B_$timestamp"

$bodyCadastrar = @{
    praca_ids = $pracaIds
    nome_rota = $nomeRota
} | ConvertTo-Json

$responseCadastrar = Invoke-RestMethod -Uri "$API_BASE/rota-temporaria" -Method POST -Body $bodyCadastrar -ContentType "application/json"

if (-not $responseCadastrar.success) {
    Write-Host "❌ ERRO ao cadastrar rota: $($responseCadastrar.message)" -ForegroundColor Red
    exit 1
}

Write-Host "✅ Rota temporária criada: $nomeRota`n" -ForegroundColor Green

# Step 3: Obter custo da rota
Write-Host "[3/5] Obtendo custo da rota..." -ForegroundColor Yellow
$bodyCusto = @{
    nome_rota = $nomeRota
    placa = $placa
    eixos = 2
    data_inicio = $hoje
    data_fim = $amanha
} | ConvertTo-Json

$responseCusto = Invoke-RestMethod -Uri "$API_BASE/custo-rota" -Method POST -Body $bodyCusto -ContentType "application/json"

if (-not $responseCusto.success) {
    Write-Host "❌ ERRO ao obter custo: $($responseCusto.message)" -ForegroundColor Red
    exit 1
}

$valorViagem = [decimal]$responseCusto.data.valor
Write-Host "✅ Custo obtido: R$ $valorViagem`n" -ForegroundColor Green

# Step 4: Comprar viagem COM persistência no Progress
Write-Host "[4/5] Comprando viagem COM persistência no Progress..." -ForegroundColor Yellow
$bodyComprar = @{
    nome_rota = $nomeRota
    placa = $placa
    eixos = 2
    data_inicio = $hoje
    data_fim = $amanha
    item_fin1 = "PEDAGIO"
    # FASE 2B - Progress fields
    cod_pac = $codPac
    cod_trn = $codTrn
    cod_rota_create_sp = $nomeRota
    s_parar_rot_id = $sPararRotID
    valor_viagem = $valorViagem
    res_compra = "sistema_teste"
} | ConvertTo-Json

try {
    $responseComprar = Invoke-RestMethod -Uri "$API_BASE/comprar-viagem" -Method POST -Body $bodyComprar -ContentType "application/json"

    if (-not $responseComprar.success) {
        Write-Host "❌ ERRO na compra: $($responseComprar.message)" -ForegroundColor Red
        exit 1
    }

    $codViagem = $responseComprar.data.cod_viagem
    $progressSaved = $responseComprar.data.progress_saved

    Write-Host "✅ Compra efetuada com sucesso!" -ForegroundColor Green
    Write-Host "   Código da viagem: $codViagem"

    if ($progressSaved) {
        Write-Host "   ✅ SALVO NO PROGRESS DATABASE!" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  NÃO foi salvo no Progress" -ForegroundColor Yellow
        if ($responseComprar.data.progress_error) {
            Write-Host "   Erro: $($responseComprar.data.progress_error)" -ForegroundColor Red
        }
    }
    Write-Host ""

} catch {
    Write-Host "❌ ERRO na requisição de compra: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Step 5: Verificar no banco Progress
Write-Host "[5/5] Verificando registro no Progress..." -ForegroundColor Yellow
$sqlVerificar = "SELECT * FROM PUB.sPararViagem WHERE codviagem = '$codViagem'"
$bodyVerificar = @{ sql = $sqlVerificar } | ConvertTo-Json

try {
    $responseVerificar = Invoke-RestMethod -Uri $PROGRESS_API -Method POST -Body $bodyVerificar -ContentType "application/json"

    if ($responseVerificar.success -and $responseVerificar.data.results.Count -gt 0) {
        $registro = $responseVerificar.data.results[0]

        Write-Host "✅ REGISTRO ENCONTRADO NO PROGRESS!" -ForegroundColor Green
        Write-Host "`nDetalhes do registro:" -ForegroundColor Cyan
        Write-Host "  Código Viagem: $($registro.codviagem)"
        Write-Host "  Código Pacote: $($registro.codpac)"
        Write-Host "  Placa: $($registro.numpla)"
        Write-Host "  Nome Rota: $($registro.nomrotsemparar)"
        Write-Host "  Valor Viagem: R$ $($registro.valviagem)"
        Write-Host "  Código Transportador: $($registro.codtrn)"
        Write-Host "  Rota Create SP: $($registro.codrotcreatesp)"
        Write-Host "  SemParar Rota ID: $($registro.spararrotid)"
        Write-Host "  Responsável: $($registro.rescompra)"
        Write-Host "  Data Compra: $($registro.datacompra)"
        Write-Host "  Cancelado: $($registro.flgcancelado)`n"

        # Validate data integrity
        $erros = @()
        if ($registro.codpac -ne $codPac) { $erros += "codpac esperado $codPac, encontrado $($registro.codpac)" }
        if ($registro.numpla -ne $placa.ToUpper()) { $erros += "placa esperada $placa, encontrada $($registro.numpla)" }
        if ($registro.codtrn -ne $codTrn) { $erros += "codtrn esperado $codTrn, encontrado $($registro.codtrn)" }
        if ($registro.spararrotid -ne $sPararRotID) { $erros += "spararrotid esperado $sPararRotID, encontrado $($registro.spararrotid)" }

        if ($erros.Count -eq 0) {
            Write-Host "✅ VALIDAÇÃO: Todos os dados estão corretos!" -ForegroundColor Green
        } else {
            Write-Host "⚠️  VALIDAÇÃO: Encontrados problemas nos dados:" -ForegroundColor Yellow
            foreach ($erro in $erros) {
                Write-Host "   - $erro" -ForegroundColor Yellow
            }
        }

    } else {
        Write-Host "❌ REGISTRO NÃO ENCONTRADO NO PROGRESS!" -ForegroundColor Red
        Write-Host "   Código da viagem procurado: $codViagem"
        Write-Host "   SQL executado: $sqlVerificar"
    }

} catch {
    Write-Host "❌ ERRO ao verificar banco: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "TESTE FASE 2B CONCLUÍDO" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan
