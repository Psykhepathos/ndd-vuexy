# 🚨 ALERTA DE SEGURANÇA CRÍTICO

**Data:** 2025-12-04
**Horário:** 09:15 (UTC-3)
**Severidade:** 🔴 **CRÍTICA** - Falha de segurança em endpoints financeiros
**Status:** ⚠️ **NÃO CORRIGIDO** - Requer ação imediata

---

## ⚠️ RESUMO EXECUTIVO

Identificada **falha crítica de segurança** no sistema de compra de viagens SemParar que permite:
- ✅ Qualquer pessoa **comprar viagens** sem autenticação
- ✅ Qualquer pessoa **cancelar viagens** de terceiros
- ✅ Qualquer pessoa **consultar histórico** de todas as viagens
- ✅ Qualquer pessoa **reemitir viagens** com nova placa

**Impacto Financeiro:** Sistema pode sofrer **fraude financeira** e **perda de receita**.

**Impacto Legal:** Violação da **LGPD** (Lei Geral de Proteção de Dados) por falta de logging e controle de acesso.

---

## 📋 ENDPOINTS VULNERÁVEIS

### 🔴 CRÍTICO: Operações Financeiras Desprotegidas

| Endpoint | Linha (api.php) | Impacto | Autenticação |
|----------|-----------------|---------|--------------|
| `POST /api/semparar/comprar-viagem` | 206-207 | Compra viagens sem autenticação | ❌ PÚBLICO |
| `POST /api/semparar/cancelar-viagem` | 218-219 | Cancela viagens de terceiros | ❌ PÚBLICO |
| `POST /api/semparar/consultar-viagens` | 216-217 | Expõe histórico completo | ❌ PÚBLICO |
| `POST /api/semparar/reemitir-viagem` | 220-221 | Altera placa sem controle | ❌ PÚBLICO |

### ⚠️ ALTO: Endpoints Sensíveis

| Endpoint | Linha (api.php) | Impacto | Autenticação |
|----------|-----------------|---------|--------------|
| `POST /api/semparar/gerar-recibo` | 212-213 | Envia recibo por WhatsApp | ❌ PÚBLICO |
| `POST /api/semparar/obter-recibo` | 210-211 | Expõe dados de viagem | ❌ PÚBLICO |

---

## 🔍 EVIDÊNCIAS

### Código Vulnerável (routes/api.php, linhas 189-226):

```php
// ❌ CRÍTICO: Grupo SEM autenticação
Route::prefix('semparar')->group(function () {
    // FASE 1A - Core (OK - públicos)
    Route::get('test-connection', [SemPararController::class, 'testConnection'])
        ->middleware('throttle:10,1');
    Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
        ->middleware('throttle:60,1');

    // FASE 1B - Routing (OK - públicos)
    Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
        ->middleware('throttle:20,1');
    Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
        ->middleware('throttle:20,1');
    Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
        ->middleware('throttle:60,1');

    // ❌ FASE 2A - Purchase (CRÍTICO - DEVE SER PROTEGIDO!)
    Route::post('comprar-viagem', [SemPararController::class, 'comprarViagem'])
        ->middleware('throttle:10,1');  // Apenas rate limiting, SEM auth!

    // ❌ FASE 2C - Receipt (CRÍTICO - DEVE SER PROTEGIDO!)
    Route::post('obter-recibo', [SemPararController::class, 'obterRecibo'])
        ->middleware('throttle:60,1');
    Route::post('gerar-recibo', [SemPararController::class, 'gerarRecibo'])
        ->middleware('throttle:20,1');

    // ❌ FASE 3A - Query & Management (CRÍTICO - DEVE SER PROTEGIDO!)
    Route::post('consultar-viagens', [SemPararController::class, 'consultarViagens'])
        ->middleware('throttle:60,1');
    Route::post('cancelar-viagem', [SemPararController::class, 'cancelarViagem'])
        ->middleware('throttle:20,1');
    Route::post('reemitir-viagem', [SemPararController::class, 'reemitirViagem'])
        ->middleware('throttle:20,1');

    // Debug endpoints (OK - protegidos no controller)
    Route::get('debug/token', [SemPararController::class, 'debugToken']);
    Route::post('debug/clear-cache', [SemPararController::class, 'clearCache']);
});
```

### Comparação: CompraViagemController (PROTEGIDO CORRETAMENTE)

**Arquivo:** routes/api.php, linhas 243-280

```php
// ✅ CORRETO: CompraViagemController está protegido
Route::middleware(['auth:sanctum'])->prefix('compra-viagem')->group(function () {
    Route::post('comprar', [CompraViagemController::class, 'comprarViagem'])
        ->middleware('throttle:10,1');  // Auth + Rate Limiting ✅
});
```

---

## 🎯 CENÁRIOS DE ATAQUE

### Ataque 1: Compra Fraudulenta
```bash
# Atacante pode comprar viagem sem login
curl -X POST http://localhost:8002/api/semparar/comprar-viagem \
  -H "Content-Type: application/json" \
  -d '{
    "nome_rota": "ROTA_ATACANTE",
    "placa": "ABC1234",
    "eixos": 2,
    "data_inicio": "2025-12-04",
    "data_fim": "2025-12-04",
    "item_fin1": "PEDAGIO"
  }'

# ✅ Sucesso! Viagem comprada sem autenticação
# ❌ Sem registro de quem comprou
# ❌ Impossível auditar
```

### Ataque 2: Cancelamento Malicioso
```bash
# Atacante cancela viagem de terceiros
curl -X POST http://localhost:8002/api/semparar/cancelar-viagem \
  -H "Content-Type: application/json" \
  -d '{"cod_viagem": "91154383"}'

# ✅ Sucesso! Viagem cancelada
# ❌ Operação irreversível sem confirmação
# ❌ Sem registro de quem cancelou
```

### Ataque 3: Raspagem de Dados (Scraping)
```bash
# Atacante consulta TODAS as viagens do sistema
for data in {01..31}; do
  curl -X POST http://localhost:8002/api/semparar/consultar-viagens \
    -H "Content-Type: application/json" \
    -d "{\"data_inicio\": \"2025-12-$data\", \"data_fim\": \"2025-12-$data\"}"
done

# ✅ Sucesso! Histórico completo de viagens obtido
# ❌ Exposição de dados confidenciais
# ❌ Violação LGPD
```

---

## 📊 ANÁLISE DE RISCO

| Categoria | Descrição | Risco |
|-----------|-----------|-------|
| **Confidencialidade** | Exposição de histórico de viagens, placas, rotas, custos | 🔴 ALTO |
| **Integridade** | Cancelamento/reemissão de viagens sem controle | 🔴 ALTO |
| **Disponibilidade** | Rate limiting pode ser contornado com IPs diferentes | 🟡 MÉDIO |
| **Auditabilidade** | Impossível rastrear quem executou operações | 🔴 ALTO |
| **Conformidade** | Violação LGPD Art. 46 (registro de acesso) | 🔴 ALTO |
| **Financeiro** | Possibilidade de fraude e perda de receita | 🔴 ALTO |

**Score de Risco:** 🔴 **CRÍTICO** (5/6 categorias com risco alto)

---

## 🔒 CORREÇÃO IMEDIATA REQUERIDA

### Passo 1: Proteger Endpoints Críticos (5 minutos)

**Arquivo:** `routes/api.php`, linhas 189-226

```php
// ✅ CORRETO: Separar rotas públicas e protegidas
Route::prefix('semparar')->group(function () {
    // Rotas PÚBLICAS (apenas consulta/teste)
    Route::get('test-connection', [SemPararController::class, 'testConnection'])
        ->middleware('throttle:10,1');
    Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
        ->middleware('throttle:60,1');
    Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
        ->middleware('throttle:20,1');
    Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
        ->middleware('throttle:20,1');
    Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
        ->middleware('throttle:60,1');

    // Debug endpoints (protegidos no controller)
    Route::get('debug/token', [SemPararController::class, 'debugToken']);
    Route::post('debug/clear-cache', [SemPararController::class, 'clearCache']);
});

// ✅ Rotas PROTEGIDAS (requerem autenticação)
Route::middleware(['auth:sanctum'])->prefix('semparar')->group(function () {
    // FASE 2A - Purchase
    Route::post('comprar-viagem', [SemPararController::class, 'comprarViagem'])
        ->middleware('throttle:10,1');

    // FASE 2C - Receipt
    Route::post('obter-recibo', [SemPararController::class, 'obterRecibo'])
        ->middleware('throttle:60,1');
    Route::post('gerar-recibo', [SemPararController::class, 'gerarRecibo'])
        ->middleware('throttle:20,1');

    // FASE 3A - Query & Management
    Route::post('consultar-viagens', [SemPararController::class, 'consultarViagens'])
        ->middleware('throttle:60,1');
    Route::post('cancelar-viagem', [SemPararController::class, 'cancelarViagem'])
        ->middleware('throttle:20,1');
    Route::post('reemitir-viagem', [SemPararController::class, 'reemitirViagem'])
        ->middleware('throttle:20,1');
});
```

### Passo 2: Adicionar Logging LGPD (10 minutos)

**Arquivo:** `app/Http/Controllers/Api/SemPararController.php`

Adicionar em TODOS os métodos críticos:

```php
use Illuminate\Support\Facades\Log;

public function comprarViagem(Request $request): JsonResponse
{
    $user = $request->user(); // Garantido por auth:sanctum

    // LGPD Art. 46: Registro de acesso
    Log::info('Compra de viagem SemParar iniciada', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'placa' => $request->input('placa'),
        'valor_estimado' => $request->input('valor_viagem'),
        'timestamp' => now()->toIso8601String()
    ]);

    try {
        $result = $this->semPararService->comprarViagem(...);

        // Log do resultado
        Log::info('Compra de viagem ' . ($result['success'] ? 'concluída' : 'falhou'), [
            'user_id' => $user->id,
            'cod_viagem' => $result['cod_viagem'] ?? null,
            'status' => $result['status'] ?? null,
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([...]);
    } catch (\Exception $e) {
        // Log do erro (completo)
        Log::error('Erro ao comprar viagem', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'input' => $request->except(['password', 'token']),
            'timestamp' => now()->toIso8601String()
        ]);

        // Retorno genérico (não expõe detalhes)
        return response()->json([
            'success' => false,
            'message' => 'Erro ao processar compra. Contate o suporte.',
            'error_id' => uniqid('err_')
        ], 500);
    }
}
```

### Passo 3: Atualizar Frontend (15 minutos)

**Impacto:** Frontend precisa incluir token de autenticação nas requisições.

**Arquivos afetados:**
- `public/test-semparar-fase*.html` - Páginas de teste
- `resources/ts/pages/compra-viagem/` - Interfaces Vue (se existirem)

**Mudanças necessárias:**

```typescript
// ❌ ANTES: Request sem autenticação
fetch('http://localhost:8002/api/semparar/comprar-viagem', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(data)
})

// ✅ DEPOIS: Request com token Sanctum
const token = localStorage.getItem('auth_token'); // Obtido no login
fetch('http://localhost:8002/api/semparar/comprar-viagem', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`  // ✅ Token obrigatório
  },
  body: JSON.stringify(data)
})
```

---

## 📝 CHECKLIST DE CORREÇÃO

- [ ] **URGENTE:** Proteger rotas críticas com `auth:sanctum` (routes/api.php)
- [ ] **URGENTE:** Adicionar logging LGPD em todos os métodos críticos
- [ ] **URGENTE:** Substituir `$e->getMessage()` por mensagens genéricas
- [ ] **ALTO:** Atualizar frontend para incluir tokens de autenticação
- [ ] **ALTO:** Adicionar confirmação dupla em `cancelarViagem()`
- [ ] **ALTO:** Implementar validação de autorização (usuário só pode ver suas viagens)
- [ ] **MÉDIO:** Criar testes automatizados para verificar autenticação
- [ ] **MÉDIO:** Adicionar middleware de audit trail
- [ ] **BAIXO:** Atualizar documentação com requisitos de autenticação

---

## 📚 DOCUMENTAÇÃO RELACIONADA

- **Auditoria Completa:** [AUDITORIA_SEMPARAR_CONTROLLER_2025-12-04.md](AUDITORIA_SEMPARAR_CONTROLLER_2025-12-04.md)
- **Encoding UTF-8:** [AUDITORIA_ENCODING_2025-12-04.md](AUDITORIA_ENCODING_2025-12-04.md)
- **Frontend Build:** [BUG_FIX_FRONTEND_2025-12-04.md](BUG_FIX_FRONTEND_2025-12-04.md)
- **Roadmap:** `SEMPARAR_IMPLEMENTATION_ROADMAP.md`

---

## ✍️ ASSINATURA

**Auditado por:** Sistema de Auditoria de Segurança
**Data:** 2025-12-04
**Horário:** 09:15 (UTC-3)
**Status:** 🔴 **CRÍTICO - AÇÃO IMEDIATA REQUERIDA**

**Próxima Ação:** Implementar correções listadas acima ANTES de continuar desenvolvimento
