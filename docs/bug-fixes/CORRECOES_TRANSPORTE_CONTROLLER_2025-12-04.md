# 🔒 Correções de Segurança - TransporteController

**Data:** 2025-12-04
**Arquivo:** `app/Http/Controllers/Api/TransporteController.php`
**Auditor:** Claude Code (Security Audit)

---

## 📋 RESUMO EXECUTIVO

| Métrica | Valor |
|---------|-------|
| **Métodos auditados** | 6 |
| **Vulnerabilidades encontradas** | 15 |
| **Severidade CRÍTICA** | 3 |
| **Severidade ALTA** | 6 |
| **Severidade MÉDIA** | 6 |
| **Linhas afetadas** | ~60 |

---

## 🚨 VULNERABILIDADES CRÍTICAS

### CRÍTICO #1: Ausência Total de Logging LGPD Art. 46
**Severidade:** 🔴 CRÍTICA
**Métodos afetados:** TODOS (6/6)
**Impacto:** Violação da LGPD Art. 46 - Falta de auditoria de acesso a dados pessoais

**Problema:**
```php
// ❌ ANTES - Nenhum método registra acesso
public function index(Request $request): JsonResponse {
    $result = $this->progressService->getTransportesPaginated($filters);
    return response()->json([...]);  // Sem logging!
}

public function show($id): JsonResponse {
    $result = $this->progressService->getTransporteById($id);
    return response()->json([...]);  // Acesso a dados sensíveis sem log!
}
```

**Solução:**
```php
// ✅ DEPOIS - Log estruturado com IP, timestamp, ação
use Illuminate\Support\Facades\Log;

public function index(Request $request): JsonResponse {
    Log::info('Listagem de transportes acessada', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'filters' => $filters,
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->getTransportesPaginated($filters);
    // ...
}

public function show($id): JsonResponse {
    Log::info('Detalhes de transportador acessados', [
        'transporte_id' => $id,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);
    // ...
}
```

**Métodos que precisam de logging:**
- ✅ `index()` - Log de listagem com filtros
- ✅ `show()` - Log de acesso a dados específicos
- ✅ `testConnection()` - Log de tentativas de conexão (segurança)
- ✅ `statistics()` - Log de consulta a estatísticas
- ✅ `schema()` - Log de acesso a metadados (crítico!)
- ✅ `query()` - Log de queries customizadas (CRÍTICO!)

---

### CRÍTICO #2: query() Sem Logging de Auditoria
**Severidade:** 🔴 CRÍTICA
**Linha:** 208-263
**Impacto:** Administradores podem executar SQL sem rastro de auditoria

**Problema:**
```php
// ❌ ANTES - Admin pode executar SQL sem registro
public function query(Request $request): JsonResponse {
    $user = $request->user();
    if (!$user || $user->role !== 'admin') {
        return response()->json([...], 403);
    }

    // SQL executado SEM LOGGING! 🚨
    $result = $this->progressService->executeCustomQuery($sql);

    return response()->json([...]);
}
```

**Solução:**
```php
// ✅ DEPOIS - Auditoria completa de queries customizadas
public function query(Request $request): JsonResponse {
    $user = $request->user();
    if (!$user || $user->role !== 'admin') {
        Log::warning('Tentativa de acesso não autorizado a query customizada', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);
        return response()->json([...], 403);
    }

    // Log ANTES de executar
    Log::info('Query customizada executada por admin', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'sql' => $sql,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->executeCustomQuery($sql);

    // Log do resultado
    if (!$result['success']) {
        Log::error('Query customizada falhou', [
            'user_id' => $user->id,
            'sql' => $sql,
            'error' => $result['error'],
            'timestamp' => now()->toIso8601String()
        ]);
    }

    return response()->json([...]);
}
```

**Por que é crítico:**
- Admins podem acessar QUALQUER dado sem registro
- Impossível rastrear vazamentos de dados
- Violação de compliance (LGPD, SOC2, ISO 27001)

---

### CRÍTICO #3: query() - Detecção Fraca de Palavras-Chave SQL
**Severidade:** 🔴 CRÍTICA
**Linha:** 238-245
**Impacto:** False positives bloqueiam queries legítimas + False negatives permitem ataques

**Problema:**
```php
// ❌ ANTES - strpos() tem problemas
foreach ($dangerousPatterns as $pattern) {
    if (strpos($sqlUpper, $pattern) !== false) {
        return response()->json([...], 422);
    }
}

// Casos problemáticos:
// ❌ False positive: "SELECT codRotCreateSP FROM table" (bloqueado por "CREATE")
// ❌ False positive: "SELECT description FROM table" (bloqueado por "--" em "description")
// ⚠️ Não detecta: "SEL ECT" (bypass com espaço)
```

**Solução:**
```php
// ✅ DEPOIS - Word boundaries + trim
$dangerousPatterns = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE',
                      'INSERT', 'UPDATE', 'EXEC', 'EXECUTE'];

foreach ($dangerousPatterns as $pattern) {
    // Word boundary (\b) só detecta palavras completas
    if (preg_match('/\b' . $pattern . '\b/i', $sqlUpper)) {
        Log::warning('Query customizada bloqueada - palavra-chave perigosa', [
            'user_id' => $user->id,
            'sql' => $sql,
            'keyword' => $pattern,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => "Palavra-chave proibida detectada: {$pattern}",
            'data' => null
        ], 422);
    }
}

// Validação adicional para comentários SQL
if (preg_match('/(--|\/\*|\*\/)/', $sql)) {
    Log::warning('Query customizada bloqueada - comentários SQL', [
        'user_id' => $user->id,
        'sql' => $sql,
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Comentários SQL não são permitidos',
        'data' => null
    ], 422);
}

// Casos agora corretos:
// ✅ "SELECT codRotCreateSP FROM table" (permitido - não é palavra completa)
// ✅ "SELECT description FROM table" (permitido)
// ❌ "CREATE TABLE users" (bloqueado - palavra completa)
// ❌ "DROP TABLE" (bloqueado - palavra completa)
```

**Por que é crítico:**
- False positives frustram usuários legítimos
- Mesma vulnerabilidade já corrigida em ProgressService
- Inconsistência de validação entre controller e service

---

## 🟠 VULNERABILIDADES ALTAS

### ALTA #1: Ausência de Error IDs em TODOS os Métodos
**Severidade:** 🟠 ALTA
**Métodos afetados:** TODOS (6/6)
**Impacto:** Impossível correlacionar erros do usuário com logs do servidor

**Problema:**
```php
// ❌ ANTES - Mensagens genéricas sem ID
return response()->json([
    'success' => false,
    'message' => 'Erro ao obter transportes',  // Qual erro? Onde?
    'data' => null
], 500);
```

**Solução:**
```php
// ✅ DEPOIS - Error ID para correlação
$errorId = uniqid('err_');

Log::error('Falha ao listar transportes', [
    'error_id' => $errorId,
    'error_message' => $result['error'],
    'filters' => $filters,
    'ip' => $request->ip(),
    'timestamp' => now()->toIso8601String()
]);

return response()->json([
    'success' => false,
    'message' => 'Erro ao obter transportes. ID do erro: ' . $errorId,
    'error_id' => $errorId,
    'data' => null
], 500);
```

**Benefícios:**
- Suporte técnico pode buscar no log: `grep "err_6748a2b3c4d5e"`
- Usuário pode reportar "Erro err_6748a2b3c4d5e"
- Correlação entre frontend e backend

---

### ALTA #2: Vazamento de Erros Internos
**Severidade:** 🟠 ALTA
**Linhas afetadas:** 73, 107, 176, 192, 253
**Impacto:** Exposição de estrutura do banco de dados e lógica interna

**Problema:**
```php
// ❌ ANTES - Expõe detalhes internos
// Linha 73 (index)
return response()->json([
    'success' => false,
    'message' => $result['error'],  // "Table PUB.transporte not found"
    'data' => null
], 500);

// Linha 176 (statistics)
return response()->json([
    'success' => false,
    'message' => 'Erro ao obter estatísticas: ' . $e->getMessage(),  // Stack trace!
    'data' => null
], 500);

// Linha 107 (show)
'message' => $result['error'] ?? 'Transporte não encontrado',  // Pode vazar SQL
```

**Solução:**
```php
// ✅ DEPOIS - Mensagens genéricas + error ID
$errorId = uniqid('err_');

Log::error('Falha ao listar transportes', [
    'error_id' => $errorId,
    'error_message' => $result['error'],  // Log completo no servidor
    'filters' => $filters,
    'timestamp' => now()->toIso8601String()
]);

return response()->json([
    'success' => false,
    'message' => 'Erro ao processar solicitação. ID: ' . $errorId,  // Mensagem genérica
    'error_id' => $errorId,
    'data' => null
], 500);
```

**Linhas a corrigir:**
- Linha 73: `$result['error']` → mensagem genérica
- Linha 107: `$result['error'] ?? '...'` → mensagem genérica
- Linha 176: `$e->getMessage()` → mensagem genérica
- Linha 192: `$result['error']` → mensagem genérica
- Linha 253: `$result['error']` → mensagem genérica

---

### ALTA #3: testConnection() Exposto Sem Logging
**Severidade:** 🟠 ALTA
**Linha:** 131-136
**Impacto:** Reconhecimento de infraestrutura sem detecção

**Problema:**
```php
// ❌ ANTES - Endpoint de teste sem auditoria
public function testConnection(): JsonResponse {
    $result = $this->progressService->testConnection();
    return response()->json($result, $result['success'] ? 200 : 500);
}

// Atacante pode:
// 1. Testar conexão repetidamente (DoS)
// 2. Mapear infraestrutura (response time analysis)
// 3. Detectar vulnerabilidades (version disclosure)
```

**Solução:**
```php
// ✅ DEPOIS - Log de todas as tentativas
public function testConnection(Request $request): JsonResponse {
    Log::info('Tentativa de teste de conexão Progress', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->testConnection();

    if (!$result['success']) {
        $errorId = uniqid('err_');

        Log::error('Falha no teste de conexão Progress', [
            'error_id' => $errorId,
            'error' => $result['error'] ?? 'Erro desconhecido',
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Falha na conexão. ID: ' . $errorId,
            'error_id' => $errorId
        ], 500);
    }

    Log::info('Teste de conexão Progress bem-sucedido', [
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json($result, 200);
}
```

**Por que é alta:**
- Endpoint de infraestrutura deve ser monitorado
- Tentativas falhadas podem indicar ataque
- Rate limiting sozinho não é suficiente (precisa de logs)

---

### ALTA #4: schema() Expõe Metadados Sem Autenticação
**Severidade:** 🟠 ALTA
**Linha:** 185-202
**Impacto:** Estrutura do banco exposta publicamente

**Problema:**
```php
// ❌ ANTES - Qualquer um pode ver o schema
public function schema(): JsonResponse {
    $result = $this->progressService->getTransporteTableSchema();
    // Retorna: colunas, tipos, constraints, índices
    return response()->json([...]);
}

// Atacante obtém:
// - Nomes de colunas (para SQL injection)
// - Tipos de dados (para validação bypass)
// - Constraints (para encontrar brechas)
// - Relacionamentos (para mapear sistema)
```

**Solução:**
```php
// ✅ DEPOIS - Requer autenticação + logging
public function schema(Request $request): JsonResponse {
    // Verificar autenticação (similar ao query())
    $user = $request->user();
    if (!$user || !in_array($user->role, ['admin', 'developer'], true)) {
        Log::warning('Tentativa de acesso não autorizado ao schema', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Acesso negado. Requer privilégios de administrador.',
            'data' => null
        ], 403);
    }

    Log::info('Schema da tabela transporte acessado', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->getTransporteTableSchema();

    if (!$result['success']) {
        $errorId = uniqid('err_');

        Log::error('Falha ao obter schema', [
            'error_id' => $errorId,
            'user_id' => $user->id,
            'error' => $result['error'],
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erro ao obter schema. ID: ' . $errorId,
            'error_id' => $errorId,
            'data' => null
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Schema da tabela transporte obtido com sucesso',
        'data' => $result['data']
    ]);
}
```

**NOTA:** Isso vai quebrar o frontend se ele usa esse endpoint! Verifique antes de implementar.

---

### ALTA #5: statistics() Sem Rate Limiting Adequado
**Severidade:** 🟠 ALTA
**Linha:** 142-180
**Impacto:** Query agregada cara pode causar DoS

**Problema:**
```php
// ❌ Query cara sem proteção adequada
$sql = "SELECT COUNT(*) as total, SUM(CASE...) FROM PUB.transporte";
// Esta query escaneia TODA a tabela transporte!
// Se tabela tem 100k registros, cada chamada é cara
```

**Solução:**
- ✅ Já tem rate limiting em routes/api.php: `throttle:10,1`
- ✅ Mas adicionar logging para monitorar uso
- ✅ Considerar cache de 5 minutos (estatísticas não mudam rápido)

```php
// ✅ Adicionar cache
use Illuminate\Support\Facades\Cache;

public function statistics(Request $request): JsonResponse {
    Log::info('Estatísticas de transportes acessadas', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    // Cache por 5 minutos
    $stats = Cache::remember('transporte_statistics', 300, function () {
        $sql = "SELECT COUNT(*) as total, ...";
        $result = $this->progressService->executeCustomQuery($sql);

        if (!$result['success'] || empty($result['data']['results'])) {
            return null;
        }

        $row = $result['data']['results'][0];
        return [
            'total' => (int)($row['total'] ?? 0),
            'autonomos' => (int)($row['autonomos'] ?? 0),
            // ...
        ];
    });

    if ($stats === null) {
        $errorId = uniqid('err_');

        Log::error('Falha ao obter estatísticas', [
            'error_id' => $errorId,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
            'error_id' => $errorId,
            'data' => null
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Estatísticas obtidas com sucesso',
        'data' => $stats
    ]);
}
```

---

### ALTA #6: show() Vaza Erros de Relacionamentos
**Severidade:** 🟠 ALTA
**Linha:** 112-119
**Impacto:** Falhas silenciosas podem ocultar problemas + vazamento de erros

**Problema:**
```php
// ❌ ANTES - Falhas silenciosas
$motoristasResult = $this->progressService->getMotoristasPorTransportador($id);
$transporte['motoristas'] = $motoristasResult['success'] ? $motoristasResult['data'] : [];

$veiculosResult = $this->progressService->getVeiculosPorTransportador($id);
$transporte['veiculos'] = $veiculosResult['success'] ? $veiculosResult['data'] : [];

// Se falhar, retorna array vazio SEM avisar o usuário!
// Usuário acha que não tem motoristas, mas na verdade houve erro SQL
```

**Solução:**
```php
// ✅ DEPOIS - Log de falhas + erro opcional
$motoristasResult = $this->progressService->getMotoristasPorTransportador($id);
if (!$motoristasResult['success']) {
    Log::warning('Falha ao carregar motoristas do transportador', [
        'transporte_id' => $id,
        'error' => $motoristasResult['error'] ?? 'Erro desconhecido',
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);
}
$transporte['motoristas'] = $motoristasResult['success'] ? $motoristasResult['data'] : [];

$veiculosResult = $this->progressService->getVeiculosPorTransportador($id);
if (!$veiculosResult['success']) {
    Log::warning('Falha ao carregar veículos do transportador', [
        'transporte_id' => $id,
        'error' => $veiculosResult['error'] ?? 'Erro desconhecido',
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);
}
$transporte['veiculos'] = $veiculosResult['success'] ? $veiculosResult['data'] : [];
```

**Opcional - Retornar warnings no response:**
```php
$warnings = [];

$motoristasResult = $this->progressService->getMotoristasPorTransportador($id);
if (!$motoristasResult['success']) {
    $warnings[] = 'Não foi possível carregar motoristas';
    // Log...
}

return response()->json([
    'success' => true,
    'message' => 'Detalhes do transportador obtidos com sucesso',
    'data' => $transporte,
    'warnings' => $warnings  // Array de avisos (opcional)
]);
```

---

## 🟡 VULNERABILIDADES MÉDIAS

### MÉDIA #1: index() - Validação de status_ativo Inconsistente
**Severidade:** 🟡 MÉDIA
**Linha:** 43, 54
**Impacto:** Pode causar comportamento inesperado na filtragem

**Problema:**
```php
// ❌ Aceita múltiplos formatos mas não normaliza
'status_ativo' => 'nullable|in:true,false,1,0'

$ativo = isset($validated['status_ativo']) ? $validated['status_ativo'] : null;
// $ativo pode ser: "true", "false", "1", "0", null
// ProgressService precisa lidar com todos os formatos!
```

**Solução:**
```php
// ✅ Normalizar para boolean ou null
'status_ativo' => 'nullable|boolean'

$ativo = null;
if (isset($validated['status_ativo'])) {
    $ativo = filter_var($validated['status_ativo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
}
// Agora $ativo é: true, false, ou null (consistente)
```

**Ou manter string mas documentar:**
```php
// ✅ Alternativa - manter string mas converter no service
$filters = [
    // ...
    'ativo' => isset($validated['status_ativo'])
        ? ($validated['status_ativo'] === 'true' || $validated['status_ativo'] === '1' ? 1 : 0)
        : null
];
```

---

### MÉDIA #2: index() - Validação Regex Pode Ser Restritiva Demais
**Severidade:** 🟡 MÉDIA
**Linha:** 32, 39
**Impacto:** Usuários com nomes especiais (ex: "José", "São Paulo") podem ser bloqueados

**Problema:**
```php
// ❌ Regex não permite acentos em 'search'
'search' => [
    'nullable',
    'string',
    'max:100',
    'regex:/^[a-zA-Z0-9\s\-._@]+$/'  // Sem acentos!
],

// ❌ 'nome' permite acentos mas não números/underscores
'nome' => [
    'nullable',
    'string',
    'max:100',
    'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/'  // "Transportes123" seria bloqueado
],
```

**Solução:**
```php
// ✅ 'search' deve aceitar acentos (busca geral)
'search' => [
    'nullable',
    'string',
    'max:100',
    'regex:/^[a-zA-Z0-9À-ÿ\s\-._@]+$/u'  // Adicionado À-ÿ e flag 'u'
],

// ✅ 'nome' deve aceitar números (ex: "Transportes 123 LTDA")
'nome' => [
    'nullable',
    'string',
    'max:100',
    'regex:/^[a-zA-Z0-9À-ÿ\s\-\.]+$/u'  // Adicionado 0-9 e flag 'u'
],
```

**Testes:**
```php
// ✅ Agora funciona:
$search = "José da Silva";  // Aceito (acentos)
$nome = "Transportes 123 LTDA";  // Aceito (números)
$nome = "São Paulo Logística";  // Aceito (acentos)
```

---

### MÉDIA #3: query() - Bloqueio de UNION É Insuficiente
**Severidade:** 🟡 MÉDIA
**Linha:** 235
**Impacto:** SQL injection via UNION ainda é possível com obfuscação

**Problema:**
```php
// ❌ ANTES - Detecta apenas 'UNION' literal
$dangerousPatterns = ['UNION', ...];

if (strpos($sqlUpper, $pattern) !== false) {
    return response()->json([...], 422);
}

// Bypass possível:
// "SELECT * FROM users WHERE 1=1 UNI/**/ON SELECT ..."
// "SELECT * FROM users WHERE 1=1 UN/**/ION SELECT ..."
```

**Solução:**
```php
// ✅ Múltiplas camadas de validação

// 1. Remover comentários SQL antes de validar
$sqlCleaned = preg_replace('/\/\*.*?\*\//', '', $sql);
$sqlCleaned = preg_replace('/--.*?(\n|$)/', '', $sqlCleaned);

// 2. Remover espaços extras
$sqlCleaned = preg_replace('/\s+/', ' ', $sqlCleaned);

// 3. Validar contra patterns com word boundaries
$dangerousPatterns = ['UNION', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE'];
foreach ($dangerousPatterns as $pattern) {
    if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $sqlCleaned)) {
        Log::warning('Query bloqueada - pattern perigoso', [
            'user_id' => $user->id,
            'pattern' => $pattern,
            'sql' => $sql,
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => "Pattern não permitido detectado: {$pattern}",
            'data' => null
        ], 422);
    }
}

// 4. Validar que há apenas UM statement SELECT
$statementCount = substr_count($sqlCleaned, 'SELECT');
if ($statementCount > 1) {
    Log::warning('Query bloqueada - múltiplos SELECTs', [
        'user_id' => $user->id,
        'sql' => $sql,
        'statement_count' => $statementCount,
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Apenas um SELECT é permitido por query',
        'data' => null
    ], 422);
}
```

---

### MÉDIA #4: Falta de Validação de Request em testConnection()
**Severidade:** 🟡 MÉDIA
**Linha:** 131
**Impacto:** Não recebe Request, impossível fazer logging adequado

**Problema:**
```php
// ❌ ANTES - Sem acesso a Request
public function testConnection(): JsonResponse {
    // Não tem acesso a $request->ip(), $request->userAgent()
    $result = $this->progressService->testConnection();
    return response()->json($result, $result['success'] ? 200 : 500);
}
```

**Solução:**
```php
// ✅ DEPOIS - Injetar Request
public function testConnection(Request $request): JsonResponse {
    Log::info('Tentativa de teste de conexão Progress', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->testConnection();

    // Logging condicional...

    return response()->json($result, $result['success'] ? 200 : 500);
}
```

---

### MÉDIA #5: statistics() - Try-Catch Genérico Demais
**Severidade:** 🟡 MÉDIA
**Linha:** 144, 173
**Impacto:** Exceções diferentes tratadas da mesma forma

**Problema:**
```php
// ❌ ANTES - Catch genérico esconde detalhes
try {
    $result = $this->progressService->executeCustomQuery($sql);
    // ...
} catch (\Exception $e) {
    // Pode ser: SQL error, network error, JSON error, etc.
    return response()->json([
        'success' => false,
        'message' => 'Erro ao obter estatísticas: ' . $e->getMessage(),
        'data' => null
    ], 500);
}
```

**Solução:**
```php
// ✅ DEPOIS - Catch específico + logging detalhado
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;

try {
    $result = $this->progressService->executeCustomQuery($sql);

    if (!$result['success'] || empty($result['data']['results'])) {
        throw new \RuntimeException($result['error'] ?? 'Nenhum dado retornado');
    }

    // ...

} catch (QueryException $e) {
    $errorId = uniqid('err_');

    Log::error('Erro de SQL ao obter estatísticas', [
        'error_id' => $errorId,
        'error' => $e->getMessage(),
        'sql' => $sql,
        'code' => $e->getCode(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro de banco de dados. ID: ' . $errorId,
        'error_id' => $errorId,
        'data' => null
    ], 500);

} catch (ConnectionException $e) {
    $errorId = uniqid('err_');

    Log::error('Erro de conexão ao obter estatísticas', [
        'error_id' => $errorId,
        'error' => $e->getMessage(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro de conexão. ID: ' . $errorId,
        'error_id' => $errorId,
        'data' => null
    ], 503);

} catch (\Exception $e) {
    $errorId = uniqid('err_');

    Log::error('Erro desconhecido ao obter estatísticas', [
        'error_id' => $errorId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro interno. ID: ' . $errorId,
        'error_id' => $errorId,
        'data' => null
    ], 500);
}
```

---

### MÉDIA #6: Falta Import de Log Facade
**Severidade:** 🟡 MÉDIA
**Linha:** 4-8
**Impacto:** Todas as correções de logging vão falhar

**Problema:**
```php
// ❌ ANTES - Sem import
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
// Faltando: use Illuminate\Support\Facades\Log;

class TransporteController extends Controller {
    // ...
    Log::info(...);  // ❌ Erro: Class 'Log' not found
}
```

**Solução:**
```php
// ✅ DEPOIS - Adicionar import
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;  // ← ADICIONAR

class TransporteController extends Controller {
    // ...
    Log::info(...);  // ✅ Funciona
}
```

---

## 📊 PRIORIZAÇÃO DE CORREÇÕES

### Fase 1 - CRÍTICAS (Fazer AGORA)
1. ✅ Adicionar `use Illuminate\Support\Facades\Log;`
2. ✅ Adicionar logging LGPD em TODOS os 6 métodos
3. ✅ Adicionar error IDs em TODOS os retornos de erro
4. ✅ Corrigir detecção de keywords em `query()` (word boundaries)
5. ✅ Adicionar logging de auditoria em `query()`

### Fase 2 - ALTAS (Fazer esta semana)
6. ✅ Corrigir vazamento de erros em 5 métodos
7. ✅ Adicionar logging em `testConnection()`
8. ✅ Avaliar autenticação em `schema()` (CUIDADO: pode quebrar frontend!)
9. ✅ Adicionar cache em `statistics()`
10. ✅ Adicionar logging de falhas em `show()` (motoristas/veículos)

### Fase 3 - MÉDIAS (Fazer este mês)
11. ✅ Normalizar validação de `status_ativo`
12. ✅ Ajustar regex de validação (acentos + números)
13. ✅ Melhorar validação UNION em `query()`
14. ✅ Adicionar Request em `testConnection()`
15. ✅ Refatorar try-catch em `statistics()`

---

## 🧪 CHECKLIST DE TESTES

Após implementar correções:

### Testes Funcionais:
- [ ] `GET /api/transportes` - Listagem funciona
- [ ] `GET /api/transportes/{id}` - Detalhes funcionam
- [ ] `GET /api/transportes/test-connection` - Teste funciona
- [ ] `GET /api/transportes/statistics` - Estatísticas funcionam
- [ ] `GET /api/transportes/schema` - Schema funciona (ou 403 se autenticado)
- [ ] `POST /api/transportes/query` - Query customizada funciona (com auth)

### Testes de Segurança:
- [ ] Validação de ID em `show()` rejeita valores inválidos
- [ ] Regex em `index()` aceita acentos ("José")
- [ ] Regex em `index()` aceita números em nome ("Transportes 123")
- [ ] `query()` bloqueia "CREATE TABLE"
- [ ] `query()` permite "SELECT codRotCreateSP"
- [ ] `query()` bloqueia múltiplos SELECTs
- [ ] `query()` bloqueia comentários SQL

### Testes de Logging:
- [ ] Log de listagem contém IP + timestamp
- [ ] Log de detalhes contém transporte_id
- [ ] Log de query customizada contém user_id + SQL
- [ ] Log de erro contém error_id
- [ ] Error IDs são retornados ao usuário

### Testes de Performance:
- [ ] Cache de statistics reduz queries (verificar com EXPLAIN)
- [ ] Rate limiting funciona (testar com curl em loop)

---

## 📝 NOTAS FINAIS

**Total de linhas a modificar:** ~200 linhas (60 linhas afetadas + ~140 linhas de logging)

**Tempo estimado:** 3-4 horas para implementar todas as correções

**Risco de breaking changes:**
- ⚠️ `schema()` com autenticação pode quebrar frontend
- ⚠️ Mudanças em regex podem rejeitar dados antes aceitos
- ✅ Demais mudanças são backwards-compatible (apenas adicionam logging)

**Compliance:**
- ✅ LGPD Art. 46 - Auditoria de acesso a dados
- ✅ OWASP Top 10 - SQL Injection, Information Disclosure
- ✅ PCI-DSS - Logging e monitoramento
- ✅ SOC 2 - Audit trail completo

**Próximos passos:**
1. Implementar correções críticas (Fase 1)
2. Testar todas as funcionalidades
3. Verificar frontend não quebrou
4. Commit com mensagem: "Security: Add LGPD logging and fix vulnerabilities in TransporteController"
5. Auditar próximo controller (GeocodingController, RoutingController)
