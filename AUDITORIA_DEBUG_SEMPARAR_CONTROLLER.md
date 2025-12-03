# 🔒 Auditoria de Segurança: DebugSemPararController

**Data:** 2025-12-03
**Auditor:** Sistema de Segurança Automatizado
**Arquivo:** `app/Http/Controllers/Api/DebugSemPararController.php`
**Linhas de código:** 162
**Endpoints auditados:** 1 (`debugFlow`)

---

## 📋 Resumo Executivo

### Estatísticas da Auditoria
- ✅ **Vulnerabilidades encontradas:** 6 total
  - 🔴 **CRITICAL:** 1
  - 🟠 **HIGH:** 2
  - 🟡 **MEDIUM:** 2
  - 🔵 **LOW:** 1
- ⚠️ **Risco geral:** CRÍTICO
- 📊 **Score de segurança:** 35/100 (INSEGURO para produção)

### Pontos Positivos ✅
- ✅ Endpoint requer autenticação (`auth:sanctum`)
- ✅ Rate limiting configurado (10 req/min)
- ✅ Não é usado pelo frontend (pode ser removido com segurança)
- ✅ Endpoint está em grupo protegido (`/api/compra-viagem/debug-flow`)

### Principais Riscos 🚨
1. 🔴 **Debug Endpoint Ativo em Produção:** Sem verificação de `APP_DEBUG`
2. 🟠 **Information Disclosure Massiva:** Expõe estrutura do banco, queries, stack traces
3. 🟠 **SQL Injection Potencial:** String concatenation sem prepared statements
4. 🟡 **Exposição de Propriedade Intelectual:** Referências ao código Progress interno

---

## 🔍 Vulnerabilidades Detalhadas

### 🔴 VULNERABILIDADE #1 (CRITICAL): Debug Endpoint Ativo em Produção
**Severidade:** CRITICAL
**CWE:** CWE-489 (Active Debug Code)
**OWASP:** A05:2021 - Security Misconfiguration

**Localização:** `DebugSemPararController.php` linhas 23-161 + `routes/api.php` linha 275

**Problema:**
Endpoint de debug está ATIVO em produção sem verificação de `APP_DEBUG` ou `APP_ENV`. Qualquer usuário autenticado pode acessar informações sensíveis do sistema.

**Código atual:**
```php
// DebugSemPararController.php
public function debugFlow(Request $request)
{
    // ❌ SEM VERIFICAÇÃO DE AMBIENTE!
    $codPac = $request->input('codpac');
    $codRota = $request->input('cod_rota');

    $debug = [
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'inputs' => ['codpac' => $codPac, 'cod_rota' => $codRota],
        'steps' => []
    ];

    // ... executa queries e retorna tudo ...
}
```

```php
// routes/api.php linha 275
Route::post('debug-flow', [\App\Http\Controllers\Api\DebugSemPararController::class, 'debugFlow'])
    ->middleware('throttle:10,1');  // ❌ SEMPRE ativo!
```

**Cenário de Exploração:**
```bash
# Atacante autenticado (ou token vazado) pode chamar:
curl -X POST http://production.com/api/compra-viagem/debug-flow \
    -H "Authorization: Bearer <token>" \
    -d '{"codpac": 123, "cod_rota": 204}'

# Resposta expõe:
{
  "success": true,
  "debug": {
    "steps": [
      {
        "name": "Buscar Rota Progress",
        "progress_ref": "Rota.cls linha 695-714",  // ❌ Código interno!
        "data": {...}  // ❌ Estrutura do banco!
      }
    ],
    "analysis": {
      "problem_identified": "...",  // ❌ Análise interna!
      "solution": "..."
    }
  }
}
```

**Impacto:**
- Exposição de estrutura interna do sistema
- Revelação de lógica de negócio
- Possível reconnaissance para ataques direcionados
- Violação de propriedade intelectual (referências ao código Progress)

**CORREÇÃO #1:**
```php
public function debugFlow(Request $request)
{
    // CORREÇÃO #1: Bloquear em produção
    if (!config('app.debug')) {
        return response()->json([
            'success' => false,
            'message' => 'Endpoint de debug desabilitado em produção'
        ], 403);
    }

    // CORREÇÃO #1 (Alternativa): Verificar ambiente
    if (config('app.env') === 'production') {
        Log::warning('Tentativa de acesso ao endpoint de debug em produção', [
            'user_id' => $request->user()->id ?? null,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Endpoint não disponível neste ambiente'
        ], 403);
    }

    // ... restante do código ...
}
```

---

### 🟠 VULNERABILIDADE #2 (HIGH): Information Disclosure Massiva
**Severidade:** HIGH
**CWE:** CWE-200 (Exposure of Sensitive Information to an Unauthorized Actor)
**OWASP:** A01:2021 - Broken Access Control

**Localização:** `DebugSemPararController.php` linhas 28-160

**Problema:**
Endpoint retorna informações extremamente sensíveis:
- Estrutura completa do banco de dados
- Queries SQL literais
- Referências ao código-fonte Progress (Rota.cls)
- Stack traces completos de exceções
- Análise interna de problemas do sistema

**Código atual:**
```php
$debug['analysis'] = [
    'progress_flow' => [
        '1. Loop municipios rota (semPararRotMu) → t-entrega com IBGE, lat=0, lon=0',
        '2. Loop entregas pacote (carga→pedido→arqrdnt) → t-entrega com GPS real',
        '3. Se achou município pelo nome → ZERA GPS e mantém IBGE (linha 787-790)',  // ❌
        '4. Envia DATASET com mix: municípios (IBGE+0,0) + entregas (GPS+IBGE=0)',
    ],
    'php_current_implementation' => [
        '1. Busca municípios → adiciona com IBGE, lat=0, lon=0 ✓',
        '2. Busca entregas via getItinerarioPacote() → TIMEOUT/LENTO ❌',  // ❌
        '3. Não está chegando entregas com GPS ❌'
    ],
    'problem_identified' => 'Query de entregas está travando. Progress usa loop FOR EACH otimizado, PHP usa JOIN pesado.',  // ❌
    'solution' => 'Simplificar query ou usar approach diferente para buscar arqrdnt'  // ❌
];

// Linhas 146-160: Stack traces completos
catch (\Exception $e) {
    $debug['steps'][] = [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()  // ❌ VAZAMENTO CRÍTICO!
    ];
}
```

**Informações expostas:**
1. **Estrutura do banco:**
   - Tabelas: `PUB.semPararRot`, `PUB.semPararRotMu`, `PUB.pacote`, `PUB.carga`, `PUB.pedido`, `PUB.arqrdnt`
   - Colunas: `sPararRotID`, `desSPararRot`, `cdibge`, `latitute`, `longitude`, etc.
   - Relacionamentos: `carga.codcar = pedido.codcar`, etc.

2. **Referências ao código Progress:**
   - `Rota.cls linha 695-714`
   - `Rota.cls linha 698-713 (loop semPararRotMu)`
   - `Rota.cls linha 716`
   - `Rota.cls linha 787-790`

3. **Análise interna:**
   - "Progress usa loop FOR EACH otimizado, PHP usa JOIN pesado"
   - "Query de entregas está travando"
   - "getItinerarioPacote() → TIMEOUT/LENTO"

**Impacto:**
- Competitor intelligence (revelação de lógica de negócio)
- Facilita engenharia reversa do sistema
- Exposição de problemas de performance não resolvidos
- Possível violação de NDA/contratos de propriedade intelectual

**CORREÇÃO #2:**
```php
// Opção A: Sanitizar completamente
$debug['steps'][] = [
    'number' => 1,
    'name' => 'Buscar Rota',
    'status' => 'success',
    'data' => [
        'id' => $rota['sPararRotID'],
        'nome' => $rota['desSPararRot']
        // ❌ NÃO expor: progress_ref, queries SQL, análise interna
    ]
];

// Opção B: Remover análise sensível
// REMOVER linhas 125-139 completamente

// Opção C: Logar em vez de retornar
Log::debug('Debug flow analysis', [
    'progress_flow' => [...],
    'problem_identified' => '...'
]);
// NÃO retornar no JSON de resposta
```

---

### 🟠 VULNERABILIDADE #3 (HIGH): SQL Injection via String Concatenation
**Severidade:** HIGH
**CWE:** CWE-89 (SQL Injection)
**OWASP:** A03:2021 - Injection

**Localização:** `DebugSemPararController.php` linhas 46, 65, 83, 105-110

**Problema:**
Queries SQL são construídas com concatenação de strings usando apenas `intval()` para sanitização. Embora `intval()` previna SQL injection para valores numéricos, a prática é insegura e pode ser esquecida em futuras modificações.

**Código atual:**
```php
// Linha 46
$sqlRota = "SELECT TOP 1 r.sPararRotID, r.desSPararRot, r.flgRetorno, r.flgCD FROM PUB.semPararRot r WHERE r.sPararRotID = " . intval($codRota);
// ❌ String concatenation

// Linha 65
$sqlMunicipios = "SELECT m.cdibge, m.desMun, m.desEst FROM PUB.semPararRotMu m WHERE m.sPararRotID = " . intval($codRota) . " ORDER BY m.sPararMuSeq";
// ❌ String concatenation

// Linha 83
$sqlPacote = "SELECT TOP 1 codpac, codrot FROM PUB.pacote WHERE codpac = " . intval($codPac);
// ❌ String concatenation

// Linhas 105-110
$sqlEntregas = "SELECT TOP 10 ped.numseqped, ped.asdped, cli.desend, ard.latitute, ard.longitude, ard.cidade " .
              "FROM PUB.carga car " .
              "INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar " .
              "INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli " .
              "LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped " .
              "WHERE car.codpac = " . intval($codPac);
// ❌ String concatenation
```

**Por que é vulnerável:**
```php
// Cenário 1: intval() protege contra SQL injection NESTE caso específico
intval("123; DROP TABLE users") → 123 ✅

// Cenário 2: MAS se futura modificação adicionar campo string...
$sqlSearch = "SELECT * FROM rota WHERE nome LIKE '%" . $request->input('nome') . "%'";
// ❌ SQL INJECTION CLÁSSICO!

// Cenário 3: Se alguém copiar o padrão sem entender...
$sqlUser = "SELECT * FROM users WHERE email = '" . $request->input('email') . "'";
// ❌ SQL INJECTION CLÁSSICO!
```

**CORREÇÃO #3:**
```php
// SEMPRE usar prepared statements (mesmo para debug!)
$sqlRota = "SELECT TOP 1 r.sPararRotID, r.desSPararRot, r.flgRetorno, r.flgCD
            FROM PUB.semPararRot r
            WHERE r.sPararRotID = ?";

$resultRota = $this->progressService->executeCustomQuery($sqlRota, [$codRota]);

// OU usar query builder (se disponível para Progress)
$rota = DB::connection('progress')
    ->table('PUB.semPararRot')
    ->where('sPararRotID', $codRota)
    ->first();
```

**Nota:** O ProgressService atual (`executeCustomQuery`) JÁ suporta bindings (linha 47 do audit mostra), então a correção é trivial!

---

### 🟡 VULNERABILIDADE #4 (MEDIUM): Exposição de Stack Traces Completos
**Severidade:** MEDIUM
**CWE:** CWE-209 (Generation of Error Message Containing Sensitive Information)
**OWASP:** A05:2021 - Security Misconfiguration

**Localização:** `DebugSemPararController.php` linhas 146-160

**Problema:**
Em caso de erro, o endpoint retorna stack traces completos da exceção, expondo:
- Caminhos absolutos de arquivos no servidor
- Nomes de classes internas
- Estrutura de diretórios
- Versões de bibliotecas (via namespaces)

**Código atual:**
```php
catch (\Exception $e) {
    $debug['steps'][] = [
        'number' => 999,
        'name' => 'ERRO',
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()  // ❌ VAZAMENTO CRÍTICO!
    ];

    return response()->json([
        'success' => false,
        'debug' => $debug,
        'error' => $e->getMessage()
    ]);
}
```

**Exemplo de stack trace vazado:**
```
#0 /var/www/ndd-vuexy/app/Services/ProgressService.php(245): executeJavaConnector()
#1 /var/www/ndd-vuexy/app/Http/Controllers/Api/DebugSemPararController.php(47): executeCustomQuery()
#2 /var/www/ndd-vuexy/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): debugFlow()
...
```

**Informações vazadas:**
- ❌ Caminho completo do servidor: `/var/www/ndd-vuexy/`
- ❌ Estrutura de diretórios: `app/Services/`, `app/Http/Controllers/`
- ❌ Nomes de classes: `ProgressService`, `DebugSemPararController`
- ❌ Números de linha exatos: `linha 245`, `linha 47`
- ❌ Versão do Laravel: `vendor/laravel/framework/`

**CORREÇÃO #4:**
```php
catch (\Exception $e) {
    // CORREÇÃO #4: Logar trace completo, retornar apenas mensagem genérica
    Log::error('Erro no debug flow', [
        'user_id' => $request->user()->id ?? null,
        'codpac' => $codPac,
        'cod_rota' => $codRota,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),  // ✅ Apenas em logs
        'timestamp' => now()->toIso8601String()
    ]);

    $debug['steps'][] = [
        'number' => 999,
        'name' => 'ERRO',
        'status' => 'error',
        'error' => 'Erro interno no processamento'  // ✅ Mensagem genérica
        // ❌ NÃO retornar: trace
    ];

    return response()->json([
        'success' => false,
        'debug' => $debug,
        'error' => 'Erro interno. Contate o suporte com timestamp: ' . now()->toIso8601String()
    ], 500);
}
```

---

### 🟡 VULNERABILIDADE #5 (MEDIUM): Sem Logging de Acesso
**Severidade:** MEDIUM
**CWE:** CWE-778 (Insufficient Logging)
**LGPD:** Art. 46 (Registro de eventos de segurança)

**Localização:** `DebugSemPararController.php` (ausência de logging)

**Problema:**
Endpoint de debug NÃO registra quem o acessou, quando, e com quais parâmetros. Isso impossibilita:
- Auditoria de acesso a informações sensíveis
- Detecção de uso indevido
- Compliance com LGPD Art. 46

**Código atual:**
```php
public function debugFlow(Request $request)
{
    // ❌ SEM LOGGING DE ACESSO!
    $codPac = $request->input('codpac');
    $codRota = $request->input('cod_rota');

    // ... executa debug ...

    return response()->json([...]);  // ❌ Sem registro de quem acessou
}
```

**CORREÇÃO #5:**
```php
public function debugFlow(Request $request)
{
    // Verificar ambiente (CORREÇÃO #1)
    if (!config('app.debug')) {
        return response()->json([...], 403);
    }

    // CORREÇÃO #5: Logging de acesso ANTES de processar
    Log::warning('Acesso ao endpoint de debug', [
        'user_id' => $request->user()->id ?? null,
        'user_email' => $request->user()->email ?? null,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'codpac' => $request->input('codpac'),
        'cod_rota' => $request->input('cod_rota'),
        'timestamp' => now()->toIso8601String()
    ]);

    // ... restante do código ...

    // CORREÇÃO #5: Logging de resultado
    Log::info('Debug flow executado com sucesso', [
        'user_id' => $request->user()->id ?? null,
        'total_steps' => count($debug['steps'])
    ]);

    return response()->json([...]);
}
```

---

### 🔵 VULNERABILIDADE #6 (LOW): Queries Lentas Sem Timeout
**Severidade:** LOW
**CWE:** CWE-400 (Uncontrolled Resource Consumption)

**Localização:** `DebugSemPararController.php` linhas 105-114

**Problema:**
Query de entregas (JOIN complexo em 4 tabelas) pode ser muito lenta, mas não há timeout configurado. Mesmo com rate limiting (10 req/min), usuário pode travar conexões do banco.

**Código atual:**
```php
// Linha 101: Warning reconhece o problema
'warning' => 'Query pode ser lenta - verificar índices'

// Linhas 105-114: Query complexa SEM timeout
$sqlEntregas = "SELECT TOP 10 ped.numseqped, ped.asdped, cli.desend, ard.latitute, ard.longitude, ard.cidade " .
              "FROM PUB.carga car " .
              "INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar " .
              "INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli " .
              "LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped " .
              "WHERE car.codpac = " . intval($codPac);

$startTime = microtime(true);
$resultEntregas = $this->progressService->executeCustomQuery($sqlEntregas);  // ❌ Sem timeout!
$endTime = microtime(true);
```

**Impacto:**
- Possível timeout do PHP (max_execution_time)
- Bloqueio de conexões do banco Progress
- Bad UX (usuário fica esperando indefinidamente)

**CORREÇÃO #6:**
```php
// Opção A: Timeout no PHP
set_time_limit(10);  // 10 segundos max

try {
    $resultEntregas = $this->progressService->executeCustomQuery($sqlEntregas);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'timeout')) {
        return response()->json([
            'success' => false,
            'error' => 'Query de entregas excedeu timeout de 10s'
        ], 504);  // Gateway Timeout
    }
    throw $e;
}

// Opção B: Timeout no Progress (se suportado)
$sqlEntregas = "SELECT TOP 10 ... WITH (TIMEOUT 10000)";  // 10s

// Opção C: Simplificar query (melhor solução!)
// Buscar apenas carga e pedido, deixar arqrdnt para depois
$sqlEntregas = "SELECT TOP 10 ped.numseqped FROM PUB.carga car
                INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar
                WHERE car.codpac = ?";
```

---

## 📊 Análise de Compatibilidade com Frontend

### Uso do Endpoint
**✅ FRONTEND NÃO USA ESTE ENDPOINT**

Verificação realizada via Grep:
```bash
# Busca em todos os arquivos .vue
grep -r "debug-flow" resources/ts/**/*.vue
# Resultado: NENHUM arquivo encontrado

grep -r "DebugSemPararController" resources/ts/**/*.vue
# Resultado: NENHUM arquivo encontrado

grep -r "api/compra-viagem/debug" resources/ts/**/*.vue
# Resultado: NENHUM arquivo encontrado
```

**Conclusão:** Endpoint pode ser:
1. ✅ Desabilitado em produção sem breaking changes
2. ✅ Removido completamente (se não há uso)
3. ✅ Mantido apenas para desenvolvimento local

---

## 📝 Análise de Risco vs Benefício

### Benefícios do Endpoint de Debug
- ✅ Útil para desenvolvimento
- ✅ Ajuda a comparar lógica Progress vs PHP
- ✅ Facilita debugging de queries lentas
- ✅ Mostra estrutura de dados para testes

### Riscos do Endpoint em Produção
- 🔴 **CRITICAL:** Information disclosure
- 🔴 **CRITICAL:** Exposição de propriedade intelectual
- 🟠 **HIGH:** Facilita reconnaissance para ataques
- 🟠 **HIGH:** SQL injection se mal utilizado
- 🟡 **MEDIUM:** Sem auditoria de acesso
- 🟡 **MEDIUM:** Queries lentas podem travar sistema

### Recomendação: **DESABILITAR EM PRODUÇÃO**

**Justificativa:**
- Frontend não usa o endpoint
- Riscos superam benefícios em produção
- Endpoint é útil APENAS para desenvolvimento
- Pode ser mantido localmente com `APP_DEBUG=true`

---

## 🛠️ Plano de Implementação

### FASE 1 - Hardening Mínimo (100% Backward Compatible)
**Prioridade:** CRÍTICA
**Tempo:** 20 minutos
**Breaking Changes:** NENHUM

**Implementar:**
- ✅ CORREÇÃO #1: Verificação de `APP_DEBUG` / `APP_ENV`
- ✅ CORREÇÃO #4: Remover stack traces da resposta (manter em logs)
- ✅ CORREÇÃO #5: Logging de acesso ao endpoint

**Resultado:** Endpoint ainda funciona em desenvolvimento, mas bloqueado em produção

---

### FASE 2 - Sanitização (Opcional, se manter endpoint)
**Prioridade:** ALTA
**Tempo:** 1 hora
**Breaking Changes:** Altera formato da resposta JSON

**Implementar:**
- ✅ CORREÇÃO #2: Remover seção `analysis` da resposta
- ✅ CORREÇÃO #2: Remover referências `progress_ref`
- ✅ CORREÇÃO #3: Usar prepared statements nas queries
- ✅ CORREÇÃO #6: Adicionar timeout nas queries

---

### FASE 3 - Remoção Completa (Recomendado)
**Prioridade:** MÉDIA
**Tempo:** 10 minutos
**Breaking Changes:** Remove endpoint (mas frontend não usa)

**Implementar:**
- ✅ Remover rota de `routes/api.php`
- ✅ Remover controller `DebugSemPararController.php`
- ✅ Adicionar comentário explicando remoção

**Justificativa:**
- Frontend não depende do endpoint
- Endpoint foi criado para desenvolvimento/debugging
- Não há uso em produção legítimo
- Melhor prática: remover código não utilizado

---

## 📝 Checklist de Implementação

### FASE 1 - Hardening Mínimo ✅
```bash
[ ] Ler DebugSemPararController.php linha por linha
[ ] Implementar CORREÇÃO #1 (APP_DEBUG check)
[ ] Implementar CORREÇÃO #4 (Remover stack traces)
[ ] Implementar CORREÇÃO #5 (Logging de acesso)
[ ] Testar endpoint com APP_DEBUG=true (deve funcionar)
[ ] Testar endpoint com APP_DEBUG=false (deve retornar 403)
[ ] Verificar logs de acesso em storage/logs/laravel.log
[ ] Commitar mudanças
```

---

## 🔐 Mapeamento de Compliance

### LGPD (Lei Geral de Proteção de Dados)
- ✅ **Art. 46:** Registro de eventos de segurança
  - CORREÇÃO #5: Logs de acesso ao endpoint de debug

### OWASP Top 10 2021
- ✅ **A01:2021 - Broken Access Control:** CORREÇÃO #2 (Information Disclosure)
- ✅ **A03:2021 - Injection:** CORREÇÃO #3 (SQL Injection)
- ✅ **A05:2021 - Security Misconfiguration:** CORREÇÃO #1, #4

### CWE (Common Weakness Enumeration)
- ✅ **CWE-89:** SQL Injection - CORREÇÃO #3
- ✅ **CWE-200:** Information Disclosure - CORREÇÃO #2
- ✅ **CWE-209:** Error Message Information Leak - CORREÇÃO #4
- ✅ **CWE-489:** Active Debug Code - CORREÇÃO #1
- ✅ **CWE-778:** Insufficient Logging - CORREÇÃO #5

---

## 📚 Referências

- [OWASP Debug Code](https://owasp.org/www-community/vulnerabilities/Leftover_Debug_Code)
- [CWE-489: Active Debug Code](https://cwe.mitre.org/data/definitions/489.html)
- [Laravel Environment Configuration](https://laravel.com/docs/12.x/configuration#environment-configuration)
- [LGPD Art. 46](http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)

---

**Próximos Passos:**
1. ✅ Revisar esta documentação
2. ✅ Implementar FASE 1 (hardening mínimo)
3. ⏳ Decidir entre FASE 2 (sanitizar) ou FASE 3 (remover)
4. ⏳ Auditar SemPararController (CRITICAL #5)
