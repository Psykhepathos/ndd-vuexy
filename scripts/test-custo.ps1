$hoje = Get-Date -Format "yyyy-MM-dd"
$amanha = (Get-Date).AddDays(1).ToString("yyyy-MM-dd")

Write-Host "Testando custo..."
Write-Host "Hoje: $hoje"
Write-Host "Amanhã: $amanha"

$body = @{
    nome_rota = "TESTE_FASE2B_1761589800"
    placa = "ABC1234"
    eixos = 2
    data_inicio = $hoje
    data_fim = $amanha
} | ConvertTo-Json

Write-Host "Body: $body"

try {
    $resp = Invoke-RestMethod -Uri "http://localhost:8002/api/semparar/custo-rota" -Method POST -Body $body -ContentType "application/json"
    $resp | ConvertTo-Json -Depth 10
} catch {
    Write-Host "Error: $($_.Exception.Message)"
    Write-Host "Response: $($_.Exception.Response)"
}
