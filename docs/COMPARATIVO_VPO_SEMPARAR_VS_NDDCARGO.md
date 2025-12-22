# Comparativo: SemParar VPO vs NDD Cargo VPO

**Ultima Atualizacao:** 2025-12-22

Este documento descreve os dois sistemas de compra/emissao de Vale Pedagio (VPO) implementados no projeto NDD.

---

## Resumo Executivo

| Aspecto | SemParar VPO | NDD Cargo VPO |
|---------|-------------|---------------|
| **Tipo** | SOAP API (sincrono) | SOAP/CrossTalk (assincrono) |
| **Autenticacao** | Token SOAP Session | Certificado Digital RSA-SHA1 |
| **Assinatura** | Nao | XML Digital Signature |
| **Processamento** | Tempo real (2-3s) | Assincrono (10-30s polling) |
| **Dados VPO** | Minimos (Placa/Eixos) | Completos (19 campos) |
| **Uso Principal** | Compra rapida de pedagio | Emissao formal de VPO |

---

## 1. SemParar VPO - Compra Via SOAP API

### 1.1 Visao Geral

O sistema SemParar utiliza a API SOAP do ViaFacil para realizar compras de vale pedagio em tempo real. E ideal para operacoes que precisam de resposta imediata.

**Arquivos Principais:**
- `app/Http/Controllers/Api/CompraViagemController.php`
- `app/Services/SemParar/SemPararSoapClient.php`
- `app/Services/SemParar/SemPararService.php`

### 1.2 Fluxo de Autenticacao

```php
// Autenticacao via SOAP - Parametros POSICIONAIS (critico!)
$resultado = $client->autenticarUsuario(
    $cnpj,      // Ex: "06378206000162"
    $user,      // Ex: "CORPORATIVO"
    $password   // Ex: "Senha123"
);

// Token armazenado em cache por 24h
$token = $resultado->return;
Cache::put('semparar_token', $token, 86400);
```

**Importante:** O SemParar usa parametros posicionais, NAO nomeados. Usar array associativo causa erro "Array to string conversion".

### 1.3 Etapas do Processo de Compra (6 Fases)

#### Fase 1: Inicializar
`GET /api/compra-viagem/initialize`

Retorna configuracoes iniciais:
- `test_mode` - Se ALLOW_SOAP_PURCHASE=false
- `allow_soap_queries` - Se permite consultas SOAP
- Modos disponiveis: CD, Outros, Retorno

#### Fase 2: Validar Pacote
`POST /api/compra-viagem/validar-pacote`

```json
// Request
{
  "codpac": 123456,
  "flgcd": false
}

// Response
{
  "success": true,
  "data": {
    "transporte": { "codtrn": 1234, "nomtrn": "TRANSPORTES ABC" },
    "rota_sugerida": 204,
    "test_mode": false
  }
}
```

**Validacoes:**
1. Pacote existe no Progress (`PUB.pacote`)
2. Pacote nao e TCD quando `flgcd=false`
3. Busca rota sugerida via `pacsoc` ou `introt`

#### Fase 3: Validar Placa
`POST /api/compra-viagem/validar-placa`

```json
// Request
{ "placa": "ABC1234" }

// Response
{
  "success": true,
  "descricao": "IVECO STRALIS 460",
  "eixos": 3,
  "proprietario": "VANDERLEI PEREIRA",
  "soap_real": true
}
```

**Fluxo:**
1. Valida formato brasileiro (ABC1234 ou ABC1D23 Mercosul)
2. Chama SOAP `statusVeiculo()` do SemParar
3. Retorna eixos reais (importante para calculo)

**Protecao LGPD:** Placa mascarada em logs (ABC****)

#### Fase 4: Selecionar Rota
`GET /api/compra-viagem/rotas?search=BARREIRAS`
`POST /api/compra-viagem/validar-rota`

**Validacoes:**
1. Rota compativel com tipo (CD/Normal/Retorno)
2. Viagem nao duplicada para pacote+rota
3. Calcula datas vigencia

#### Fase 5: Verificar Preco
`POST /api/compra-viagem/verificar-preco`

```json
// Request
{
  "codpac": 123456,
  "cod_rota": 204,
  "qtd_eixos": 3,
  "placa": "ABC1234",
  "data_inicio": "2025-10-27",
  "data_fim": "2025-11-03"
}

// Response
{
  "success": true,
  "data": {
    "valor_viagem": 1250.50,
    "pracas": 12,
    "rodovia_principal": "BR-040"
  }
}
```

**Fluxo Interno:**
1. Cria rota temporaria com pracas do Progress
2. Cadastra no SemParar via `cadastrarRotaTemporaria()`
3. Calcula custo via `obterCustoRota()`

#### Fase 6: Comprar Viagem (CRITICO!)
`POST /api/compra-viagem/comprar`

```json
// Request
{
  "codpac": 123456,
  "cod_rota": 204,
  "placa": "ABC1234",
  "qtd_eixos": 3,
  "data_inicio": "2025-10-27",
  "data_fim": "2025-11-03",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
}

// Response
{
  "success": true,
  "message": "Viagem comprada com sucesso!",
  "data": {
    "numero_viagem": "SPV202510271250",
    "valor": 1250.50,
    "test_mode": false
  }
}
```

### 1.4 Protecoes de Seguranca

#### Idempotencia
```php
// Aceita idempotency_key (UUID)
// Cacheia resultado por 24h
// Detecta duplicatas com lock de 10 segundos
if (Cache::has("compra_viagem_lock_{$idempotencyKey}")) {
    return response()->json(['error' => 'Operacao em andamento'], 409);
}
```

#### Re-validacoes (Protecao Race Condition)
```php
// CORRECAO #1: Re-validacao de duplicatas
$viagemCheck = $this->progressService->viagemJaComprada($codpac, $rotaId);
if ($viagemCheck['duplicada']) {
    return response()->json(['error' => 'Viagem duplicada'], 409);
}

// CORRECAO #2: Re-validacao de eixos (evita manipulacao frontend)
$validacaoPlaca = $this->semPararService->statusVeiculo($placa);
$eixosReais = $validacaoPlaca['eixos'];
if ($eixosReais != $qtd_eixos) {
    return response()->json(['error' => 'Eixos manipulados'], 400);
}
```

#### Controle de Compras
```env
# .env
ALLOW_SOAP_PURCHASE=true   # Compra REAL via SOAP
ALLOW_SOAP_PURCHASE=false  # Compra SIMULADA (UUID local)
```

### 1.5 Tabelas Progress Utilizadas

| Tabela | Funcao |
|--------|--------|
| `PUB.pacote` | Dados do pacote |
| `PUB.transporte` | Dados do transportador |
| `PUB.semPararRot` | Rotas cadastradas |
| `PUB.semPararRotMu` | Municipios das rotas |
| `PUB.sPararViagem` | Viagens compradas |
| `PUB.semPararRotMuLog` | Log de municipios por viagem |

---

## 2. NDD Cargo VPO - Emissao Assincrona com Assinatura Digital

### 2.1 Visao Geral

O sistema NDD Cargo utiliza protocolo CrossTalk com assinatura digital RSA-SHA1 para emissao formal de VPO. O processamento e assincrono com polling via GUID.

**Arquivos Principais:**
- `app/Http/Controllers/Api/VpoEmissaoController.php`
- `app/Services/Vpo/VpoEmissaoService.php`
- `app/Services/Vpo/VpoDataSyncService.php`
- `app/Services/NddCargo/NddCargoService.php`
- `app/Services/NddCargo/DigitalSignature.php`

### 2.2 Autenticacao via Certificado Digital

```php
// app/Services/NddCargo/DigitalSignature.php

class DigitalSignature {
    public function signXml(string $xml, string $uuid): string {
        // 1. Carrega certificado PKCS#12
        $content = file_get_contents($this->certPath);
        openssl_pkcs12_read($content, $certs, $this->certPassword);

        // 2. Assina XML com SHA1+RSA
        openssl_sign($xml, $signature, $certs['pkey'], OPENSSL_ALGO_SHA1);

        // 3. Embed signature no XML
        return $this->embedSignature($xml, $signature);
    }
}
```

**NAO usa Token:** Autenticacao via certificado digital + CNPJ em cada requisicao.

### 2.3 Fluxo Assincrono com GUID

Diferente do SemParar (sincrono), NDD Cargo usa processamento assincrono:

```
1. Cliente envia requisicao com GUID unico
2. API retorna status 202 (Aceito para processamento)
3. Cliente faz polling com GET /resultado/{guid}
4. Resultado fica pronto em segundos/minutos
```

### 2.4 Wizard VPO Emissao (5 Etapas)

#### Etapa 1: Iniciar Emissao
`POST /api/vpo/emissao/iniciar`

```json
// Request
{
  "codpac": 123456,
  "rota_id": 204,
  "codmot": 1234,
  "placa": "ABC1234",
  "eixos": 3,
  "data_inicio": "2025-10-27",
  "data_fim": "2025-11-03",
  "valor_total": 1250.50,
  "km_total": 850.0
}

// Response
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "codpac": 123456
  }
}
```

**Fluxo Interno:**
1. Buscar transportador no Progress
2. Sincronizar dados VPO (Progress + ANTT)
3. Validar 19 campos obrigatorios
4. Criar registro `VpoEmissao` com status `pending`

#### Etapa 2: Sincronizacao VPO
`POST /api/vpo/sync/transportador`

O sistema sincroniza automaticamente 19 campos VPO de multiplas fontes:

| Campo | Fonte Autonomo | Fonte Empresa |
|-------|----------------|---------------|
| cpf_cnpj | `transporte.codcnpjcpf` | `trnmot.codcpf` |
| antt_rntrc | Progress direto | ANTT API |
| antt_nome | `transporte.nomtrn` | `trnmot.nommot` |
| placa | `transporte.numpla` | `trnvei.numpla` |
| veiculo_tipo | ANTT API | ANTT API |
| condutor_nome | `transporte.nomtrn` | `trnmot.nommot` |
| condutor_rg | `transporte.numrg` | `trnmot.numrg` |
| condutor_nome_mae | `transporte.NomMae` | `trnmot.NomMae` |
| endereco_* | `bairro.*` + `municipio.*` | idem |
| contato_* | `transporte.*` | `trnmot.*` |

**Score de Qualidade:** Sistema calcula score 0-100 baseado em campos preenchidos.

#### Etapa 3: Enviar para NDD Cargo
Processado via Job/Queue automaticamente:

```php
// 1. Montar XML de negocio
$xml = VpoXmlBuilder::buildVpoXml($vpoData);

// 2. Assinar digitalmente
$xmlAssinado = $this->digitalSignature->signXml($xml, $uuid);

// 3. Encapsular em SOAP CrossTalk
$envelope = $this->buildCrossTalkEnvelope($xmlAssinado, $uuid);

// 4. Enviar via SOAP
$response = $this->soapClient->send($envelope);
```

**Formato CrossTalk:**
```xml
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <CrossTalk_Header>
      <ProcessCode>Roteirizador</ProcessCode>
      <GUID>{uuid}</GUID>
      <RawData>{xmlAssinado}</RawData>
    </CrossTalk_Header>
    <CrossTalk_Body>
      <VersionAPI>4.2.12.0</VersionAPI>
    </CrossTalk_Body>
  </soap:Body>
</soap:Envelope>
```

#### Etapa 4: Polling Resultado
`GET /api/vpo/emissao/{uuid}`

```json
// Response (processando)
{
  "uuid": "550e8400-...",
  "status": "processing",
  "retry_after": 5
}

// Response (completo)
{
  "uuid": "550e8400-...",
  "status": "completed",
  "data": {
    "vpo_response": { ... },
    "completed_at": "2025-10-27 14:35:00"
  }
}
```

**Estados Possiveis:**
- `pending` - Aguardando processamento
- `processing` - Enviado para NDD Cargo
- `completed` - Sucesso
- `failed` - Erro
- `cancelled` - Cancelado

#### Etapa 5: Finalizar/Cancelar
`POST /api/vpo/emissao/{uuid}/cancelar`

```php
// So pode cancelar se ainda nao foi emitida
if ($emissao->status === 'completed') {
    return ['error' => 'VPO ja emitida, nao pode cancelar'];
}

$emissao->update(['status' => 'cancelled']);
```

### 2.5 Tabelas Utilizadas

| Tabela | Tipo | Funcao |
|--------|------|--------|
| `PUB.transporte` | Progress | Dados transportador |
| `PUB.trnmot` | Progress | Motoristas (empresa) |
| `PUB.trnvei` | Progress | Veiculos (empresa) |
| `vpo_emissao` | Laravel | Registro de emissoes |
| `vpo_transportadores_cache` | Laravel | Cache dados VPO |
| `motorista_empresa_cache` | Laravel | Cache motoristas |

---

## 3. Comparacao Detalhada

### 3.1 Arquitetura

```
SEMPARAR VPO                          NDD CARGO VPO
============                          ==============

Frontend                              Frontend
    |                                     |
    v                                     v
CompraViagemController                VpoEmissaoController
    |                                     |
    v                                     v
SemPararService                       VpoEmissaoService
    |                                     |
    |                                     v
    |                                 VpoDataSyncService
    |                                     |
    v                                     v
SemPararSoapClient                    NddCargoService
    |                                     |
    |                                     v
    |                                 DigitalSignature
    |                                     |
    v                                     v
SOAP ViaFacil                         SOAP CrossTalk NDD
(sincrono)                            (assincrono + RSA)
```

### 3.2 Seguranca

| Aspecto | SemParar | NDD Cargo |
|---------|----------|-----------|
| Autenticacao | Token Session | Certificado Digital |
| Assinatura | Nenhuma | RSA-SHA1 XML |
| Idempotencia | UUID + Lock | UUID + Polling |
| Validacao Eixos | 2x (frontend + SOAP) | Pre-validacao ANTT |
| LGPD | Mascara placa | Score qualidade |
| Auditoria | sPararViagem | XML request/response salvo |

### 3.3 Performance

| Metrica | SemParar | NDD Cargo |
|---------|----------|-----------|
| Tempo Resposta | 2-3 segundos | 10-30 segundos |
| Tipo | Sincrono | Assincrono |
| Retry | Manual | Automatico (polling) |
| Timeout | Falha imediata | Continua processando |

### 3.4 Dados Coletados

| Campo | SemParar | NDD Cargo |
|-------|----------|-----------|
| Placa | Sim | Sim |
| Eixos | Sim | Sim |
| CPF/CNPJ | Nao | Sim |
| ANTT RNTRC | Nao | Sim |
| Nome Condutor | Nao | Sim |
| RG Condutor | Nao | Sim |
| Nome Mae | Nao | Sim |
| Endereco Completo | Nao | Sim |
| Contatos | Nao | Sim |
| **Total Campos** | **3** | **19** |

---

## 4. Quando Usar Cada Sistema

### Use SemParar VPO quando:
- Precisa de resposta imediata (< 5 segundos)
- Compra simples de vale pedagio
- Nao precisa de dados completos do transportador
- Operacao critica que nao pode esperar

### Use NDD Cargo VPO quando:
- Precisa de emissao formal de VPO
- Requer dados completos validados contra ANTT
- Pode tolerar latencia (polling)
- Precisa de auditoria completa (XML salvo)
- Conformidade regulatoria e importante

---

## 5. Configuracao

### SemParar (.env)
```env
SEMPARAR_WSDL_URL=https://app.viafacil.com.br/wsvp/ValePedagio?wsdl
SEMPARAR_CNPJ=2024209702
SEMPARAR_USER=CORPORATIVO
SEMPARAR_PASSWORD=Senha123
SEMPARAR_TIMEOUT=30
ALLOW_SOAP_PURCHASE=true  # false = modo simulacao
```

### NDD Cargo (.env)
```env
NDD_CARGO_ENVIRONMENT=homologacao
NDD_CARGO_CNPJ=17359233000188
NDD_CARGO_TOKEN=2342bbkjkh23423bn2j3n42a
NDD_CARGO_PT_EMISSOR=17359233000188

# Certificado Digital
NDD_CARGO_CERT_TYPE=pfx
NDD_CARGO_CERT_PASSWORD=AP300480

# Configuracoes
NDD_CARGO_VERSAO_LAYOUT=4.2.12.0
NDD_CARGO_TIMEOUT=60
NDD_CARGO_CACHE_ENABLED=true
NDD_CARGO_LOG_XML=true
```

---

## 6. Fluxo Visual

### SemParar - 6 Fases

```
[Iniciar] --> [Validar Pacote] --> [Validar Placa] --> [Selecionar Rota]
                                                              |
                                                              v
                        [COMPRAR] <-- [Verificar Preco] <-----+
                            |
                            v
                    [Salvar Progress]
                            |
                            v
                       [Sucesso]
```

### NDD Cargo - 5 Etapas

```
[Iniciar Emissao] --> [Sync VPO Data] --> [Validar 19 Campos]
                                                   |
                                                   v
                                          [Score >= 80?]
                                           /          \
                                         Sim          Nao
                                          |            |
                                          v            v
                                   [Enviar NDD]   [Retornar Erro]
                                          |
                                          v
                                   [Polling GUID]
                                          |
                                          v
                                   [Processar Resposta]
                                          |
                                          v
                                      [Sucesso]
```

---

## 7. Conclusao

O sistema NDD utiliza **ambos** os metodos de forma complementar:

1. **SemParar VPO** - Para compras urgentes de vale pedagio no dia-a-dia
2. **NDD Cargo VPO** - Para emissao formal com todos os dados regulatorios

Esta abordagem hibrida permite flexibilidade operacional enquanto mantem conformidade com requisitos legais.

---

**Documentacao relacionada:**
- [docs/integracoes/ndd-cargo/INDEX.md](integracoes/ndd-cargo/INDEX.md)
- [docs/semparar-phases/SEMPARAR_IMPLEMENTATION_ROADMAP.md](semparar-phases/SEMPARAR_IMPLEMENTATION_ROADMAP.md)
- [CLAUDE.md](../CLAUDE.md) - Regras de desenvolvimento

---

**Mantido por:** Psykhepathos
