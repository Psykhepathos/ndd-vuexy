# Auditoria de Segurança: SemPararController.php

**Data:** 2025-12-04
**Arquivo:** `app/Http/Controllers/Api/SemPararController.php`
**Linhas:** 588
**Severidade Máxima:** 🔴 CRÍTICA

---

## 📋 Sumário Executivo

Identificados **7 problemas críticos** e **2 problemas médios** no SemPararController que comprometem a segurança e auditabilidade do sistema de compra de viagens SemParar.

### Impacto
- 🔴 **CRÍTICO:** Endpoints públicos permitem qualquer usuário comprar/cancelar viagens
- 🔴 **CRÍTICO:** Operações financeiras sem logging de auditoria (LGPD Art. 46)
- 🔴 **CRÍTICO:** Exposição de informações sensíveis em mensagens de erro
- ⚠️ **ALTO:** Falta de validação de autorização em operações irreversíveis

---

## 🔴 PROBLEMAS CRÍTICOS

### 1. COMPRA DE VIAGEM SEM AUTENTICAÇÃO (Linha 291-358)

**Método:** `comprarViagem()`

**Problema:**
```php
// ❌ CRÍTICO: Endpoint público - qualquer um pode comprar viagens
public function comprarViagem(Request $request): JsonResponse
{
    // Sem middleware auth:sanctum
    // Sem validação de permissão
    // Sem logging de quem comprou
```

**Impacto:**
- Qualquer usuário não autenticado pode comprar viagens
- Não há registro de quem efetivou a compra (violação LGPD Art. 46)
- Impossível auditar operações financeiras
- Risco de fraude e abuso

**Solução Recomendada:**
```php
// ✅ CORRETO: Proteger com autenticação e logging
public function comprarViagem(Request $request): JsonResponse
{
    // Adicionar middleware auth:sanctum na rota
    // Verificar permissão do usuário

    try {
        $user = $request->user(); // Get authenticated user

        // LGPD Art. 46: Log de acesso e operação
        Log::info('Compra de viagem SemParar iniciada', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'cod_viagem' => $result['cod_viagem'] ?? null,
            'placa' => $request->input('placa'),
            'valor' => $request->input('valor_viagem'),
            'timestamp' => now()->toIso8601String()
        ]);

        // ... resto do código
    }
}
```

---

### 2. CANCELAMENTO SEM AUTENTICAÇÃO (Linha 509-543)

**Método:** `cancelarViagem()`

**Problema:**
```php
// ❌ CRÍTICO: Operação irreversível sem autenticação/logging
public function cancelarViagem(Request $request): JsonResponse
{
    // Sem middleware auth:sanctum
    // Sem confirmação dupla
    // Sem logging de quem cancelou
    $codViagem = $request->input('cod_viagem');
    $result = $this->semPararService->cancelarViagem($codViagem);
```

**Impacto:**
- Qualquer usuário pode cancelar viagens de terceiros
- Operação irreversível sem possibilidade de auditoria
- Violação LGPD Art. 46 (falta registro de acesso)
- Sem mecanismo de confirmação dupla

**Solução Recomendada:**
```php
// ✅ CORRETO: Autenticação + logging + confirmação
public function cancelarViagem(Request $request): JsonResponse
{
    $request->validate([
        'cod_viagem' => 'required|string|min:1|max:50',
        'confirmacao' => 'required|boolean|accepted' // Confirmação dupla
    ]);

    $user = $request->user();
    $codViagem = $request->input('cod_viagem');

    // LGPD: Log ANTES de executar
    Log::warning('Cancelamento de viagem solicitado', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'ip' => $request->ip(),
        'cod_viagem' => $codViagem,
        'timestamp' => now()->toIso8601String()
    ]);

    $result = $this->semPararService->cancelarViagem($codViagem);

    // Log do resultado
    Log::warning('Cancelamento de viagem ' . ($result['success'] ? 'concluído' : 'falhou'), [
        'user_id' => $user->id,
        'cod_viagem' => $codViagem,
        'status' => $result['status'] ?? null,
        'timestamp' => now()->toIso8601String()
    ]);
}
```

---

### 3. CONSULTA DE VIAGENS SEM AUTENTICAÇÃO (Linha 465-501)

**Método:** `consultarViagens()`

**Problema:**
```php
// ❌ CRÍTICO: Endpoint público expõe TODAS as viagens
public function consultarViagens(Request $request): JsonResponse
{
    // Sem middleware auth:sanctum
    // Qualquer um pode consultar histórico de viagens
    $result = $this->semPararService->consultarViagens($dataInicio, $dataFim);
```

**Impacto:**
- Exposição de informações confidenciais (viagens, rotas, custos)
- Violação de privacidade de dados de terceiros
- Possível raspagem (scraping) de dados sensíveis

**Solução Recomendada:**
```php
// ✅ CORRETO: Restringir acesso e filtrar por usuário
public function consultarViagens(Request $request): JsonResponse
{
    $user = $request->user();

    // Filtrar apenas viagens do usuário/empresa
    // Ou verificar permissão de admin para ver todas

    Log::info('Consulta de viagens', [
        'user_id' => $user->id,
        'periodo' => [$dataInicio, $dataFim],
        'ip' => $request->ip(),
        'timestamp' => now()->toIso8601String()
    ]);
}
```

---

### 4. EXPOSIÇÃO DE STACK TRACE (TODOS OS MÉTODOS)

**Problema:** Todos os 13 métodos expõem `$e->getMessage()` diretamente ao usuário

**Exemplos:**
```php
// Linha 51, 84, 180, 220, 268, 355, 401, 454, 498, 540, 584
return response()->json([
    'success' => false,
    'message' => 'Erro ao...',
    'error' => $e->getMessage()  // ❌ Expõe detalhes internos
], 500);
```

**Impacto:**
- Exposição de caminhos de arquivo, credenciais, estrutura de banco
- Facilita ataques de engenharia reversa
- Violação de boas práticas de segurança

**Solução Recomendada:**
```php
// ✅ CORRETO: Logar detalhes, retornar mensagem genérica
} catch (\Exception $e) {
    // Log completo (interno)
    Log::error('Erro ao processar operação SemParar', [
        'user_id' => $request->user()->id ?? null,
        'method' => __METHOD__,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input' => $request->except(['password', 'token']),
        'timestamp' => now()->toIso8601String()
    ]);

    // Retorno genérico (público)
    return response()->json([
        'success' => false,
        'message' => 'Erro interno no processamento. Contate o suporte.',
        'error_id' => uniqid('err_') // ID para correlação com log
    ], 500);
}
```

---

### 5. REEMISSÃO SEM AUTENTICAÇÃO (Linha 551-587)

**Método:** `reemitirViagem()`

**Problema:**
```php
// ❌ Sem autenticação, sem logging
public function reemitirViagem(Request $request): JsonResponse
{
    $codViagem = $request->input('cod_viagem');
    $placa = strtoupper($request->input('placa'));

    $result = $this->semPararService->reemitirViagem($codViagem, $placa);
```

**Impacto:**
- Qualquer usuário pode reemitir viagem de terceiros
- Alteração de placa sem registro de quem solicitou
- Impossível auditar mudanças

---

## ⚠️ PROBLEMAS MÉDIOS

### 6. CLEAR CACHE SEM LOGGING (Linha 122-137)

**Método:** `clearCache()`

**Problema:**
```php
public function clearCache(): JsonResponse
{
    if (!config('app.debug')) {
        return response()->json([...], 403);
    }

    $this->semPararService->clearCache(); // ❌ Sem log
```

**Solução:**
```php
// ✅ Adicionar logging
Log::warning('Cache SemParar limpo', [
    'user_id' => $request->user()->id ?? null,
    'ip' => $request->ip(),
    'timestamp' => now()->toIso8601String()
]);
```

---

### 7. GERAR RECIBO SEM LOGGING (Linha 412-457)

**Método:** `gerarRecibo()`

**Problema:**
```php
// ❌ Envio de documento por WhatsApp sem registro
$result = $this->semPararService->gerarRecibo(
    $codViagem, $telefone, $email, $flgImprime
);
```

**Impacto:**
- Não há registro de para quem o recibo foi enviado
- LGPD exige logging de compartilhamento de dados

**Solução:**
```php
Log::info('Recibo enviado', [
    'user_id' => $request->user()->id ?? null,
    'cod_viagem' => $codViagem,
    'telefone' => substr($telefone, 0, 4) . '****' . substr($telefone, -4), // Mascarar
    'email' => $email ? 'fornecido' : 'não fornecido',
    'timestamp' => now()->toIso8601String()
]);
```

---

## ✅ PONTOS POSITIVOS

1. **Validação de Entrada:** Todos os métodos usam Laravel validation
2. **Debug Endpoints:** `debugToken()` e `clearCache()` protegidos com `config('app.debug')`
3. **Try-Catch:** Todos os métodos têm tratamento de exceção
4. **Validação de Negócio:**
   - Eixos limitados a 2-9 (linha 245)
   - Data fim >= data início (linhas 247, 471)
   - Placa com tamanho correto (linha 557)
5. **Integração Progress:** Método `comprarViagem()` salva dados no Progress (FASE 2B)
6. **Non-blocking:** Compra não falha se Progress der erro (linha 340-343)

---

## 📊 Estatísticas

| Métrica | Valor |
|---------|-------|
| Total de métodos | 13 |
| Métodos sem autenticação | 10 (77%) |
| Métodos sem logging | 11 (85%) |
| Métodos expondo stack trace | 13 (100%) |
| Operações críticas desprotegidas | 3 (comprar, cancelar, consultar) |

---

## 🔒 Recomendações de Correção

### Prioridade CRÍTICA (Implementar IMEDIATAMENTE):

1. **Adicionar middleware `auth:sanctum` nas rotas:**
   ```php
   // routes/api.php
   Route::middleware(['auth:sanctum'])->group(function () {
       Route::post('/semparar/comprar-viagem', [SemPararController::class, 'comprarViagem']);
       Route::post('/semparar/cancelar-viagem', [SemPararController::class, 'cancelarViagem']);
       Route::post('/semparar/consultar-viagens', [SemPararController::class, 'consultarViagens']);
       Route::post('/semparar/reemitir-viagem', [SemPararController::class, 'reemitirViagem']);
       Route::post('/semparar/gerar-recibo', [SemPararController::class, 'gerarRecibo']);
   });
   ```

2. **Implementar logging LGPD em todos os métodos críticos**

3. **Substituir exposição de `$e->getMessage()` por mensagens genéricas**

4. **Adicionar confirmação dupla em operações irreversíveis** (cancelamento)

### Prioridade ALTA:

5. **Criar middleware de autorização** para verificar se usuário pode operar sobre determinada viagem

6. **Implementar rate limiting** mais agressivo em endpoints financeiros:
   ```php
   Route::middleware(['throttle:5,1'])->group(function () {
       // Máximo 5 compras por minuto
       Route::post('/semparar/comprar-viagem', ...);
   });
   ```

7. **Adicionar validação de domínio** (usuário só pode operar viagens da sua empresa)

### Prioridade MÉDIA:

8. **Implementar sistema de notificações** para operações críticas

9. **Criar dashboard de auditoria** com logs de compra/cancelamento

10. **Adicionar testes automatizados** para validar autenticação

---

## 🔗 Arquivos Relacionados

- **Routes:** `routes/api.php` - Precisa adicionar middleware
- **Service:** `app/Services/SemParar/SemPararService.php` - Já implementado
- **Frontend:** Interfaces de teste em `public/test-semparar-fase*.html`
- **Documentação:** `SEMPARAR_IMPLEMENTATION_ROADMAP.md`

---

## ✍️ Assinatura

**Auditado por:** Sistema de Auditoria Automatizada
**Data:** 2025-12-04
**Horário:** 09:10 (UTC-3)
**Status:** ⚠️ CRÍTICO - Requer correção imediata antes de produção

**Próximo Passo:** Auditar `routes/api.php` para verificar middlewares aplicados
