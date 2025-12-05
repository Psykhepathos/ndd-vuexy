# Auditoria de Segurança: PacoteController.php

**Data:** 2025-12-04
**Arquivo:** `app/Http/Controllers/Api/PacoteController.php`
**Linhas:** 275
**Severidade Máxima:** 🟡 MÉDIA (rotas públicas, apenas leitura, mas com stack trace exposure)

---

## 📋 Sumário Executivo

Identificados **2 problemas de exposição de stack trace** e **falta de logging LGPD** em 5 métodos no PacoteController. O controller NÃO lida com operações financeiras críticas (apenas leitura), mas precisa de melhorias em logging de auditoria e tratamento de erros.

### Impacto
- 🟡 **MÉDIO:** Exposição de stack trace em 2 métodos (autocomplete, statistics)
- 🟡 **MÉDIO:** Falta logging LGPD em todos os 5 métodos (quem consultou quais pacotes)
- 🟢 **BAIXO:** Apenas operações de leitura (GET/POST para consulta)
- ✅ **POSITIVO:** Rotas são públicas (correto para autocomplete/listagem)
- ✅ **POSITIVO:** Validação de entrada em todos os métodos

---

## 🔴 PROBLEMAS IDENTIFICADOS

### 1. EXPOSIÇÃO DE STACK TRACE - autocomplete() (Linha 210-216)

**Método:** `autocomplete()`

**Problema:**
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao buscar pacotes: ' . $e->getMessage(),  // ❌ Expõe stack trace
        'data' => []
    ], 500);
}
```

**Impacto:**
- Exposição de caminhos de arquivo, estrutura de banco, credenciais
- Facilita ataques de engenharia reversa
- Violação de boas práticas de segurança

**Localização:** Linhas 210-216

---

### 2. EXPOSIÇÃO DE STACK TRACE - statistics() (Linha 267-273)

**Método:** `statistics()`

**Problema:**
```php
} catch (\Exception $e) {
    return response()->json([
        'success' => false,
        'message' => 'Erro ao obter estatísticas: ' . $e->getMessage(),  // ❌ Expõe stack trace
        'data' => null
    ], 500);
}
```

**Impacto:**
- Exposição de informações sensíveis do sistema
- Stack trace pode revelar lógica de negócio

**Localização:** Linhas 267-273

---

### 3. FALTA DE LOGGING LGPD (Todos os 5 métodos)

**Métodos afetados:**
1. `index()` - Lista pacotes (linha 22-83)
2. `show($id)` - Detalhes de pacote (linha 88-105)
3. `itinerario(Request $request)` - Itinerário com entregas/clientes (linha 110-133)
4. `autocomplete(Request $request)` - Busca rápida (linha 138-217)
5. `statistics()` - Estatísticas (linha 222-274)

**Problema:**
Nenhum método possui logging de auditoria LGPD (Art. 46):
- ❌ Sem registro de user_id (se autenticado)
- ❌ Sem registro de IP
- ❌ Sem timestamp ISO8601
- ❌ Sem registro do que foi consultado

**Impacto:**
- **CRÍTICO para `itinerario()`:** Expõe dados de clientes (endereços, razão social) sem logging de quem acessou
- **MÉDIO para outros métodos:** Impossível auditar quem consultou quais pacotes
- Violação LGPD Art. 46 (registro de acesso a dados pessoais)

**Exemplo de dados sensíveis em `itinerario()`:**
```php
// Dados retornados pelo método (linha 118):
$result = $this->progressService->getItinerarioPacote($codPac);

// Contém:
// - razcli (razão social do cliente)
// - nomcli (nome do cliente)
// - endcli (endereço completo)
// - coordenadas GPS (lat/lon)
// - telefone, email, etc.
```

---

## 🟡 PROBLEMAS MÉDIOS

### 4. EXPOSIÇÃO DE ERROS DE SERVICE (4 métodos)

**Métodos afetados:**

#### Linha 72 - `index()`
```php
if (!$result['success']) {
    return response()->json([
        'success' => false,
        'message' => $result['error'],  // ❌ Expõe erro interno do service
        'data' => null
    ], 500);
}
```

#### Linha 95 - `show($id)`
```php
return response()->json([
    'success' => false,
    'message' => $result['error'] ?? 'Pacote não encontrado',  // ❌ Expõe erro
    'data' => null
], $result['error'] ? 500 : 404);
```

#### Linha 123 - `itinerario()`
```php
return response()->json([
    'success' => false,
    'message' => $result['error'] ?? 'Erro ao buscar itinerário',  // ❌ Expõe erro
    'data' => null
], 500);
```

#### Linha 184 - `autocomplete()`
```php
return response()->json([
    'success' => false,
    'message' => 'Erro ao buscar pacotes: ' . ($result['error'] ?? 'Erro desconhecido'),  // ❌ Expõe erro
    'data' => []
], 500);
```

**Impacto:**
- Exposição de mensagens de erro internas do ProgressService
- Pode revelar detalhes de consultas SQL, estrutura de banco, etc.

---

## ✅ PONTOS POSITIVOS

### 1. Validação de Entrada
```php
// Linha 24-37: index()
$request->validate([
    'page' => 'integer|min:1',
    'per_page' => 'integer|min:5|max:100',
    'search' => 'nullable|string|max:255',
    // ... outros campos
]);

// Linha 112-114: itinerario()
$request->validate([
    'codPac' => 'required|integer'
]);

// Linha 140-142: autocomplete()
$request->validate([
    'search' => 'nullable|string|max:50'
]);
```

**Benefício:** Todos os métodos validam entrada antes de processar.

---

### 2. Range Numérico Inteligente para Autocomplete

**Linhas 154-173:**
```php
// Busca parcial de código usando range numérico (evita LIKE que Progress JDBC não suporta)
if (is_numeric($search)) {
    $searchInt = (int)$search;
    $searchLen = strlen($search);

    if ($searchLen >= 7) {
        // Busca exata: 3043368 -> WHERE codpac = 3043368
        $sql .= " AND p.codpac = " . $searchInt;
    } else {
        // Range numérico: "304" -> WHERE codpac >= 3040000 AND codpac < 3050000
        $multiplier = pow(10, 7 - $searchLen);
        $rangeStart = $searchInt * $multiplier;
        $rangeEnd = ($searchInt + 1) * $multiplier;
        $sql .= " AND p.codpac >= " . $rangeStart . " AND p.codpac < " . $rangeEnd;
    }
}
```

**Benefício:** Solução elegante para busca parcial sem LIKE (que não funciona em Progress JDBC).

---

### 3. Try-Catch onde necessário

**Linhas 146, 224:**
```php
// autocomplete() - try-catch implementado
try {
    $sql = "SELECT ...";
    $result = $this->progressService->executeCustomQuery($sql);
    // ...
} catch (\Exception $e) {
    return response()->json([...], 500);
}

// statistics() - try-catch implementado
try {
    $sql = "SELECT ...";
    $result = $this->progressService->executeCustomQuery($sql);
    // ...
} catch (\Exception $e) {
    return response()->json([...], 500);
}
```

**Benefício:** Tratamento de exceção presente nos métodos mais complexos.

---

### 4. SQL Injection Protection

**Linhas 154-173:**
```php
// is_numeric() + cast para int previne SQL injection
if (is_numeric($search)) {
    $searchInt = (int)$search;  // ✅ Safe cast
    // ... uso de $searchInt em SQL
}

// Linhas 235, 249: SQL hardcoded (sem interpolação de variáveis)
$sql = "SELECT COUNT(*) ... FROM PUB.pacote WHERE datforpac >= '2024-01-01'";
```

**Benefício:** Não há risco de SQL injection no autocomplete.

---

## 📊 Estatísticas

| Métrica | Valor |
|---------|-------|
| Total de métodos | 5 |
| Métodos expondo stack trace | 2 (40%) |
| Métodos sem logging LGPD | 5 (100%) |
| Métodos expondo erro de service | 4 (80%) |
| Métodos com validação de entrada | 3 (60%) |
| Métodos com try-catch | 2 (40%) |
| **Operações críticas** | 0 (apenas leitura) |
| **Autenticação requerida** | 0 (rotas públicas) |

---

## 🔒 Recomendações de Correção

### Prioridade MÉDIA (Melhorias de Segurança):

#### 1. Substituir Exposição de `$e->getMessage()` por Mensagens Genéricas

**Aplicar em:**
- `autocomplete()` (linha 213)
- `statistics()` (linha 270)

**Padrão recomendado:**
```php
} catch (\Exception $e) {
    $errorId = uniqid('err_');

    Log::error('Erro ao processar operação', [
        'error_id' => $errorId,
        'method' => __METHOD__,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input' => $request->all(),
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro interno no processamento. Contate o suporte.',
        'error_id' => $errorId
    ], 500);
}
```

---

#### 2. Adicionar LGPD Logging em Todos os Métodos

**Métodos prioritários (dados sensíveis):**

**a) `itinerario()` - ALTA PRIORIDADE**
```php
public function itinerario(Request $request): JsonResponse
{
    $request->validate([
        'codPac' => 'required|integer'
    ]);

    $codPac = $request->input('codPac');

    // ✅ LGPD Art. 46: Log de acesso a dados de clientes
    Log::info('Consulta de itinerário de pacote', [
        'method' => __METHOD__,
        'cod_pac' => $codPac,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->getItinerarioPacote($codPac);
    // ...
}
```

**b) `show()` - MÉDIA PRIORIDADE**
```php
public function show($id): JsonResponse
{
    Log::info('Consulta de detalhes de pacote', [
        'method' => __METHOD__,
        'pac_id' => $id,
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->progressService->getPacoteById($id);
    // ...
}
```

**c) `index()` - BAIXA PRIORIDADE**
```php
public function index(Request $request): JsonResponse
{
    // Log apenas quando há filtros específicos (evita spam de logs)
    if ($request->has(['codigo', 'codigo_transportador', 'motorista'])) {
        Log::info('Busca de pacotes com filtros', [
            'method' => __METHOD__,
            'filtros' => array_filter($request->only(['codigo', 'codigo_transportador', 'motorista'])),
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);
    }
    // ...
}
```

---

#### 3. Substituir Exposição de `$result['error']` por Mensagens Genéricas

**Aplicar em:**
- `index()` (linha 72)
- `show()` (linha 95)
- `itinerario()` (linha 123)
- `autocomplete()` (linha 184)

**Padrão recomendado:**
```php
if (!$result['success']) {
    $errorId = uniqid('err_');

    Log::error('Erro ao processar requisição', [
        'error_id' => $errorId,
        'method' => __METHOD__,
        'service_error' => $result['error'] ?? 'Erro desconhecido',
        'input' => $request->all(),
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Erro interno no processamento. Contate o suporte.',
        'error_id' => $errorId
    ], 500);
}
```

---

### Prioridade BAIXA (Melhorias Opcionais):

#### 4. Adicionar Try-Catch em Métodos Restantes

**Aplicar em:**
- `index()` - Envolver tudo em try-catch
- `show()` - Envolver tudo em try-catch
- `itinerario()` - Envolver tudo em try-catch

---

#### 5. Adicionar User ID em Logs (se houver autenticação futura)

```php
// Se no futuro adicionar autenticação:
Log::info('...', [
    'user_id' => $request->user()->id ?? null,
    'user_email' => $request->user()->email ?? null,
    // ...
]);
```

---

## 🔗 Arquivos Relacionados

- **Routes:** `routes/api.php` (linhas 80-84) - ✅ Rotas públicas (correto para leitura)
- **Frontend:**
  - `resources/ts/pages/pacotes/index.vue` - Listagem de pacotes
  - `resources/ts/pages/pacotes/[id].vue` - Detalhes + itinerário
- **Service:** `app/Services/ProgressService.php` - Queries para Progress DB
- **Documentação:** Comparar com SemPararController e CompraViagemController

---

## 🎯 Impacto no Frontend

**Páginas Vue que usam PacoteController:**
1. `resources/ts/pages/pacotes/index.vue` (linha 178): `GET /api/pacotes`
2. `resources/ts/pages/pacotes/[id].vue` (linha 64): `GET /api/pacotes/{id}`
3. `resources/ts/pages/pacotes/[id].vue` (linha 98): `POST /api/pacotes/itinerario`

**Verificação de compatibilidade:**
✅ Todas as correções propostas **NÃO quebram o contrato da API**
✅ Formato de resposta permanece o mesmo: `{ success, message, data }`
✅ Apenas muda mensagens de erro (mais genéricas) e adiciona `error_id`
✅ Frontend continuará funcionando normalmente

---

## ✍️ Assinatura

**Auditado por:** Sistema de Auditoria de Segurança
**Data:** 2025-12-04
**Horário:** 11:45 (UTC-3)
**Status:** 🟡 MÉDIO - Melhorias recomendadas (não críticas)

**Observação:** Este controller está em **BOM ESTADO** comparado aos anteriores. Não lida com operações financeiras críticas, apenas leitura de dados. As correções são melhorias incrementais de segurança e auditoria, não correções urgentes.

**Próxima Ação:** Implementar correções de stack trace (prioridade média) e adicionar logging LGPD especialmente em `itinerario()` (dados de clientes).
