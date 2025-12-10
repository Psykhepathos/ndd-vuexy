# Auditoria de Código - Integração NDD Cargo
**Data:** 2025-12-05
**Escopo:** Backend Laravel - NDD Cargo API Integration
**Arquivos Analisados:** 8 arquivos principais

---

## 🔴 PROBLEMAS CRÍTICOS (HIGH)

### 1. **Exposição de Token no Log (CRÍTICO)**
**Arquivo:** `app/Services/NddCargo/NddCargoSoapClient.php:261-264`
**Severidade:** 🔴 CRÍTICA - Vazamento de Credenciais

```php
Log::info('Enviando requisição SOAP para NDD Cargo', [
    'endpoint' => $this->endpointUrl,
    'size_bytes' => strlen($soapEnvelopeUtf16),
    'preview' => substr($soapEnvelope, 0, 500) . '...'  // ❌ CONTÉM TOKEN!
]);
```

**Problema:** O `preview` do envelope SOAP contém:
- Token de autenticação (`<Token>2342bbkjkh23423bn2j3n42a</Token>`)
- CNPJ da empresa (`<EnterpriseId>17359233000188</EnterpriseId>`)

**Impacto:**
- Logs podem ser acessados por desenvolvedores sem necessidade de conhecer credenciais
- Se logs forem exportados/compartilhados, credenciais são vazadas
- Violação de LGPD (dados de CNPJ em logs)

**Solução:**
```php
// Opção 1: Remover preview completamente
Log::info('Enviando requisição SOAP para NDD Cargo', [
    'endpoint' => $this->endpointUrl,
    'size_bytes' => strlen($soapEnvelopeUtf16)
]);

// Opção 2: Sanitizar preview
$previewSanitized = preg_replace(
    ['/<Token>.*?<\/Token>/', '/<EnterpriseId>.*?<\/EnterpriseId>/'],
    ['<Token>***REDACTED***</Token>', '<EnterpriseId>***REDACTED***</EnterpriseId>'],
    substr($soapEnvelope, 0, 500)
);

Log::info('Enviando requisição SOAP para NDD Cargo', [
    'endpoint' => $this->endpointUrl,
    'size_bytes' => strlen($soapEnvelopeUtf16),
    'preview' => $previewSanitized . '...'
]);
```

---

### 2. **Log de XML Completo com Dados Sensíveis**
**Arquivo:** `app/Services/NddCargo/DTOs/RoteirizadorResponse.php:141-145`
**Severidade:** 🔴 CRÍTICA - Vazamento de Dados

```php
// Log completo se ResponseCode 400
if (strpos($xmlString, '<ResponseCode>400</ResponseCode>') !== false) {
    Log::error('Resposta com erro 400 (completa)', [
        'xml' => $xmlString  // ❌ XML COMPLETO PODE CONTER DADOS SENSÍVEIS
    ]);
}
```

**Problema:**
- XML de resposta pode conter CNPJs, rotas, valores comerciais
- Logs persistem indefinidamente por padrão
- Violação de LGPD (armazenamento desnecessário de dados pessoais)

**Solução:**
```php
// Apenas log de preview sanitizado
if (strpos($xmlString, '<ResponseCode>400</ResponseCode>') !== false) {
    Log::error('Resposta com erro 400', [
        'xml_preview' => substr($xmlString, 0, 300),
        'response_code' => 400,
        'size_bytes' => strlen($xmlString)
        // Não incluir XML completo!
    ]);
}
```

---

### 3. **Falta de Validação de Tamanho de Entrada**
**Arquivo:** `app/Http/Controllers/Api/NddCargoController.php:64-89`
**Severidade:** 🔴 ALTA - DoS / Memory Exhaustion

```php
public function consultarRoteirizador(Request $request): JsonResponse
{
    // Validação NÃO verifica tamanho máximo de arrays
    $validator = Validator::make($request->all(), [
        'pontos_parada' => 'required|array',  // ❌ SEM max:N
        // ...
    ]);
}
```

**Problema:**
- Atacante pode enviar array com milhares de pontos de parada
- Causará timeout ou estouro de memória
- DoS (Denial of Service)

**Solução:**
```php
$validator = Validator::make($request->all(), [
    'pontos_parada' => 'required|array|max:100',  // ✅ Limite máximo
    'pontos_parada.*' => 'string|size:8',
    // ...
]);
```

---

### 4. **Certificado Privado Não Protegido em Cache**
**Arquivo:** `app/Services/NddCargo/NddCargoService.php:306-336`
**Severidade:** 🔴 ALTA - Segurança de Credenciais

```php
private function loadCertificate(): void
{
    // Cache apenas verifica FLAG, mas não protege a chave privada
    if (Cache::has($cacheKey)) {
        // ⚠️ Recarrega chave privada do disco SEM validação adicional
        $this->digitalSignature = new DigitalSignature();
        // ...
    }
}
```

**Problema:**
- Chave privada é carregada repetidamente do disco
- Senha está em `.env` (texto plano)
- Sem verificação de integridade do arquivo .pfx

**Solução:**
```php
// Verificar hash do arquivo antes de carregar
$cacheKey = 'nddcargo_certificate_' . md5_file(config('nddcargo.certificate_pfx_path'));

// OU melhor: usar Laravel's encrypted cookies/session para chave privada (NÃO cache padrão)
```

---

### 5. **Falta de Rate Limiting no Endpoint de Teste**
**Arquivo:** `app/Http/Controllers/Api/NddCargoController.php:273-291`
**Severidade:** 🟡 MÉDIA - Potencial Abuso

```php
public function testConnection(): JsonResponse
{
    try {
        $result = $this->nddCargoService->testConnection();
        // ❌ Faz chamada REAL ao NDD Cargo sem rate limit explícito
        // ❌ Sem autenticação!
    }
}
```

**Problema:**
- Endpoint `/api/ndd-cargo/test-connection` está público (conforme CLAUDE.md)
- Cada chamada faz requisição REAL ao NDD Cargo
- Atacante pode esgotar quota da API NDD Cargo

**Solução:**
```php
// routes/api.php
Route::get('/ndd-cargo/test-connection', [NddCargoController::class, 'testConnection'])
    ->middleware(['throttle:test-ndd-cargo']); // ✅ Rate limit específico

// app/Providers/RouteServiceProvider.php
RateLimiter::for('test-ndd-cargo', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip()); // Máximo 5 testes por minuto
});
```

---

## 🟡 PROBLEMAS MÉDIOS (MEDIUM)

### 6. **Validação Insuficiente de GUID**
**Arquivo:** `app/Http/Controllers/Api/NddCargoController.php:224-233`
**Severidade:** 🟡 MÉDIA - Input Validation

```php
public function consultarResultado(string $guid): JsonResponse
{
    // Validar GUID
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
        return response()->json([
            'success' => false,
            'message' => 'GUID inválido'
        ], 422);
    }
    // ✅ BOM! Mas...
}
```

**Problema:**
- Validação está apenas no controller
- Se chamarmos `consultarResultado()` diretamente do service, não há validação
- Violação do princípio "defense in depth"

**Solução:**
```php
// NddCargoService.php
public function consultarResultado(string $guid): RoteirizadorResponse
{
    // ✅ Validação também no service
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
        return RoteirizadorResponse::error(-3, 'GUID inválido ou malformado');
    }

    // ... resto do código
}
```

---

### 7. **Possível XML Injection em RoteirizadorBuilder**
**Arquivo:** `app/Services/NddCargo/XmlBuilders/RoteirizadorBuilder.php:60-94`
**Severidade:** 🟡 MÉDIA - XML Injection

```php
$cnpj = $xml->createElement('cnpj', $request->cnpjEmpresa); // ⚠️ Sem escape XML!
```

**Problema:**
- `createElement()` NÃO escapa automaticamente o conteúdo
- Se `cnpjEmpresa` contiver `<`, `>`, `&`, o XML será malformado
- Embora o DTO valide que seja numérico, é má prática não escapar

**Análise:**
```php
// ConsultarRoteirizadorRequest.php valida:
if (!preg_match('/^\d{14}$/', $this->cnpjEmpresa)) {
    throw new \InvalidArgumentException('CNPJ da empresa deve conter 14 dígitos');
}
```

**Status:** ✅ **MITIGADO** pela validação estrita do DTO (apenas dígitos)

**Recomendação:** Adicionar escape defensivo mesmo assim:
```php
$cnpj = $xml->createElement('cnpj');
$cnpj->textContent = htmlspecialchars($request->cnpjEmpresa, ENT_XML1, 'UTF-8');
$inf->appendChild($cnpj);
```

---

### 8. **Falta de Timeout em openssl_pkcs12_read**
**Arquivo:** `app/Services/NddCargo/DigitalSignature.php:62-64`
**Severidade:** 🟡 MÉDIA - Performance

```php
if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
    throw new \Exception('Erro ao ler certificado .pfx: ' . openssl_error_string());
}
```

**Problema:**
- `openssl_pkcs12_read()` pode travar se o arquivo .pfx estiver corrompido
- Sem timeout, pode causar hang em produção

**Solução:**
```php
// Set timeout antes de operações OpenSSL
set_time_limit(10); // 10 segundos máximo para carregar certificado

if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
    throw new \Exception('Erro ao ler certificado .pfx: ' . openssl_error_string());
}

set_time_limit(120); // Restaurar timeout padrão
```

---

### 9. **Erro de Parseamento Silencioso nos Trechos**
**Arquivo:** `app/Services/NddCargo/DTOs/RoteirizadorResponse.php:235-249`
**Severidade:** 🟡 MÉDIA - Data Integrity

```php
// Parse dos trechos (opcional)
$trechos = null;
if (isset($dataNode->trechos) || isset($dataNode->Trechos)) {
    $trechosNode = $dataNode->trechos ?? $dataNode->Trechos;
    $trechos = [];

    foreach ($trechosNode->children() as $trechoNode) {
        $trechos[] = [
            'origem' => (string) ($trechoNode->origem ?? $trechoNode->Origem ?? ''),
            'destino' => (string) ($trechoNode->destino ?? $trechoNode->Destino ?? ''),
            'distancia' => (float) ((string) ($trechoNode->distancia ?? $trechoNode->Distancia ?? 0)),
            'tempo' => (int) ((string) ($trechoNode->tempo ?? $trechoNode->Tempo ?? 0)),
        ];
    }
}
```

**Problema:**
- Se `$trechosNode->children()` retornar elementos vazios, adiciona arrays com zeros
- No teste, retornou 49 trechos vazios (origem='', destino='', distancia=0, tempo=0)
- Isso polui a resposta e confunde o consumidor da API

**Solução:**
```php
foreach ($trechosNode->children() as $trechoNode) {
    $origem = (string) ($trechoNode->origem ?? $trechoNode->Origem ?? '');
    $destino = (string) ($trechoNode->destino ?? $trechoNode->Destino ?? '');
    $distancia = (float) ((string) ($trechoNode->distancia ?? $trechoNode->Distancia ?? 0));
    $tempo = (int) ((string) ($trechoNode->tempo ?? $trechoNode->Tempo ?? 0));

    // ✅ Apenas adicionar se tiver dados válidos
    if ($origem !== '' || $destino !== '' || $distancia > 0 || $tempo > 0) {
        $trechos[] = [
            'origem' => $origem,
            'destino' => $destino,
            'distancia' => $distancia,
            'tempo' => $tempo,
        ];
    }
}
```

---

### 10. **Memory Leak Potencial em DigitalSignature**
**Arquivo:** `app/Services/NddCargo/DigitalSignature.php:313-318`
**Severidade:** 🟡 MÉDIA - Resource Management

```php
public function __destruct()
{
    if ($this->privateKey) {
        openssl_free_key($this->privateKey);
    }
}
```

**Problema:**
- Se exceção ocorrer durante `signXml()`, o destrutor pode não ser chamado imediatamente
- Chaves privadas ficam em memória até garbage collection
- Em alta carga, pode acumular recursos OpenSSL

**Solução:**
```php
// Adicionar método de cleanup explícito
public function cleanup(): void
{
    if ($this->privateKey) {
        openssl_free_key($this->privateKey);
        $this->privateKey = null;
    }
}

// NddCargoService.php
try {
    $xmlAssinado = $this->digitalSignature->signXml($xml, $uuid);
} finally {
    $this->digitalSignature->cleanup(); // ✅ Sempre libera recursos
}
```

---

## 🟢 PROBLEMAS MENORES (LOW)

### 11. **Código Duplicado: Validação de ResponseCode**
**Arquivo:** `app/Services/NddCargo/DTOs/RoteirizadorResponse.php:149-171`
**Severidade:** 🟢 BAIXA - Code Smell

```php
// Verificar se é resposta CrossTalk (com ResponseCode)
if (isset($xml->CrossTalk_Header) || isset($xml->{'CrossTalk_Header'})) {
    $header = $xml->{'CrossTalk_Header'};
    $responseCode = (int) ((string) ($header->ResponseCode ?? 0));
    $responseMessage = (string) ($header->ResponseCodeMessage ?? '');
    $guid = (string) ($header->GUID ?? '');

    // ResponseCode 202 = Aceito para processamento assíncrono
    if ($responseCode === 202) {
        return new self(
            sucesso: false,
            status: 202,
            mensagem: $responseMessage,
            guid: $guid,
            rawData: ['response_code' => $responseCode]
        );
    }

    // ResponseCode diferente de 200 = Erro
    if ($responseCode !== 200 && $responseCode !== 0) {
        return self::error($responseCode, $responseMessage);
    }
}
```

**Recomendação:** Extrair para método privado:
```php
private static function handleCrossTalkHeader(\SimpleXMLElement $xml): ?self
{
    if (!isset($xml->CrossTalk_Header) && !isset($xml->{'CrossTalk_Header'})) {
        return null;
    }

    $header = $xml->{'CrossTalk_Header'};
    $responseCode = (int) ((string) ($header->ResponseCode ?? 0));
    $responseMessage = (string) ($header->ResponseCodeMessage ?? '');
    $guid = (string) ($header->GUID ?? '');

    if ($responseCode === 202) {
        return new self(
            sucesso: false,
            status: 202,
            mensagem: $responseMessage,
            guid: $guid,
            rawData: ['response_code' => $responseCode]
        );
    }

    if ($responseCode !== 200 && $responseCode !== 0) {
        return self::error($responseCode, $responseMessage);
    }

    return null;
}

// Uso:
public static function fromXml(string $xmlString): self
{
    // ...
    $xml = new \SimpleXMLElement($xmlString);

    $headerResult = self::handleCrossTalkHeader($xml);
    if ($headerResult !== null) {
        return $headerResult;
    }

    // ... resto do parsing
}
```

---

### 12. **Magic Numbers sem Constantes**
**Arquivo:** `app/Services/NddCargo/DTOs/ConsultarRoteirizadorRequest.php:59-93`
**Severidade:** 🟢 BAIXA - Maintainability

```php
// Validar categoria de pedágio (1-7)
if ($this->categoriaPedagio < 1 || $this->categoriaPedagio > 7) {
    throw new \InvalidArgumentException('Categoria de pedágio deve estar entre 1 e 7');
}

// Validar tipo de veículo (1-10)
if ($this->tipoVeiculo < 1 || $this->tipoVeiculo > 10) {
    throw new \InvalidArgumentException('Tipo de veículo deve estar entre 1 e 10');
}
```

**Recomendação:**
```php
class ConsultarRoteirizadorRequest
{
    private const MIN_CATEGORIA_PEDAGIO = 1;
    private const MAX_CATEGORIA_PEDAGIO = 7;

    private const MIN_TIPO_VEICULO = 1;
    private const MAX_TIPO_VEICULO = 10;

    private const MIN_TIPO_ROTA = 1;
    private const MAX_TIPO_ROTA = 3;

    private function validate(): void
    {
        if ($this->categoriaPedagio < self::MIN_CATEGORIA_PEDAGIO
            || $this->categoriaPedagio > self::MAX_CATEGORIA_PEDAGIO) {
            throw new \InvalidArgumentException(
                sprintf('Categoria de pedágio deve estar entre %d e %d',
                    self::MIN_CATEGORIA_PEDAGIO,
                    self::MAX_CATEGORIA_PEDAGIO)
            );
        }
        // ... similar para outros
    }
}
```

---

### 13. **Inconsistência de Nomenclatura: snake_case vs camelCase**
**Arquivo:** `app/Services/NddCargo/DTOs/RoteirizadorResponse.php:106-112`
**Severidade:** 🟢 BAIXA - Style

```php
public function toArray(): array
{
    $data = [
        'sucesso' => $this->sucesso,
        'status' => $this->status,
        'mensagem' => $this->mensagem,
        'distancia_km' => $this->distanciaKm,  // snake_case
        'tempo_minutos' => $this->tempoMinutos,  // snake_case
        'valor_total_pedagogios' => $this->valorTotalPedagios,  // snake_case
        'pracas_pedagio' => array_map(fn($praca) => $praca->toArray(), $this->pracasPedagio),  // snake_case
        'quantidade_pracas' => count($this->pracasPedagio),  // snake_case
        'trechos' => $this->trechos,
    ];
    // ...
}
```

**Análise:** Está correto! Laravel/PHP padrão é usar snake_case para arrays JSON (PSR-12).

**Status:** ✅ **SEM PROBLEMA** - Seguindo padrão Laravel

---

### 14. **Falta de Type Hints em Métodos Privados**
**Arquivo:** `app/Services/NddCargo/NddCargoSoapClient.php:237-246`
**Severidade:** 🟢 BAIXA - Type Safety

```php
private function escapeCdata(string $content): string
{
    // Remover CDATA aninhado (não permitido em XML)
    return str_replace(['<![CDATA[', ']]>'], ['', ''], $content);
}
```

**Status:** ✅ **BOM!** - Tem type hints corretos

---

### 15. **Debug Log Permanente em Produção**
**Arquivo:** `app/Services/NddCargo/DTOs/RoteirizadorResponse.php:193-206`
**Severidade:** 🟢 BAIXA - Performance

```php
Log::debug('Nó de dados encontrado com namespace', [
    'node_name' => $dataNode->getName()
]);
// ...
Log::debug('Nó de dados encontrado sem namespace', [
    'node_name' => $dataNode->getName()
]);
```

**Problema:**
- Logs de debug podem poluir em produção
- Consumo desnecessário de I/O

**Solução:**
```php
if (config('nddcargo.logging.log_xml_parsing', false)) {
    Log::debug('Nó de dados encontrado com namespace', [
        'node_name' => $dataNode->getName()
    ]);
}
```

---

## 📊 RESUMO EXECUTIVO

| Categoria | Quantidade | Prioridade |
|-----------|------------|------------|
| 🔴 Críticos | 5 | **URGENTE** |
| 🟡 Médios | 5 | Alta |
| 🟢 Menores | 5 | Baixa |
| **Total** | **15** | - |

### ✅ Pontos Positivos
1. **Validação Estrita nos DTOs** - `ConsultarRoteirizadorRequest` valida todos os inputs
2. **Type Safety** - Uso correto de type hints em PHP 8+
3. **Separação de Responsabilidades** - DTOs, Services, Controllers bem separados
4. **Tratamento de Exceções** - Try-catch em todos os pontos críticos
5. **Nomenclatura Consistente** - snake_case para JSON, camelCase para PHP (padrão Laravel)

### ❌ Pontos Negativos Principais
1. **Exposição de Credenciais em Logs** - Token, CNPJ, XML completo (CRÍTICO)
2. **Falta de Rate Limiting** - Endpoints públicos podem ser abusados
3. **Validação de Entrada Incompleta** - Arrays sem limite de tamanho (DoS)
4. **Gerenciamento de Recursos** - Chaves privadas sem cleanup explícito
5. **Trechos Vazios Poluindo Response** - 49 objetos vazios retornados

---

## 🛠️ PLANO DE AÇÃO RECOMENDADO

### Fase 1: Correções Críticas (Imediato - 1 dia)
- [ ] Remover logs com credenciais (`NddCargoSoapClient.php:261`)
- [ ] Sanitizar logs de erro (`RoteirizadorResponse.php:141`)
- [ ] Adicionar rate limiting em endpoints públicos
- [ ] Validar tamanho máximo de arrays (`NddCargoController.php:68`)

### Fase 2: Melhorias de Segurança (1 semana)
- [ ] Implementar cleanup explícito de recursos OpenSSL
- [ ] Adicionar validação de GUID no service layer
- [ ] Filtrar trechos vazios na resposta
- [ ] Adicionar timeout em operações OpenSSL

### Fase 3: Refatorações (Quando houver tempo)
- [ ] Extrair constantes para magic numbers
- [ ] Refatorar código duplicado em métodos privados
- [ ] Condicionalizar logs de debug
- [ ] Adicionar escape defensivo em XML builder

---

## 📝 NOTAS FINAIS

**Avaliação Geral:** ⭐⭐⭐⭐☆ (4/5)

O código está **bem estruturado** e segue boas práticas de Laravel/PHP moderno. Os problemas identificados são principalmente relacionados a **segurança de logs** e **validações defensivas**, que são fáceis de corrigir.

**Recomendação:** Corrigir os **5 problemas críticos** antes de ir para produção. Os problemas médios e menores podem ser endereçados em sprints futuros.

---

**Auditor:** Claude Code
**Metodologia:** Análise estática de código + Threat Modeling
**Referências:**
- OWASP Top 10 2021
- PSR-12: Extended Coding Style Guide
- Laravel Security Best Practices
- LGPD (Lei Geral de Proteção de Dados)
