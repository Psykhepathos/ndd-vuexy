# Análise Minuciosa - Fluxo de Compra de Viagem SemParar

**Data:** 2025-12-03
**URL:** http://localhost:8002/compra-viagem/nova
**Página Principal:** `resources/ts/pages/compra-viagem/nova.vue`

---

## 📋 Arquitetura do Sistema

### Split-Screen Layout
```
┌─────────────────────────────────────────────────────────┐
│                        Header                           │
├──────────────────┬──────────────────────────────────────┤
│  LEFT (4 cols)   │       RIGHT (8 cols)                 │
│  ┌────────────┐  │   ┌──────────────────────────────┐   │
│  │  Stepper   │  │   │                              │   │
│  │  Vertical  │  │   │    Mapa Fixo Leaflet         │   │
│  └────────────┘  │   │    (CompraViagemMapaFixo)    │   │
│                  │   │                              │   │
│  ┌────────────┐  │   │  • Exibe rota selecionada    │   │
│  │  VWindow   │  │   │  • Mostra entregas com GPS   │   │
│  │  (Steps)   │  │   │  • Atualiza em tempo real    │   │
│  └────────────┘  │   └──────────────────────────────┘   │
│                  │                                      │
│  ┌────────────┐  │                                      │
│  │  Botões    │  │                                      │
│  │  Nav       │  │                                      │
│  └────────────┘  │                                      │
└──────────────────┴──────────────────────────────────────┘
```

### Wizard de 5 Etapas
1. **Step 1: Pacote** - Selecionar pacote e carregar entregas
2. **Step 2: Placa** - Validar veículo no SemParar
3. **Step 3: Rota** - Escolher rota SemParar
4. **Step 4: Preço** - Calcular automaticamente o custo
5. **Step 5: Confirmação** - Revisar e efetivar compra

---

## 🔄 Fluxo Detalhado (Step by Step)

### STEP 1: Seleção de Pacote
**Arquivo:** `CompraViagemStep1Pacote.vue`
**Responsável:** Carregar pacote + entregas + auto-preencher placa

#### Endpoints Chamados:
1. **Autocomplete de Pacotes**
   - **URL:** `GET /api/pacotes/autocomplete?search={termo}`
   - **Trigger:** Ao digitar no campo de busca (mínimo 2 caracteres)
   - **Retorno:** Lista de pacotes com formato
   ```json
   {
     "success": true,
     "data": [{
       "codpac": 3043368,
       "nomtrn": "TRANSPORTADORA X",
       "sitpac": "FECHADO"
     }]
   }
   ```

2. **Carregar Itinerário**
   - **URL:** `POST /api/pacotes/itinerario`
   - **Body:** `{ "codPac": 3043368 }`
   - **Trigger:** Ao selecionar um pacote no autocomplete
   - **Retorno:** Pedidos/entregas do pacote
   ```json
   {
     "success": true,
     "data": {
       "placa": "ABC1234",  // ⚠️ AUTO-PREENCHE Step 2
       "transportador": "TRANSP X",
       "pedidos": [{
         "numseqped": 123,
         "razcli": "Cliente A",
         "gps_lat": "230876543",
         "gps_lon": "460123456"
       }]
     }
   }
   ```

#### Processamento de Dados:
**Linha 164-178:** Função `processGpsCoordinate()` converte coordenadas do Progress
```typescript
// Formato 1: "-23,0876543" → -23.0876543
if (coord.includes(',')) {
  return parseFloat(coord.replace(',', '.'))
}

// Formato 2: "230876543" → -23.0876543
const num = parseInt(coord)
if (Math.abs(num) > 1000000) {
  return num / 10000000  // Divide por 10 milhões
}
```

#### Dados Salvos no FormData:
- `pacote.pacote`: Objeto pacote completo
- `pacote.entregas`: Todas as entregas (array)
- `pacote.entregas_com_gps`: Apenas entregas com lat/lon válidos
- `placa.placa`: **AUTO-PREENCHIDO** (linha 128)
- `placa.proprietario`: Nome do transportador

#### Validação:
✅ **Step completo quando:** `pacoteSelecionado.value !== null`

---

### STEP 2: Validação de Placa
**Arquivo:** `CompraViagemStep2Placa.vue`
**Responsável:** Validar veículo no sistema SemParar via SOAP

#### Endpoint Chamado:
**URL:** `POST /api/compra-viagem/validar-placa`
**Body:**
```json
{
  "placa": "ABC1234"
}
```
**Trigger:** Ao perder foco do campo (blur) ou Enter

#### Fluxo:
1. Usuário digita/confirma placa (pode estar auto-preenchida do Step 1)
2. Frontend chama `/validar-placa`
3. **Backend chama SOAP SemParar** `statusVeiculo()`
4. Retorna dados do veículo:
   ```json
   {
     "success": true,
     "data": {
       "descricao": "CAMINHÃO VOLVO FH 540",
       "eixos": 9,
       "proprietario": "TRANSPORTADORA X",
       "tag": "TAG123456"
     },
     "soap_real": true  // true = chamada SOAP real, false = simulado
   }
   ```
5. Frontend mostra **dialog de confirmação** com os dados
6. Usuário pode **editar eixos manualmente** (linha 377)
7. Ao confirmar, salva no FormData

#### ⚠️ Ponto de Atenção - Edição de Eixos:
**Linha 377:** Usuário pode manipular o número de eixos no dialog
```vue
<VTextField
  v-model.number="eixos"
  type="number"
  min="2"
  max="10"
/>
```

**Validação:**
- ❌ Frontend: Apenas `min="2"` `max="10"` no HTML (bypassável)
- ❌ Backend: **NÃO valida se eixos foram alterados!**
- 🔴 **Vulnerabilidade:** Usuário pode pagar por 2 eixos mas informar 9 eixos

#### Validação:
✅ **Step completo quando:** `placa !== '' && step2Completo === true`

---

### STEP 3: Escolha de Rota SemParar
**Arquivo:** `CompraViagemStep3Rota.vue`
**Responsável:** Selecionar rota pré-cadastrada no Progress

#### Endpoints Chamados:
1. **Autocomplete de Rotas**
   - **URL:** `GET /api/compra-viagem/rotas?search={termo}&flg_cd={bool}`
   - **Trigger:** Ao digitar no campo de busca
   - **Retorno:**
   ```json
   {
     "success": true,
     "data": [{
       "value": 204,
       "title": "ROTA SP-RJ",
       "subtitle": "Rota | 12 municípios | 5 dias",
       "flgcd": false,
       "flgretorno": false,
       "tempoviagem": 5
     }]
   }
   ```

2. **Validar Rota Selecionada**
   - **URL:** `POST /api/compra-viagem/validar-rota`
   - **Body:**
   ```json
   {
     "codpac": 3043368,
     "cod_rota": 204,
     "flgcd": false,
     "flgretorno": false
   }
   ```
   - **Trigger:** Ao selecionar rota no autocomplete
   - **Validações Backend:**
     1. Rota existe?
     2. Rota é CD quando deveria ser?
     3. Rota é Retorno quando deveria ser?
     4. **Já existe viagem comprada para este pacote/rota?** 🔴
   - **Retorno:**
   ```json
   {
     "success": true,
     "data": {
       "rota": { ...dadosDaRota },
       "data_inicio": "2025-12-03",
       "data_fim": "2025-12-08",
       "tempo_viagem_dias": 5
     }
   }
   ```

#### Switches de Modo:
- **Modo CD:** Para Centro de Distribuição (TCD)
- **Modo Retorno:** Para viagens de volta

#### Validação:
✅ **Step completo quando:** Rota validada com sucesso pelo backend

---

### STEP 4: Cálculo de Preço
**Arquivo:** `CompraViagemStep4Preco.vue`
**Responsável:** Calcular custo da viagem via SemParar SOAP

#### Endpoint Chamado:
**URL:** `POST /api/compra-viagem/verificar-preco`
**Body:**
```json
{
  "codpac": 3043368,
  "cod_rota": 204,
  "qtd_eixos": 9,
  "placa": "ABC1234",
  "data_inicio": "2025-12-03",
  "data_fim": "2025-12-08"
}
```

#### Fluxo Backend (Progress-style):
1. Backend busca municípios da rota no Progress (`PUB.semPararRotMu`)
2. **Cria rota temporária no SemParar** via SOAP `cadastrarRotaTemporaria()`
3. **Calcula preço** via SOAP `obterCustoRota()`
4. Retorna:
```json
{
  "success": true,
  "data": {
    "valor": 1234.56,
    "numero_viagem": "TEMP_123456",
    "nome_rota_semparar": "ROTA_TEMP_204_3043368",
    "cod_rota_semparar": "TEMP_204_3043368",
    "pracas": [
      {
        "id": 1030,
        "nome": "RÉGIS BITTENCOURT KM 422",
        "cidade": "REGISTRO",
        "uf": "SP",
        "valor": 58.40
      }
    ],
    "soap_real": true
  },
  "test_mode": false
}
```

#### Auto-execução:
**Linha 48:** Cálculo é **automático** ao entrar no step
```typescript
if (!props.formData.preco.calculado) {
  await calcularPreco()
}
```

#### Validação:
✅ **Step completo quando:** `preco.calculado === true`

---

### STEP 5: Confirmação e Compra
**Arquivo:** `CompraViagemStep5Confirmacao.vue`
**Responsável:** Revisar dados e efetivar compra

#### Resumo Exibido:
```
┌─────────────────────────────────────┐
│ Pacote #3043368                     │
│ Transportador: TRANSP X             │
├─────────────────────────────────────┤
│ Veículo: ABC1234                    │
│ Eixos: 9                            │
│ Descrição: VOLVO FH 540             │
├─────────────────────────────────────┤
│ Rota: ROTA SP-RJ                    │
│ Municípios: 12                      │
│ Praças: 6                           │
├─────────────────────────────────────┤
│ Período: 03/12/2025 - 08/12/2025    │
│ Duração: 5 dias                     │
├─────────────────────────────────────┤
│ VALOR TOTAL: R$ 1.234,56            │
└─────────────────────────────────────┘

[Confirmar e Comprar Viagem]
```

#### Endpoint de Compra:
**URL:** `POST /api/compra-viagem/comprar`
**Linha 103:** Chamada fetch direta
**Body:**
```json
{
  "codpac": 3043368,
  "cod_rota": 204,
  "placa": "ABC1234",
  "qtd_eixos": 9,
  "data_inicio": "2025-12-03",
  "data_fim": "2025-12-08",
  "nome_rota_semparar": "ROTA_TEMP_204_3043368",
  "cod_rota_semparar": "TEMP_204_3043368",
  "valor_viagem": 1234.56,
  "flgcd": false,
  "flgretorno": false
}
```

#### Fluxo Backend Compra:
1. **Validação ALLOW_SOAP_PURCHASE** (linha 698)
   - Se `false`: Retorna erro 403 "COMPRA BLOQUEADA"
   - Se `true`: Prossegue com compra real

2. **Busca dados do pacote** (codtrn necessário)

3. **⚠️ PONTO CRÍTICO:** Chama SOAP `comprarViagem()` (linha 731)
   ```php
   $resultadoCompra = $this->semPararService->comprarViagem(
       $validated['nome_rota_semparar'],
       $validated['placa'],
       $validated['qtd_eixos'],  // ⚠️ Eixos do frontend sem re-validação!
       $validated['data_inicio'],
       $validated['data_fim'],
       (string)$validated['codpac']
   );
   ```

4. **Salva no Progress** `PUB.sPararViagem` (linha 782)

5. **Salva log de municípios** `PUB.semPararRotMuLog` (linha 802)

6. Retorna sucesso:
   ```json
   {
     "success": true,
     "message": "Viagem comprada com sucesso!",
     "data": {
       "numero_viagem": "91234567",
       "codpac": 3043368,
       "rota": "ROTA SP-RJ",
       "placa": "ABC1234",
       "valor": 1234.56,
       "data_compra": "2025-12-03 15:30:00"
     }
   }
   ```

#### Após Compra Bem-Sucedida:
**Linha 121:** Emite evento `comprar`
```typescript
emit('comprar')  // Volta para página principal (nova.vue linha 152)
```

Frontend exibe:
```
✅ Viagem Comprada com Sucesso!
Número da Viagem: 91234567

[Nova Compra]  [Ver Viagens]
```

---

## 🚨 VULNERABILIDADES IDENTIFICADAS

### 🔴 CRÍTICA #1: Race Condition em Validação de Duplicatas

**Localização:** Between Step 3 validação e Step 5 compra

**Problema:**
1. **Step 3 (linha 97):** `validar-rota` verifica se viagem já existe
   ```typescript
   // Usuário A valida rota às 15:00:00 → OK (sem duplicata)
   ```

2. **Tempo passa...** (usuário preenche outros dados, 30-60 segundos)

3. **Step 5 (linha 103):** `comprar` **NÃO verifica duplicata novamente!**
   ```typescript
   // Usuário B valida rota às 15:00:30 → OK (sem duplicata)
   // Usuário A compra às 15:00:45 → SUCESSO
   // Usuário B compra às 15:01:00 → SUCESSO ❌ DUPLICATA!
   ```

**Cenário Real:**
```
15:00:00 - Usuário A abre Step 3, valida pacote #3043368 + rota #204 → OK
15:00:30 - Usuário B abre Step 3, valida pacote #3043368 + rota #204 → OK
15:00:45 - Usuário A clica "Confirmar Compra" → Viagem #9123 criada ✅
15:01:00 - Usuário B clica "Confirmar Compra" → Viagem #9124 criada ✅ (DUPLICATA!)
```

**Impacto:**
- Compra duplicada para mesmo pacote/rota
- Prejuízo financeiro real
- Inconsistência no Progress

**Backend Vulnerável:**
**CompraViagemController.php linha 672:** `comprarViagem()` **NÃO re-valida duplicatas**

**Correção Necessária:**
```php
// ADICIONAR ANTES DA LINHA 698 (verificação ALLOW_SOAP_PURCHASE)
$viagemCheck = $this->progressService->viagemJaComprada(
    $validated['codpac'],
    $validated['cod_rota']
);

if ($viagemCheck['duplicada']) {
    return response()->json([
        'success' => false,
        'error' => 'Viagem já foi comprada por outro usuário',
        'code' => 'VIAGEM_JA_COMPRADA',
        'viagem_existente' => $viagemCheck['viagem']
    ], 409); // 409 Conflict
}
```

---

### 🔴 CRÍTICA #2: Manipulação de Eixos no Frontend

**Localização:** Step 2 (CompraViagemStep2Placa.vue linha 377)

**Problema:**
1. Backend retorna eixos real: `"eixos": 9`
2. Frontend permite editar no dialog:
   ```vue
   <VTextField
     v-model.number="eixos"
     type="number"
     min="2"
     max="10"
   />
   ```
3. Usuário altera para `2` (mais barato)
4. Backend **aceita sem questionar** (CompraViagemController.php linha 731)

**Cenário de Fraude:**
```
1. Veículo real: VOLVO FH 540 - 9 eixos
2. SemParar retorna: eixos=9, valor=R$ 1.234,56
3. Usuário edita para: eixos=2
4. Backend calcula preço para 2 eixos: valor=R$ 284,20
5. Compra é efetivada com 2 eixos (FRAUDE!)
```

**Impacto:**
- Prejuízo financeiro (diferença entre categorias)
- Veículo 9 eixos pagando pedágio de 2 eixos
- Possível bloqueio pela concessionária

**Backend Vulnerável:**
**CompraViagemController.php linha 238-303:** `validarPlaca()` retorna eixos mas não salva
**CompraViagemController.php linha 731:** `comprarViagem()` usa eixos do frontend sem re-validação

**Correção Necessária:**
```php
// Em comprarViagem() ANTES da linha 723
// Re-validar placa para obter eixos reais
$resultValidacao = $this->progressService->validateVehicleStatusSemParar(
    $validated['placa'],
    false  // Chamada real
);

if (!$resultValidacao['success']) {
    return response()->json([
        'success' => false,
        'error' => 'Falha ao re-validar veículo',
        'code' => 'VEICULO_INVALIDO'
    ], 400);
}

$eixosReais = $resultValidacao['data']['eixos'];

// Verificar se eixos foram alterados
if ($validated['qtd_eixos'] != $eixosReais) {
    Log::warning('Tentativa de manipulação de eixos', [
        'placa' => $validated['placa'],
        'eixos_reais' => $eixosReais,
        'eixos_informados' => $validated['qtd_eixos']
    ]);

    return response()->json([
        'success' => false,
        'error' => sprintf(
            'Número de eixos incorreto. Veículo possui %d eixos, não %d.',
            $eixosReais,
            $validated['qtd_eixos']
        ),
        'code' => 'EIXOS_INVALIDOS'
    ], 400);
}

// Prosseguir com compra usando $eixosReais
```

---

### 🔴 CRÍTICA #3: Sem Autenticação nos Endpoints

**Localização:** Todos os endpoints de compra-viagem

**Problema:**
Nenhum endpoint tem `auth:sanctum` middleware:
```php
// routes/api.php
Route::post('/compra-viagem/comprar', [CompraViagemController::class, 'comprarViagem']);
// ⚠️ SEM MIDDLEWARE auth:sanctum!
```

**Impacto:**
- Qualquer pessoa na rede pode comprar viagens
- Sem controle de quem está comprando
- Logs com `usuario = 'SYSTEM'` (linha 779)

**Cenário de Ataque:**
```bash
# Atacante externo envia POST direto
curl -X POST http://192.168.x.x:8002/api/compra-viagem/comprar \
  -H "Content-Type: application/json" \
  -d '{"codpac": 123, "cod_rota": 204, ...}'
# Compra é efetivada sem autenticação!
```

**Correção Necessária:**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/compra-viagem/validar-pacote', [CompraViagemController::class, 'validarPacote']);
    Route::post('/compra-viagem/validar-placa', [CompraViagemController::class, 'validarPlaca']);
    Route::get('/compra-viagem/rotas', [CompraViagemController::class, 'listarRotas']);
    Route::post('/compra-viagem/validar-rota', [CompraViagemController::class, 'validarRota']);
    Route::post('/compra-viagem/verificar-preco', [CompraViagemController::class, 'verificarPreco']);
    Route::post('/compra-viagem/comprar', [CompraViagemController::class, 'comprarViagem']);
});

// Endpoints públicos (info apenas)
Route::get('/compra-viagem/initialize', [CompraViagemController::class, 'initialize']);
Route::get('/compra-viagem/health', [CompraViagemController::class, 'health']);
```

**⚠️ ATENÇÃO FRONTEND:** Precisará incluir token:
```typescript
const token = localStorage.getItem('accessToken')  // Ou onde guardam
const response = await fetch(url, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
})
```

---

### 🟡 MÉDIA #4: Sem Rate Limiting

**Problema:** Nenhuma proteção contra abuso

**Cenários:**
- Brute force de códigos de pacote
- Spam de validações de placa
- DoS no SemParar SOAP

**Correção:**
```php
// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    // 60 requests/minuto - validações normais
});

Route::middleware(['throttle:10,1'])->group(function () {
    // 10 requests/minuto - compras
    Route::post('/compra-viagem/comprar', ...);
});
```

---

### 🟡 MÉDIA #5: Logs com Dados Sensíveis (LGPD)

**Problema:** Placas e valores são logados sem sanitização

**Exemplos:**
- CompraViagemController.php linha 245: `'placa' => $validated['placa']`
- CompraViagemController.php linha 692: `'placa' => $validated['placa']`

**Impacto:** Violação LGPD (placas são dados pessoais)

**Correção:** Implementar função de sanitização (conforme AUDITORIA_COMPRAVIAGEM_CONTROLLER.md)

---

## 📊 Resumo de Endpoints

| Step | Endpoint | Método | Autenticado? | Rate Limit? | Vulnerável? |
|------|----------|--------|--------------|-------------|-------------|
| 1 | `/api/pacotes/autocomplete` | GET | ❌ Não | ❌ Não | 🟡 Média |
| 1 | `/api/pacotes/itinerario` | POST | ❌ Não | ❌ Não | 🟡 Média |
| 2 | `/api/compra-viagem/validar-placa` | POST | ❌ Não | ❌ Não | 🔴 Crítica (#2) |
| 3 | `/api/compra-viagem/rotas` | GET | ❌ Não | ❌ Não | 🟡 Média |
| 3 | `/api/compra-viagem/validar-rota` | POST | ❌ Não | ❌ Não | 🔴 Crítica (#1) |
| 4 | `/api/compra-viagem/verificar-preco` | POST | ❌ Não | ❌ Não | 🟡 Média |
| 5 | `/api/compra-viagem/comprar` | POST | ❌ Não | ❌ Não | 🔴 CRÍTICA (#1 #2 #3) |

---

## ✅ Pontos Positivos do Sistema

1. ✅ **UX Excelente:** Wizard intuitivo com validação progressiva
2. ✅ **Split-Screen:** Mapa ao vivo mostrando rota/entregas
3. ✅ **Auto-preenchimento:** Placa vem do pacote automaticamente
4. ✅ **Validação por etapas:** Não avança sem completar step
5. ✅ **Feedback visual:** Loading, confirmações, alertas bem implementados
6. ✅ **Integração SOAP:** Backend gerencia complexidade da API SemParar
7. ✅ **Modo seguro:** Flag `ALLOW_SOAP_PURCHASE` protege contra compras acidentais

---

## 🔧 Checklist de Correções URGENTES

### Antes de Produção (OBRIGATÓRIO):
- [ ] **#1 CRÍTICO:** Adicionar re-validação de duplicatas em `comprarViagem()`
- [ ] **#2 CRÍTICO:** Re-validar eixos em `comprarViagem()` e bloquear manipulação
- [ ] **#3 CRÍTICO:** Adicionar `auth:sanctum` em todos os endpoints sensíveis
- [ ] **#4 MÉDIO:** Implementar rate limiting (throttle)
- [ ] **#5 MÉDIO:** Sanitizar logs (LGPD)

### Após Produção (Melhorias):
- [ ] Adicionar idempotency_key para evitar double-click
- [ ] Implementar audit trail completo
- [ ] Validar formato de placa brasileira
- [ ] Limitar período máximo de viagem (30 dias)

---

## 📝 Conclusão

**Sistema bem arquitetado com UX excepcional**, mas com **vulnerabilidades críticas de segurança** que podem causar:
- Compras duplicadas (prejuízo financeiro)
- Fraude em categoria de eixos (prejuízo financeiro)
- Acesso não autorizado (sem autenticação)

**Recomendação:** **NÃO LIBERAR EM PRODUÇÃO** até implementar correções #1, #2 e #3.

---

**Análise realizada por:** Claude Code Assistant
**Metodologia:** Auditoria de código linha por linha + análise de fluxo completo
**Próxima ação:** Implementar correções críticas
