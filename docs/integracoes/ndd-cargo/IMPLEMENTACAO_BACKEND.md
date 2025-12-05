# 🚀 Implementação Backend NDD Cargo - Guia Completo

**Data:** 2025-12-05
**Status:** ✅ Implementação Completa
**Versão:** 1.0.0

---

## 📋 Sumário

1. [Visão Geral](#visão-geral)
2. [Arquitetura Implementada](#arquitetura-implementada)
3. [Arquivos Criados](#arquivos-criados)
4. [Configuração](#configuração)
5. [Testes](#testes)
6. [Endpoints Disponíveis](#endpoints-disponíveis)
7. [Próximos Passos](#próximos-passos)

---

## 🎯 Visão Geral

Implementação completa do backend Laravel para integração com a API NDD Cargo seguindo as melhores práticas do projeto ndd-vuexy.

### Funcionalidades Implementadas

✅ **DTOs (Data Transfer Objects)**
- Validação de dados tipados
- Conversão de/para array e XML
- Tratamento de erros

✅ **Assinatura Digital XML**
- RSA-SHA1 conforme padrão XML Digital Signature
- Suporte para certificados .pfx e .pem
- Canonicalização C14N

✅ **XML Builders**
- Construção de XML de negócio
- Namespace NDD Cargo
- Estrutura conforme documentação

✅ **SOAP Client**
- Protocolo CrossTalk sobre SOAP 1.1
- Encoding UTF-16 (obrigatório)
- CDATA sections para message e rawData

✅ **Service Layer**
- Orquestração de alto nível
- Cache de certificados
- Logging estruturado

✅ **Controller REST**
- Endpoints REST padronizados
- Rate limiting
- Validação de entrada

---

## 🏗️ Arquitetura Implementada

```
┌─────────────────────────────────────────────────────────────┐
│                    NddCargoController                        │
│                   (API REST Endpoints)                       │
│  - POST /api/ndd-cargo/roteirizador                         │
│  - POST /api/ndd-cargo/rota-simples                         │
│  - GET  /api/ndd-cargo/resultado/{guid}                     │
│  - GET  /api/ndd-cargo/test-connection                      │
│  - GET  /api/ndd-cargo/info                                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    NddCargoService                           │
│                 (Business Logic Layer)                       │
│  - consultarRoteirizador()                                   │
│  - consultarResultado()                                      │
│  - consultarRotaSimples()                                    │
│  - testConnection()                                          │
└──────────────┬────────────────┬─────────────────────────────┘
               │                │
               ▼                ▼
┌──────────────────────┐  ┌────────────────────────────────┐
│  DigitalSignature    │  │  NddCargoSoapClient            │
│  - loadFromPfx()     │  │  - consultarRoteirizador()     │
│  - loadFromPem()     │  │  - consultarResultado()        │
│  - signXml()         │  │  - buildCrossTalkMessage()     │
└──────────────────────┘  │  - buildSoapEnvelope()         │
                          │  - sendSoapRequest()           │
               ┌──────────┴────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────┐
│                  RoteirizadorBuilder                         │
│                  (XML Construction)                          │
│  - build()                                                   │
│  - buildSimple()                                             │
└─────────────────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────┐
│                      DTOs                                    │
│  - ConsultarRoteirizadorRequest                             │
│  - PracaPedagioDTO                                           │
│  - RoteirizadorResponse                                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Arquivos Criados

### 1. DTOs (Data Transfer Objects)

#### `app/Services/NddCargo/DTOs/ConsultarRoteirizadorRequest.php`
- Request tipado para consulta de roteirizador
- Validação automática de CNPJs, CEPs, categorias
- Métodos `toArray()` e `fromArray()`

#### `app/Services/NddCargo/DTOs/PracaPedagioDTO.php`
- Representa uma praça de pedágio
- Métodos `fromArray()` e `fromXml()`
- Campos: id, nome, localização, rodovia, concessionária, valor

#### `app/Services/NddCargo/DTOs/RoteirizadorResponse.php`
- Response completo da consulta
- Parse de XML de resposta
- Factory methods: `success()` e `error()`

### 2. Assinatura Digital

#### `app/Services/NddCargo/DigitalSignature.php` (322 linhas)
**Implementação completa de RSA-SHA1 para XML:**
- Carregamento de certificados .pfx e .pem
- Canonicalização C14N
- Estrutura XML Digital Signature
- SignedInfo, SignatureValue, KeyInfo

**Métodos principais:**
```php
loadFromPfx(string $pfxPath, string $password): self
loadFromPem(string $certPath, string $keyPath, ?string $password): self
signXml(string $xmlContent, string $referenceId): string
```

### 3. XML Builders

#### `app/Services/NddCargo/XmlBuilders/RoteirizadorBuilder.php`
**Constrói XML consultarRoteirizador_envio:**
```php
build(ConsultarRoteirizadorRequest $request, ?string $uuid): array
buildSimple(string $cnpjEmpresa, string $cnpjContratante, ...): array
```

**Estrutura XML gerada:**
```xml
<consultarRoteirizador_envio versao="4.2.12.0">
  <infConsultarRoteirizador ID="uuid" versao="4.2.12.0">
    <cnpj>17359233000188</cnpj>
    <consulta>
      <cnpjContratante>17359233000188</cnpjContratante>
      <categoriaPedagio>7</categoriaPedagio>
      <informacoes>
        <tipoRotaPadrao>1</tipoRotaPadrao>
        <pontosParada>...</pontosParada>
        <configuracaoRoteirizador>...</configuracaoRoteirizador>
      </informacoes>
    </consulta>
  </infConsultarRoteirizador>
  <Signature>...</Signature>
</consultarRoteirizador_envio>
```

### 4. SOAP Client

#### `app/Services/NddCargo/NddCargoSoapClient.php` (374 linhas)
**Cliente SOAP de baixo nível:**

**Métodos principais:**
```php
consultarRoteirizador(string $xmlAssinado, string $guid): array
consultarResultado(string $guid): array
```

**Características:**
- Encoding UTF-16 (obrigatório NDD Cargo)
- CrossTalk Message construction
- CDATA encapsulation
- HTTP POST via Laravel Http facade
- Extract SendResult from response

### 5. Service Principal

#### `app/Services/NddCargo/NddCargoService.php` (278 linhas)
**Service de alto nível:**

**Métodos públicos:**
```php
consultarRoteirizador(ConsultarRoteirizadorRequest $request): RoteirizadorResponse
consultarResultado(string $guid): RoteirizadorResponse
consultarRotaSimples(string $cepOrigem, string $cepDestino, int $categoria): RoteirizadorResponse
testConnection(): array
```

**Fluxo completo:**
1. Carregar certificado digital (com cache)
2. Construir XML de negócio
3. Assinar XML digitalmente
4. Enviar via SOAP
5. Processar resposta
6. Retornar DTO tipado

### 6. Controller

#### `app/Http/Controllers/Api/NddCargoController.php` (367 linhas)
**Endpoints REST:**
```php
POST   /api/ndd-cargo/roteirizador         - Consulta completa
POST   /api/ndd-cargo/rota-simples          - Consulta simples (CEPs)
GET    /api/ndd-cargo/resultado/{guid}      - Consulta assíncrona
GET    /api/ndd-cargo/test-connection       - Health check
GET    /api/ndd-cargo/info                  - Informações da API
```

**Rate Limiting:**
- Consultas: 60 req/min
- Testes: 10 req/min
- Info: 120 req/min

### 7. Configuração

#### `config/nddcargo.php` (169 linhas)
**Configurações centralizadas:**
- Endpoints (homologação/produção)
- Credenciais (CNPJ, Token)
- Certificado digital
- Versão da API
- Timeout SOAP
- Cache
- Logging
- Rate limiting

### 8. Rotas

#### `routes/api.php` (modificado)
```php
Route::prefix('ndd-cargo')->group(function () {
    Route::get('info', [NddCargoController::class, 'info'])
        ->middleware('throttle:120,1');
    Route::get('test-connection', [NddCargoController::class, 'testConnection'])
        ->middleware('throttle:10,1');
    Route::post('roteirizador', [NddCargoController::class, 'consultarRoteirizador'])
        ->middleware('throttle:60,1');
    Route::post('rota-simples', [NddCargoController::class, 'consultarRotaSimples'])
        ->middleware('throttle:60,1');
    Route::get('resultado/{guid}', [NddCargoController::class, 'consultarResultado'])
        ->middleware('throttle:60,1');
});
```

---

## ⚙️ Configuração

### 1. Variáveis de Ambiente

Adicionar ao `.env`:

```env
# NDD Cargo API Configuration
NDD_CARGO_ENVIRONMENT=homologacao
NDD_CARGO_CNPJ=17359233000188
NDD_CARGO_TOKEN=2342bbkjkh23423bn2j3n42a

# Certificado Digital
NDD_CARGO_CERT_TYPE=pfx
NDD_CARGO_CERT_PFX_PATH=storage/certificates/nddcargo/cert.pfx
NDD_CARGO_CERT_PASSWORD=AP300480

# Opcional
NDD_CARGO_VERSAO_LAYOUT=4.2.12.0
NDD_CARGO_TIMEOUT=60
NDD_CARGO_CACHE_ENABLED=true
```

### 2. Instalar Certificado

```bash
# Criar pasta de certificados
mkdir -p storage/certificates/nddcargo

# Copiar certificado .pfx
cp /caminho/para/cert.pfx storage/certificates/nddcargo/

# Ou converter .pfx para .pem (opcional)
# Extrair certificado
openssl pkcs12 -in cert.pfx -clcerts -nokeys -out cert_cert.pem

# Extrair chave privada
openssl pkcs12 -in cert.pfx -nocerts -nodes -out cert_key.pem
```

### 3. Configurar Permissões

```bash
# Linux/Mac
chmod 600 storage/certificates/nddcargo/*
chown www-data:www-data storage/certificates/nddcargo/*

# Windows
# Usar propriedades do arquivo > Segurança > Permissões
```

---

## 🧪 Testes

### 1. Teste de Conexão

```bash
curl http://localhost:8002/api/ndd-cargo/test-connection
```

**Resposta esperada (sucesso):**
```json
{
  "success": true,
  "message": "Conexão com NDD Cargo OK",
  "details": {
    "certificado": "Válido",
    "credenciais": "Válidas",
    "endpoint": "https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx",
    "distancia_teste_km": 356.7,
    "quantidade_pracas_teste": 12
  }
}
```

### 2. Consulta Simples (CEPs)

```bash
curl -X POST http://localhost:8002/api/ndd-cargo/rota-simples \
  -H "Content-Type: application/json" \
  -d '{
    "cep_origem": "01310100",
    "cep_destino": "20040020",
    "categoria_pedagio": 7
  }'
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "sucesso": true,
    "status": 0,
    "mensagem": "Rota calculada com sucesso",
    "distancia_km": 356.7,
    "tempo_minutos": 280,
    "valor_total_pedagogios": 45.30,
    "pracas_pedagio": [
      {
        "id": 123,
        "nome": "Praça de Pedágio Teste",
        "localizacao": "Rodovia Presidente Dutra - KM 150",
        "rodovia": "BR-116",
        "concessionaria": "CCR NovaDutra",
        "valor": 15.10,
        "latitude": "-23.5505",
        "longitude": "-46.6333"
      }
    ],
    "quantidade_pracas": 3
  }
}
```

### 3. Consulta Completa

```bash
curl -X POST http://localhost:8002/api/ndd-cargo/roteirizador \
  -H "Content-Type: application/json" \
  -d '{
    "cnpj_empresa": "17359233000188",
    "cnpj_contratante": "17359233000188",
    "categoria_pedagio": 7,
    "pontos_parada": {
      "origem": "01310100",
      "destino": "20040020"
    },
    "tipo_rota_padrao": 1,
    "evitar_pedagogios": false,
    "priorizar_rodovias": false,
    "tipo_rota": 1,
    "tipo_veiculo": 5,
    "retornar_trecho": true
  }'
```

### 4. Info da API

```bash
curl http://localhost:8002/api/ndd-cargo/info
```

### 5. Teste via Browser

Criar arquivo `public/test-ndd-cargo.html`:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste NDD Cargo API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        button { padding: 10px 20px; margin: 10px; cursor: pointer; }
        #result { background: #f5f5f5; padding: 15px; margin-top: 20px; border-radius: 5px; }
        pre { background: #fff; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Teste NDD Cargo API</h1>

    <button onclick="testConnection()">1. Testar Conexão</button>
    <button onclick="consultarRotaSimples()">2. Consultar Rota Simples</button>
    <button onclick="getInfo()">3. Obter Informações</button>

    <div id="result"></div>

    <script>
        const API_BASE = 'http://localhost:8002/api/ndd-cargo';

        async function testConnection() {
            showResult('Testando conexão...');
            try {
                const response = await fetch(`${API_BASE}/test-connection`);
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Erro: ' + error.message);
            }
        }

        async function consultarRotaSimples() {
            showResult('Consultando rota SP → RJ...');
            try {
                const response = await fetch(`${API_BASE}/rota-simples`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cep_origem: '01310100',
                        cep_destino: '20040020',
                        categoria_pedagio: 7
                    })
                });
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Erro: ' + error.message);
            }
        }

        async function getInfo() {
            showResult('Obtendo informações da API...');
            try {
                const response = await fetch(`${API_BASE}/info`);
                const data = await response.json();
                showResult(JSON.stringify(data, null, 2));
            } catch (error) {
                showResult('Erro: ' + error.message);
            }
        }

        function showResult(text) {
            document.getElementById('result').innerHTML = `<pre>${text}</pre>`;
        }
    </script>
</body>
</html>
```

Acessar: `http://localhost:8002/test-ndd-cargo.html`

---

## 📡 Endpoints Disponíveis

### GET /api/ndd-cargo/info

Retorna informações sobre a integração.

**Rate Limit:** 120 req/min

**Response:**
```json
{
  "success": true,
  "data": {
    "name": "NDD Cargo API Integration",
    "version": "1.0.0",
    "environment": "homologacao",
    "endpoint": "https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx",
    "versao_layout": "4.2.12.0",
    "documentation": {...},
    "endpoints": {...}
  }
}
```

### GET /api/ndd-cargo/test-connection

Testa conectividade, certificado e credenciais.

**Rate Limit:** 10 req/min

**Response:**
```json
{
  "success": true,
  "message": "Conexão com NDD Cargo OK",
  "details": {
    "certificado": "Válido",
    "credenciais": "Válidas",
    "endpoint": "https://...",
    "distancia_teste_km": 356.7,
    "quantidade_pracas_teste": 12
  }
}
```

### POST /api/ndd-cargo/rota-simples

Consulta rota simples (apenas CEPs).

**Rate Limit:** 60 req/min

**Body:**
```json
{
  "cep_origem": "01310100",
  "cep_destino": "20040020",
  "categoria_pedagio": 7
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sucesso": true,
    "distancia_km": 356.7,
    "tempo_minutos": 280,
    "valor_total_pedagogios": 45.30,
    "pracas_pedagio": [...],
    "quantidade_pracas": 3
  }
}
```

### POST /api/ndd-cargo/roteirizador

Consulta completa com todas as opções.

**Rate Limit:** 60 req/min

**Body:** Ver seção de Testes

### GET /api/ndd-cargo/resultado/{guid}

Consulta resultado de operação assíncrona.

**Rate Limit:** 60 req/min

**Response:** Mesmo formato da consulta síncrona

---

## 🚀 Próximos Passos

### 1. Frontend Vue

Criar páginas e componentes Vue para:
- [ ] Consulta de rotas
- [ ] Visualização de praças de pedágio
- [ ] Comparação de rotas
- [ ] Histórico de consultas

**Referência:** Seguir padrão de `resources/ts/pages/rotas-padrao/`

### 2. Integração com Sistema Atual

- [ ] Integrar com módulo de pacotes
- [ ] Sugestão automática de rotas
- [ ] Cálculo de custos de viagem
- [ ] Relatórios de pedágios

### 3. Funcionalidades Adicionais

- [ ] Implementar outras operações NDD Cargo:
  - OVP (Ordem de Vale Pedágio)
  - CIOT (Conhecimento de Transporte)
  - Pagamentos
- [ ] Cache inteligente de rotas frequentes
- [ ] Notificações de alterações de preços

### 4. Testes Automatizados

```bash
# Criar testes unitários
php artisan make:test NddCargoServiceTest --unit

# Criar testes de feature
php artisan make:test NddCargoApiTest
```

### 5. Documentação Swagger

Adicionar anotações Swagger nos controllers para documentação automática da API.

---

## 📚 Referências

- [README.md](./README.md) - Visão geral da integração
- [INDEX.md](./INDEX.md) - Índice completo da documentação
- [ANALISE_NTESTE_PY.md](./ANALISE_NTESTE_PY.md) - Análise detalhada do Python
- [ANALISE_RESULTADO_PY.md](./ANALISE_RESULTADO_PY.md) - Análise do script de consulta

**Manuais NDD:**
- http://manuais.nddigital.com.br/nddCargo/

---

## ✅ Checklist de Implementação

### Backend (Completo!)

- [x] DTOs para requests e responses
- [x] Assinatura digital RSA-SHA1
- [x] XML Builders
- [x] SOAP Client (CrossTalk)
- [x] Service de alto nível
- [x] Controller REST
- [x] Configuração centralizada
- [x] Rotas API
- [x] Variáveis de ambiente
- [x] Documentação

### Próximos (Pendentes)

- [ ] Frontend Vue
- [ ] Testes automatizados
- [ ] Documentação Swagger
- [ ] Deploy em homologação
- [ ] Testes integrados com certificado real
- [ ] Implementação de outras operações (OVP, CIOT)

---

**Implementado por:** Claude Code
**Data:** 2025-12-05
**Versão:** 1.0.0
