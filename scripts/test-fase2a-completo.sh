#!/bin/bash
# Script de teste completo FASE 2A
# Execute: bash test-fase2a-completo.sh

API_BASE="http://localhost:8002/api/semparar"
PLACA="HNE3C80"
HOJE=$(date +%Y-%m-%d)

echo "=========================================="
echo "🧪 TESTE COMPLETO FASE 2A"
echo "=========================================="
echo ""

# PASSO 1: Roteirizar praças
echo "1️⃣  Roteirizando SP → RJ..."
RESPONSE=$(curl -s -X POST "${API_BASE}/roteirizar" \
  -H "Content-Type: application/json" \
  -d '{
    "pontos": [
      {"cod_ibge": 3550308, "desc": "SAO PAULO - SP", "latitude": -23.5505199, "longitude": -46.6333094},
      {"cod_ibge": 3304557, "desc": "RIO DE JANEIRO - RJ", "latitude": -22.9068467, "longitude": -43.1728965}
    ],
    "alternativas": false
  }')

echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(f\"   ✅ {data['data']['total']} praças encontradas\")"

# Extrair IDs das praças
PRACA_IDS=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print('[' + ','.join(str(p['id']) for p in data['data']['pracas']) + ']')")
echo "   Praças: $PRACA_IDS"
echo ""

# PASSO 2: Cadastrar rota temporária
echo "2️⃣  Cadastrando rota temporária..."
RESPONSE=$(curl -s -X POST "${API_BASE}/rota-temporaria" \
  -H "Content-Type: application/json" \
  -d "{
    \"praca_ids\": $PRACA_IDS,
    \"nome_rota\": \"TESTE_FASE2A_$(date +%s)\"
  }")

NOME_ROTA=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(data['data']['nome_rota_semparar'])")
COD_ROTA=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(data['data']['cod_rota_semparar'])")
echo "   ✅ Rota cadastrada: $NOME_ROTA (código: $COD_ROTA)"
echo ""

# PASSO 3: Obter custo
echo "3️⃣  Calculando custo para placa $PLACA..."
RESPONSE=$(curl -s -X POST "${API_BASE}/custo-rota" \
  -H "Content-Type: application/json" \
  -d "{
    \"nome_rota\": \"$NOME_ROTA\",
    \"placa\": \"$PLACA\",
    \"eixos\": 2,
    \"data_inicio\": \"$HOJE\",
    \"data_fim\": \"$HOJE\"
  }")

VALOR=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(f\"R$ {data['data']['valor']:.2f}\")")
echo "   ✅ Custo calculado: $VALOR"
echo ""

# PASSO 4: Comprar viagem (FASE 2A!)
echo "4️⃣  COMPRANDO VIAGEM (FASE 2A)..."
echo "   ⚠️  ATENÇÃO: Esta operação EFETIVA a compra!"
read -p "   Deseja continuar? (s/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Ss]$ ]]; then
    RESPONSE=$(curl -s -X POST "${API_BASE}/comprar-viagem" \
      -H "Content-Type: application/json" \
      -d "{
        \"nome_rota\": \"$NOME_ROTA\",
        \"placa\": \"$PLACA\",
        \"eixos\": 2,
        \"data_inicio\": \"$HOJE\",
        \"data_fim\": \"$HOJE\",
        \"item_fin1\": \"PEDAGIO\"
      }")

    COD_VIAGEM=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(data['data']['cod_viagem'])")
    STATUS=$(echo "$RESPONSE" | python -c "import sys, json; data = json.load(sys.stdin); print(data['data']['status'])")

    echo ""
    echo "=========================================="
    echo "✅ VIAGEM COMPRADA COM SUCESSO!"
    echo "=========================================="
    echo "Código da Viagem: $COD_VIAGEM"
    echo "Status: $STATUS"
    echo "Placa: $PLACA"
    echo "Custo: $VALOR"
    echo "=========================================="
else
    echo "   ❌ Compra cancelada pelo usuário"
fi

echo ""
echo "🏁 Teste concluído!"
