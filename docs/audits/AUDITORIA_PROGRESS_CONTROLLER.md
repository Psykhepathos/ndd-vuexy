# 🔐 AUDITORIA DE SEGURANÇA - ProgressController & ProgressService

**Data da Auditoria:** 2025-12-03
**Arquivo Principal:** `app/Http/Controllers/Api/ProgressController.php` (297 linhas)
**Serviço Base:** `app/Services/ProgressService.php` (1500+ linhas)
**Rotas:** `routes/api.php` (linhas 35-40)
**Auditor:** Claude Code (Modelo: Sonnet 4.5)

---

## 📊 RESUMO EXECUTIVO

**Status Geral:** 🔴 **CRÍTICO - VULNERABILIDADES GRAVES IDENTIFICADAS**

**Vulnerabilidades Encontradas:** 7 problemas de segurança
**Nível de Risco:** 3 CRÍTICAS, 2 ALTAS, 1 MÉDIA, 1 BAIXA

### Impacto Potencial

1. **SQL Injection via Custom Query** → Exposição de dados sensíveis (CPF, CNPJ, salários)
2. **Endpoint Público sem Autenticação** → Qualquer pessoa pode executar queries
3. **Falta de Rate Limiting** → DoS e enumeração de dados
4. **Information Disclosure** → Acesso irrestrito ao schema completo do Progress
5. **Logging de Dados Sensíveis** → Violação LGPD
6. **Falta de Auditoria** → Impossível rastrear abusos
7. **Validação Insuficiente** → Bypass de controles de segurança

---

## 🚨 VULNERABILIDADES IDENTIFICADAS

### #1 - SQL Injection via Custom Query Endpoint (CRÍTICO)

**Severidade:** 🔴 **CRÍTICA**
**CWE:** CWE-89 (SQL Injection)
**OWASP Top 10:** A03:2021 - Injection

#### Descrição do Problema

O endpoint `POST /api/progress/query` é **PÚBLICO** (sem autenticação), **sem rate limiting**, e permite executar **qualquer consulta SELECT** no banco Progress OpenEdge. Embora o método `executeCustomQuery()` valide que apenas SELECT é permitido, **não há controle sobre QUAIS tabelas podem ser acessadas** e **não há autenticação/autorização**.

#### Localização no Código

**routes/api.php - Linha 39:**
```php
Route::prefix('progress')->group(function () {
    Route::get('test-connection', [ProgressController::class, 'testConnection']);
    Route::get('transportes', [ProgressController::class, 'getTransportes']);
    Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById']);
    Route::post('query', [ProgressController::class, 'executeCustomQuery']);  // ❌ SEM AUTH!
});
```

**ProgressController.php - Linhas 262-296:**
```php
/**
 * @OA\Post(
 *     path="/api/progress/query",
 *     summary="Executa consulta SQL personalizada (apenas SELECT)",
 *     ...
 * )
 */
public function executeCustomQuery(Request $request): JsonResponse
{
    try {
        $validator = Validator::make($request->all(), [
            'sql' => 'required|string',
            'bindings' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([...], 400);
        }

        $sql = $request->input('sql');
        $bindings = $request->input('bindings', []);

        $result = $this->progressService->executeCustomQuery($sql, $bindings);

        return response()->json($result, $result['success'] ? 200 : 400);

    } catch (\Exception $e) {
        Log::error('Erro na execução de consulta customizada', [...]);

        return response()->json([
            'success' => false,
            'error' => 'Erro interno na execução da consulta'
        ], 500);
    }
}
```

**ProgressService.php - Linhas 614-668:**
```php
/**
 * Executa consulta SQL customizada (para debug e testes)
 */
public function executeCustomQuery(string $sql): array
{
    try {
        // Validação 1: SQL não pode ser vazio
        $sql = trim($sql);
        if (empty($sql)) {
            throw new Exception('SQL query não pode ser vazio');
        }

        // Validação 2: Tamanho máximo (prevenir consultas gigantes)
        if (strlen($sql) > 50000) {
            throw new Exception('SQL query muito grande (máximo 50.000 caracteres)');
        }

        Log::info('Executando consulta SQL customizada', ['sql' => substr($sql, 0, 200) . '...']);

        // Validação 3: Limitar a apenas SELECT por segurança
        $sql_upper = strtoupper($sql);
        if (!str_starts_with($sql_upper, 'SELECT')) {
            throw new Exception('Apenas consultas SELECT são permitidas');
        }

        // Validação 4: Prevenir comandos perigosos embutidos
        $dangerous_keywords = ['DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'EXEC'];
        foreach ($dangerous_keywords as $keyword) {
            if (str_contains($sql_upper, $keyword)) {
                throw new Exception("Palavra-chave não permitida detectada: {$keyword}");
            }
        }

        // Validação 5: Prevenir comentários SQL que podem esconder código malicioso
        if (str_contains($sql, '--') || str_contains($sql, '/*') || str_contains($sql, '*/')) {
            throw new Exception('Comentários SQL não são permitidos em consultas customizadas');
        }

        $result = $this->executeJavaConnector('query', $sql);

        Log::info('Consulta SQL executada com sucesso', [
            'total_registros' => $result['data']['total'] ?? 0
        ]);

        return $result;

    } catch (Exception $e) {
        Log::error('Erro na execução da consulta SQL', [
            'sql' => substr($sql ?? 'null', 0, 200),
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => 'Erro na consulta SQL: ' . $e->getMessage()
        ];
    }
}
```

#### Cenário de Exploração

**Atacante não autenticado** pode:

1. **Enumerar tabelas sensíveis:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 1000 codtrn, nomtrn, codcnpjcpf FROM PUB.transporte"}'
```

2. **Extrair dados de salários:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 1000 nommot, codcpf, valfre FROM PUB.trnmot"}'
```

3. **Fazer JOIN entre tabelas para correlacionar dados:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT t.nomtrn, t.codcnpjcpf, m.nommot, m.codcpf, v.numpla FROM PUB.transporte t LEFT JOIN PUB.trnmot m ON t.codtrn = m.codtrn LEFT JOIN PUB.veiculos v ON t.codtrn = v.codtrn"}'
```

4. **Extrair todas as viagens SemParar compradas:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT codViagem, NumPla, valViagem, dataCompra, codpac FROM PUB.sPararViagem"}'
```

#### Impacto

- ✅ **Exposição de Dados Pessoais** (CPF, CNPJ) → Violação LGPD Art. 46
- ✅ **Exposição de Dados Financeiros** (salários, valores de frete)
- ✅ **Enumeração Completa do Banco** (atacante pode mapear todo o schema)
- ✅ **Concorrência Desleal** (competidores podem extrair lista de clientes/fornecedores)
- ✅ **Base para Ataques Avançados** (com conhecimento do schema, pode planejar outros ataques)

#### Solução Proposta

**CORREÇÃO IMEDIATA (Linha 39 routes/api.php):**

```php
// ❌ ANTES - Público e sem rate limiting
Route::prefix('progress')->group(function () {
    Route::post('query', [ProgressController::class, 'executeCustomQuery']);
});

// ✅ DEPOIS - Auth obrigatório + rate limiting agressivo
Route::prefix('progress')->group(function () {
    Route::get('test-connection', [ProgressController::class, 'testConnection'])
        ->middleware('throttle:10,1');  // 10 req/min para público
    Route::get('transportes', [ProgressController::class, 'getTransportes'])
        ->middleware('throttle:60,1');  // 60 req/min para público
    Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById'])
        ->middleware('throttle:60,1');  // 60 req/min para público

    // ✅ CRÍTICO: Autenticação + rate limiting + whitelist de tabelas
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('query', [ProgressController::class, 'executeCustomQuery'])
            ->middleware('throttle:5,1');  // 5 req/min apenas para admins
    });
});
```

**CORREÇÃO ADICIONAL (ProgressController.php - Método executeCustomQuery):**

Adicionar novo método privado `validateQuerySecurity()` e chamar antes de executar:

```php
/**
 * CORREÇÃO #1: Valida segurança da query customizada
 *
 * Regras:
 * - Apenas tabelas whitelisted podem ser acessadas
 * - Não pode usar SELECT * (deve especificar colunas)
 * - Não pode acessar colunas sensíveis (CPF, CNPJ, senhas)
 * - Limite de 100 registros por query
 */
private function validateQuerySecurity(string $sql): array
{
    $sql_upper = strtoupper($sql);

    // Regra 1: Whitelist de tabelas permitidas para usuários autenticados
    $allowedTables = [
        'PUB.TRANSPORTE',
        'PUB.PACOTE',
        'PUB.INTROT',       // Rotas
        'PUB.SEMPARATOT',   // Rotas SemParar (apenas leitura de metadados)
        'PUB.MUNICIPIO',
        'PUB.ESTADO'
    ];

    $tablesInQuery = [];
    foreach ($allowedTables as $table) {
        if (str_contains($sql_upper, $table)) {
            $tablesInQuery[] = $table;
        }
    }

    // Se não encontrou nenhuma tabela permitida, verificar se está tentando acessar tabela não permitida
    if (empty($tablesInQuery)) {
        // Detectar se está tentando acessar tabela proibida
        $forbiddenTables = ['TRNMOT', 'USUARIO', 'SPARARVIAGEM'];
        foreach ($forbiddenTables as $forbidden) {
            if (str_contains($sql_upper, $forbidden)) {
                return [
                    'valid' => false,
                    'error' => 'Acesso negado: Tabela não permitida para usuários. Contate o administrador.'
                ];
            }
        }

        return [
            'valid' => false,
            'error' => 'Nenhuma tabela permitida encontrada na query. Tabelas permitidas: ' . implode(', ', $allowedTables)
        ];
    }

    // Regra 2: Proibir SELECT * (deve especificar colunas)
    if (preg_match('/SELECT\s+\*/i', $sql)) {
        return [
            'valid' => false,
            'error' => 'SELECT * não é permitido. Especifique as colunas desejadas.'
        ];
    }

    // Regra 3: Detectar acesso a colunas sensíveis (mesmo que whitelisted)
    $sensitiveCols = ['CODCNPJCPF', 'CODCPF', 'SENHA', 'PASSWORD', 'TOKEN'];
    foreach ($sensitiveCols as $col) {
        if (str_contains($sql_upper, $col)) {
            return [
                'valid' => false,
                'error' => "Acesso à coluna sensível '{$col}' não é permitido."
            ];
        }
    }

    // Regra 4: Limitar quantidade de registros (máximo 100)
    if (!preg_match('/TOP\s+\d+/i', $sql)) {
        return [
            'valid' => false,
            'error' => 'Query deve incluir TOP N (máximo 100 registros). Exemplo: SELECT TOP 100 ...'
        ];
    }

    // Extrair número do TOP
    preg_match('/TOP\s+(\d+)/i', $sql, $matches);
    $topLimit = (int)($matches[1] ?? 0);
    if ($topLimit > 100) {
        return [
            'valid' => false,
            'error' => 'TOP não pode ser maior que 100. Use paginação para grandes volumes.'
        ];
    }

    return ['valid' => true];
}

/**
 * MODIFICAR executeCustomQuery() para usar a validação
 */
public function executeCustomQuery(Request $request): JsonResponse
{
    try {
        $validator = Validator::make($request->all(), [
            'sql' => 'required|string',
            'bindings' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $sql = $request->input('sql');

        // ✅ CORREÇÃO #1: Validar segurança da query
        $securityCheck = $this->validateQuerySecurity($sql);
        if (!$securityCheck['valid']) {
            Log::warning('Query rejeitada por validação de segurança', [
                'sql' => substr($sql, 0, 200),
                'user_id' => $request->user()->id ?? 'guest',
                'ip' => $request->ip(),
                'error' => $securityCheck['error']
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Query rejeitada por validação de segurança',
                'error' => $securityCheck['error']
            ], 403);  // 403 Forbidden
        }

        $bindings = $request->input('bindings', []);

        // ✅ CORREÇÃO #6: Registrar auditoria ANTES de executar
        Log::info('Executando query customizada', [
            'user_id' => $request->user()->id ?? null,
            'user_email' => $request->user()->email ?? null,
            'ip' => $request->ip(),
            'sql' => substr($sql, 0, 200) . (strlen($sql) > 200 ? '...' : ''),
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->executeCustomQuery($sql, $bindings);

        // ✅ CORREÇÃO #6: Registrar resultado da auditoria
        Log::info('Query executada com sucesso', [
            'user_id' => $request->user()->id ?? null,
            'total_registros' => $result['data']['total'] ?? 0
        ]);

        return response()->json($result, $result['success'] ? 200 : 400);

    } catch (\Exception $e) {
        Log::error('Erro na execução de consulta customizada', [
            'user_id' => $request->user()->id ?? null,
            'ip' => $request->ip(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Erro interno na execução da consulta'
        ], 500);
    }
}
```

**Backward Compatibility:** ✅ **100% Compatível**
- Endpoint continua funcionando para usuários autenticados
- `resources/ts/config/api.ts` define o endpoint mas **nenhum componente Vue atual o usa**
- Sistema não será quebrado

---

### #2 - Falta de Rate Limiting em Endpoints Públicos (CRÍTICO)

**Severidade:** 🔴 **CRÍTICA**
**CWE:** CWE-770 (Allocation of Resources Without Limits or Throttling)
**OWASP Top 10:** A04:2021 - Insecure Design

#### Descrição do Problema

Os endpoints `test-connection`, `transportes` e `transportes/{id}` são **públicos** (sem autenticação) e **não têm rate limiting**, permitindo:
- DoS (Denial of Service) via flood de requisições
- Enumeração massiva de IDs de transportadores
- Sobrecarga do banco Progress OpenEdge

#### Localização no Código

**routes/api.php - Linhas 35-40:**
```php
Route::prefix('progress')->group(function () {
    Route::get('test-connection', [ProgressController::class, 'testConnection']);        // ❌ SEM RATE LIMIT
    Route::get('transportes', [ProgressController::class, 'getTransportes']);            // ❌ SEM RATE LIMIT
    Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById']);    // ❌ SEM RATE LIMIT
    Route::post('query', [ProgressController::class, 'executeCustomQuery']);             // ❌ SEM RATE LIMIT
});
```

#### Cenário de Exploração

**Atacante pode:**

1. **DoS via Flood:**
```bash
# 1000 requisições simultâneas (sem throttle)
for i in {1..1000}; do
  curl http://localhost:8002/api/progress/test-connection &
done
```

2. **Enumeração de IDs:**
```bash
# Testar todos os IDs de 1 a 10000 em segundos
for id in {1..10000}; do
  curl http://localhost:8002/api/progress/transportes/$id &
done
```

#### Impacto

- ✅ **DoS (Denial of Service)** → Aplicação indisponível
- ✅ **Sobrecarga do Progress Database** → Performance degradada
- ✅ **Enumeração de Dados** → Mapear todos os transportadores

#### Solução Proposta

**CORREÇÃO (routes/api.php - Linhas 35-40):**

```php
// ❌ ANTES - Sem rate limiting
Route::prefix('progress')->group(function () {
    Route::get('test-connection', [ProgressController::class, 'testConnection']);
    Route::get('transportes', [ProgressController::class, 'getTransportes']);
    Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById']);
    Route::post('query', [ProgressController::class, 'executeCustomQuery']);
});

// ✅ DEPOIS - Com rate limiting diferenciado
Route::prefix('progress')->group(function () {
    // CORREÇÃO #2: Rate limiting para prevenir DoS e enumeração
    Route::get('test-connection', [ProgressController::class, 'testConnection'])
        ->middleware('throttle:10,1');  // 10 req/min - health check

    Route::get('transportes', [ProgressController::class, 'getTransportes'])
        ->middleware('throttle:60,1');  // 60 req/min - listagem

    Route::get('transportes/{id}', [ProgressController::class, 'getTransporteById'])
        ->middleware('throttle:60,1');  // 60 req/min - leitura específica

    // Autenticação obrigatória para custom queries
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('query', [ProgressController::class, 'executeCustomQuery'])
            ->middleware('throttle:5,1');  // 5 req/min - apenas admins
    });
});
```

**Backward Compatibility:** ✅ **100% Compatível**
- Frontend transparente ao rate limiting
- Apenas impede flood malicioso

---

### #3 - Information Disclosure via Query Customizada (ALTA)

**Severidade:** 🟠 **ALTA**
**CWE:** CWE-200 (Exposure of Sensitive Information to an Unauthorized Actor)
**OWASP Top 10:** A01:2021 - Broken Access Control

#### Descrição do Problema

Mesmo após adicionar autenticação, o método `executeCustomQuery()` **não valida quais tabelas/colunas podem ser acessadas**. Usuário autenticado (não necessariamente admin) pode:
- Acessar tabelas de outros módulos (RH, Financeiro)
- Correlacionar dados entre tabelas via JOIN
- Extrair dados fora do escopo de sua permissão

#### Localização no Código

**ProgressService.php - Linhas 614-668:**
```php
public function executeCustomQuery(string $sql): array
{
    try {
        // Validação 3: Limitar a apenas SELECT por segurança
        $sql_upper = strtoupper($sql);
        if (!str_starts_with($sql_upper, 'SELECT')) {
            throw new Exception('Apenas consultas SELECT são permitidas');
        }

        // ❌ NÃO VALIDA QUAIS TABELAS PODEM SER ACESSADAS!
        // ❌ NÃO VALIDA QUAIS COLUNAS PODEM SER SELECIONADAS!
        // ❌ NÃO VALIDA SE USUÁRIO TEM PERMISSÃO!

        $result = $this->executeJavaConnector('query', $sql);
        return $result;

    } catch (Exception $e) {
        // ...
    }
}
```

#### Cenário de Exploração

**Usuário autenticado (não-admin)** pode:

1. **Acessar tabela de RH:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 100 * FROM PUB.funcionario"}'
```

2. **Acessar tabela de contas a pagar:**
```bash
curl -X POST http://localhost:8002/api/progress/query \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 100 * FROM PUB.contapagar"}'
```

#### Impacto

- ✅ **Escalação de Privilégios** → Usuário comum acessa dados restritos
- ✅ **Violação de Separação de Dados** → Acesso a múltiplos módulos
- ✅ **Espionagem Interna** → Funcionários acessando dados de outros setores

#### Solução Proposta

**Já implementada na CORREÇÃO #1** - Método `validateQuerySecurity()` com:
- ✅ Whitelist de tabelas permitidas
- ✅ Blacklist de colunas sensíveis
- ✅ Proibição de SELECT *
- ✅ Limite de 100 registros

---

### #4 - Logging de Dados Sensíveis (LGPD) (MÉDIA)

**Severidade:** 🟡 **MÉDIA**
**CWE:** CWE-532 (Insertion of Sensitive Information into Log File)
**Regulamentação:** LGPD Art. 46 (Segurança dos Dados)

#### Descrição do Problema

O código atual loga queries SQL completas que podem conter:
- CPF/CNPJ na cláusula WHERE
- Valores de salário em JOINs
- Dados pessoais em predicados

#### Localização no Código

**ProgressService.php - Linha 628:**
```php
Log::info('Executando consulta SQL customizada', ['sql' => substr($sql, 0, 200) . '...']);
```

**ProgressService.php - Linha 659:**
```php
Log::error('Erro na execução da consulta SQL', [
    'sql' => substr($sql ?? 'null', 0, 200),  // ❌ Pode conter dados sensíveis
    'error' => $e->getMessage()
]);
```

#### Exemplo de Log Problemático

```
[2025-12-03 10:00:00] local.INFO: Executando consulta SQL customizada {"sql":"SELECT nomtrn, codcnpjcpf FROM PUB.transporte WHERE codcnpjcpf = '12345678901234'"}
                                                                                                                              ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                                                                                                                              CNPJ EXPOSTO NO LOG!
```

#### Impacto

- ✅ **Violação LGPD** → Armazenamento não seguro de dados pessoais
- ✅ **Risco de Vazamento** → Logs acessíveis a analistas de suporte
- ✅ **Auditoria Negativa** → Não compliance em fiscalização

#### Solução Proposta

**CORREÇÃO (ProgressService.php - Adicionar método helper):**

```php
/**
 * CORREÇÃO #4: Sanitiza SQL para logs (LGPD compliance)
 *
 * Mascara:
 * - CPF: 123.456.789-01 → ***.***.***.--**
 * - CNPJ: 12.345.678/0001-23 → **.***.***/****-**
 * - Números longos em WHERE: codcnpjcpf = '12345678901234' → codcnpjcpf = '***'
 */
private function sanitizeSqlForLogging(string $sql): string
{
    // Mascara CPF (11 dígitos)
    $sql = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '***.***.***.--**', $sql);
    $sql = preg_replace('/\b\d{11}\b/', '***********', $sql);

    // Mascara CNPJ (14 dígitos)
    $sql = preg_replace('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', '**.***.***/****-**', $sql);
    $sql = preg_replace('/\b\d{14}\b/', '**************', $sql);

    // Mascara valores monetários grandes (> 1000)
    $sql = preg_replace('/\b\d{4,}\.\d{2}\b/', '*****.--**', $sql);

    // Mascara strings em aspas simples com mais de 5 caracteres (pode ser nome/endereço)
    $sql = preg_replace_callback(
        "/'([^']{6,})'/",
        function($matches) {
            $length = strlen($matches[1]);
            return "'" . str_repeat('*', min($length, 10)) . "'";
        },
        $sql
    );

    return $sql;
}

/**
 * MODIFICAR executeCustomQuery() para usar sanitização
 */
public function executeCustomQuery(string $sql): array
{
    try {
        // ... validações existentes ...

        // ✅ CORREÇÃO #4: Sanitizar SQL antes de logar
        $sanitizedSql = $this->sanitizeSqlForLogging($sql);
        Log::info('Executando consulta SQL customizada', [
            'sql' => substr($sanitizedSql, 0, 200) . (strlen($sanitizedSql) > 200 ? '...' : '')
        ]);

        $result = $this->executeJavaConnector('query', $sql);  // Executa SQL original

        Log::info('Consulta SQL executada com sucesso', [
            'total_registros' => $result['data']['total'] ?? 0
        ]);

        return $result;

    } catch (Exception $e) {
        // ✅ CORREÇÃO #4: Sanitizar SQL em logs de erro
        $sanitizedSql = $this->sanitizeSqlForLogging($sql ?? 'null');
        Log::error('Erro na execução da consulta SQL', [
            'sql' => substr($sanitizedSql, 0, 200),
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => 'Erro na consulta SQL: ' . $e->getMessage()
        ];
    }
}
```

**Backward Compatibility:** ✅ **100% Compatível**
- Apenas afeta logs internos
- Não muda comportamento da API

---

### #5 - Falta de Auditoria de Acesso (BAIXA)

**Severidade:** 🟢 **BAIXA**
**CWE:** CWE-778 (Insufficient Logging)
**Regulamentação:** LGPD Art. 37 (Auditoria)

#### Descrição do Problema

Não há registro estruturado de:
- **Quem** executou a query (user_id)
- **Quando** executou (timestamp preciso)
- **De onde** executou (IP + user agent)
- **O que** foi retornado (quantidade de registros)

#### Impacto

- ✅ **Impossível Rastrear Abusos** → Não há como identificar quem acessou dados indevidamente
- ✅ **Não Compliance LGPD** → Art. 37 exige auditoria de acesso a dados pessoais
- ✅ **Dificulta Investigação** → Em caso de vazamento, não há trilha de auditoria

#### Solução Proposta

**Já implementada na CORREÇÃO #1** - Logs estruturados com:
- ✅ `user_id` e `user_email`
- ✅ `ip` e timestamp ISO8601
- ✅ SQL sanitizado
- ✅ Total de registros retornados

**ALTERNATIVA AVANÇADA (Futuro):**

Criar tabela de auditoria dedicada:

```php
// Migration
Schema::create('progress_query_audit', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->ipAddress('ip_address');
    $table->string('user_agent', 500)->nullable();
    $table->text('sql_query_hash');  // SHA256 da query
    $table->integer('rows_returned');
    $table->integer('execution_time_ms');
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
    $table->index('created_at');
});

// Uso no ProgressController
$auditLog = ProgressQueryAudit::create([
    'user_id' => $request->user()->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'sql_query_hash' => hash('sha256', $sql),
    'rows_returned' => count($result['data']['results'] ?? []),
    'execution_time_ms' => $executionTime,
]);
```

---

### #6 - Validação Insuficiente de Parâmetros de Busca (BAIXA)

**Severidade:** 🟢 **BAIXA**
**CWE:** CWE-20 (Improper Input Validation)

#### Descrição do Problema

O método `getTransportes()` valida filtros básicos, mas não valida:
- Caracteres especiais em `codigo` (pode causar erro SQL)
- Formato de datas inválidas
- Limite excessivo (pode causar timeout)

#### Localização no Código

**ProgressController.php - Linhas 131-137:**
```php
$validator = Validator::make($request->all(), [
    'codigo' => 'nullable|string|max:50',           // ✅ OK
    'data_inicio' => 'nullable|date',               // ✅ OK
    'data_fim' => 'nullable|date|after_or_equal:data_inicio',  // ✅ OK
    'status' => 'nullable|string|max:20',           // ✅ OK
    'limit' => 'nullable|integer|min:1|max:1000'    // ❌ 1000 é muito! (timeout possível)
]);
```

#### Impacto

- ✅ **Timeout do Progress** → Query muito grande (limit=1000)
- ✅ **Erro SQL** → Caracteres especiais não escapados

#### Solução Proposta

**CORREÇÃO (ProgressController.php - Linha 136):**

```php
// ❌ ANTES - Limite muito alto
'limit' => 'nullable|integer|min:1|max:1000'

// ✅ DEPOIS - Limite razoável
'limit' => 'nullable|integer|min:1|max:100'  // CORREÇÃO #6: Reduzir limite para prevenir timeout
```

**Backward Compatibility:** ✅ **100% Compatível**
- Frontend não envia `limit` atualmente
- Se algum código enviar `limit=1000`, receberá erro de validação claro

---

### #7 - Falta de Proteção CSRF (INFORMATIVO)

**Severidade:** 🔵 **INFORMATIVO**
**CWE:** CWE-352 (Cross-Site Request Forgery)

#### Descrição do Problema

Laravel Sanctum para SPA usa autenticação via token no header `Authorization`, que é **naturalmente protegido contra CSRF** (atacante não consegue ler o token via JavaScript de outro domínio devido ao CORS).

**Portanto, NÃO É UMA VULNERABILIDADE REAL neste contexto**, mas é importante documentar.

#### Confirmação

**Proteção Ativa:**
- ✅ Sanctum usa Bearer Token no header (não em cookie)
- ✅ CORS configurado corretamente
- ✅ JavaScript de outro domínio não consegue fazer requisições autenticadas

**Nenhuma ação necessária.**

---

## 📋 RESUMO DAS CORREÇÕES PROPOSTAS

| # | Vulnerabilidade | Severidade | Arquivo | Linha | Status |
|---|----------------|-----------|---------|-------|--------|
| 1 | SQL Injection via Custom Query | 🔴 CRÍTICA | routes/api.php | 39 | ⏳ Pendente |
| 2 | Falta de Rate Limiting | 🔴 CRÍTICA | routes/api.php | 35-40 | ⏳ Pendente |
| 3 | Information Disclosure | 🟠 ALTA | ProgressController.php | Novo método | ⏳ Pendente |
| 4 | Logging de Dados Sensíveis | 🟡 MÉDIA | ProgressService.php | 628, 659 | ⏳ Pendente |
| 5 | Falta de Auditoria | 🟢 BAIXA | ProgressController.php | 262-296 | ⏳ Pendente |
| 6 | Validação Insuficiente | 🟢 BAIXA | ProgressController.php | 136 | ⏳ Pendente |
| 7 | Proteção CSRF | 🔵 INFO | - | - | ✅ N/A |

---

## 🎯 PLANO DE IMPLEMENTAÇÃO

### Fase 1 - CRÍTICAS (IMEDIATO)
1. ✅ **Adicionar autenticação obrigatória** em `POST /api/progress/query`
2. ✅ **Adicionar rate limiting** em todos os endpoints Progress
3. ✅ **Implementar whitelist de tabelas** no método `validateQuerySecurity()`

### Fase 2 - ALTAS (ESTA SEMANA)
4. ✅ **Implementar validação de segurança** completa antes de executar queries

### Fase 3 - MÉDIAS (PRÓXIMA SEMANA)
5. ✅ **Sanitizar logs** para compliance LGPD
6. ✅ **Implementar auditoria estruturada** de acesso

### Fase 4 - BAIXAS (BACKLOG)
7. ✅ **Ajustar validações** de parâmetros
8. ✅ **Criar tabela de auditoria** dedicada (opcional)

---

## ✅ CHECKLIST DE VERIFICAÇÃO PÓS-CORREÇÃO

Após implementar as correções, verificar:

- [ ] Endpoint `POST /api/progress/query` exige autenticação?
- [ ] Todos os endpoints Progress têm rate limiting?
- [ ] Queries rejeitam acesso a tabelas não whitelisted?
- [ ] Queries rejeitam `SELECT *` e colunas sensíveis?
- [ ] Logs não expõem CPF/CNPJ/dados pessoais?
- [ ] Auditoria registra user_id + IP + timestamp?
- [ ] Limite máximo de registros é 100?
- [ ] Frontend continua funcionando após mudanças?

---

## 📖 REFERÊNCIAS

- [OWASP Top 10 2021](https://owasp.org/www-project-top-ten/)
- [CWE-89: SQL Injection](https://cwe.mitre.org/data/definitions/89.html)
- [LGPD - Lei nº 13.709/2018](http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [Laravel Rate Limiting](https://laravel.com/docs/12.x/routing#rate-limiting)

---

**FIM DA AUDITORIA**

**Data de Conclusão:** 2025-12-03
**Próximo Passo:** Implementar correções conforme prioridades acima

