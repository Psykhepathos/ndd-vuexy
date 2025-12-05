# Auditoria de Segurança - CompraViagemController.php

**Data:** 2025-12-03
**Arquivo:** `app/Http/Controllers/Api/CompraViagemController.php`
**Linhas:** 862
**Status:** ⚠️ CRÍTICO - Sistema com transações financeiras

---

## 📋 Resumo Executivo

### Classificação de Severidade
- 🔴 **CRÍTICA** (3 issues) - Requer correção imediata
- 🟡 **MÉDIA** (5 issues) - Deve ser corrigida em breve
- 🟢 **BAIXA** (2 issues) - Melhorias recomendadas

### Funcionalidade Geral
O controller gerencia o fluxo de compra de viagens SemParar em 6 fases:
1. Inicialização
2. Validação de pacote
3. Validação de placa/veículo
4. Seleção de rota
5. Verificação de preço
6. Compra efetiva

**Controle de segurança implementado:**
- ✅ `ALLOW_SOAP_QUERIES` (queries/validações)
- ✅ `ALLOW_SOAP_PURCHASE` (compras reais)

---

## 🔴 VULNERABILIDADES CRÍTICAS

### 1. **SQL Injection via User Input** (Linha 779)
**Severidade:** 🔴 CRÍTICA
**Localização:** `comprarViagem()` linha 779

**Problema:**
```php
$dadosViagem = [
    'usuario' => 'SYSTEM' // TODO: Pegar usuário autenticado
];
```

O código está hardcoded como 'SYSTEM', mas o TODO indica que futuramente receberá user input. Se implementado sem sanitização, pode abrir vulnerabilidade de SQL injection no Progress.

**Impacto:**
- SQL injection no Progress Database
- Bypass de auditoria
- Manipulação de registros

**Solução (sem quebrar API):**
```php
// Em CompraViagemController.php linha 779
$usuario = auth()->check() ? auth()->user()->name : 'SYSTEM';
$dadosViagem = [
    'usuario' => substr($usuario, 0, 15) // Limite de 15 chars da coluna Progress
];
```

**Alternativa com validação:**
```php
$usuario = 'SYSTEM';
if (auth()->check()) {
    $authenticatedUser = auth()->user()->name;
    // Sanitiza: apenas alfanuméricos e underscore
    $usuario = preg_replace('/[^a-zA-Z0-9_]/', '', $authenticatedUser);
    $usuario = substr($usuario, 0, 15);
}
$dadosViagem['usuario'] = $usuario;
```

---

### 2. **Falta de Autenticação nos Endpoints** (Todo o arquivo)
**Severidade:** 🔴 CRÍTICA
**Localização:** Todas as rotas públicas

**Problema:**
O controller não verifica autenticação. Qualquer pessoa com acesso à URL pode:
- Comprar viagens (se `ALLOW_SOAP_PURCHASE=true`)
- Listar viagens de qualquer transportadora
- Ver preços e dados sensíveis

**Verificação atual:**
```bash
# routes/api.php
Route::post('/compra-viagem/comprar', [CompraViagemController::class, 'comprarViagem']);
// SEM middleware auth:sanctum!
```

**Impacto:**
- Qualquer pessoa pode comprar viagens
- Acesso não autorizado a dados financeiros
- Bypass completo de controle de acesso

**Solução (sem quebrar frontend):**
```php
// Em routes/api.php
Route::middleware(['auth:sanctum'])->prefix('compra-viagem')->group(function () {
    Route::post('/comprar', [CompraViagemController::class, 'comprarViagem']);
    Route::get('/listar-viagens', [CompraViagemController::class, 'listarViagens']);
    // ... outros endpoints sensíveis
});

// Endpoints públicos (informação apenas)
Route::prefix('compra-viagem')->group(function () {
    Route::get('/initialize', [CompraViagemController::class, 'initialize']);
    Route::get('/health', [CompraViagemController::class, 'health']);
});
```

**⚠️ ATENÇÃO FRONTEND:** Se adicionar auth:sanctum, o frontend precisa:
1. Incluir token nos headers: `Authorization: Bearer {token}`
2. Tratar erro 401 (não autenticado)

---

### 3. **Race Condition na Verificação de Duplicatas** (Linha 445)
**Severidade:** 🔴 CRÍTICA
**Localização:** `validarRota()` linha 445 + `comprarViagem()` linha 672

**Problema:**
```php
// validarRota() - linha 445
$viagemCheck = $this->progressService->viagemJaComprada($codpac, $rotaId);
if ($viagemCheck['duplicada']) { return error; }

// ... TEMPO PASSA (usuário preenche outros campos) ...

// comprarViagem() - linha 672
// NÃO verifica novamente se viagem já foi comprada!
$resultadoCompra = $this->semPararService->comprarViagem(...);
```

**Cenário de Ataque:**
1. Usuário A valida rota (sem duplicata)
2. Usuário B valida mesma rota (sem duplicata)
3. Usuário A compra viagem (sucesso)
4. Usuário B compra viagem (sucesso - DUPLICATA!)

**Impacto:**
- Compra duplicada para mesmo pacote/rota
- Perda financeira
- Inconsistência de dados

**Solução (sem quebrar API):**
```php
// Em comprarViagem() ANTES da linha 698
public function comprarViagem(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([...]);

        // VALIDAÇÃO CRÍTICA: Verifica duplicata NOVAMENTE antes de comprar
        $viagemCheck = $this->progressService->viagemJaComprada(
            $validated['codpac'],
            $validated['cod_rota']
        );

        if ($viagemCheck['duplicada']) {
            $viagem = $viagemCheck['viagem'];
            Log::warning('Tentativa de compra duplicada bloqueada', [
                'codpac' => $validated['codpac'],
                'cod_rota' => $validated['cod_rota'],
                'viagem_existente' => $viagem['codViagem']
            ]);

            return response()->json([
                'success' => false,
                'error' => sprintf(
                    'Viagem já foi comprada por outro usuário. Viagem %s, placa %s',
                    $viagem['codViagem'],
                    $viagem['NumPla']
                ),
                'code' => 'VIAGEM_JA_COMPRADA',
                'viagem_existente' => $viagem
            ], 409); // 409 Conflict
        }

        // Verifica se compras estão permitidas
        if (!$this->ALLOW_SOAP_PURCHASE) { ... }

        // ... resto do código
```

**Nota:** Progress JDBC não suporta transações, então não podemos usar locks. A solução é verificar novamente no último momento antes de comprar.

---

## 🟡 VULNERABILIDADES MÉDIAS

### 4. **Falta de Rate Limiting** (Todo o controller)
**Severidade:** 🟡 MÉDIA
**Localização:** Todas as rotas

**Problema:**
Nenhum endpoint tem rate limiting. Possibilita:
- Brute force de códigos de pacote
- DoS ao sistema SemParar (chamadas SOAP excessivas)
- Spam de compras

**Impacto:**
- Bloqueio da conta SemParar por abuso
- Lentidão do sistema
- Custos de API elevados

**Solução (sem quebrar frontend):**
```php
// Em routes/api.php
Route::middleware(['throttle:60,1'])->prefix('compra-viagem')->group(function () {
    // 60 requests por minuto
    Route::post('/validar-pacote', [CompraViagemController::class, 'validarPacote']);
    Route::post('/validar-placa', [CompraViagemController::class, 'validarPlaca']);
    Route::get('/listar-rotas', [CompraViagemController::class, 'listarRotas']);
    Route::post('/verificar-preco', [CompraViagemController::class, 'verificarPreco']);
    Route::get('/listar-viagens', [CompraViagemController::class, 'listarViagens']);
});

// Endpoints sensíveis: rate limiting mais restritivo
Route::middleware(['throttle:10,1'])->prefix('compra-viagem')->group(function () {
    // 10 compras por minuto
    Route::post('/comprar', [CompraViagemController::class, 'comprarViagem']);
});

// Endpoints informativos: rate limiting leve
Route::middleware(['throttle:120,1'])->prefix('compra-viagem')->group(function () {
    Route::get('/initialize', [CompraViagemController::class, 'initialize']);
    Route::get('/health', [CompraViagemController::class, 'health']);
});
```

---

### 5. **Validação Insuficiente de Placa** (Linha 242, 524, 678)
**Severidade:** 🟡 MÉDIA
**Localização:** Múltiplas validações

**Problema:**
```php
'placa' => 'required|string|min:7|max:10'  // linha 242
'placa' => 'required|string|size:7',       // linha 524
'placa' => 'required|string|size:7',       // linha 678
```

**Issues:**
1. Inconsistência: `validarPlaca()` aceita 7-10 chars, mas outros endpoints exigem 7
2. Não valida formato brasileiro (ABC1234 ou ABC1D23)
3. Aceita caracteres especiais

**Impacto:**
- Placas inválidas enviadas ao SemParar
- Erro SOAP difícil de debugar
- Inconsistência de dados

**Solução (sem quebrar API):**
```php
// Criar FormRequest customizado
// app/Http/Requests/PlacaBrasileiraRequest.php
class PlacaBrasileiraRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'placa' => [
                'required',
                'string',
                'size:7',
                'regex:/^[A-Z]{3}[0-9]{1}[A-Z0-9]{1}[0-9]{2}$/i'  // ABC1234 ou ABC1D23
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'placa.regex' => 'Placa inválida. Use formato brasileiro (ABC1234 ou ABC1D23)'
        ];
    }
}

// Usar em todos os endpoints:
public function validarPlaca(PlacaBrasileiraRequest $request): JsonResponse
{
    $validated = $request->validated();
    $validated['placa'] = strtoupper($validated['placa']); // Padroniza uppercase
    // ... resto do código
}
```

---

### 6. **Logs Excessivos com Dados Sensíveis** (Linhas 43, 137, 245, etc.)
**Severidade:** 🟡 MÉDIA
**Localização:** Todo o controller

**Problema:**
```php
Log::info('API: Validando placa no SemParar', [
    'placa' => $validated['placa'],  // Dado sensível!
    'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
]);
```

**Issues:**
1. Placas de veículos são dados sensíveis (LGPD)
2. Logs podem conter valores financeiros
3. Traces completas expõem estrutura do código

**Impacto:**
- Violação LGPD
- Exposição de informações sensíveis em logs
- Ataques baseados em estrutura do código

**Solução (sem quebrar funcionalidade):**
```php
// Função helper para sanitizar logs
private function sanitizeLogData(array $data): array
{
    $sanitized = $data;

    // Mascara placas: ABC1234 -> ABC****
    if (isset($sanitized['placa'])) {
        $sanitized['placa'] = substr($sanitized['placa'], 0, 3) . '****';
    }

    // Remove valores exatos, deixa apenas indicação
    if (isset($sanitized['valor_viagem'])) {
        $sanitized['valor_viagem'] = $sanitized['valor_viagem'] > 0 ? 'R$ XX.XX' : '0';
    }

    return $sanitized;
}

// Uso:
Log::info('API: Validando placa no SemParar', $this->sanitizeLogData([
    'placa' => $validated['placa'],
    'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
]));

// Para erros críticos, manter dados completos mas marcar como sensível
Log::channel('secure')->error('Erro ao comprar viagem', [
    'placa' => $validated['placa'],  // Log seguro, separado
    'trace' => $e->getTraceAsString()
]);
```

**Configuração de canal seguro em config/logging.php:**
```php
'channels' => [
    'secure' => [
        'driver' => 'single',
        'path' => storage_path('logs/secure.log'),
        'level' => 'error',
        'permission' => 0600,  // Acesso restrito
    ],
]
```

---

### 7. **Falta de Idempotência na Compra** (Linha 672)
**Severidade:** 🟡 MÉDIA
**Localização:** `comprarViagem()`

**Problema:**
Se o endpoint `comprarViagem()` for chamado duas vezes (ex: double-click, retry automático), duas compras serão efetuadas.

**Impacto:**
- Compras duplicadas acidentais
- Perda financeira
- Frustração do usuário

**Solução (sem quebrar API):**
```php
// Adicionar campo idempotency_key ao request
public function comprarViagem(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'codpac' => 'required|integer|min:1',
            'cod_rota' => 'required|integer',
            // ... outros campos
            'idempotency_key' => 'nullable|string|max:100'  // NOVO
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? null;

        // Verifica se já processamos esta requisição
        if ($idempotencyKey) {
            $cached = Cache::get("compra_viagem:{$idempotencyKey}");
            if ($cached) {
                Log::info('Requisição duplicada detectada (idempotency)', [
                    'key' => $idempotencyKey
                ]);
                return response()->json($cached);
            }
        }

        // ... processo de compra normal ...

        $response = [
            'success' => true,
            'message' => 'Viagem comprada com sucesso!',
            'data' => [...]
        ];

        // Cacheia resultado por 5 minutos
        if ($idempotencyKey) {
            Cache::put("compra_viagem:{$idempotencyKey}", $response, 300);
        }

        return response()->json($response);

    } catch (\Exception $e) {
        // ... tratamento de erro
    }
}
```

**Frontend precisa gerar:**
```typescript
// resources/ts/pages/compra-viagem/
import { v4 as uuidv4 } from 'uuid'

const idempotencyKey = uuidv4()  // Gera UUID único
const response = await fetch('/api/compra-viagem/comprar', {
  method: 'POST',
  body: JSON.stringify({
    ...dados,
    idempotency_key: idempotencyKey
  })
})
```

---

### 8. **Validação de Data Insuficiente** (Linha 526)
**Severidade:** 🟡 MÉDIA
**Localização:** `verificarPreco()` e `comprarViagem()`

**Problema:**
```php
'data_inicio' => 'required|date',
'data_fim' => 'required|date|after_or_equal:data_inicio'
```

**Issues:**
1. Não valida se data_inicio é no passado
2. Não valida período máximo (usuário pode comprar viagem de 1 ano)
3. Aceita datas muito antigas ou futuras

**Impacto:**
- Viagens com validade inválida
- Erro no SemParar
- Custos inesperados

**Solução (sem quebrar API):**
```php
use Illuminate\Validation\Rule;

$validated = $request->validate([
    'data_inicio' => [
        'required',
        'date',
        'after_or_equal:today',  // Não aceita datas passadas
        'before:' . now()->addMonths(6)->format('Y-m-d')  // Máx 6 meses no futuro
    ],
    'data_fim' => [
        'required',
        'date',
        'after_or_equal:data_inicio',
        function ($attribute, $value, $fail) use ($request) {
            $dataInicio = \Carbon\Carbon::parse($request->input('data_inicio'));
            $dataFim = \Carbon\Carbon::parse($value);
            $dias = $dataInicio->diffInDays($dataFim);

            if ($dias > 30) {
                $fail('Período máximo de viagem é 30 dias.');
            }
            if ($dias < 1) {
                $fail('Viagem deve ter pelo menos 1 dia de duração.');
            }
        }
    ]
], [
    'data_inicio.after_or_equal' => 'Data de início não pode ser no passado',
    'data_inicio.before' => 'Data de início não pode ser superior a 6 meses no futuro'
]);
```

---

## 🟢 MELHORIAS RECOMENDADAS

### 9. **Implementar Estatísticas Reais** (Linha 84)
**Severidade:** 🟢 BAIXA
**Localização:** `statistics()`

**Problema:**
```php
public function statistics(): JsonResponse
{
    // TODO: Implementar estatísticas reais
    return response()->json([
        'success' => true,
        'data' => [
            'total_viagens_compradas' => 0,  // Hardcoded!
            'ultima_compra' => null,         // Hardcoded!
```

**Solução:**
```php
public function statistics(): JsonResponse
{
    try {
        $hoje = now()->format('Y-m-d');
        $inicioMes = now()->startOfMonth()->format('Y-m-d');
        $fimMes = now()->endOfMonth()->format('Y-m-d');

        // Busca estatísticas reais do Progress
        $viagensMes = $this->progressService->getViagensCompradas(
            $inicioMes,
            $fimMes
        );

        $ultimaCompra = $this->progressService->getUltimaViagemComprada();

        return response()->json([
            'success' => true,
            'data' => [
                'total_viagens_mes' => $viagensMes['pagination']['total'] ?? 0,
                'ultima_compra' => $ultimaCompra['data'] ?? null,
                'periodo' => [
                    'inicio' => $inicioMes,
                    'fim' => $fimMes
                ],
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE
            ]
        ]);

    } catch (\Exception $e) {
        // ... tratamento de erro
    }
}
```

---

### 10. **Adicionar Audit Trail Completo** (Todo o controller)
**Severidade:** 🟢 BAIXA
**Localização:** Todas as operações críticas

**Problema:**
Não há trilha de auditoria completa para:
- Quem validou o pacote
- Quem verificou o preço
- Quem comprou a viagem
- Quando cada ação foi executada

**Solução:**
```php
// Criar Migration
Schema::create('compra_viagem_audit_log', function (Blueprint $table) {
    $table->id();
    $table->integer('codpac');
    $table->integer('cod_rota')->nullable();
    $table->string('action', 50);  // 'validar_pacote', 'verificar_preco', 'comprar'
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('ip', 45);
    $table->json('request_data');
    $table->json('response_data')->nullable();
    $table->string('status', 20);  // 'success', 'error', 'blocked'
    $table->timestamps();

    $table->index(['codpac', 'action', 'created_at']);
});

// Criar Middleware
class AuditCompraViagem
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Log audit após response
        AuditLog::create([
            'codpac' => $request->input('codpac'),
            'cod_rota' => $request->input('cod_rota'),
            'action' => $request->route()->getActionMethod(),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'request_data' => $request->except(['_token', 'password']),
            'response_data' => json_decode($response->getContent(), true),
            'status' => $response->isSuccessful() ? 'success' : 'error'
        ]);

        return $response;
    }
}
```

---

## 📊 Mapeamento Frontend ↔ Backend

### Endpoints Configurados no Frontend
**Arquivo:** `resources/ts/config/api.ts`

| Endpoint Frontend | Controller Method | Status | Observações |
|-------------------|-------------------|--------|-------------|
| `/initialize` | `initialize()` | ✅ OK | Retorna config inicial |
| `/statistics` | `statistics()` | ⚠️ TODO | Retorna dados hardcoded |
| `/health` | `health()` | ✅ OK | Health check |
| `/validar-pacote` | `validarPacote()` | ✅ OK | Valida pacote + busca transporte |
| `/validar-placa` | `validarPlaca()` | ✅ OK | SOAP statusVeiculo |
| `/rotas` | `listarRotas()` | ✅ OK | Autocomplete rotas |
| `/verificar-preco` | `verificarPreco()` | ✅ OK | SOAP verificarPreco |
| `/comprar` | ❌ **NÃO EXISTE** | ❌ COMENTADO | Frontend não implementou! |
| `/gerar-recibo` | ❌ **NÃO EXISTE** | ❌ COMENTADO | Frontend não implementou! |

### Endpoints NÃO Configurados no Frontend
| Controller Method | URL | Usado? |
|-------------------|-----|--------|
| `validarRota()` | POST `/validar-rota` | ❌ Frontend não usa |
| `listarViagens()` | GET `/listar-viagens` | ❌ Frontend não usa |
| `comprarViagem()` | POST `/comprar` | ⚠️ BLOQUEADO (ALLOW_SOAP_PURCHASE=false) |

**⚠️ ATENÇÃO:** Frontend ainda não implementou os endpoints de compra efetiva e listagem de viagens!

---

## 🔧 Checklist de Implementação

### Correções CRÍTICAS (Fazer AGORA)
- [ ] **#2**: Adicionar `auth:sanctum` middleware aos endpoints sensíveis
- [ ] **#3**: Re-validar duplicatas em `comprarViagem()` antes de SOAP
- [ ] **#1**: Implementar sanitização de `usuario` em `comprarViagem()`

### Correções MÉDIAS (Próxima Sprint)
- [ ] **#4**: Adicionar rate limiting (throttle) em todas as rotas
- [ ] **#5**: Validar formato de placa brasileira com regex
- [ ] **#6**: Implementar logs sanitizados e canal seguro
- [ ] **#7**: Adicionar suporte a idempotency_key
- [ ] **#8**: Validar datas com limites razoáveis

### Melhorias BAIXAS (Quando possível)
- [ ] **#9**: Implementar estatísticas reais em `statistics()`
- [ ] **#10**: Criar audit trail completo

---

## 🧪 Testes Recomendados

### Testes de Segurança
```php
// tests/Feature/CompraViagemSecurityTest.php

public function test_comprar_viagem_requer_autenticacao()
{
    $response = $this->postJson('/api/compra-viagem/comprar', [
        'codpac' => 123,
        'cod_rota' => 204,
        // ...
    ]);

    $response->assertStatus(401);  // Unauthorized
}

public function test_valida_duplicata_antes_de_comprar()
{
    // 1. Compra primeira viagem
    $this->actingAs($user);
    $response1 = $this->postJson('/api/compra-viagem/comprar', $dados);
    $response1->assertStatus(200);

    // 2. Tenta comprar novamente (deve falhar)
    $response2 = $this->postJson('/api/compra-viagem/comprar', $dados);
    $response2->assertStatus(409);  // Conflict
    $response2->assertJson(['code' => 'VIAGEM_JA_COMPRADA']);
}

public function test_rate_limiting_funciona()
{
    for ($i = 0; $i < 12; $i++) {
        $response = $this->postJson('/api/compra-viagem/comprar', $dados);
    }

    // 11ª requisição deve ser bloqueada
    $response->assertStatus(429);  // Too Many Requests
}
```

### Testes de Integração
```php
public function test_fluxo_completo_compra_viagem()
{
    // 1. Initialize
    $init = $this->getJson('/api/compra-viagem/initialize');
    $init->assertStatus(200);

    // 2. Valida pacote
    $pacote = $this->postJson('/api/compra-viagem/validar-pacote', [
        'codpac' => 3043368,
        'flgcd' => false
    ]);
    $pacote->assertStatus(200);

    // 3. Valida placa
    $placa = $this->postJson('/api/compra-viagem/validar-placa', [
        'placa' => 'ABC1234'
    ]);
    $placa->assertStatus(200);

    // 4. Verifica preço
    $preco = $this->postJson('/api/compra-viagem/verificar-preco', [
        'codpac' => 3043368,
        'cod_rota' => 204,
        'qtd_eixos' => 2,
        'placa' => 'ABC1234',
        'data_inicio' => now()->format('Y-m-d'),
        'data_fim' => now()->addDays(5)->format('Y-m-d')
    ]);
    $preco->assertStatus(200);

    // 5. Compra (se permitido)
    if (env('ALLOW_SOAP_PURCHASE')) {
        $compra = $this->postJson('/api/compra-viagem/comprar', [...]);
        $compra->assertStatus(200);
        $compra->assertJsonStructure(['data' => ['numero_viagem']]);
    }
}
```

---

## 📝 Notas Finais

### Pontos Positivos ✅
1. ✅ Sistema de flags de segurança (`ALLOW_SOAP_*`) bem implementado
2. ✅ Validações básicas em todos os endpoints
3. ✅ Logs detalhados para debugging
4. ✅ Tratamento de exceções consistente
5. ✅ Separação clara entre chamadas SOAP reais e simuladas

### Pontos de Atenção ⚠️
1. ⚠️ Frontend ainda não implementou endpoint de compra efetiva
2. ⚠️ Falta autenticação em endpoints sensíveis
3. ⚠️ Race condition crítica na validação de duplicatas
4. ⚠️ Logs expõem dados sensíveis (LGPD)
5. ⚠️ Sem rate limiting (vulnerável a abuso)

### Recomendação Geral
**NÃO LIBERAR PARA PRODUÇÃO** sem implementar pelo menos as correções CRÍTICAS (#1, #2, #3).

---

**Auditor:** Claude Code Assistant
**Metodologia:** OWASP Top 10 + Laravel Security Best Practices
**Próxima revisão:** Após implementação das correções críticas
