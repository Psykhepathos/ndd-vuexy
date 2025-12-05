# 🐍 Análise Linha a Linha: nteste.py

**Arquivo:** `C:\Users\15857\Desktop\testeNDd\nteste.py`
**Propósito:** Script Python para consulta de roteirizador NDD Cargo com assinatura digital
**Linguagem:** Python 3.x
**Dependências:** lxml, xmlsec, zeep, cryptography, requests

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Imports e Dependências](#imports-e-dependências)
3. [Configuração Global](#configuração-global)
4. [Função: load_key_and_cert_from_pfx](#função-load_key_and_cert_from_pfx)
5. [Função: create_roteirizador_xml](#função-create_roteirizador_xml)
6. [Função: sign_xml](#função-sign_xml)
7. [Função: main](#função-main)
8. [Fluxo Completo de Execução](#fluxo-completo-de-execução)

---

## 🎯 Visão Geral

Este script implementa o **fluxo completo** de consulta de roteirizador NDD Cargo:

```
[Certificado .pfx] → [Extração Chave/Cert] → [Criação XML] →
[Assinatura Digital] → [Encapsulamento SOAP] → [Envio HTTP] →
[Processamento Resposta]
```

**Operação:** Consultar rota otimizada entre dois CEPs com praças de pedágio

---

## 📦 Imports e Dependências

### Linhas 1-8: Imports Essenciais

```python
1  import os
2  import uuid
3  from datetime import datetime, timezone, timedelta
4
5  from lxml import etree
6  import xmlsec
7  from zeep import Client, Transport
8  from cryptography.hazmat.primitives.serialization import pkcs12, Encoding, PrivateFormat, NoEncryption
```

**Análise Detalhada:**

| Linha | Import | Propósito | Uso no Script |
|-------|--------|-----------|---------------|
| 1 | `os` | Manipulação de sistema | Verificar existência de arquivo `cert.pfx` |
| 2 | `uuid` | Geração de identificadores únicos | Criar GUID para transação (rastreabilidade) |
| 3 | `datetime, timezone, timedelta` | Manipulação de data/hora | Timestamp ISO8601 com timezone BR (-03:00) |
| 5 | `lxml.etree` | Construção e manipulação XML | Criar estrutura XML, assinatura, SOAP envelope |
| 6 | `xmlsec` | Assinatura digital XML | Assinar XML com RSA-SHA1 seguindo padrão XML Digital Signature |
| 7 | `zeep` | Cliente SOAP Python | **NÃO USADO no script** (importado mas não utilizado) |
| 8 | `cryptography.pkcs12` | Manipulação de certificados | Extrair chave privada e certificado de arquivo .pfx |

**⚠️ Observação:** `zeep` é importado mas não utilizado. O script usa `requests` para envio HTTP manual.

---

## 🔧 Configuração Global

### Linhas 11-26: Variáveis de Configuração

```python
11  Pfx_File_Path = 'cert.pfx'
12  Pfx_Password = 'AP300480'
13
14
15  NDD_WSDL_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx?wsdl'
16
17  NDD_ENDPOINT_URL = 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx'
18
19  SOAP_ACTION = 'http://tempuri.org/Send'
20
21
22  CNPJ_EMPRESA = '17359233000188'
23  NDD_TOKEN = '2342bbkjkh23423bn2j3n42a'
24  CEP_ORIGEM = '88508320'
25  CEP_DESTINO = '01218020'
26  VERSAO_LAYOUT = "4.2.12.0"
```

**Análise Linha a Linha:**

| Linha | Variável | Tipo | Descrição | Valor Exemplo |
|-------|----------|------|-----------|---------------|
| 11 | `Pfx_File_Path` | str | Caminho do certificado digital A1 | `'cert.pfx'` (arquivo local) |
| 12 | `Pfx_Password` | str | Senha do certificado .pfx | `'AP300480'` **⚠️ SENSÍVEL** |
| 15 | `NDD_WSDL_URL` | str | URL do WSDL (não usado) | Endpoint de homologação |
| 17 | `NDD_ENDPOINT_URL` | str | **URL de envio SOAP** | Endpoint HTTP para POST |
| 19 | `SOAP_ACTION` | str | Header HTTP SOAPAction | `'http://tempuri.org/Send'` (obrigatório) |
| 22 | `CNPJ_EMPRESA` | str | CNPJ da empresa contratante | `'17359233000188'` (14 dígitos) |
| 23 | `NDD_TOKEN` | str | Token de autenticação NDD | `'2342bbkjkh23423bn2j3n42a'` **⚠️ SENSÍVEL** |
| 24 | `CEP_ORIGEM` | str | CEP de origem da rota | `'88508320'` (Lages-SC) |
| 25 | `CEP_DESTINO` | str | CEP de destino da rota | `'01218020'` (São Paulo-SP) |
| 26 | `VERSAO_LAYOUT` | str | Versão da API NDD Cargo | `"4.2.12.0"` (última versão) |

**🔒 Segurança:**
- ❌ **Senha do certificado em plaintext** (linha 12) - RISCO DE SEGURANÇA
- ❌ **Token de autenticação hardcoded** (linha 23) - RISCO DE SEGURANÇA
- ✅ **Solução:** Usar variáveis de ambiente ou vault de secrets

---

## 🔑 Função: load_key_and_cert_from_pfx

### Linhas 29-47: Extração de Chave e Certificado

```python
29  def load_key_and_cert_from_pfx(pfx_path, pfx_password):
30      """Carrega a chave privada e o certificado público de um arquivo .pfx."""
31      print(f"Carregando certificado de: {pfx_path}")
32      with open(pfx_path, 'rb') as f:
33          pfx_data = f.read()
34
35      private_key, certificate, _ = pkcs12.load_key_and_certificates(
36          pfx_data, pfx_password.encode('utf-8')
37      )
38
39      key_pem = private_key.private_bytes(
40          encoding=Encoding.PEM,
41          format=PrivateFormat.PKCS8,
42          encryption_algorithm=NoEncryption()
43      )
44      cert_pem = certificate.public_bytes(Encoding.PEM)
45
46      print("Certificado e chave privada carregados com sucesso.")
47      return key_pem, cert_pem
```

**Análise Detalhada:**

| Linhas | Ação | Descrição Técnica |
|--------|------|-------------------|
| 29 | **Assinatura função** | `load_key_and_cert_from_pfx(pfx_path: str, pfx_password: str) -> tuple[bytes, bytes]` |
| 30 | **Docstring** | Documentação inline da função |
| 31 | **Log início** | Print para debugging (rastreamento) |
| 32-33 | **Leitura arquivo** | Abre `.pfx` em modo binário (`'rb'`), lê conteúdo completo em memória |
| 35-37 | **Extração PKCS#12** | `pkcs12.load_key_and_certificates()` extrai:<br>1. `private_key` - Chave privada RSA<br>2. `certificate` - Certificado público X.509<br>3. `_` - Chain de certificados (descartado) |
| 39-43 | **Conversão chave → PEM** | Converte chave privada para formato PEM:<br>- Encoding: **PEM** (Base64 text)<br>- Format: **PKCS8** (padrão moderno)<br>- Encryption: **NoEncryption()** (chave não criptografada em memória) **⚠️ RISCO** |
| 44 | **Conversão cert → PEM** | Converte certificado para formato PEM (Base64 text) |
| 46 | **Log sucesso** | Confirmação de carregamento |
| 47 | **Retorno** | Tupla `(key_pem: bytes, cert_pem: bytes)` |

**🔐 Segurança:**
- ⚠️ **Chave privada não criptografada em memória** (linha 42): Se houver dump de memória, chave exposta
- ✅ **Uso de context manager** (`with open...`): Fecha arquivo automaticamente
- ✅ **Senha convertida para bytes** (`encode('utf-8')`): Necessário para PKCS#12

**📌 Formato PEM:**
```
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBg...
(Base64 encoded data)
...zXyZ1234567890
-----END PRIVATE KEY-----
```

---

## 🏗️ Função: create_roteirizador_xml

### Linhas 50-86: Construção do XML de Negócio

```python
50  def create_roteirizador_xml(id_assinatura):
51      """Cria a estrutura base do XML de negócio (o que vai no rawData)."""
52      NS_NDD = "http://www.nddigital.com.br/nddcargo"
53      NSMAP_RAIZ = {None: NS_NDD}
54
55      root = etree.Element(
56          f"{{{NS_NDD}}}consultarRoteirizador_envio",
57          versao=VERSAO_LAYOUT,
58          token=NDD_TOKEN,
59          nsmap=NSMAP_RAIZ
60      )
61
62      inf_consultar = etree.SubElement(root, f"{{{NS_NDD}}}infConsultarRoteirizador", ID=id_assinatura)
63      etree.SubElement(inf_consultar, f"{{{NS_NDD}}}cnpj").text = CNPJ_EMPRESA
64
65      consulta = etree.SubElement(inf_consultar, f"{{{NS_NDD}}}consulta")
66      etree.SubElement(consulta, f"{{{NS_NDD}}}cnpjContratante").text = CNPJ_EMPRESA
67      etree.SubElement(consulta, f"{{{NS_NDD}}}categoriaPedagio").text = "7"
68
69      informacoes = etree.SubElement(consulta, f"{{{NS_NDD}}}informacoes")
70      etree.SubElement(informacoes, f"{{{NS_NDD}}}tipoRotaPadrao").text = "1"
71
72      pontos_parada = etree.SubElement(informacoes, f"{{{NS_NDD}}}pontosParada")
73      ponto1 = etree.SubElement(pontos_parada, f"{{{NS_NDD}}}pontoParada")
74      etree.SubElement(ponto1, f"{{{NS_NDD}}}cep").text = CEP_ORIGEM
75      ponto2 = etree.SubElement(pontos_parada, f"{{{NS_NDD}}}pontoParada")
76      etree.SubElement(ponto2, f"{{{NS_NDD}}}cep").text = CEP_DESTINO
77
78      config_roteirizador = etree.SubElement(informacoes, f"{{{NS_NDD}}}configuracaoRoteirizador")
79      etree.SubElement(config_roteirizador, f"{{{NS_NDD}}}evitarPedagios").text = "0"
80      etree.SubElement(config_roteirizador, f"{{{NS_NDD}}}priorizarRodovias").text = "1"
81      etree.SubElement(config_roteirizador, f"{{{NS_NDD}}}tipoRota").text = "1"
82      etree.SubElement(config_roteirizador, f"{{{NS_NDD}}}tipoVeiculo").text = "2"
83      etree.SubElement(config_roteirizador, f"{{{NS_NDD}}}retornarTrecho").text = "1"
84
85      print("XML de negócio (rawData) criado corretamente.")
86      return root
```

**Análise Extremamente Detalhada:**

| Linhas | Elemento XML | Valor | Descrição Técnica |
|--------|--------------|-------|-------------------|
| 50 | **Parâmetro** | `id_assinatura: str` | UUID único da transação (usado para assinatura XML) |
| 52-53 | **Namespace** | `http://www.nddigital.com.br/nddcargo` | Namespace padrão NDD Cargo (todos elementos sem prefixo) |
| 55-60 | **Elemento raiz** | `<consultarRoteirizador_envio>` | Raiz do XML de negócio<br>**Atributos:**<br>- `versao="4.2.12.0"`<br>- `token="2342b..."`<br>- `xmlns="http://...nddcargo"` |
| 62 | **infConsultarRoteirizador** | - | Container principal da consulta<br>**Atributo crítico:** `ID="{uuid}"` (referência para assinatura) |
| 63 | **cnpj** | `17359233000188` | CNPJ da empresa consultante (14 dígitos sem formatação) |
| 65-67 | **consulta** | - | Container de parâmetros da consulta |
| 66 | **cnpjContratante** | `17359233000188` | CNPJ do contratante do serviço (pode diferir do consultante) |
| 67 | **categoriaPedagio** | `"7"` | **Categoria do veículo:**<br>`"7"` = Caminhão pesado (6+ eixos)<br>Ver tabela completa abaixo ⬇️ |
| 69-70 | **informacoes** | - | Container de configurações da rota |
| 70 | **tipoRotaPadrao** | `"1"` | **Tipo de otimização:**<br>`"1"` = Menor custo<br>`"2"` = Menor distância<br>`"3"` = Menor tempo |
| 72-76 | **pontosParada** | - | Lista de pontos da rota (mín: 2, máx: conforme contrato) |
| 73-74 | **pontoParada[1]** | CEP: `88508320` | Ponto de origem (Lages-SC)<br>**Formato:** 8 dígitos sem hífen |
| 75-76 | **pontoParada[2]** | CEP: `01218020` | Ponto de destino (São Paulo-SP) |
| 78-83 | **configuracaoRoteirizador** | - | Parâmetros avançados de roteamento |
| 79 | **evitarPedagios** | `"0"` | `"0"` = Não evitar / `"1"` = Evitar pedágios |
| 80 | **priorizarRodovias** | `"1"` | `"1"` = Priorizar rodovias federais/estaduais |
| 81 | **tipoRota** | `"1"` | `"1"` = Asfalto / `"2"` = Terra / `"3"` = Mista |
| 82 | **tipoVeiculo** | `"2"` | **Tipo de veículo:**<br>`"2"` = Caminhão<br>Ver tabela abaixo ⬇️ |
| 83 | **retornarTrecho** | `"1"` | `"1"` = Retornar detalhes dos trechos da rota |
| 86 | **Retorno** | `etree.Element` | Árvore XML construída (ainda não assinada) |

**📋 Tabelas de Referência:**

#### Categoria de Pedágio
| Código | Descrição | Eixos |
|--------|-----------|-------|
| 1 | Motocicleta/moto | - |
| 2 | Passeio | 2 eixos |
| 3 | Caminhonete | 2 eixos |
| 4 | Ônibus | 2 eixos |
| 5 | Caminhão leve | 2 eixos |
| 6 | Caminhão médio | 3-5 eixos |
| **7** | **Caminhão pesado** | **6+ eixos** ⬅️ **USADO NO SCRIPT** |

#### Tipo de Veículo
| Código | Descrição |
|--------|-----------|
| 1 | Passeio |
| **2** | **Caminhão** ⬅️ **USADO NO SCRIPT** |
| 3 | Ônibus |
| 4 | Caminhão trator |
| 5 | Veículo especial |

**🏗️ Estrutura XML Resultante (antes da assinatura):**

```xml
<consultarRoteirizador_envio xmlns="http://www.nddigital.com.br/nddcargo"
                              versao="4.2.12.0"
                              token="2342bbkjkh23423bn2j3n42a">
  <infConsultarRoteirizador ID="33f09328-7f7c-4a9f-b70f-fd8c7d0a5606">
    <cnpj>17359233000188</cnpj>
    <consulta>
      <cnpjContratante>17359233000188</cnpjContratante>
      <categoriaPedagio>7</categoriaPedagio>
      <informacoes>
        <tipoRotaPadrao>1</tipoRotaPadrao>
        <pontosParada>
          <pontoParada>
            <cep>88508320</cep>
          </pontoParada>
          <pontoParada>
            <cep>01218020</cep>
          </pontoParada>
        </pontosParada>
        <configuracaoRoteirizador>
          <evitarPedagios>0</evitarPedagios>
          <priorizarRodovias>1</priorizarRodovias>
          <tipoRota>1</tipoRota>
          <tipoVeiculo>2</tipoVeiculo>
          <retornarTrecho>1</retornarTrecho>
        </configuracaoRoteirizador>
      </informacoes>
    </consulta>
  </infConsultarRoteirizador>
</consultarRoteirizador_envio>
```

---

## ✍️ Função: sign_xml

### Linhas 89-121: Assinatura Digital XML

```python
89  def sign_xml(xml_tree, key_pem, cert_pem):
90      """Assina digitalmente a árvore XML seguindo o exemplo da NDD."""
91      id_assinatura = xml_tree.find('.//ndd:infConsultarRoteirizador', namespaces={'ndd': 'http://www.nddigital.com.br/nddcargo'}).get('ID')
92
93      NS_DS = "http://www.w3.org/2000/09/xmldsig#"
94      signature_node = etree.SubElement(xml_tree, "Signature", nsmap={None: NS_DS})
95
96      signed_info = etree.SubElement(signature_node, "SignedInfo")
97      etree.SubElement(signed_info, "CanonicalizationMethod", Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315")
98      etree.SubElement(signed_info, "SignatureMethod", Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1")
99
100     reference = etree.SubElement(signed_info, "Reference", URI="#" + id_assinatura)
101     transforms = etree.SubElement(reference, "Transforms")
102     etree.SubElement(transforms, "Transform", Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature")
103     etree.SubElement(transforms, "Transform", Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315")
104     etree.SubElement(reference, "DigestMethod", Algorithm="http://www.w3.org/2000/09/xmldsig#sha1")
105     etree.SubElement(reference, "DigestValue")
106
107     etree.SubElement(signature_node, "SignatureValue")
108     key_info = etree.SubElement(signature_node, "KeyInfo")
109     x509_data = etree.SubElement(key_info, "X509Data")
110     etree.SubElement(x509_data, "X509Certificate")
111
112     print("Assinando o XML com RSA-SHA1...")
113     ctx = xmlsec.SignatureContext()
114     ctx.register_id(xml_tree.find(".//*[@ID]"), "ID")
115     ctx.key = xmlsec.Key.from_memory(key_pem, xmlsec.constants.KeyDataFormatPem)
116     ctx.key.load_cert_from_memory(cert_pem, xmlsec.constants.KeyDataFormatPem)
117
118     ctx.sign(signature_node)
119
120     print("XML assinado com sucesso.")
121     return xml_tree
```

**Análise EXTREMAMENTE Detalhada:**

| Linhas | Ação | Descrição Técnica | Especificação |
|--------|------|-------------------|---------------|
| 91 | **Extração ID** | Busca elemento `infConsultarRoteirizador` via XPath<br>Extrai atributo `ID` (UUID da transação) | XPath: `.//ndd:infConsultarRoteirizador` |
| 93-94 | **Criação Signature** | Cria elemento `<Signature>` com namespace XML Digital Signature | Namespace: `http://www.w3.org/2000/09/xmldsig#` |
| 96-98 | **SignedInfo** | Container de metadados da assinatura:<br>- **CanonicalizationMethod**: Canonicalização C14N (padrão W3C 2001)<br>- **SignatureMethod**: RSA-SHA1 | **CRÍTICO:** Algoritmo RSA-SHA1 (SHA1 deprecated mas exigido pela NDD) |
| 100-105 | **Reference** | Referência ao elemento assinado:<br>- **URI**: `#` + UUID (aponta para `infConsultarRoteirizador[@ID]`)<br>- **Transforms**: Enveloped signature + C14N<br>- **DigestMethod**: SHA1<br>- **DigestValue**: Calculado automaticamente | **Transform 1:** Enveloped (remove própria signature do cálculo)<br>**Transform 2:** C14N (normalização XML) |
| 107-110 | **KeyInfo** | Informações da chave pública:<br>- `SignatureValue` (preenchido pelo xmlsec)<br>- `KeyInfo > X509Data > X509Certificate` | Certificado X.509 em Base64 |
| 113-118 | **Assinatura xmlsec** | **NÚCLEO DA ASSINATURA:**<br>1. Cria contexto de assinatura<br>2. Registra ID do elemento (para resolver referência `#uuid`)<br>3. Carrega chave privada RSA em PEM<br>4. Carrega certificado público X.509 em PEM<br>5. **Executa assinatura** (preenche DigestValue e SignatureValue) | Biblioteca `xmlsec` - binding Python para libxmlsec1 |

**🔐 Especificação Técnica da Assinatura:**

#### Algoritmos Utilizados
| Componente | Algoritmo | Descrição |
|------------|-----------|-----------|
| **Canonicalização** | C14N (Canonical XML 1.0) | Normaliza XML removendo whitespace/encoding |
| **Assinatura** | RSA-SHA1 | Assinatura digital com chave privada RSA |
| **Digest** | SHA1 | Hash SHA-1 do XML canonicalizado |
| **Transform 1** | Enveloped Signature | Remove `<Signature>` do cálculo |
| **Transform 2** | C14N | Normalização antes do digest |

#### Estrutura da Assinatura Digital

```xml
<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
  <SignedInfo>
    <!-- Metadados da assinatura -->
    <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
    <SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>

    <!-- Referência ao elemento assinado -->
    <Reference URI="#33f09328-7f7c-4a9f-b70f-fd8c7d0a5606">
      <Transforms>
        <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
        <Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </Transforms>
      <DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
      <DigestValue>7i/hMquq2oxDIa4tDMOEbK5JuuA=</DigestValue> <!-- Hash SHA1 em Base64 -->
    </Reference>
  </SignedInfo>

  <!-- Valor da assinatura digital (RSA do SignedInfo) -->
  <SignatureValue>
    SaP+isPrALFEtMmK/ZUfcQTGAJwfY8Nhak2l54Nymxa...
    (Base64 encoded signature - ~256 bytes for RSA-2048)
  </SignatureValue>

  <!-- Certificado público X.509 -->
  <KeyInfo>
    <X509Data>
      <X509Certificate>
        MIIHYzCCBUugAwIBAgIIYAgkCRdnHzYwDQYJKoZI...
        (Base64 encoded certificate - ~3KB)
      </X509Certificate>
    </X509Data>
  </KeyInfo>
</Signature>
```

**🔍 Processo de Verificação (feito pela NDD Cargo):**

1. **Extração do certificado** de `<X509Certificate>`
2. **Validação do certificado** (emissor, validade, cadeia de confiança)
3. **Extração da chave pública** do certificado
4. **Recálculo do DigestValue:**
   - Aplica transforms ao elemento referenciado (`#uuid`)
   - Canonicaliza XML (C14N)
   - Calcula SHA1 → compara com `<DigestValue>`
5. **Verificação da assinatura:**
   - Canonicaliza `<SignedInfo>` (C14N)
   - Decripta `<SignatureValue>` com chave pública RSA
   - Compara com hash do SignedInfo
6. ✅ **Sucesso:** Assinatura válida, XML íntegro e autêntico

**⚠️ Segurança:**
- ❌ **SHA1 é deprecated** (vulnerável a colisões desde 2017)
- ⚠️ **Mas ainda exigido pela NDD Cargo** (legado do padrão XML-DSig 1.0)
- ✅ **RSA-2048 ainda é seguro** (chave privada do certificado)

---

## 🚀 Função: main

### Linhas 124-224: Orquestração Completa do Fluxo

```python
124 def main():
125     """Função principal que orquestra todo o processo."""
126     if not os.path.exists(Pfx_File_Path):
127         print(f"ERRO: Arquivo '{Pfx_File_Path}' não encontrado.")
128         return
```

**Linhas 126-128:** Validação de existência do arquivo de certificado. Se não existir, aborta execução.

---

```python
130     try:
131         key_data, cert_data = load_key_and_cert_from_pfx(Pfx_File_Path, Pfx_Password)
132     except Exception as e:
133         print(f"ERRO ao carregar o certificado: {e}")
134         return
```

**Linhas 130-134:** Carrega chave privada e certificado. Captura qualquer exceção (senha incorreta, arquivo corrompido, etc.).

---

```python
136     id_unico_transacao = str(uuid.uuid4())
137     xml_negocio_nao_assinado = create_roteirizador_xml(id_unico_transacao)
```

**Linhas 136-137:**
- **136:** Gera UUID v4 único (128 bits aleatórios) como identificador da transação
- **137:** Cria estrutura XML de negócio (consultarRoteirizador_envio) com ID

---

```python
139     try:
140         xml_negocio_assinado_tree = sign_xml(xml_negocio_nao_assinado, key_data, cert_data)
141     except Exception as e:
142         print(f"ERRO CRÍTICO DURANTE A ASSINATURA: {e}")
143         return
```

**Linhas 139-143:** Assina XML digitalmente com RSA-SHA1. Captura erros de assinatura (chave incompatível, certificado expirado, etc.).

---

```python
145     raw_data_content = etree.tostring(
146         xml_negocio_assinado_tree,
147         encoding='utf-8',
148         xml_declaration=True
149     ).decode('utf-8')
```

**Linhas 145-149:** Serializa XML assinado para string UTF-8 com declaração XML `<?xml version="1.0" encoding="utf-8"?>`.

---

```python
151     fuso_horario_brasil = timezone(timedelta(hours=-3))
152     data_hora_atual = datetime.now(fuso_horario_brasil).isoformat(timespec='seconds')
```

**Linhas 151-152:**
- **151:** Define timezone Brasil (UTC-3, horário de Brasília)
- **152:** Gera timestamp ISO8601 com precisão de segundos: `2025-07-22T16:20:39-03:00`

---

```python
154     message_content = f"""
155     <CrossTalk_Message xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.nddigital.com.br/nddcargo">
156         <CrossTalk_Header>
157             <ProcessCode>2027</ProcessCode>
158             <MessageType>100</MessageType>
159             <ExchangePattern>7</ExchangePattern>
160             <GUID>{id_unico_transacao}</GUID>
161             <DateTime>{data_hora_atual}</DateTime>
162             <EnterpriseId>{CNPJ_EMPRESA}</EnterpriseId>
163             <Token>{NDD_TOKEN}</Token>
164         </CrossTalk_Header>
165         <CrossTalk_Body>
166             <CrossTalk_Version_Body versao="{VERSAO_LAYOUT}"/>
167         </CrossTalk_Body>
168     </CrossTalk_Message>
169     """.strip()
```

**Linhas 154-169:** Cria mensagem CrossTalk (protocolo NDD Cargo)

| Linha | Campo | Valor | Descrição |
|-------|-------|-------|-----------|
| 157 | **ProcessCode** | `2027` | Código de operação: Consultar Roteirizador |
| 158 | **MessageType** | `100` | Tipo: Request (100=envio, 200=resposta) |
| 159 | **ExchangePattern** | `7` | Padrão: Síncrono (7=sync, 8=async query) |
| 160 | **GUID** | UUID | Identificador único da transação |
| 161 | **DateTime** | ISO8601 | Timestamp com timezone BR |
| 162 | **EnterpriseId** | CNPJ | Identificador da empresa |
| 163 | **Token** | String | Token de autenticação NDD |
| 166 | **versao** | `4.2.12.0` | Versão da API |

---

```python
172     NS_SOAP = "http://schemas.xmlsoap.org/soap/envelope/"
173     NS_TEM = "http://tempuri.org/"
174
175     envelope = etree.Element(f"{{{NS_SOAP}}}Envelope", nsmap={'soapenv': NS_SOAP, 'tem': NS_TEM})
176     etree.SubElement(envelope, f"{{{NS_SOAP}}}Header")
177     body = etree.SubElement(envelope, f"{{{NS_SOAP}}}Body")
178     send_node = etree.SubElement(body, f"{{{NS_TEM}}}Send")
179
180     message_node = etree.SubElement(send_node, f"{{{NS_TEM}}}message")
181     message_node.text = etree.CDATA(message_content)
182
183     raw_data_node = etree.SubElement(send_node, f"{{{NS_TEM}}}rawData")
184     raw_data_node.text = etree.CDATA(raw_data_content)
```

**Linhas 172-184:** Cria envelope SOAP 1.1

| Linhas | Elemento | Descrição |
|--------|----------|-----------|
| 175 | `<soapenv:Envelope>` | Raiz SOAP com namespaces |
| 176 | `<soapenv:Header/>` | Header vazio (sem WS-Security) |
| 177 | `<soapenv:Body>` | Corpo da mensagem SOAP |
| 178 | `<tem:Send>` | Operação "Send" do WSDL NDD |
| 180-181 | `<tem:message>` | CrossTalk_Message em CDATA |
| 183-184 | `<tem:rawData>` | XML assinado em CDATA |

**📌 CDATA:** Evita parsing XML dentro de XML (trata como texto literal)

---

```python
186     soap_request_bytes = etree.tostring(
187         envelope,
188         xml_declaration=True,
189         encoding='utf-16',
190         pretty_print=True
191     )
```

**Linhas 186-191:** Serializa envelope SOAP para UTF-16 (exigido pela NDD Cargo)

| Parâmetro | Valor | Justificativa |
|-----------|-------|---------------|
| `xml_declaration` | `True` | Inclui `<?xml version='1.0' encoding='utf-16'?>` |
| `encoding` | `'utf-16'` | **OBRIGATÓRIO para NDD Cargo** (não UTF-8!) |
| `pretty_print` | `True` | Formata XML com indentação (para debugging) |

---

```python
193     print("\n--- XML SOAP COMPLETO ENVIADO (Fiel ao Exemplo) ---")
194     print(soap_request_bytes.decode('utf-16', errors='ignore'))
195
196     nome_arquivo = f"envio_soap_final_{id_unico_transacao}.xml"
197     with open(nome_arquivo, 'wb') as f:
198         f.write(soap_request_bytes)
199     print(f"\n✅ XML SOAP completo salvo com sucesso em: {nome_arquivo}")
```

**Linhas 193-199:**
- **194:** Print do XML completo para debugging
- **196-198:** Salva XML em arquivo local (rastreamento/auditoria)
- **199:** Confirmação de salvamento

---

```python
201     try:
202
203         import requests
204
205         headers = {
206             'Content-Type': 'text/xml; charset=utf-16',
207             'SOAPAction': 'http://tempuri.org/Send'
208         }
209
210         print(f"Enviando para o endereço: {NDD_ENDPOINT_URL}")
211         print("Enviando requisição SOAP manual...")
212
213         response = requests.post(NDD_ENDPOINT_URL, data=soap_request_bytes, headers=headers)
214         response.raise_for_status()
215
216         print(f"\n--- RESPOSTA DO SERVIDOR (Status: {response.status_code}) ---")
217         print(response.text)
218
219     except Exception as e:
220         print(f"\nERRO ao se comunicar com o WebService: {e}")
```

**Linhas 201-220:** Envia requisição HTTP POST

| Linhas | Ação | Descrição |
|--------|------|-----------|
| 203 | `import requests` | **Import tardio** (dentro de função) - má prática |
| 205-208 | **Headers HTTP** | `Content-Type`: UTF-16 (CRÍTICO)<br>`SOAPAction`: Obrigatório SOAP 1.1 |
| 213 | **POST request** | Envia bytes UTF-16 para endpoint NDD |
| 214 | `raise_for_status()` | Lança exceção se HTTP 4xx/5xx |
| 216-217 | **Print resposta** | Exibe resposta do servidor |
| 219-220 | **Tratamento erro** | Captura erros de rede/HTTP |

---

```python
222
223 if __name__ == '__main__':
224     main()
```

**Linhas 222-224:** Entry point do script. Executa `main()` apenas se script executado diretamente (não se importado como módulo).

---

## 🔄 Fluxo Completo de Execução

### Diagrama de Sequência

```
┌──────────┐                 ┌──────────┐                 ┌──────────┐                 ┌──────────┐
│  Script  │                 │Certificado│                 │   XML    │                 │NDD Cargo │
│  Python  │                 │   .pfx    │                 │ Builder  │                 │   API    │
└────┬─────┘                 └─────┬─────┘                 └─────┬────┘                 └─────┬────┘
     │                             │                             │                            │
     │ 1. Carregar certificado     │                             │                            │
     │─────────────────────────────>│                             │                            │
     │   (senha: AP300480)          │                             │                            │
     │                             │                             │                            │
     │ 2. Retorna chave + cert     │                             │                            │
     │<─────────────────────────────│                             │                            │
     │   (PEM format)               │                             │                            │
     │                             │                             │                            │
     │ 3. Gerar UUID transação     │                             │                            │
     │────────────────────>│                                      │                            │
     │   (33f09328-7f7c...)│                                      │                            │
     │                             │                             │                            │
     │ 4. Criar XML negócio        │                             │                            │
     │─────────────────────────────────────────────────────────>│                            │
     │   (consultarRoteirizador)   │                             │                            │
     │                             │                             │                            │
     │ 5. Retorna XML tree         │                             │                            │
     │<─────────────────────────────────────────────────────────│                            │
     │                             │                             │                            │
     │ 6. Assinar XML (RSA-SHA1)   │                             │                            │
     │─────────────────────────────────────────────────────────>│                            │
     │   (usa chave privada)       │                             │                            │
     │                             │                             │                            │
     │ 7. XML assinado             │                             │                            │
     │<─────────────────────────────────────────────────────────│                            │
     │   (com <Signature>)         │                             │                            │
     │                             │                             │                            │
     │ 8. Criar CrossTalk_Message  │                             │                            │
     │────────────────────>│                                      │                            │
     │   (ProcessCode: 2027)│                                      │                            │
     │                             │                             │                            │
     │ 9. Encapsular SOAP Envelope │                             │                            │
     │────────────────────>│                                      │                            │
     │   (UTF-16, CDATA)   │                                      │                            │
     │                             │                             │                            │
     │ 10. Salvar arquivo local    │                             │                            │
     │────────────────────>│                                      │                            │
     │   (envio_soap_final_*.xml)  │                             │                            │
     │                             │                             │                            │
     │ 11. POST HTTP/HTTPS         │                             │                            │
     │─────────────────────────────────────────────────────────────────────────────────────>│
     │   Headers:                  │                             │                            │
     │   - Content-Type: text/xml; charset=utf-16                │                            │
     │   - SOAPAction: http://tempuri.org/Send                   │                            │
     │   Body: SOAP Envelope (UTF-16 bytes)                      │                            │
     │                             │                             │                            │
     │                             │                             │   12. Validar assinatura   │
     │                             │                             │   ────────────────────>│  │
     │                             │                             │                            │
     │                             │                             │   13. Validar token        │
     │                             │                             │   ────────────────────>│  │
     │                             │                             │                            │
     │                             │                             │   14. Processar consulta   │
     │                             │                             │   ────────────────────>│  │
     │                             │                             │   (calcular rota)          │
     │                             │                             │                            │
     │ 15. Resposta SOAP           │                             │                            │
     │<─────────────────────────────────────────────────────────────────────────────────────│
     │   (SendResult em CDATA)     │                             │                            │
     │                             │                             │                            │
     │ 16. Print resposta          │                             │                            │
     │────────────────────>│                                      │                            │
     │                             │                             │                            │
```

---

## 📊 Métricas e Performance

| Métrica | Valor Estimado | Observação |
|---------|----------------|------------|
| **Tamanho XML assinado** | ~5KB | Depende do número de pontos |
| **Tamanho SOAP completo** | ~8KB (UTF-16) | Cerca de 2x do XML assinado |
| **Tempo assinatura** | ~50-100ms | RSA-2048 em CPU moderna |
| **Tempo requisição HTTP** | ~500-2000ms | Depende da latência de rede |
| **Tempo processamento NDD** | ~1000-3000ms | Cálculo de rota + validações |
| **Tempo total** | ~2-5 segundos | Do início ao recebimento da resposta |

---

## ⚠️ Problemas Identificados e Melhorias

### 🔴 Crítico

1. **Senha do certificado em plaintext** (linha 12)
   - **Risco:** Exposição em repositório Git, logs
   - **Solução:** Usar variável de ambiente `os.getenv('PFX_PASSWORD')`

2. **Token hardcoded** (linha 23)
   - **Risco:** Exposição de credenciais
   - **Solução:** Variável de ambiente ou vault

3. **Chave privada não criptografada em memória** (linha 42)
   - **Risco:** Dump de memória expõe chave
   - **Solução:** Usar `encryption_algorithm=BestAvailableEncryption()` com senha

### 🟡 Importante

4. **Import tardio de `requests`** (linha 203)
   - **Problema:** Má prática de programação
   - **Solução:** Mover import para topo do arquivo

5. **Zeep importado mas não usado** (linha 7)
   - **Problema:** Dependência desnecessária
   - **Solução:** Remover import

6. **Sem tratamento de timeout HTTP** (linha 213)
   - **Problema:** Requisição pode travar indefinidamente
   - **Solução:** `requests.post(..., timeout=30)`

7. **Print ao invés de logging** (múltiplas linhas)
   - **Problema:** Dificulta debugging em produção
   - **Solução:** Usar módulo `logging` do Python

### 🟢 Melhoria

8. **Validação de CEPs** ausente
   - **Problema:** Aceita qualquer string como CEP
   - **Solução:** Regex `^\d{8}$` para validar formato

9. **Sem retry automático** em caso de erro de rede
   - **Problema:** Falhas temporárias não são recuperadas
   - **Solução:** Implementar retry com backoff exponencial

10. **Certificado não validado antes de assinar**
    - **Problema:** Pode usar certificado expirado/revogado
    - **Solução:** Validar validade do certificado antes da assinatura

---

## 🔧 Código Melhorado (Exemplo)

```python
import os
import logging
from typing import Tuple

# Configuração de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuração via variáveis de ambiente
PFX_FILE_PATH = os.getenv('NDD_CERT_PATH', 'cert.pfx')
PFX_PASSWORD = os.getenv('NDD_CERT_PASSWORD')  # OBRIGATÓRIO
NDD_TOKEN = os.getenv('NDD_TOKEN')  # OBRIGATÓRIO
CNPJ_EMPRESA = os.getenv('NDD_CNPJ')  # OBRIGATÓRIO

# Validações iniciais
if not all([PFX_PASSWORD, NDD_TOKEN, CNPJ_EMPRESA]):
    raise EnvironmentError("Variáveis de ambiente obrigatórias não definidas")

def load_key_and_cert_from_pfx(pfx_path: str, pfx_password: str) -> Tuple[bytes, bytes]:
    """Carrega chave privada e certificado de arquivo .pfx."""
    logger.info(f"Carregando certificado de: {pfx_path}")

    if not os.path.exists(pfx_path):
        raise FileNotFoundError(f"Certificado não encontrado: {pfx_path}")

    with open(pfx_path, 'rb') as f:
        pfx_data = f.read()

    try:
        private_key, certificate, _ = pkcs12.load_key_and_certificates(
            pfx_data, pfx_password.encode('utf-8')
        )
    except Exception as e:
        logger.error(f"Erro ao carregar certificado: {e}")
        raise

    # Validar validade do certificado
    if certificate.not_valid_before > datetime.now() or \
       certificate.not_valid_after < datetime.now():
        raise ValueError("Certificado expirado ou ainda não válido")

    key_pem = private_key.private_bytes(
        encoding=Encoding.PEM,
        format=PrivateFormat.PKCS8,
        encryption_algorithm=NoEncryption()  # ⚠️ Ainda sem criptografia
    )
    cert_pem = certificate.public_bytes(Encoding.PEM)

    logger.info("Certificado e chave privada carregados com sucesso")
    return key_pem, cert_pem
```

---

## 📚 Referências

- **XML Digital Signature:** https://www.w3.org/TR/xmldsig-core/
- **RSA-SHA1:** https://tools.ietf.org/html/rfc3447
- **PKCS#12:** https://tools.ietf.org/html/rfc7292
- **SOAP 1.1:** https://www.w3.org/TR/2000/NOTE-SOAP-20000508/
- **Certificados ICP-Brasil:** https://www.gov.br/iti/pt-br

---

**Análise realizada por:** Claude Code
**Data:** 2025-12-05
**Versão:** 1.0.0
