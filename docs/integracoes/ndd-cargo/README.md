# 🚛 Integração NDD Cargo - Documentação Completa

**Data de Análise:** 2025-12-05
**Fonte:** Projeto `C:\Users\15857\Desktop\testeNDd`
**Versão API:** 4.2.12.0
**Ambiente:** Homologação NDD Cargo

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura da Integração](#arquitetura-da-integração)
3. [Arquivos do Projeto](#arquivos-do-projeto)
4. [Documentação Detalhada](#documentação-detalhada)
5. [Fluxos de Integração](#fluxos-de-integração)
6. [Implementação no ndd-vuexy](#implementação-no-ndd-vuexy)

---

## 🎯 Visão Geral

Esta documentação descreve a integração com a **API NDD Cargo** para:
- **Consulta de Roteirizador**: Calcular rotas otimizadas entre pontos com praças de pedágio
- **Vale Pedágio (OVP)**: Operações de vale pedágio eletrônico
- **CIOT**: Conhecimento de Transporte Obrigatório
- **Pagamentos**: Gestão de pagamentos de pedágio

### Protocolo CrossTalk

A NDD Cargo utiliza um protocolo proprietário chamado **CrossTalk** sobre SOAP 1.1:
- **Envelope SOAP**: Estrutura padrão SOAP com namespaces específicos
- **CrossTalk_Header**: Metadados da operação (ProcessCode, GUID, Token, etc.)
- **CrossTalk_Body**: Versionamento da API
- **rawData (CDATA)**: XML de negócio assinado digitalmente

---

## 🏗️ Arquitetura da Integração

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Python/PHP)                      │
│  - Carrega certificado digital (.pfx)                        │
│  - Cria XML de negócio                                       │
│  - Assina XML com RSA-SHA1                                   │
│  - Encapsula em CrossTalk Message                            │
│  - Envia via SOAP                                            │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ HTTPS POST
                         │ Content-Type: text/xml; charset=utf-16
                         │ SOAPAction: http://tempuri.org/Send
                         ▼
┌─────────────────────────────────────────────────────────────┐
│         API NDD CARGO (SOAP Web Service)                     │
│  Endpoint: homologa.nddcargo.com.br/wsagente/               │
│           ExchangeMessage.asmx                               │
│                                                               │
│  Operações:                                                   │
│  - Send                     (envio normal)                   │
│  - CompressedSend           (envio comprimido)               │
│  - SendWithCompressedResponse                                │
│  - CompressedSendWithCompressedResponse                      │
│  - Ativo                    (health check)                   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ SOAP Response (XML)
                         │ SendResult em CDATA
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    RESPOSTA PROCESSADA                       │
│  - CrossTalk_Message com resultado                           │
│  - Status da operação                                        │
│  - Dados de rota/pedagio/CIOT                                │
│  - Mensagens de erro (se houver)                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Arquivos do Projeto

### Estrutura do Projeto `testeNDd`

```
C:\Users\15857\Desktop\testeNDd\
├── Cargo Projeto Doug-soapui-project.xml    # SOAP UI Project completo
├── cert.pfx                                  # Certificado digital A1
├── cert_cert.pem                             # Certificado público exportado
├── cert_key.pem                              # Chave privada exportada
├── nteste.py                                 # Script de ENVIO (consulta roteirizador)
├── resultado.py                              # Script de CONSULTA (resultado assíncrono)
├── test_api.html                             # Teste básico HTML (não NDD)
├── envio_soap_final_*.xml                    # XMLs de envio gerados
└── consulta_resultado_*.xml                  # XMLs de consulta gerados
```

### Descrição dos Arquivos

| Arquivo | Tipo | Descrição |
|---------|------|-----------|
| `nteste.py` | **Script Principal** | Implementa fluxo completo: carrega certificado, cria XML, assina digitalmente, envia SOAP |
| `resultado.py` | **Script Consulta** | Consulta resultado de operação assíncrona usando GUID + ExchangePattern 8 |
| `Cargo Projeto Doug-soapui-project.xml` | **SOAP UI** | Projeto completo com TODAS operações NDD Cargo (OVP, CIOT, Pagamentos, Roteirizador) |
| `cert.pfx` | **Certificado** | Certificado digital A1 para assinatura XML (senha: AP300480) |
| `envio_soap_final_*.xml` | **XML Exemplo** | Exemplos reais de requisições SOAP enviadas |
| `consulta_resultado_*.xml` | **XML Exemplo** | Exemplos reais de consultas de resultado |

---

## 📚 Documentação Detalhada

### Documentos Disponíveis

1. **[ANALISE_NTESTE_PY.md](./ANALISE_NTESTE_PY.md)** - Análise linha a linha do script de envio
2. **[ANALISE_RESULTADO_PY.md](./ANALISE_RESULTADO_PY.md)** - Análise linha a linha do script de consulta
3. **[ESTRUTURA_XML_ENVIO.md](./ESTRUTURA_XML_ENVIO.md)** - Estrutura completa do XML de envio
4. **[ESTRUTURA_XML_CONSULTA.md](./ESTRUTURA_XML_CONSULTA.md)** - Estrutura completa do XML de consulta
5. **[ASSINATURA_DIGITAL.md](./ASSINATURA_DIGITAL.md)** - Processo de assinatura XML com RSA-SHA1
6. **[PROTOCOLO_CROSSTALK.md](./PROTOCOLO_CROSSTALK.md)** - Especificação do protocolo CrossTalk
7. **[PROCESS_CODES.md](./PROCESS_CODES.md)** - Códigos de processo e operações disponíveis
8. **[IMPLEMENTACAO_PHP_LARAVEL.md](./IMPLEMENTACAO_PHP_LARAVEL.md)** - Guia de implementação no ndd-vuexy

---

## 🔄 Fluxos de Integração

### Fluxo 1: Consulta de Roteirizador (Síncrono)

```
1. Cliente Python (nteste.py)
   ├─ Carrega certificado .pfx
   ├─ Gera UUID único para transação
   ├─ Cria XML consultarRoteirizador_envio
   │  ├─ infConsultarRoteirizador (ID = UUID)
   │  ├─ cnpj da empresa
   │  ├─ consulta
   │  │  ├─ cnpjContratante
   │  │  ├─ categoriaPedagio (7 = caminhão pesado)
   │  │  └─ informacoes
   │  │     ├─ tipoRotaPadrao (1 = menor custo)
   │  │     ├─ pontosParada (CEPs origem/destino)
   │  │     └─ configuracaoRoteirizador
   │  │        ├─ evitarPedagios (0/1)
   │  │        ├─ priorizarRodovias (0/1)
   │  │        ├─ tipoRota (1/2/3)
   │  │        ├─ tipoVeiculo (1-10)
   │  │        └─ retornarTrecho (0/1)
   │  └─ Signature (RSA-SHA1)
   ├─ Assina XML digitalmente
   ├─ Cria CrossTalk_Message
   │  ├─ ProcessCode: 2027 (Consultar Roteirizador)
   │  ├─ MessageType: 100 (Request)
   │  ├─ ExchangePattern: 7 (Síncrono)
   │  ├─ GUID: UUID da transação
   │  ├─ DateTime: ISO8601 com timezone BR
   │  ├─ EnterpriseId: CNPJ
   │  └─ Token: Token de autenticação
   ├─ Encapsula em SOAP Envelope
   │  ├─ Header (vazio)
   │  └─ Body > Send
   │     ├─ message (CDATA): CrossTalk_Message
   │     └─ rawData (CDATA): XML assinado
   └─ Envia POST para endpoint NDD

2. API NDD Cargo
   ├─ Valida assinatura digital
   ├─ Valida token de autenticação
   ├─ Processa consulta de roteamento
   ├─ Calcula rota otimizada
   ├─ Identifica praças de pedágio
   └─ Retorna resultado em SendResult

3. Resposta Processada
   ├─ CrossTalk_Message de retorno
   ├─ Status: 0 (sucesso) ou código de erro
   ├─ Dados da rota
   │  ├─ Distância total (km)
   │  ├─ Tempo estimado
   │  ├─ Lista de praças de pedágio
   │  │  ├─ ID da praça
   │  │  ├─ Nome/localização
   │  │  ├─ Rodovia
   │  │  ├─ Concessionária
   │  │  └─ Valor do pedágio
   │  └─ Trechos da rota (se solicitado)
   └─ Salva resultado localmente
```

### Fluxo 2: Consulta de Resultado (Assíncrono)

```
1. Cliente Python (resultado.py)
   ├─ Define GUID da transação original
   ├─ Cria CrossTalk_Message
   │  ├─ ProcessCode: 2027 (mesmo da operação original)
   │  ├─ MessageType: 100
   │  ├─ ExchangePattern: 8 (Consulta Assíncrona)
   │  ├─ GUID: UUID da transação ORIGINAL
   │  ├─ DateTime: Timestamp atual
   │  ├─ EnterpriseId: CNPJ
   │  └─ Token: Token de autenticação
   ├─ Encapsula em SOAP Envelope
   │  ├─ message: CrossTalk_Message
   │  └─ rawData: "" (VAZIO para consulta)
   └─ Envia POST para endpoint NDD

2. API NDD Cargo
   ├─ Busca resultado armazenado pelo GUID
   ├─ Retorna dados processados
   └─ Status da operação

3. Resposta Processada
   ├─ SendResult com dados completos
   └─ Mesmo formato da resposta síncrona
```

---

## 🔐 Credenciais e Configuração

### Ambiente de Homologação

```python
# URLs
NDD_WSDL_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx?wsdl'
NDD_ENDPOINT_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx'

# Autenticação
CNPJ_EMPRESA = '17359233000188'
NDD_TOKEN = '2342bbkjkh23423bn2j3n42a'

# Certificado
Pfx_File_Path = 'cert.pfx'
Pfx_Password = 'AP300480'

# API
VERSAO_LAYOUT = "4.2.12.0"
SOAP_ACTION = 'http://tempuri.org/Send'
```

### Ambiente de Produção

```python
# URLs (Produção)
NDD_ENDPOINT_URL = 'https://nddintegra-dtp-nddcargo.ndd.tech/WSNDDConnect.asmx'
# OU
NDD_ENDPOINT_URL = 'http://wsagent.nddcargo.com.br/wsagente/exchangemessage.asmx'

# Token e CNPJ: Fornecidos pela NDD Cargo após contratação
# Certificado: Certificado digital A1 da empresa (ICP-Brasil)
```

---

## 🚀 Implementação no ndd-vuexy

### Próximos Passos

1. **Criar Service NDD Cargo** em `app/Services/NddCargo/`
   - `NddCargoService.php` - Lógica de negócio
   - `NddCargoSoapClient.php` - Cliente SOAP low-level
   - `XmlBuilders/RoteirizadorBuilder.php` - Construtor de XML
   - `DigitalSignature.php` - Assinatura digital XML

2. **Criar Controller** em `app/Http/Controllers/Api/`
   - `NddCargoController.php` - Endpoints REST

3. **Configuração**
   - Adicionar credenciais em `.env`
   - Criar `config/nddcargo.php`
   - Instalar certificado digital

4. **Frontend Vue**
   - Página de consulta de rotas
   - Visualização de praças de pedagio
   - Comparação de valores

### Ver Mais

Consulte [IMPLEMENTACAO_PHP_LARAVEL.md](./IMPLEMENTACAO_PHP_LARAVEL.md) para guia completo de implementação.

---

## 📖 Referências

- **Documentação Oficial NDD Cargo**: http://manuais.nddigital.com.br/nddCargo/
- **SOAP UI Project**: `Cargo Projeto Doug-soapui-project.xml`
- **Scripts Python**: `nteste.py` e `resultado.py`
- **Exemplos XML**: `envio_soap_final_*.xml` e `consulta_resultado_*.xml`

---

**Documentação gerada por:** Claude Code
**Data:** 2025-12-05
**Versão:** 1.0.0
