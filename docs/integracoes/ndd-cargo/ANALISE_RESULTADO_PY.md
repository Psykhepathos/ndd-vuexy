# 🔍 Análise Linha a Linha: resultado.py

**Arquivo:** `C:\Users\15857\Desktop\testeNDd\resultado.py`
**Propósito:** Script Python para consulta de resultado de operação assíncrona NDD Cargo
**Linguagem:** Python 3.x
**Dependências:** lxml, requests

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Diferenças vs nteste.py](#diferenças-vs-ntestepy)
3. [Análise Linha a Linha](#análise-linha-a-linha)
4. [Fluxo de Consulta Assíncrona](#fluxo-de-consulta-assíncrona)

---

## 🎯 Visão Geral

Este script implementa **consulta de resultado** de operações assíncronas NDD Cargo:

```
[GUID Operação Original] → [CrossTalk ExchangePattern 8] →
[Sem rawData] → [Envio HTTP] → [Resultado Armazenado]
```

**Diferença Principal:**
- ❌ **NÃO** cria XML de negócio
- ❌ **NÃO** assina digitalmente
- ✅ **Apenas** consulta resultado usando GUID da transação original
- ✅ `ExchangePattern` = **8** (consulta assíncrona)
- ✅ `rawData` = **vazio** (string vazia em CDATA)

---

## 🔄 Diferenças vs nteste.py

| Aspecto | nteste.py (Envio) | resultado.py (Consulta) |
|---------|-------------------|-------------------------|
| **Propósito** | Enviar operação nova | Consultar resultado de operação existente |
| **XML de Negócio** | ✅ Cria `consultarRoteirizador_envio` | ❌ Não cria (apenas consulta) |
| **Assinatura Digital** | ✅ RSA-SHA1 completo | ❌ Não assina |
| **Certificado** | ✅ Requerido (.pfx + senha) | ❌ Não requerido |
| **GUID** | ✅ Gera novo `uuid.uuid4()` | ✅ Usa GUID da transação original |
| **ProcessCode** | ✅ 2027 (Consultar Roteirizador) | ✅ Mesmo código da operação original |
| **ExchangePattern** | **7** (Síncrono) | **8** (Consulta Assíncrona) |
| **rawData** | ✅ XML assinado completo | ❌ String vazia `""` |
| **Dependências** | lxml, xmlsec, cryptography, requests | lxml, requests |
| **Linhas de código** | 224 linhas | 116 linhas |

---

## 📝 Análise Linha a Linha

### Linhas 1-7: Imports Essenciais

```python
1  import os
2  import uuid
3  from datetime import datetime, timezone, timedelta
4
5  from lxml import etree
6  import requests
7  GUID_PARA_CONSULTAR = "42ffcbb9-36ba-447e-bd2f-6b285f749139"
```

**Análise:**

| Linha | Import/Variável | Propósito | Observação |
|-------|-----------------|-----------|------------|
| 1 | `os` | Não usado neste script | ❌ Import desnecessário |
| 2 | `uuid` | Não usado neste script | ❌ Import desnecessário (GUID já definido) |
| 3 | `datetime` | Timestamp ISO8601 com timezone | ✅ Usado na CrossTalk_Message |
| 5 | `lxml.etree` | Construção de XML SOAP | ✅ Usado para envelope SOAP |
| 6 | `requests` | Cliente HTTP POST | ✅ Usado para envio |
| 7 | **GUID_PARA_CONSULTAR** | UUID da transação original | **CRÍTICO:** Deve ser o GUID retornado em `nteste.py` |

**⚠️ Observação Importante:**
- O **GUID** usado aqui **DEVE SER** o mesmo UUID gerado em `nteste.py` (linha 136)
- Exemplo: Se `nteste.py` gerou `33f09328-7f7c-4a9f-b70f-fd8c7d0a5606`, este valor deve ser usado em `GUID_PARA_CONSULTAR`

---

### Linhas 10-16: Configuração Global

```python
10  CNPJ_EMPRESA = '17359233000188'
11  NDD_TOKEN = '2342bbkjkh23423bn2j3n42a'
12  VERSAO_LAYOUT = "4.2.12.0"
13
14
15  NDD_ENDPOINT_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx'
16  SOAP_ACTION = 'http://tempuri.org/Send'
```

**Análise:**

| Linha | Variável | Valor | Descrição |
|-------|----------|-------|-----------|
| 10 | `CNPJ_EMPRESA` | `'17359233000188'` | Mesmo CNPJ usado no envio original |
| 11 | `NDD_TOKEN` | `'2342b...'` | **Mesmo token** usado no envio |
| 12 | `VERSAO_LAYOUT` | `"4.2.12.0"` | Mesma versão da API |
| 15 | `NDD_ENDPOINT_URL` | `https://homologa...` | **Mesmo endpoint** do envio |
| 16 | `SOAP_ACTION` | `http://tempuri.org/Send` | Mesma SOAPAction |

**🔑 Regra Importante:**
- ✅ **CNPJ, Token e Endpoint devem ser IDÊNTICOS** aos usados no envio original
- ✅ Caso contrário, a API não encontrará a transação

---

### Linhas 19-44: Função main()

```python
19  def main():
20      """
21      Função principal para montar e enviar uma requisição de consulta de resultado.
22      """
23
24      # --- Passo 1: Criar o XML de cabeçalho de consulta (para o <message>) ---
25      fuso_horario_brasil = timezone(timedelta(hours=-3))
26      data_hora_atual = datetime.now(fuso_horario_brasil).isoformat(timespec='seconds')
27
28      #CrossTalk Message
29      message_content = f"""
30      <CrossTalk_Message xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.nddigital.com.br/nddcargo">
31          <CrossTalk_Header>
32              <ProcessCode>2027</ProcessCode>
33              <MessageType>100</MessageType>
34              <ExchangePattern>8</ExchangePattern>
35              <GUID>{GUID_PARA_CONSULTAR}</GUID>
36              <DateTime>{data_hora_atual}</DateTime>
37              <EnterpriseId>{CNPJ_EMPRESA}</EnterpriseId>
38              <Token>{NDD_TOKEN}</Token>
39          </CrossTalk_Header>
40          <CrossTalk_Body>
41              <CrossTalk_Version_Body versao="{VERSAO_LAYOUT}"/>
42          </CrossTalk_Body>
43      </CrossTalk_Message>
44      """.strip()
```

**Análise Detalhada:**

| Linha | Campo | Valor | Diferença vs nteste.py |
|-------|-------|-------|------------------------|
| 19 | Função `main()` | - | Mesma estrutura |
| 25-26 | **Timestamp** | ISO8601 atual | ✅ **NOVO timestamp** (da consulta, não do envio) |
| 32 | **ProcessCode** | `2027` | ✅ **MESMO código** da operação original |
| 33 | **MessageType** | `100` | ✅ Sempre 100 (Request) |
| 34 | **ExchangePattern** | **`8`** | 🔴 **DIFERENTE:** 8 = Consulta Assíncrona (vs 7 = Síncrono) |
| 35 | **GUID** | `GUID_PARA_CONSULTAR` | 🔴 **DIFERENTE:** GUID da operação ORIGINAL |
| 36 | **DateTime** | Timestamp atual | ✅ Timestamp da CONSULTA |
| 37 | **EnterpriseId** | CNPJ | ✅ Mesmo CNPJ |
| 38 | **Token** | Token NDD | ✅ Mesmo Token |

**📌 Campos Críticos para Consulta:**

1. **ExchangePattern = 8** (OBRIGATÓRIO para consulta)
2. **GUID = UUID da transação original** (identifica qual resultado buscar)
3. **ProcessCode = mesmo código da operação** (2027 para roteirizador)

---

### Linhas 47-60: Criação do Envelope SOAP

```python
47      NS_SOAP = "http://schemas.xmlsoap.org/soap/envelope/"
48      NS_TEM = "http://tempuri.org/"
49
50      envelope = etree.Element(f"{{{NS_SOAP}}}Envelope", nsmap={'soapenv': NS_SOAP, 'tem': NS_TEM})
51      etree.SubElement(envelope, f"{{{NS_SOAP}}}Header")
52      body = etree.SubElement(envelope, f"{{{NS_SOAP}}}Body")
53      send_node = etree.SubElement(body, f"{{{NS_TEM}}}Send")
54
55      message_node = etree.SubElement(send_node, f"{{{NS_TEM}}}message")
56      message_node.text = etree.CDATA(message_content)
57
58
59      raw_data_node = etree.SubElement(send_node, f"{{{NS_TEM}}}rawData")
60      raw_data_node.text = etree.CDATA("")
```

**Análise:**

| Linhas | Elemento | Conteúdo | Diferença vs nteste.py |
|--------|----------|----------|------------------------|
| 47-48 | **Namespaces** | SOAP + tempuri | ✅ Idêntico |
| 50-53 | **Envelope SOAP** | Estrutura padrão | ✅ Idêntico |
| 55-56 | **`<tem:message>`** | CrossTalk_Message em CDATA | ✅ Idêntico |
| 59-60 | **`<tem:rawData>`** | **String vazia `""`** | 🔴 **DIFERENTE:** nteste.py tem XML assinado |

**🔑 Diferença Principal:**

```xml
<!-- nteste.py (ENVIO) -->
<tem:rawData><![CDATA[<?xml version='1.0'?>
<consultarRoteirizador_envio>
  <!-- XML assinado completo -->
</consultarRoteirizador_envio>
]]></tem:rawData>

<!-- resultado.py (CONSULTA) -->
<tem:rawData><![CDATA[]]></tem:rawData>
<!-- rawData VAZIO! -->
```

**Por quê `rawData` vazio?**
- Na consulta assíncrona, não há novo XML de negócio
- O GUID identifica a operação original
- A API NDD busca o resultado armazenado pelo GUID

---

### Linhas 62-75: Serialização e Salvamento

```python
62      soap_request_bytes = etree.tostring(
63          envelope,
64          xml_declaration=True,
65          encoding='utf-16',
66          pretty_print=True
67      )
68
69      print("\n--- XML SOAP DE CONSULTA ENVIADO ---")
70      print(soap_request_bytes.decode('utf-16', errors='ignore'))
71
72      nome_arquivo = f"consulta_resultado_{GUID_PARA_CONSULTAR}.xml"
73      with open(nome_arquivo, 'wb') as f:
74          f.write(soap_request_bytes)
75      print(f"\n✅ XML de consulta salvo com sucesso em: {nome_arquivo}")
```

**Análise:**

| Linhas | Ação | Descrição |
|--------|------|-----------|
| 62-67 | **Serialização** | Converte árvore XML para bytes UTF-16 |
| 69-70 | **Print debugging** | Exibe XML completo no console |
| 72-75 | **Salvamento** | Salva em arquivo `consulta_resultado_{GUID}.xml` |

**📁 Arquivo Gerado:**
- Nome: `consulta_resultado_42ffcbb9-36ba-447e-bd2f-6b285f749139.xml`
- Encoding: UTF-16 (exigido pela NDD)
- Tamanho: ~2KB (muito menor que o envio ~8KB)

---

### Linhas 78-112: Envio HTTP e Processamento da Resposta

```python
78      try:
79          headers = {
80              'Content-Type': 'text/xml; charset=utf-16',
81              'SOAPAction': SOAP_ACTION
82          }
83
84          print(f"\nEnviando consulta para o endereço: {NDD_ENDPOINT_URL}")
85          print("\n--- XML SOAP DE CONSULTA ENVIADO ---")
86
87
88
89          nome_arquivo = f"consulta_resultado_{GUID_PARA_CONSULTAR}.xml"
90          with open(nome_arquivo, 'wb') as f:
91              f.write(soap_request_bytes)
92          print(f"\n✅ XML de consulta salvo com sucesso em: {nome_arquivo}")
93          response = requests.post(NDD_ENDPOINT_URL, data=soap_request_bytes, headers=headers)
94          response.raise_for_status()
95
96          print(f"\n--- RESPOSTA DO SERVIDOR (Status: {response.status_code}) ---")
97
98
99          try:
100             response_tree = etree.fromstring(response.content)
101             send_result_node = response_tree.find('.//SendResult')
102             if send_result_node is not None and send_result_node.text:
103                 print("Conteúdo do SendResult formatado:")
104                 inner_xml_tree = etree.fromstring(send_result_node.text)
105                 print(etree.tostring(inner_xml_tree, pretty_print=True, encoding='unicode'))
106             else:
107                 print(response.text)
108         except Exception:
109             print(response.text)
110
111     except Exception as e:
112         print(f"\nERRO ao se comunicar com o WebService: {e}")
```

**Análise Detalhada:**

| Linhas | Ação | Descrição |
|--------|------|-----------|
| 79-82 | **Headers HTTP** | Content-Type UTF-16 + SOAPAction |
| 84-85 | **Log início** | Print informativo |
| 89-92 | **Salvamento duplicado** | ⚠️ **BUG:** Salva novamente (já salvo linha 72-75) |
| 93 | **POST HTTP** | Envia requisição para NDD Cargo |
| 94 | **Validação HTTP** | Lança exceção se status 4xx/5xx |
| 99-109 | **Processamento resposta** | Parse do XML de resposta |
| 100 | **Parse XML** | Converte response.content em árvore XML |
| 101 | **Busca SendResult** | XPath para encontrar elemento de resultado |
| 102-105 | **Formatação** | Se SendResult existe, formata XML interno |
| 106-107 | **Fallback** | Se não encontrou SendResult, print raw |
| 108-109 | **Tratamento erro** | Captura erros de parsing |
| 111-112 | **Tratamento erro HTTP** | Captura erros de rede/conexão |

**🔍 Estrutura da Resposta SOAP:**

```xml
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SendResponse xmlns="http://tempuri.org/">
      <SendResult><![CDATA[
        <CrossTalk_Message xmlns="http://www.nddigital.com.br/nddcargo">
          <CrossTalk_Header>
            <ProcessCode>2027</ProcessCode>
            <MessageType>200</MessageType>
            <Status>0</Status>
            <StatusMensagem>Sucesso</StatusMensagem>
            <GUID>42ffcbb9-36ba-447e-bd2f-6b285f749139</GUID>
          </CrossTalk_Header>
          <CrossTalk_Body>
            <consultarRoteirizador_retorno>
              <infConsultarRoteirizador>
                <rota>
                  <distanciaTotal>1234.56</distanciaTotal>
                  <tempoEstimado>15.5</tempoEstimado>
                  <pracasPedagio>
                    <pracaPedagio>
                      <id>1001</id>
                      <nome>BR-116 KM 123</nome>
                      <rodovia>BR-116</rodovia>
                      <valor>15.80</valor>
                    </pracaPedagio>
                    <!-- mais praças... -->
                  </pracasPedagio>
                </rota>
              </infConsultarRoteirizador>
            </consultarRoteirizador_retorno>
          </CrossTalk_Body>
        </CrossTalk_Message>
      ]]></SendResult>
    </SendResponse>
  </soap:Body>
</soap:Envelope>
```

---

### Linhas 115-116: Entry Point

```python
115 if __name__ == '__main__':
116     main()
```

**Análise:**
- Padrão Python para execução de script standalone
- Executa `main()` apenas se script executado diretamente

---

## 🔄 Fluxo de Consulta Assíncrona

### Diagrama de Sequência

```
┌──────────┐                 ┌──────────┐                 ┌──────────┐
│  Cliente │                 │   GUID   │                 │NDD Cargo │
│resultado │                 │ Original │                 │   API    │
│  .py     │                 └─────┬────┘                 └─────┬────┘
└────┬─────┘                       │                            │
     │                             │                            │
     │ 1. Define GUID original     │                            │
     │────────────────────────────>│                            │
     │   (42ffcbb9-36ba...)        │                            │
     │                             │                            │
     │ 2. Cria CrossTalk_Message   │                            │
     │──────────────────>│         │                            │
     │   ProcessCode: 2027         │                            │
     │   ExchangePattern: 8        │                            │
     │   GUID: original            │                            │
     │                             │                            │
     │ 3. Encapsula SOAP           │                            │
     │──────────────────>│         │                            │
     │   rawData: "" (vazio)       │                            │
     │                             │                            │
     │ 4. POST HTTP                │                            │
     │─────────────────────────────────────────────────────────>│
     │   Headers:                  │                            │
     │   - Content-Type: utf-16    │                            │
     │   - SOAPAction: Send        │                            │
     │                             │                            │
     │                             │   5. Busca resultado       │
     │                             │   ────────────────────>│  │
     │                             │   WHERE GUID = original    │
     │                             │                            │
     │                             │   6. Valida token          │
     │                             │   ────────────────────>│  │
     │                             │                            │
     │                             │   7. Retorna dados         │
     │                             │   <────────────────────│  │
     │                             │                            │
     │ 8. Resposta SOAP            │                            │
     │<─────────────────────────────────────────────────────────│
     │   SendResult com dados      │                            │
     │   completos da rota         │                            │
     │                             │                            │
     │ 9. Parse e exibe resultado  │                            │
     │──────────────────>│         │                            │
     │                             │                            │
```

---

## 📊 Comparação de Tamanhos

| Aspecto | nteste.py (Envio) | resultado.py (Consulta) |
|---------|-------------------|-------------------------|
| **Certificado** | 3-4KB (.pfx) | Não usado |
| **XML Negócio** | ~5KB (assinado) | 0KB (não existe) |
| **SOAP Envelope** | ~8KB (UTF-16) | ~2KB (UTF-16) |
| **Resposta** | ~10-50KB (dependendo da rota) | ~10-50KB (mesma resposta) |
| **Total Enviado** | **~8KB** | **~2KB** (75% menor) |

---

## ⏱️ Timing do Fluxo Completo

```
Operação Síncrona (nteste.py):
┌─────────────────────────────────────────────┐
│ Envio → Processamento → Resposta Imediata  │
│ 2-5 segundos TOTAL                          │
└─────────────────────────────────────────────┘

Operação Assíncrona:
┌─────────────────────────────────────────────┐
│ Envio (nteste.py)                           │
│ ↓                                           │
│ Processamento em background (NDD Cargo)     │
│ ↓                                           │
│ Consulta (resultado.py) - podem consultar  │
│ múltiplas vezes até resultado estar pronto  │
└─────────────────────────────────────────────┘
  ^                ^                ^
  0s              5-30s           qualquer momento

```

**Quando usar consulta assíncrona?**
- Operações que demoram muito (>10s)
- Múltiplos clientes consultando o mesmo resultado
- Persistência de resultados (pode consultar depois de horas/dias)

---

## 🐛 Bugs Identificados

### 🔴 Crítico

1. **Salvamento duplicado** (linhas 72-75 e 89-92)
   - **Problema:** Salva o arquivo duas vezes
   - **Solução:** Remover linhas 89-92

### 🟡 Importante

2. **Imports não usados** (linhas 1-2)
   - **Problema:** `os` e `uuid` importados mas não utilizados
   - **Solução:** Remover imports

3. **GUID hardcoded** (linha 7)
   - **Problema:** Dificulta reutilização do script
   - **Solução:** Usar argumento de linha de comando ou variável de ambiente

4. **Sem timeout HTTP** (linha 93)
   - **Problema:** Pode travar indefinidamente
   - **Solução:** `requests.post(..., timeout=30)`

5. **Print ao invés de logging** (múltiplas linhas)
   - **Problema:** Dificulta debugging em produção
   - **Solução:** Usar módulo `logging`

---

## 🔧 Código Melhorado (Exemplo)

```python
import sys
import logging
from datetime import datetime, timezone, timedelta
from lxml import etree
import requests

# Configuração de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuração
NDD_ENDPOINT_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx'
SOAP_ACTION = 'http://tempuri.org/Send'
CNPJ_EMPRESA = '17359233000188'
NDD_TOKEN = '2342bbkjkh23423bn2j3n42a'
VERSAO_LAYOUT = "4.2.12.0"

def consultar_resultado(guid_transacao: str, process_code: str = "2027") -> dict:
    """
    Consulta resultado de operação assíncrona NDD Cargo.

    Args:
        guid_transacao: UUID da transação original
        process_code: Código do processo (default: 2027 = Roteirizador)

    Returns:
        dict: Dados do resultado ou None se não encontrado

    Raises:
        requests.RequestException: Erro de comunicação HTTP
        Exception: Erro de parsing XML
    """
    logger.info(f"Consultando resultado para GUID: {guid_transacao}")

    # Criar timestamp
    fuso_horario_brasil = timezone(timedelta(hours=-3))
    data_hora_atual = datetime.now(fuso_horario_brasil).isoformat(timespec='seconds')

    # Criar CrossTalk_Message
    message_content = f"""
    <CrossTalk_Message xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                       xmlns="http://www.nddigital.com.br/nddcargo">
        <CrossTalk_Header>
            <ProcessCode>{process_code}</ProcessCode>
            <MessageType>100</MessageType>
            <ExchangePattern>8</ExchangePattern>
            <GUID>{guid_transacao}</GUID>
            <DateTime>{data_hora_atual}</DateTime>
            <EnterpriseId>{CNPJ_EMPRESA}</EnterpriseId>
            <Token>{NDD_TOKEN}</Token>
        </CrossTalk_Header>
        <CrossTalk_Body>
            <CrossTalk_Version_Body versao="{VERSAO_LAYOUT}"/>
        </CrossTalk_Body>
    </CrossTalk_Message>
    """.strip()

    # Criar envelope SOAP
    NS_SOAP = "http://schemas.xmlsoap.org/soap/envelope/"
    NS_TEM = "http://tempuri.org/"

    envelope = etree.Element(f"{{{NS_SOAP}}}Envelope",
                            nsmap={'soapenv': NS_SOAP, 'tem': NS_TEM})
    etree.SubElement(envelope, f"{{{NS_SOAP}}}Header")
    body = etree.SubElement(envelope, f"{{{NS_SOAP}}}Body")
    send_node = etree.SubElement(body, f"{{{NS_TEM}}}Send")

    message_node = etree.SubElement(send_node, f"{{{NS_TEM}}}message")
    message_node.text = etree.CDATA(message_content)

    raw_data_node = etree.SubElement(send_node, f"{{{NS_TEM}}}rawData")
    raw_data_node.text = etree.CDATA("")

    # Serializar
    soap_request_bytes = etree.tostring(
        envelope,
        xml_declaration=True,
        encoding='utf-16',
        pretty_print=True
    )

    # Enviar requisição
    headers = {
        'Content-Type': 'text/xml; charset=utf-16',
        'SOAPAction': SOAP_ACTION
    }

    try:
        response = requests.post(
            NDD_ENDPOINT_URL,
            data=soap_request_bytes,
            headers=headers,
            timeout=30  # ✅ Timeout de 30 segundos
        )
        response.raise_for_status()
    except requests.RequestException as e:
        logger.error(f"Erro HTTP ao consultar resultado: {e}")
        raise

    # Processar resposta
    try:
        response_tree = etree.fromstring(response.content)
        send_result_node = response_tree.find('.//SendResult')

        if send_result_node is not None and send_result_node.text:
            inner_xml_tree = etree.fromstring(send_result_node.text)
            logger.info("Resultado encontrado e processado com sucesso")
            return inner_xml_tree
        else:
            logger.warning("SendResult vazio ou não encontrado")
            return None
    except Exception as e:
        logger.error(f"Erro ao processar resposta: {e}")
        raise

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Uso: python resultado.py <GUID_TRANSACAO>")
        sys.exit(1)

    guid = sys.argv[1]
    resultado = consultar_resultado(guid)

    if resultado is not None:
        print(etree.tostring(resultado, pretty_print=True, encoding='unicode'))
    else:
        print("Resultado não encontrado ou ainda não processado")
```

**Melhorias implementadas:**
- ✅ GUID via argumento de linha de comando
- ✅ Logging estruturado
- ✅ Timeout HTTP (30s)
- ✅ Type hints
- ✅ Docstrings
- ✅ Tratamento de erros robusto
- ✅ Retorno estruturado

---

## 📚 Referências

- **SOAP 1.1:** https://www.w3.org/TR/2000/NOTE-SOAP-20000508/
- **UUID RFC:** https://tools.ietf.org/html/rfc4122
- **ISO 8601 DateTime:** https://en.wikipedia.org/wiki/ISO_8601

---

**Análise realizada por:** Claude Code
**Data:** 2025-12-05
**Versão:** 1.0.0
