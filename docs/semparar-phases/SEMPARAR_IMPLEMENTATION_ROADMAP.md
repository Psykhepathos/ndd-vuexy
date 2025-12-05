# SEMPARAR - ROADMAP DE IMPLEMENTAÇÃO LARAVEL
## Guia Sequencial para IA - Migração Progress → Laravel

**Baseado em:** `SEMPARAR_AI_REFERENCE.md` + `COMPRA_VIAGEM_ERROS.md`
**Objetivo:** Implementar sistema de compra de viagem SemParar funcional
**Método:** Fases independentes, executáveis em sessões separadas

---

## 📋 ÍNDICE DE FASES

| Fase | Nome | Duração | Status | Arquivo de Checkpoint |
|------|------|---------|--------|----------------------|
| **1A** | SemParar SOAP Core | 2-3 dias | 🔴 PENDENTE | `PHASE_1A_COMPLETE.md` |
| **1B** | SemParar SOAP Routing | 2-3 dias | 🔴 PENDENTE | `PHASE_1B_COMPLETE.md` |
| **2A** | Roteirização Básica | 3-4 dias | 🔴 PENDENTE | `PHASE_2A_COMPLETE.md` |
| **2B** | Lógica Regional | 2 dias | 🔴 PENDENTE | `PHASE_2B_COMPLETE.md` |
| **2C** | GPS & Entregas | 2 dias | 🔴 PENDENTE | `PHASE_2C_COMPLETE.md` |
| **3** | Pricing & Purchase | 2-3 dias | 🔴 PENDENTE | `PHASE_3_COMPLETE.md` |
| **4** | Receipts & Emails | 2 dias | 🔴 PENDENTE | `PHASE_4_COMPLETE.md` |
| **5** | Personalização | 3 dias | 🔴 PENDENTE | `PHASE_5_COMPLETE.md` |
| **6** | Polish & Testing | 2 dias | 🔴 PENDENTE | `PHASE_6_COMPLETE.md` |

**TOTAL:** 20-24 dias úteis (~4-5 semanas)

---

## 🎯 COMO USAR ESTE DOCUMENTO

### Para a IA (você):

1. **Ao iniciar sessão:** Leia este arquivo + arquivo de checkpoint da última fase concluída
2. **Durante implementação:** Siga instruções da fase atual
3. **Ao finalizar:** Crie arquivo `PHASE_X_COMPLETE.md` com:
   - ✅ Checklist de tarefas concluídas
   - 📝 Código implementado (resumo)
   - 🧪 Testes executados
   - 📦 Arquivos criados/modificados
   - ⚠️ Problemas encontrados
   - ➡️ Próximos passos

### Para o usuário (humano):

1. Abra nova sessão de chat
2. Diga: "Implementar FASE [X] do SEMPARAR_IMPLEMENTATION_ROADMAP.md"
3. IA lerá roadmap + checkpoints anteriores
4. IA executará fase completa
5. Ao finalizar, IA criará checkpoint
6. Feche sessão e repita para próxima fase

---

# 📦 FASE 1A: SEMPARAR SOAP CORE
**Duração:** 2-3 dias
**Status:** 🔴 PENDENTE
**Dependências:** Nenhuma
**Checkpoint:** `PHASE_1A_COMPLETE.md`

## 🎯 OBJETIVO
Criar serviço SOAP básico para conectar com API SemParar e implementar métodos fundamentais (autenticação e status de veículo).

## 📚 REFERÊNCIAS
- `SEMPARAR_AI_REFERENCE.md` linhas 52-108 (SemParar.Connect & Rota classes)
- `SEMPARAR_AI_REFERENCE.md` linhas 608-630 (SOAP Endpoints)
- `COMPRA_VIAGEM_ERROS.md` erro #6 (linha 250-315)

## 📋 CHECKLIST DE TAREFAS

### 1.1 - Criar Estrutura Base
- [ ] Criar arquivo `app/Services/SemParar/SemPararSoapClient.php`
- [ ] Criar arquivo `app/Services/SemParar/SemPararService.php`
- [ ] Criar arquivo `config/semparar.php`
- [ ] Adicionar variáveis ao `.env`:
  ```env
  SEMPARAR_WSDL_URL=https://app.viafacil.com.br/wsvp/ValePedagio?wsdl
  SEMPARAR_CNPJ=2024209702
  SEMPARAR_USER=CORPORATIVO
  SEMPARAR_PASSWORD=Tambasa20
  SEMPARAR_TIMEOUT=30
  ```

### 1.2 - Implementar SemPararSoapClient (Base)
Criar classe com:
- [ ] Propriedade `$soapClient` (PHP SoapClient)
- [ ] Propriedade `$cToken` (session token, cacheable)
- [ ] Método `__construct()` - Inicializa SOAP com TLS 1.3
- [ ] Método `autenticarUsuario(): string` - Retorna cToken
- [ ] Método `getCachedToken(): ?string` - Cache de 1 hora
- [ ] Método `callSoapMethod(string $method, array $params): mixed`
- [ ] Método `parseXmlResponse(string $xml, string $tag): mixed`

### 1.3 - Implementar Autenticação
```php
// app/Services/SemParar/SemPararSoapClient.php

public function autenticarUsuario(): string
{
    // Check cache first
    $cachedToken = Cache::get('semparar_token');
    if ($cachedToken) {
        return $cachedToken;
    }

    // SOAP call
    $response = $this->soapClient->__soapCall('autenticarUsuario', [
        'cnpj' => config('semparar.cnpj'),
        'usuario' => config('semparar.user'),
        'senha' => config('semparar.password')
    ]);

    // Parse XML: <sessao xsi:type="xsd:long">VALUE</sessao>
    $xml = simplexml_load_string($response);
    $token = (string)$xml->sessao;

    // Cache for 1 hour
    Cache::put('semparar_token', $token, now()->addHour());

    return $token;
}
```

**Referência Progress:**
```progress
// Connect.cls linha 39-45
GET():
  CREATE SERVER hWebService
  hWebService:CONNECT(c-connect)
  RUN VALUE("ValePedagio") SET hPorta ON hWebService
  RUN VALUE("autenticarUsuario") IN hPorta(
    INPUT "2024209702",
    INPUT "CORPORATIVO",
    INPUT "Tambasa20",
    OUTPUT xml
  )
  cToken = extractContentFromXml(xml)
```

### 1.4 - Implementar Status de Veículo
```php
public function obterStatusVeiculo(string $placa): array
{
    $token = $this->autenticarUsuario();

    $response = $this->soapClient->__soapCall('obterStatusVeiculo', [
        'placa' => $placa,
        'sessao' => $token
    ]);

    // Parse XML response
    $xml = simplexml_load_string($response);

    return [
        'descricao' => (string)$xml->descricao,
        'eixos' => (int)$xml->eixos,
        'proprietario' => (string)$xml->proprietario,
        'tag' => (string)$xml->tag,
        'status' => (int)$xml->status
    ];
}
```

**Referência Progress:**
```progress
// Rota.cls linha 109-123
statusVei(placa, OUT desc, OUT eixos, OUT proprietario, OUT tag, OUT erro):
  SOAP: obterStatusVeiculo(placa, cToken, OUT retorno-Xml-Roteriza)
  PARSE: <descricao>, <eixos>, <proprietario>, <tag>
```

### 1.5 - Criar SemPararService (Wrapper)
```php
// app/Services/SemParar/SemPararService.php

namespace App\Services\SemParar;

class SemPararService
{
    protected SemPararSoapClient $soapClient;

    public function __construct()
    {
        $this->soapClient = new SemPararSoapClient();
    }

    public function validateVehicleStatus(string $placa): array
    {
        try {
            $result = $this->soapClient->obterStatusVeiculo($placa);

            // Verify status code (0 = success)
            if ($result['status'] !== 0) {
                return [
                    'success' => false,
                    'error' => $this->getStatusMessage($result['status']),
                    'code' => 'SEMPARAR_ERROR_' . $result['status']
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'descricao' => $result['descricao'],
                    'eixos' => $result['eixos'],
                    'proprietario' => $result['proprietario'],
                    'tag' => $result['tag']
                ]
            ];

        } catch (\SoapFault $e) {
            Log::error('SOAP Error - obterStatusVeiculo', [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao conectar com SemParar: ' . $e->getMessage(),
                'code' => 'SOAP_FAULT'
            ];
        }
    }

    protected function getStatusMessage(int $code): string
    {
        // TODO: Query PUB.semPararStatus table
        $messages = [
            0 => 'Sucesso',
            999 => 'Erro no serviço externo SemParar',
            // Add more codes from database
        ];

        return $messages[$code] ?? "Erro desconhecido: {$code}";
    }
}
```

### 1.6 - Atualizar ProgressService
```php
// app/Services/ProgressService.php

public function validateVehicleStatusSemParar(string $placa, bool $simulated = false): array
{
    if ($simulated) {
        // Return mock data
        return [
            'success' => true,
            'data' => [
                'descricao' => 'CAMINHÃO TRUCK SIMULADO',
                'eixos' => 3,
                'proprietario' => 'TESTE SIMULADO',
                'tag' => '12345678'
            ]
        ];
    }

    // Real SOAP call
    $semparar = app(SemPararService::class);
    return $semparar->validateVehicleStatus($placa);
}
```

### 1.7 - Criar Config File
```php
// config/semparar.php

return [
    'wsdl_url' => env('SEMPARAR_WSDL_URL', 'https://app.viafacil.com.br/wsvp/ValePedagio?wsdl'),
    'cnpj' => env('SEMPARAR_CNPJ', '2024209702'),
    'user' => env('SEMPARAR_USER', 'CORPORATIVO'),
    'password' => env('SEMPARAR_PASSWORD'),
    'timeout' => env('SEMPARAR_TIMEOUT', 30),

    'soap_options' => [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'connection_timeout' => 30,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            ]
        ])
    ]
];
```

### 1.8 - Criar Testes
- [ ] Criar `tests/Unit/SemPararSoapClientTest.php`
- [ ] Testar autenticação (mock SOAP)
- [ ] Testar obterStatusVeiculo (mock SOAP)
- [ ] Testar cache de token
- [ ] Testar tratamento de erros

```php
// tests/Unit/SemPararSoapClientTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SemParar\SemPararService;
use Illuminate\Support\Facades\Cache;

class SemPararSoapClientTest extends TestCase
{
    public function test_autenticacao_cacheia_token()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('semparar_token')
            ->andReturn(null);

        Cache::shouldReceive('put')
            ->once()
            ->with('semparar_token', \Mockery::any(), \Mockery::any());

        // Mock SOAP client
        // ... assert token returned
    }

    public function test_obter_status_veiculo_valido()
    {
        $service = new SemPararService();

        // Mock SOAP response
        $result = $service->validateVehicleStatus('ABC1234');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('descricao', $result['data']);
        $this->assertArrayHasKey('eixos', $result['data']);
    }
}
```

### 1.9 - Integrar com Controller
```php
// app/Http/Controllers/Api/CompraViagemController.php

// Remover linha 245-248 (mock data)
// Substituir por:

public function validarPlaca(Request $request): JsonResponse
{
    // ... validação ...

    $result = $this->progressService->validateVehicleStatusSemParar(
        $validated['placa'],
        !$this->ALLOW_SOAP_QUERIES  // false = real SOAP call
    );

    // ... resto do código ...
}
```

## 🧪 TESTES MANUAIS

### Teste 1: Autenticação
```bash
php artisan tinker

$soap = new \App\Services\SemParar\SemPararSoapClient();
$token = $soap->autenticarUsuario();
echo "Token: {$token}\n";
```

**Resultado esperado:** Token numérico longo (ex: "1234567890")

### Teste 2: Status de Veículo
```bash
php artisan tinker

$service = new \App\Services\SemParar\SemPararService();
$result = $service->validateVehicleStatus('ABC1234');
print_r($result);
```

**Resultado esperado:**
```php
[
    'success' => true,
    'data' => [
        'descricao' => 'CAMINHÃO TRUCK',
        'eixos' => 3,
        'proprietario' => 'TRANSPORTADORA XYZ',
        'tag' => '12345678'
    ]
]
```

### Teste 3: Frontend
1. Abrir: http://localhost:8002/compra-viagem
2. Preencher pacote: 3043368
3. Validar pacote
4. Preencher placa: ABC1234
5. Pressionar ENTER

**Resultado esperado:** Dialog com dados do veículo (descrição, eixos, proprietário, tag)

## 📦 ENTREGÁVEIS

Ao finalizar esta fase, você deve ter criado:

### Arquivos Novos:
- `app/Services/SemParar/SemPararSoapClient.php` (~200 linhas)
- `app/Services/SemParar/SemPararService.php` (~150 linhas)
- `config/semparar.php` (~30 linhas)
- `tests/Unit/SemPararSoapClientTest.php` (~100 linhas)

### Arquivos Modificados:
- `app/Services/ProgressService.php` (método validateVehicleStatusSemParar)
- `app/Http/Controllers/Api/CompraViagemController.php` (remover mock, usar real)
- `.env` (adicionar variáveis SEMPARAR_*)

### Funcionalidades:
- ✅ Conexão SOAP com SemParar funcional
- ✅ Autenticação com cache de 1 hora
- ✅ Validação de placa via API real
- ✅ Tratamento de erros SOAP
- ✅ Testes unitários básicos

## ⚠️ PROBLEMAS CONHECIDOS

### Problema 1: TLS 1.3 no Windows
**Sintoma:** `SSL: Connection reset by peer`
**Solução:** Usar TLS 1.2 como fallback:
```php
'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
```

### Problema 2: WSDL Cache
**Sintoma:** Mudanças na API não refletem
**Solução:** Desabilitar cache durante desenvolvimento:
```php
'cache_wsdl' => WSDL_CACHE_NONE
```

### Problema 3: Timeout
**Sintoma:** `Maximum execution time exceeded`
**Solução:** Aumentar timeout:
```php
'connection_timeout' => 60,
'default_socket_timeout' => 60
```

## ➡️ PRÓXIMA FASE

Após concluir esta fase:
1. Criar arquivo `PHASE_1A_COMPLETE.md` com checklist
2. Commitar código: `git commit -m "feat: FASE 1A - SemParar SOAP Core implementado"`
3. Fechar sessão
4. Nova sessão: "Implementar FASE 1B do SEMPARAR_IMPLEMENTATION_ROADMAP.md"

---

# 📦 FASE 1B: SEMPARAR SOAP ROUTING
**Duração:** 2-3 dias
**Status:** 🔴 PENDENTE
**Dependências:** FASE 1A
**Checkpoint:** `PHASE_1B_COMPLETE.md`

## 🎯 OBJETIVO
Implementar métodos SOAP de roteirização: roteirizarPracasPedagio, cadastrarRotaTemporaria, obterCustoRota.

## 📚 REFERÊNCIAS
- `SEMPARAR_AI_REFERENCE.md` linhas 125-212 (roterizaCa method)
- `SEMPARAR_AI_REFERENCE.md` linhas 248-280 (Temp tables & datasets)
- `COMPRA_VIAGEM_ERROS.md` erro #1 (linha 12-95)

## 📋 CHECKLIST DE TAREFAS

### 1B.1 - Criar Builders de XML
- [ ] Criar `app/Services/SemParar/XmlBuilders/PontosParadaBuilder.php`
- [ ] Implementar `buildPontosParadaXml(array $pontos): string`
- [ ] Implementar `buildOpcoesXml(): string`
- [ ] Implementar `buildPracasXml(array $pracas): string`

### 1B.2 - Implementar roteirizarPracasPedagio
```php
// app/Services/SemParar/SemPararSoapClient.php

public function roteirizarPracasPedagio(string $pontosXml, string $opcoesXml): array
{
    $token = $this->autenticarUsuario();

    $response = $this->soapClient->__soapCall('roteirizarPracasPedagio', [
        'pontos' => $pontosXml,
        'opcoes' => $opcoesXml,
        'sessao' => $token
    ]);

    // Parse pracaPedagio dataset from XML
    $xml = simplexml_load_string($response);
    $pracas = [];

    foreach ($xml->pracaPedagio as $praca) {
        $pracas[] = [
            'id' => (int)$praca->id,
            'praca' => (string)$praca->praca,
            'rodovia' => (string)$praca->rodovia,
            'km' => (float)$praca->km,
            'concessionaria' => (string)$praca->concessionaria,
            'status' => (int)$praca->status
        ];
    }

    return $pracas;
}
```

### 1B.3 - Implementar cadastrarRotaTemporaria
```php
public function cadastrarRotaTemporaria(string $pracasXml, string $nomeRota): string
{
    $token = $this->autenticarUsuario();

    $response = $this->soapClient->__soapCall('cadastrarRotaTemporaria', [
        'pracas' => $pracasXml,
        'nomeRota' => $nomeRota,
        'sessao' => $token
    ]);

    // Parse <id> from XML
    $xml = simplexml_load_string($response);
    $codRotaSemParar = (string)$xml->id;

    return $codRotaSemParar;
}
```

### 1B.4 - Implementar obterCustoRota
```php
public function obterCustoRota(
    string $nomeRota,
    string $placa,
    int $eixos,
    string $dataInicio,
    string $dataFim
): float {
    $token = $this->autenticarUsuario();

    $response = $this->soapClient->__soapCall('obterCustoRota', [
        'nomeRota' => $nomeRota,
        'placa' => $placa,
        'eixos' => $eixos,
        'dataInicio' => $dataInicio,
        'dataFim' => $dataFim,
        'sessao' => $token
    ]);

    // Parse <valor xsi:type="xsd:decimal">
    $xml = simplexml_load_string($response);
    $valor = (float)$xml->valor;

    return $valor;
}
```

### 1B.5 - Criar XmlBuilders
```php
// app/Services/SemParar/XmlBuilders/PontosParadaBuilder.php

namespace App\Services\SemParar\XmlBuilders;

class PontosParadaBuilder
{
    public static function buildPontosParadaXml(array $pontos): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<pontosParadaDset>';
        $xml .= '<pontosParada><status>0</status></pontosParada>';
        $xml .= '<pontoParada>';

        foreach ($pontos as $ponto) {
            $xml .= '<ponto>';
            $xml .= "<codigoIBGE>{$ponto['codibge']}</codigoIBGE>";
            $xml .= "<descricao>{$ponto['descricao']}</descricao>";
            $xml .= '<latLong>';
            $xml .= "<latitude>{$ponto['latitude']}</latitude>";
            $xml .= "<longitude>{$ponto['longitude']}</longitude>";
            $xml .= '</latLong>';
            $xml .= '</ponto>';
        }

        $xml .= '</pontoParada>';
        $xml .= '</pontosParadaDset>';

        return $xml;
    }

    public static function buildOpcoesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><opcoes></opcoes>';
    }

    public static function buildPracasXml(array $pracas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<pracas>';

        foreach ($pracas as $praca) {
            $xml .= "<id>{$praca['id']}</id>";
        }

        $xml .= '</pracas>';

        return $xml;
    }
}
```

### 1B.6 - Testes
- [ ] Testar buildPontosParadaXml
- [ ] Testar roteirizarPracasPedagio (mock)
- [ ] Testar cadastrarRotaTemporaria (mock)
- [ ] Testar obterCustoRota (mock)

## 📦 ENTREGÁVEIS

### Arquivos Novos:
- `app/Services/SemParar/XmlBuilders/PontosParadaBuilder.php`
- `tests/Unit/XmlBuildersTest.php`

### Arquivos Modificados:
- `app/Services/SemParar/SemPararSoapClient.php` (+150 linhas)

### Funcionalidades:
- ✅ Construção de XML para pontos de parada
- ✅ Roteirização via API SemParar
- ✅ Cadastro de rota temporária
- ✅ Cálculo de custo de rota

## ➡️ PRÓXIMA FASE

Após concluir:
1. Criar `PHASE_1B_COMPLETE.md`
2. Commitar: `git commit -m "feat: FASE 1B - SemParar SOAP Routing"`
3. Nova sessão: "Implementar FASE 2A"

---

# 📦 FASE 2A: ROTEIRIZAÇÃO BÁSICA
**Duração:** 3-4 dias
**Status:** 🔴 PENDENTE
**Dependências:** FASE 1A, 1B
**Checkpoint:** `PHASE_2A_COMPLETE.md`

## 🎯 OBJETIVO
Implementar método roterizaCa() básico: carregar municípios da rota, construir XML, chamar SOAP, retornar nome/código da rota temporária.

[CONTINUA COM DETALHES...]

---

# 📦 FASE 2B: LÓGICA REGIONAL
[DETALHES COMPLETOS...]

---

# 📦 FASE 2C: GPS & ENTREGAS
[DETALHES COMPLETOS...]

---

# 📦 FASE 3: PRICING & PURCHASE
[DETALHES COMPLETOS...]

---

# 📦 FASE 4: RECEIPTS & EMAILS
[DETALHES COMPLETOS...]

---

# 📦 FASE 5: PERSONALIZAÇÃO
[DETALHES COMPLETOS...]

---

# 📦 FASE 6: POLISH & TESTING
[DETALHES COMPLETOS...]

---

## 📊 TRACKING DE PROGRESSO

### Atualizado por última vez: [DATA]
### Última fase concluída: Nenhuma
### Próxima fase: 1A

### Status Geral:
- [ ] FASE 1A - SemParar SOAP Core
- [ ] FASE 1B - SemParar SOAP Routing
- [ ] FASE 2A - Roteirização Básica
- [ ] FASE 2B - Lógica Regional
- [ ] FASE 2C - GPS & Entregas
- [ ] FASE 3 - Pricing & Purchase
- [ ] FASE 4 - Receipts & Emails
- [ ] FASE 5 - Personalização
- [ ] FASE 6 - Polish & Testing

**Progresso:** 0/9 fases (0%)
