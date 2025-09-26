# 📡 NDD Cargo SOAP API - Documentação Completa

Documentação baseada na análise do projeto SOAP UI "Cargo Projeto Doug-soapui-project.xml"

## 📋 Índice

- [Serviços Base](#serviços-base)
- [APIs Vale Pedágio (OVP)](#apis-vale-pedágio-ovp)
- [APIs CIOT (Conhecimento de Transporte)](#apis-ciot-conhecimento-de-transporte)
- [APIs de Roteamento](#apis-de-roteamento)
- [APIs de Pagamento](#apis-de-pagamento)
- [APIs de Gestão de Transporte](#apis-de-gestão-de-transporte)
- [APIs de Consulta e Lookup](#apis-de-consulta-e-lookup)
- [APIs de Documentos](#apis-de-documentos)
- [Endpoints e Configuração](#endpoints-e-configuração)

---

## 🔧 Serviços Base

### `Ativo`
**Funcionalidade:** Verificação de status do serviço/health check
**Uso:** Confirmar se o serviço está ativo e disponível
**Versão:** Todas
**Endpoint:** ExchangeMessage.asmx

### `Send`
**Funcionalidade:** Serviço principal de envio de mensagens
**Uso:** Enviar operações para todas as funcionalidades do sistema
**Versão:** Todas
**Formato:** SOAP XML com protocolo CrossTalk

### `CompressedSend`
**Funcionalidade:** Envio de mensagens com compressão
**Uso:** Para payloads grandes que precisam de compressão
**Versão:** Todas
**Benefício:** Reduz largura de banda para mensagens volumosas

### `SendWithCompressedResponse`
**Funcionalidade:** Envio normal com resposta comprimida
**Uso:** Quando a resposta é grande mas o envio é pequeno

### `CompressedSendWithCompressedResponse`
**Funcionalidade:** Envio e resposta ambos comprimidos
**Uso:** Comunicação totalmente otimizada para grandes volumes

---

## 🎫 APIs Vale Pedágio (OVP)

### `OVP 7`
**Funcionalidade:** Operação Vale Pedágio versão 7
**Uso:** Criação e gestão de vales pedagio eletrônicos
**ProcessCode:** 2019
**Estrutura XML:** `operacaoValePedagio_envio`

**Principais Campos:**
- `infOperacaoValePedagio` - Informações da operação
- `cnpj` - CNPJ da empresa
- `ide` - Identificação (número, série, emissor, data final)
- `transportador` - Dados do transportador (RNTRC, CPF, TAC)
- `infRota` - Informações da rota (categoria pedagio, rota ERP)
- `veiculo` - Dados do veículo (placa, modelo, tipo)
- `informacoesTag` - Código do fornecedor da tag
- `Signature` - Assinatura digital XML

### `OVP 8`
**Funcionalidade:** Operação Vale Pedágio versão 8 (atualizada)
**Uso:** Versão mais recente com melhorias e novos campos
**Diferenças:** Campos adicionais e validações aprimoradas

---

## 📋 APIs CIOT (Conhecimento de Transporte)

### `CIOT 7`
**Funcionalidade:** Criação de Conhecimento de Transporte versão 7
**ProcessCode:** 1000
**Uso:** Gerar documentos oficiais de conhecimento de transporte
**Estrutura XML:** `loteOT_envio`

**Principais Seções:**
- `operacoes/OT` - Operação de transporte
- `infOT` - Informações da OT (número, série, data início)
- `carga` - Dados da carga (código SH, quantidade, remetente, destinatário)
- `transp` - Dados do transportador (RNTRC, CPF, gestora cartão)
- `rota` - Informações de rota (rotaERP, pontos de parada)

**Parâmetros Importantes:**
- `impAuto` - Impressão automática
- `gerPgtoFin` - Geração pagamento financeiro
- `gerPgtoPedagio` - Geração pagamento pedagio

### `CIOT 8`
**Funcionalidade:** Criação de Conhecimento de Transporte versão 8
**Uso:** Versão atualizada com novos recursos e validações

### `Cancelamento CIOT 7`
**Funcionalidade:** Cancelamento de CIOT versão 7
**Uso:** Anular/cancelar conhecimentos de transporte já emitidos
**Motivo:** Erros na emissão, mudança de planos, etc.

### `Cancelamento CIOT 8`
**Funcionalidade:** Cancelamento de CIOT versão 8
**Uso:** Cancelamento na versão mais recente

---

## 🗺️ APIs de Roteamento

### `Consulta Roteirizador 7`
**Funcionalidade:** Consulta de rotas otimizadas versão 7
**Uso:** Calcular rotas, distâncias e tempos estimados
**Benefícios:** Otimização de combustível e tempo de viagem

### `Consulta Roteirizador 8`
**Funcionalidade:** Consulta de rotas otimizadas versão 8
**Uso:** Versão melhorada com algoritmos de otimização aprimorados

---

## 💰 APIs de Pagamento

### `PAGAMENTO IMEDIADO`
**Funcionalidade:** Processamento de pagamento imediato
**Uso:** Efetuar pagamentos de pedágio/frete instantaneamente
**Casos:** Situações de urgência, pagamentos à vista

### `CONSULTA PAGAMENTO REALIZADO`
**Funcionalidade:** Consulta de status de pagamentos
**Uso:** Verificar se pagamentos foram processados com sucesso
**Retorno:** Status, valor, data/hora, detalhes da transação

---

## 🚛 APIs de Gestão de Transporte

### `CONSULTA TRANSP`
**Funcionalidade:** Consulta de transportadoras versão 7
**Uso:** Buscar informações de empresas transportadoras
**Dados Retornados:** RNTRC, razão social, endereço, situação cadastral

### `CONSULTA TRANSP8`
**Funcionalidade:** Consulta de transportadoras versão 8
**Uso:** Versão atualizada com mais campos e validações

### `RV OT 7`
**Funcionalidade:** Operação de Ordem de Transporte versão 7
**Uso:** Gestão de ordens de transporte e operações

---

## 🔍 APIs de Consulta e Lookup

### `Consulta ID`
**Funcionalidade:** Consulta por identificadores específicos
**Uso:** Buscar informações usando IDs de CIOT, OVP, etc.
**Parâmetros:** ID do documento, tipo de consulta

### `ENCERRAMENTO 7`
**Funcionalidade:** Encerramento/finalização de operações versão 7
**Uso:** Finalizar e fechar operações de transporte
**Processo:** Confirmar entrega, atualizar status, gerar relatórios

---

## 🖨️ APIs de Documentos

### `Solicitação de Impressão`
**Funcionalidade:** Solicitação de impressão de documentos
**Uso:** Gerar documentos físicos/PDF para impressão
**Tipos:** CIOT, Vale Pedágio, Relatórios, Comprovantes
**Formatos:** PDF, XML formatado, layouts específicos

---

## 🌐 Endpoints e Configuração

### Endpoints Disponíveis

```
🔴 PRODUÇÃO
https://nddintegra-dtp-nddcargo.ndd.tech/WSNDDConnect.asmx

🟡 HOMOLOGAÇÃO
https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx

🔵 LEGACY (Produção Antiga)
http://wsagent.nddcargo.com.br/wsagente/exchangemessage.asmx
```

### Autenticação

**Sistema de Tokens:**
- `Token` - Token de autenticação por empresa
- `EnterpriseId` - CNPJ da empresa
- Exemplo: `CIOTNDD01446320000132SSW`

**Certificados Digitais:**
- Assinatura XML obrigatória para documentos oficiais
- Algoritmo RSA-SHA1
- Certificado X.509

### Estrutura de Mensagem

**CrossTalk Protocol:**
```xml
<CrossTalk_Message>
  <CrossTalk_Header>
    <ProcessCode>XXXX</ProcessCode>          <!-- Código da operação -->
    <MessageType>100</MessageType>           <!-- Tipo da mensagem -->
    <ExchangePattern>7</ExchangePattern>     <!-- Padrão de troca -->
    <GUID>uuid</GUID>                        <!-- ID único -->
    <DateTime>timestamp</DateTime>           <!-- Data/hora -->
    <EnterpriseId>CNPJ</EnterpriseId>       <!-- CNPJ empresa -->
    <Token>token</Token>                     <!-- Token auth -->
  </CrossTalk_Header>
  <CrossTalk_Body>
    <CrossTalk_Version_Body versao="4.2.x.x"/>
  </CrossTalk_Body>
</CrossTalk_Message>
```

### Versionamento

**Versões Principais:**
- **v7** - Versão anterior estável
- **v8** - Versão atual com melhorias
- **Versão API:** 4.2.x.x (exemplo: 4.2.12.0)

### Process Codes Identificados

- **1000** - CIOT (Conhecimento de Transporte)
- **2019** - OVP (Operação Vale Pedágio)

---

## 🔒 Segurança e Compliance

### Assinatura Digital
- **Obrigatória** para documentos oficiais (CIOT, OVP)
- **Algoritmo:** RSA-SHA1
- **Canonização:** C14N
- **Envelope:** XML Digital Signature

### Dados Sensíveis
- CPF/CNPJ sempre validados
- Certificados digitais para autenticação
- Tokens específicos por empresa
- Logs de auditoria para compliance

### Regulamentação
- **ANTT** - Agência Nacional de Transportes Terrestres
- **CIOT** - Conhecimento de Transporte Obrigatório
- **Vale Pedágio Obrigatório** - Lei Federal

---

## ⚡ Observações de Performance

### Compressão
- Use `CompressedSend` para payloads > 1MB
- `CompressedResponse` para respostas volumosas
- Redução típica: 60-80% do tamanho original

### Rate Limiting
- Limite de requests por token/minuto
- Retry automático em caso de throttling
- Backoff exponencial recomendado

### Timeouts
- Operações síncronas: 30s timeout
- Operações de documento: até 60s
- Consultas: 15s timeout

---

**Última Atualização:** Dezembro 2024
**Fonte:** Análise SOAP UI Project - Cargo Projeto Doug
**Versão Doc:** 1.0