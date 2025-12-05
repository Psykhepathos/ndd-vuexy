# Auditoria de Segurança: CompraViagemController.php

**Data:** 2025-12-04
**Arquivo:** `app/Http/Controllers/Api/CompraViagemController.php`
**Linhas:** 1286
**Severidade Máxima:** 🟡 MÉDIA (rotas já protegidas com auth:sanctum)

---

## 📋 Sumário Executivo

Identificados **10 problemas de exposição de stack trace** e **1 TODO não resolvido** no CompraViagemController. O controller já possui proteção de autenticação adequada (`auth:sanctum`) e várias correções de segurança implementadas (CORREÇÃO #1-#9).

### Impacto
- 🟡 **MÉDIO:** Exposição de informações sensíveis em mensagens de erro (stack trace)
- 🟢 **BAIXO:** LGPD logging está implementado na maioria dos métodos críticos
- ✅ **POSITIVO:** Rotas críticas já protegidas com autenticação
- ✅ **POSITIVO:** Sistema de idempotência previne duplo-clique
- ✅ **POSITIVO:** Re-validação anti-fraude implementada

---

## ✅ PONTOS POSITIVOS (Correções Já Implementadas)

### CORREÇÃO #1: Idempotência (Linhas 797-815, 1080-1096)
```php
// ✅ Mecanismo de idempotência para prevenir compras duplicadas
if (isset($validated['idempotency_key']) && !empty($validated['idempotency_key'])) {
    $cacheKey = 'idempotency:compra:' . $validated['idempotency_key'];
    if (Cache::has($cacheKey)) {
        $cachedResult = Cache::get($cacheKey);
        Log::info('Requisição idempotente detectada - retornando resultado cached', [
            'idempotency_key' => $validated['idempotency_key'],
            'cached_at' => $cachedResult['cached_at']
        ]);
        return response()->json($cachedResult['response'], $cachedResult['status_code']);
    }
}
```

**Benefício:** Previne compras duplicadas por duplo-clique ou retry de requisição.

---

### CORREÇÃO #2: Re-validação Anti-Fraude de Eixos (Linhas 904-948)
```php
// ✅ Re-valida eixos do veículo antes da compra para prevenir manipulação
$validacaoPlaca = $this->progressService->validateVehicleStatusSemParar(
    $validated['placa'],
    false  // false = chamada SOAP real, sem cache
);

$eixosReais = $validacaoPlaca['data']['eixos'];

if ($validated['qtd_eixos'] != $eixosReais) {
    Log::warning('Tentativa de manipulação de eixos detectada e bloqueada', [
        'placa' => $this->sanitizeLogData(['placa' => $validated['placa']])['placa'],
        'eixos_declarados' => $validated['qtd_eixos'],
        'eixos_reais' => $eixosReais,
        'ip' => request()->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Número de eixos incorreto',
        'error' => sprintf(
            'O veículo de placa %s possui %d eixos, não %d. Por favor, valide novamente a placa.',
            $validated['placa'],
            $eixosReais,
            $validated['qtd_eixos']
        ),
        'code' => 'EIXOS_INVALIDOS',
        'eixos_corretos' => $eixosReais
    ], 400);
}
```

**Benefício:** Previne fraude onde usuário poderia manipular número de eixos entre validação e compra.

---

### CORREÇÃO #3: Verificação de Duplicação com Race Condition Protection (Linhas 951-997)
```php
// ✅ Verifica duplicação IMEDIATAMENTE antes da compra (race condition protection)
Log::info('Verificando duplicação de viagem antes da compra final');

$dataInicio = Carbon::parse($validated['data_inicio_viagem']);
$dataFim = Carbon::parse($validated['data_fim_viagem']);

$viagemExistente = DB::connection('progress')->selectOne(
    "SELECT TOP 1 codviagem, datacompra
     FROM PUB.sPararViagem
     WHERE UPPER(numpla) = ?
       AND nomrotsemparar = ?
       AND datacompra >= ?
       AND datacompra <= ?
       AND (flgcancelado IS NULL OR flgcancelado = FALSE)
     ORDER BY datacompra DESC",
    [
        strtoupper($validated['placa']),
        $validated['nome_rota_temp'],
        $dataInicio->subDays(1)->format('Y-m-d'),
        $dataFim->addDays(1)->format('Y-m-d')
    ]
);

if ($viagemExistente) {
    Log::warning('Tentativa de compra duplicada detectada (verificação final)');
    return response()->json([
        'success' => false,
        'message' => 'Viagem duplicada detectada',
        'error' => 'Já existe uma viagem ativa para esta placa, rota e período.',
        'code' => 'VIAGEM_DUPLICADA'
    ], 409);
}
```

**Benefício:** Previne race conditions onde duas requisições simultâneas poderiam criar viagens duplicadas.

---

### CORREÇÃO #4: LGPD Data Sanitization (Linhas 1237-1284)
```php
// ✅ Sanitiza dados sensíveis antes de logar (LGPD compliance)
private function sanitizeLogData(array $data, bool $maskValues = false): array
{
    $sanitized = $data;

    // Mascara placa (ABC1234 -> ABC****)
    if (isset($sanitized['placa'])) {
        $placa = strtoupper($sanitized['placa']);
        $sanitized['placa'] = strlen($placa) >= 3
            ? substr($placa, 0, 3) . str_repeat('*', strlen($placa) - 3)
            : str_repeat('*', strlen($placa));
    }

    // Mascara valores monetários (apenas em warnings/errors de segurança)
    if ($maskValues) {
        if (isset($sanitized['valor'])) {
            $sanitized['valor'] = '***.**';
        }
        if (isset($sanitized['valor_pratica'])) {
            $sanitized['valor_pratica'] = '***.**';
        }
        if (isset($sanitized['valor_pedagio'])) {
            $sanitized['valor_pedagio'] = '***.**';
        }
    }

    // Remove campos sensíveis
    unset($sanitized['password']);
    unset($sanitized['token']);
    unset($sanitized['api_key']);

    return $sanitized;
}
```

**Benefício:** Protege dados sensíveis em logs (LGPD Art. 46).

---

### CORREÇÃO #5: Validação de Placa Brasileira (Linhas 440-481)
```php
// ✅ Valida formato de placa brasileira (Mercosul e antigo)
public function validarPlaca(Request $request): JsonResponse
{
    $request->validate([
        'placa' => 'required|string|min:7|max:8'
    ]);

    $placa = strtoupper(trim($request->input('placa')));

    // Formato Mercosul: ABC1D23
    $mercosulPattern = '/^[A-Z]{3}[0-9]{1}[A-Z]{1}[0-9]{2}$/';
    // Formato antigo: ABC1234
    $antigoPattern = '/^[A-Z]{3}[0-9]{4}$/';

    if (!preg_match($mercosulPattern, $placa) && !preg_match($antigoPattern, $placa)) {
        return response()->json([
            'success' => false,
            'message' => 'Formato de placa inválido',
            'error' => 'A placa deve estar no formato ABC1234 (antigo) ou ABC1D23 (Mercosul)'
        ], 400);
    }

    // Consulta SemParar API para status do veículo
    // ...
}
```

**Benefício:** Previne placas inválidas e consultas desnecessárias à API.

---

### CORREÇÃO #6: Validação de Datas de Viagem (Linhas 738-777)
```php
// ✅ Valida período de viagem com regras de negócio
private function validarDatasViagem(Request $request): void
{
    $request->validate([
        'data_inicio_viagem' => 'required|date|after_or_equal:' . now()->subDays(7)->format('Y-m-d'),
        'data_fim_viagem' => 'required|date|after_or_equal:data_inicio_viagem|before_or_equal:' . now()->addDays(90)->format('Y-m-d')
    ], [
        'data_inicio_viagem.after_or_equal' => 'A data de início não pode ser mais de 7 dias no passado',
        'data_fim_viagem.after_or_equal' => 'A data de fim deve ser igual ou posterior à data de início',
        'data_fim_viagem.before_or_equal' => 'A data de fim não pode ser mais de 90 dias no futuro'
    ]);

    $dataInicio = Carbon::parse($request->input('data_inicio_viagem'));
    $dataFim = Carbon::parse($request->input('data_fim_viagem'));

    $diasDiferenca = $dataInicio->diffInDays($dataFim);

    if ($diasDiferenca > 30) {
        throw new \InvalidArgumentException(
            'O período da viagem não pode ser superior a 30 dias. ' .
            'Período solicitado: ' . $diasDiferenca . ' dias.'
        );
    }
}
```

**Benefício:** Previne viagens com datas inválidas ou períodos muito longos.

---

### CORREÇÃO #7: Modo de Teste com Flag (Linha 779)
```php
// ✅ Flag para permitir testes sem efetuar compra SOAP real
const ALLOW_SOAP_PURCHASE = true; // true = permitir compra SOAP real
```

**Benefício:** Permite desenvolvimento e testes sem custos reais.

---

### CORREÇÃO #8: Logging LGPD em Compra (Linhas 1000-1014, 1108-1139)
```php
// ✅ Log completo de operação de compra (LGPD Art. 46)
Log::info('Iniciando compra de viagem SemParar', [
    'pacote' => $validated['cod_pac'],
    'placa' => $this->sanitizeLogData(['placa' => $validated['placa']])['placa'],
    'rota' => $validated['nome_rota_temp'],
    'periodo' => [
        'inicio' => $validated['data_inicio_viagem'],
        'fim' => $validated['data_fim_viagem']
    ],
    'valor' => $validated['valor_pedagio'],
    'usuario' => 'SYSTEM', // TODO: Pegar usuário autenticado
    'ip' => request()->ip(),
    'timestamp' => now()->toIso8601String()
]);

// Log após compra bem-sucedida
Log::info('Compra de viagem concluída com sucesso', [
    'pacote' => $validated['cod_pac'],
    'cod_viagem' => $codViagem,
    'placa' => $this->sanitizeLogData(['placa' => $validated['placa']])['placa'],
    'rota' => $validated['nome_rota_temp'],
    'valor' => $validated['valor_pedagio'],
    'progress_saved' => $viagemSalvaProgress,
    'usuario' => 'SYSTEM',
    'ip' => request()->ip(),
    'timestamp' => now()->toIso8601String()
]);
```

**Benefício:** Auditoria completa de operações financeiras.

---

### CORREÇÃO #9: Autenticação nas Rotas (routes/api.php, linhas 247-284)
```php
// ✅ Rotas protegidas com auth:sanctum + rate limiting
Route::middleware(['auth:sanctum'])->prefix('compra-viagem')->group(function () {
    Route::get('statistics', [CompraViagemController::class, 'statistics'])
        ->middleware('throttle:10,1');  // 10 req/min

    Route::post('viagens', [CompraViagemController::class, 'listarViagens'])
        ->middleware('throttle:60,1');  // 60 req/min

    Route::post('validar-pacote', [CompraViagemController::class, 'validarPacote'])
        ->middleware('throttle:60,1');

    Route::post('validar-placa', [CompraViagemController::class, 'validarPlaca'])
        ->middleware('throttle:60,1');

    Route::get('rotas', [CompraViagemController::class, 'listarRotas'])
        ->middleware('throttle:60,1');

    Route::post('validar-rota', [CompraViagemController::class, 'validarRota'])
        ->middleware('throttle:60,1');

    Route::post('verificar-preco', [CompraViagemController::class, 'verificarPreco'])
        ->middleware('throttle:30,1');  // 30 req/min

    Route::post('comprar', [CompraViagemController::class, 'comprarViagem'])
        ->middleware('throttle:10,1');  // 10 req/min - operação crítica
});

// ✅ Endpoints públicos (apenas informação, sem dados sensíveis)
Route::prefix('compra-viagem')->group(function () {
    Route::get('initialize', [CompraViagemController::class, 'initialize'])
        ->middleware('throttle:30,1');  // 30 req/min

    Route::get('health', [CompraViagemController::class, 'health'])
        ->middleware('throttle:60,1');  // 60 req/min
});
```

**Benefício:** Apenas usuários autenticados podem realizar operações críticas.

---

## 🟡 PROBLEMAS MÉDIOS

### 1. EXPOSIÇÃO DE STACK TRACE EM ERROS (10 ocorrências)

**Métodos afetados:**

#### Linha 77 - `initialize()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao inicializar sistema',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 150 - `statistics()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao obter estatísticas',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 214 - `listarViagens()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao listar viagens',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 333 - `validarPacote()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao validar pacote',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 479 - `validarPlaca()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao validar placa',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 541 - `listarRotas()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao listar rotas',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 637 - `validarRota()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao validar rota',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 735 - `verificarPreco()`
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao verificar preço',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 1176 - `comprarViagem()`
```php
} catch (\Exception $e) {
    Log::error('Erro ao comprar viagem', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input' => $this->sanitizeLogData($validated, true),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro ao processar compra de viagem',
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos
    ], 500);
}
```

#### Linha 1225 - `salvarViagemProgress()`
```php
} catch (\Exception $e) {
    Log::error('Erro ao salvar viagem no Progress', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'cod_viagem' => $codViagem,
        'timestamp' => now()->toIso8601String()
    ]);

    return [
        'success' => false,
        'error' => $e->getMessage()  // ❌ Expõe detalhes internos (retorno interno, mas ainda inseguro)
    ];
}
```

**Impacto:**
- Exposição de caminhos de arquivo
- Exposição de estrutura de banco de dados
- Exposição de credenciais em alguns casos
- Facilita ataques de engenharia reversa

---

### 2. TODO NÃO RESOLVIDO (Linha 1012)

```php
Log::info('Iniciando compra de viagem SemParar', [
    'pacote' => $validated['cod_pac'],
    'placa' => $this->sanitizeLogData(['placa' => $validated['placa']])['placa'],
    'rota' => $validated['nome_rota_temp'],
    'periodo' => [
        'inicio' => $validated['data_inicio_viagem'],
        'fim' => $validated['data_fim_viagem']
    ],
    'valor' => $validated['valor_pedagio'],
    'usuario' => 'SYSTEM', // TODO: Pegar usuário autenticado ❌
    'ip' => request()->ip(),
    'timestamp' => now()->toIso8601String()
]);
```

**Impacto:**
- LGPD Art. 46 exige identificação do usuário em operações de dados
- Logs sem user_id dificultam auditoria
- Impossível rastrear quem executou a compra

**Solução:**
```php
'usuario' => request()->user()->id ?? 'SYSTEM',
'user_email' => request()->user()->email ?? null,
```

---

## 📊 Estatísticas

| Métrica | Valor |
|---------|-------|
| Total de métodos | 15 |
| Métodos com stack trace exposto | 10 (67%) |
| Métodos com LGPD logging | 3 (20%) |
| TODOs não resolvidos | 1 |
| Métodos protegidos com auth | 8 (53%) |
| Métodos públicos (correto) | 2 (initialize, health) |
| **Correções já implementadas** | **9** |
| **Idempotência** | ✅ Sim |
| **Anti-fraude (re-validação)** | ✅ Sim |
| **Race condition protection** | ✅ Sim |
| **LGPD sanitization** | ✅ Sim |
| **Validação de placa** | ✅ Sim |
| **Validação de datas** | ✅ Sim |

---

## 🔒 Recomendações de Correção

### Prioridade MÉDIA (Melhorias de Segurança):

#### 1. Substituir Exposição de `$e->getMessage()` por Mensagens Genéricas

**Padrão recomendado:**
```php
} catch (\Exception $e) {
    // Log completo (interno)
    Log::error('Erro ao processar operação', [
        'method' => __METHOD__,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input' => $this->sanitizeLogData($request->all()),
        'user_id' => request()->user()->id ?? null,
        'ip' => request()->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    // Retorno genérico (público)
    return response()->json([
        'success' => false,
        'message' => 'Erro interno no processamento. Contate o suporte.',
        'error_id' => uniqid('err_')  // ID para correlação com log
    ], 500);
}
```

**Aplicar em:**
- `initialize()` (linha 77)
- `statistics()` (linha 150)
- `listarViagens()` (linha 214)
- `validarPacote()` (linha 333)
- `validarPlaca()` (linha 479)
- `listarRotas()` (linha 541)
- `validarRota()` (linha 637)
- `verificarPreco()` (linha 735)
- `comprarViagem()` (linha 1176)
- `salvarViagemProgress()` (linha 1225)

---

#### 2. Resolver TODO: Adicionar User ID aos Logs

**Linha 1012 + 1125:**
```php
// ❌ ANTES
'usuario' => 'SYSTEM', // TODO: Pegar usuário autenticado

// ✅ DEPOIS
'user_id' => request()->user()->id ?? null,
'user_email' => request()->user()->email ?? null,
```

**Aplicar em:**
- Log de início de compra (linha 1012)
- Log de compra concluída (linha 1125)

---

#### 3. Adicionar LGPD Logging Completo em Métodos Restantes

Métodos que ainda não têm logging LGPD completo:
- `statistics()` - Deveria logar consulta de estatísticas
- `listarViagens()` - Deveria logar consulta de viagens
- `validarPacote()` - Deveria logar validação de pacote
- `validarPlaca()` - Já tem log básico, adicionar user_id
- `listarRotas()` - Deveria logar consulta de rotas
- `validarRota()` - Deveria logar validação de rota
- `verificarPreco()` - Deveria logar consulta de preço

**Padrão recomendado:**
```php
// No início do método
Log::info('Operação iniciada', [
    'method' => __METHOD__,
    'user_id' => request()->user()->id ?? null,
    'user_email' => request()->user()->email ?? null,
    'ip' => request()->ip(),
    'input' => $this->sanitizeLogData($request->all()),
    'timestamp' => now()->toIso8601String()
]);
```

---

### Prioridade BAIXA (Melhorias Opcionais):

#### 4. Adicionar Confirmação Dupla para Compra (Opcional)

Similar ao cancelamento do SemPararController:
```php
// Validação
$request->validate([
    // ... outros campos
    'confirmacao' => 'required|boolean|accepted'  // Confirmação dupla
]);
```

**Benefício:** Previne compras acidentais em produção.

---

#### 5. Criar Dashboard de Auditoria (Opcional)

- Exibir logs de compras por usuário
- Filtrar por período, placa, rota
- Mostrar tentativas bloqueadas (fraude, duplicação)

---

## 🔗 Arquivos Relacionados

- **Routes:** `routes/api.php` (linhas 237-284) - ✅ Já protegido com auth:sanctum
- **Service:** `app/Services/SemParar/SemPararService.php` - Integração SOAP
- **Service:** `app/Services/ProgressService.php` - Integração Progress DB
- **Frontend:** `resources/ts/pages/compra-viagem/` - Interface Vue (em desenvolvimento)
- **Documentação:** `SEMPARAR_IMPLEMENTATION_ROADMAP.md`

---

## ✍️ Assinatura

**Auditado por:** Sistema de Auditoria de Segurança
**Data:** 2025-12-04
**Horário:** 11:00 (UTC-3)
**Status:** 🟡 MÉDIO - Melhorias recomendadas (não críticas)

**Observação:** Este controller está em **MUITO MELHOR ESTADO** que o SemPararController. Já possui 9 correções de segurança importantes implementadas, autenticação adequada nas rotas, e apenas requer pequenos ajustes na exposição de erros e logging de usuário.

**Próxima Ação:** Implementar correções de exposição de stack trace e resolver TODO de user_id em logs.
