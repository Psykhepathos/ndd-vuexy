# Análise e Plano de Implementação: Compra de Viagem SemParar

## 📋 Visão Geral

**Sistema Original:** `C:\Users\15857\Desktop\corporativo\SemParar\compraRota.p`
**Objetivo:** Recriar o sistema de compra de viagens SemParar na interface web (Vue.js + Laravel)
**Abordagem:** Implementação incremental e testável por etapas

---

## 🔍 Análise do Sistema Original

### **Fluxo Principal do compraRota.p:**

```
1. Validar Pacote (codpac)
   ├─ Verifica se pacote existe
   ├─ Verifica se é TCD quando não deve ser
   ├─ Carrega transporte associado
   └─ Carrega rota (introt)

2. Validar Placa
   ├─ Verifica placa no ERP (trnvei)
   ├─ Chama API SemParar: statusVei()
   └─ Solicita confirmação de eixos (2-10)

3. Selecionar Rota SemParar
   ├─ Auto-preenche baseado no introt do pacote
   ├─ Filtra por CD/Retorno conforme mode
   └─ Valida flags (flgCD, flgRetorno)

4. Roteirização
   ├─ OPÇÃO A: Personalizado (usuário seleciona municípios)
   │   └─ retornoCa() → retornoTemp()
   └─ OPÇÃO B: Automático (baseado na rota padrão + pedidos)
       └─ roterizaCa()

5. Verificar Preço
   └─ verificaPreco() → API SemParar

6. Comprar Viagem
   ├─ compraViagem() → API SemParar
   ├─ Salva em PUB.sPararViagem
   ├─ Salva log em PUB.semPararRotMuLog
   └─ criaRecibo()

7. Imprimir/Enviar Recibo
   └─ obterReciboViagem() + envio por email/WhatsApp
```

---

## 🗂️ Tabelas Progress Envolvidas

### **Leitura:**
- `PUB.pacote` - Dados do pacote
- `PUB.transporte` - Transportadora
- `PUB.trnvei` - Veículos do transportador
- `PUB.introt` - Rota do pacote
- `PUB.semPararRot` - Rotas SemParar cadastradas
- `PUB.semPararRotMu` - Municípios da rota
- `PUB.semPararIntrot` - Vínculo introt ↔ rota SemParar
- `PUB.paccd` - Pacotes TCD (validação)
- `PUB.pacsoc` - Pacotes socilitários
- `PUB.carga` - Cargas do pacote
- `PUB.pedido` - Pedidos da carga
- `PUB.cliente` - Clientes dos pedidos
- `PUB.arqrdnt` - Coordenadas GPS dos pedidos
- `PUB.municipio` - Dados de municípios
- `PUB.estado` - Dados de estados
- `PUB.usuario` - Email para notificações

### **Escrita:**
- `PUB.sPararViagem` - Registro da viagem comprada
- `PUB.semPararRotMuLog` - Log dos municípios usados na viagem

---

## 🌐 APIs SemParar (SOAP Web Service)

**Host:** `http://192.168.19.35:5000` (SOAP)
**Autenticação:** Token de sessão (`cToken`)

### **Métodos SOAP Utilizados:**

1. **`obterStatusVeiculo(placa, token)`**
   - Retorna: descricao, eixos, proprietario, tag
   - Validação inicial do veículo

2. **`roteirizarPracasPedagio(pontosXML, opcoesXML, token)`**
   - Entrada: XML com pontos (lat/lon ou IBGE)
   - Saída: XML com praças de pedágio da rota
   - Utilizado tanto em modo automático quanto personalizado

3. **`cadastrarRotaTemporaria(pracasXML, nomeRota, token)`**
   - Entrada: XML com IDs das praças
   - Saída: Código da rota temporária (codRotaSemParar)

4. **`obterCustoRota(nomeRota, placa, eixos, dataInicio, dataFim, token)`**
   - Retorna: Preço total da viagem

5. **`comprarViagem(rota, placa, eixos, dataInicio, dataFim, itemFin1, token)`**
   - Compra efetiva da viagem
   - Retorna: Número da viagem (codViagem)

6. **`obterReciboViagem(codViagem, token)`**
   - Retorna: XML com dados do recibo

### **APIs REST Internas (Python):**

- `POST http://192.168.19.35:5001/gerar-vale-pedagio` - Gera PDF do recibo
- `POST http://192.168.19.35:5000/imprimir-vale-pedagio` - Imprime recibo
- `POST http://192.168.19.35:5000/obter-recibo-viagem` - Obter recibo em PDF

---

## 🎯 Plano de Implementação por Etapas

### ✅ **FASE 1: Estrutura Base e Menu** (2h)

**Objetivo:** Criar interface básica funcional

**Backend:**
- Criar controller `CompraViagemController.php`
- Adicionar rotas em `routes/api.php`
- Método: `GET /api/compra-viagem/initialize` (retorna configs iniciais)

**Frontend:**
- Criar página `resources/ts/pages/compra-viagem/index.vue`
- Adicionar no menu `resources/ts/navigation/vertical/ndd.ts`
- Layout baseado em Vuexy form template
- Toggle para CD/Outros/Retorno

**Teste:**
- [ ] Menu aparece corretamente
- [ ] Página carrega sem erros
- [ ] Toggle CD/Outros/Retorno funciona

---

### ✅ **FASE 2: Validação de Pacote** (3h)

**Objetivo:** Validar pacote e carregar dados relacionados

**Backend:**
```php
POST /api/compra-viagem/validar-pacote
{
  "codpac": 3043824,
  "flgcd": false,
  "flgretorno": false
}

Response:
{
  "success": true,
  "data": {
    "pacote": {...},
    "transporte": {...},
    "introt": {...},
    "isTCD": false
  }
}
```

**ProgressService Methods:**
- `validatePackageForCompraViagem($codpac, $flgcd)`
- Validações:
  - Pacote existe?
  - Tem transporte associado?
  - É TCD quando não deveria?
  - Tem rota (introt)?

**Frontend:**
- Campo autocomplete para pacote
- Display de informações após validação
- Checkbox de verificação "Pacote OK"

**Teste:**
- [ ] Validação de pacote inexistente
- [ ] Validação de pacote TCD em modo "Outros"
- [ ] Validação de pacote sem transporte
- [ ] Carregamento correto dos dados

---

### ✅ **FASE 3: Validação de Placa** (4h)

**Objetivo:** Validar placa no ERP e SemParar API

**Backend:**
```php
POST /api/compra-viagem/validar-placa
{
  "placa": "ABC1234",
  "codtrn": 123
}

Response:
{
  "success": true,
  "data": {
    "erp": {
      "modelo": "VOLVO FH",
      "exists": true
    },
    "semparar": {
      "descricao": "VOLVO FH 540",
      "eixos": "5",
      "proprietario": "TAMBASA",
      "tag": "123456789"
    }
  }
}
```

**Service:**
- Criar `SemPararService.php`
- Método: `obterStatusVeiculo($placa, $token)`
- Integração com SOAP API

**ProgressService:**
- `getVeiculoByPlaca($placa, $codtrn)`

**Frontend:**
- Campo placa com autocomplete
- Modal para confirmar/editar eixos
- Display de informações do veículo
- Checkbox "Placa OK"

**Teste:**
- [ ] Validação de placa não cadastrada no ERP
- [ ] Validação de placa não cadastrada no SemParar
- [ ] Edição de eixos (2-10)
- [ ] Confirmação de dados do veículo

---

### ✅ **FASE 4: Seleção e Processamento de Rota** (5h)

**Objetivo:** Selecionar rota SemParar e processar pontos de parada

**Backend:**
```php
POST /api/compra-viagem/processar-rota
{
  "spararrotid": 204,
  "codpac": 3043824,
  "flgretorno": false,
  "personalizado": false,
  "pontos_customizados": [] // Se personalizado = true
}

Response:
{
  "success": true,
  "data": {
    "pontos": [
      {
        "seqped": 1,
        "descidade": "BELO HORIZONTE",
        "desestado": "MG",
        "codibge": "3106200",
        "latitude": "-19.9167",
        "longitude": "-43.9345",
        "end_destinatario": "RUA ABC 123"
      },
      ...
    ],
    "nome_rota_semparar": "204 - PP - MG 123456-12",
    "cod_rota_semparar": "987654",
    "pracas": [...] // Lista de praças retornadas pela roteirização
  }
}
```

**SemPararService Methods:**
- `roterizaCa($flgretorno, $spararrotid, $codpac)` - Roteirização automática
- `retornoCa($flgretorno, $spararrotid, $codpac)` - Carrega pontos para personalização
- `retornoTemp($pontos, $spararrotid, $codpac)` - Roteirização personalizada
- `roteirizarPracasPedagio($pontosXML, $opcoesXML, $token)`
- `cadastrarRotaTemporaria($pracasXML, $nomeRota, $token)`

**ProgressService:**
- `getRoterizationPoints($flgretorno, $spararrotid, $codpac)`
  - Carrega municípios de `semPararRotMu`
  - Se não é CD: carrega entregas reais de `pedido` + `arqrdnt`
  - Aplica regras de filtro (PA, AC/AM, etc.)
  - Retorna array de pontos

**Frontend:**
- Autocomplete de rota SemParar
- Botão "Personalizar Pontos"
- Modal de personalização (se escolhido):
  - Lista drag & drop de municípios
  - Adicionar/remover municípios
  - Salvar customização
- Display de rota gerada
- Checkbox "Rota OK"

**Teste:**
- [ ] Seleção de rota automática
- [ ] Filtragem por CD/Retorno
- [ ] Personalização de pontos
- [ ] Roteirização via SemParar API
- [ ] Criação de rota temporária

---

### ✅ **FASE 5: Verificação de Preço** (2h)

**Objetivo:** Calcular preço da viagem

**Backend:**
```php
POST /api/compra-viagem/verificar-preco
{
  "nome_rota": "204 - PP - MG 123456-12",
  "placa": "ABC1234",
  "eixos": "5",
  "data_inicio": "2025-10-25",
  "data_fim": "2025-10-30"
}

Response:
{
  "success": true,
  "data": {
    "preco": "1234.56"
  }
}
```

**SemPararService:**
- `verificaPreco($nomeRota, $placa, $eixos, $dataInicio, $dataFim, $token)`

**Frontend:**
- Modal exibindo preço
- Botões: "Comprar" / "Cancelar"
- Checkbox "Valor OK"

**Teste:**
- [ ] Cálculo de preço via API
- [ ] Display correto do valor
- [ ] Opção de cancelar antes da compra

---

### ✅ **FASE 6: Compra de Viagem** (4h)

**Objetivo:** Efetivar compra e salvar no Progress

**Backend:**
```php
POST /api/compra-viagem/comprar
{
  "nome_rota": "204 - PP - MG 123456-12",
  "cod_rota_semparar": "987654",
  "placa": "ABC1234",
  "eixos": "5",
  "data_inicio": "2025-10-25",
  "data_fim": "2025-10-30",
  "codpac": 3043824,
  "codtrn": 123,
  "valor": "1234.56",
  "spararrotid": 204,
  "pontos_customizados": [] // Se houve personalização
}

Response:
{
  "success": true,
  "data": {
    "cod_viagem": "123456789",
    "numero_viagem": "123456789"
  }
}
```

**SemPararService:**
- `compraViagem($rota, $placa, $eixos, $dataInicio, $dataFim, $itemFin, $token)`

**ProgressService:**
- `saveSPararViagem($data)` - Salva em `PUB.sPararViagem`
- `saveSemPararRotMuLog($pontos, $codViagem)` - Salva log dos municípios

**Frontend:**
- Modal de confirmação final
- Loader durante compra
- Display de sucesso com número da viagem
- Checkbox "Compra OK"

**Teste:**
- [ ] Compra via SemParar API
- [ ] Salvamento correto no Progress
- [ ] Log de municípios salvo
- [ ] Tratamento de erros da API

---

### ✅ **FASE 7: Geração de Recibo** (3h)

**Objetivo:** Gerar e enviar recibo

**Backend:**
```php
POST /api/compra-viagem/gerar-recibo
{
  "cod_viagem": "123456789",
  "codtrn": 123,
  "email": "user@tambasa.com.br",
  "imprimir": true
}

Response:
{
  "success": true,
  "data": {
    "status": "success",
    "url_pdf": "http://192.168.19.35:5001/recibos/123456789.pdf"
  }
}
```

**SemPararService:**
- `obterReciboViagem($codViagem, $token)`
- `criaRecibo($codViagem, $codtrn, $email, $flgImprime)`

**Frontend:**
- Modal "Deseja imprimir recibo?"
- Envio automático por email
- Opção de download do PDF
- Mensagem de sucesso final

**Teste:**
- [ ] Geração de recibo via API
- [ ] Envio por email
- [ ] Download de PDF
- [ ] Impressão automática (se escolhido)

---

## 🧪 Estratégia de Testes

### **Testes por Fase:**
Cada fase deve ser testada individualmente antes de prosseguir para a próxima.

### **Dados de Teste:**
- **Pacote:** 3043824 (já usado anteriormente)
- **Placa:** Usar placa real do transportador
- **Rota:** 204 (já cadastrada)

### **Testes de Integração:**
- Fluxo completo CD
- Fluxo completo Outros
- Fluxo completo Retorno
- Fluxo com personalização de pontos

---

## 🚨 Pontos de Atenção

### **1. API SemParar (SOAP)**
- Requer conexão SOAP complexa (WSDL)
- Resposta em XML precisa ser parseada
- Tratamento de erros via `verificaErro()`
- Token de sessão precisa ser gerenciado

### **2. Progress Database**
- Não suporta transações
- SQLs devem ser single-line
- Usa JDBC direto (não Eloquent)

### **3. Regras de Negócio Complexas**
- Filtros especiais para estados PA, AC/AM
- Lógica de "retorno" para rotas não-CD
- Pacotes TCD vs normais
- Pacotes "socilitários" (pacsoc)

### **4. Emails e Arquivos CSV**
- Sistema gera CSVs temporários
- Envia emails com anexos
- Arquivos devem ser limpos após uso

---

## 📦 Dependências Técnicas

### **Backend (Laravel):**
```bash
composer require php-soap/ext-soap  # Para SOAP client
```

### **Frontend (Vue):**
- `vuedraggable` - Já instalado (para reordenar pontos)
- `VAutocomplete` - Já disponível (Vuetify)
- `VDataTable` - Já disponível (Vuetify)

---

## 🎨 Design da Interface

**Baseado em:**
- `resources/ts/pages/rotas-padrao/mapa/[id].vue` (formulário com etapas)
- `resources/ts/pages/vale-pedagio/index.vue` (layout de processo)

**Layout Sugerido:**
```
┌─────────────────────────────────────────────────────────┐
│  🚛 COMPRA DE VIAGEM SEMPARAR - CD | OUTROS | RETORNO  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────┐  ┌──────────────────────────────┐ │
│  │  ETAPAS         │  │  FORMULÁRIO                  │ │
│  │                 │  │                              │ │
│  │  ✅ Pacote      │  │  Código Pacote: ________    │ │
│  │  ⏸️ Transporte  │  │  Transportador: Carregando │ │
│  │  ⏸️ Placa       │  │  Rota: __________________   │ │
│  │  ⏸️ Rota        │  │                              │ │
│  │  ⏸️ Preço       │  │                              │ │
│  │  ⏸️ Compra      │  │                              │ │
│  └─────────────────┘  └──────────────────────────────┘ │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  INFORMAÇÕES DA COMPRA                           │  │
│  │  Rota SemParar: ________________________________  │  │
│  │  Código Rota: __________________________________  │  │
│  │  Valor Viagem: R$ 0,00                           │  │
│  │  Número Viagem: ________________________________  │  │
│  │  Início: __/__/____ Fim: __/__/____             │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Estimativa de Tempo

| Fase | Descrição | Tempo Estimado |
|------|-----------|----------------|
| 1 | Estrutura Base e Menu | 2h |
| 2 | Validação de Pacote | 3h |
| 3 | Validação de Placa | 4h |
| 4 | Seleção e Processamento de Rota | 5h |
| 5 | Verificação de Preço | 2h |
| 6 | Compra de Viagem | 4h |
| 7 | Geração de Recibo | 3h |
| **TOTAL** | **23 horas** |

---

## ✅ Critérios de Sucesso

1. ✅ Cada fase funciona independentemente
2. ✅ Fluxo completo CD funciona
3. ✅ Fluxo completo Outros funciona
4. ✅ Fluxo completo Retorno funciona
5. ✅ Personalização de pontos funciona
6. ✅ Integração com SemParar API funciona
7. ✅ Dados salvos corretamente no Progress
8. ✅ Recibo gerado e enviado com sucesso

---

## 🚀 Próximos Passos

1. ✅ Revisar e aprovar este plano
2. ⏳ Implementar Fase 1
3. ⏳ Testar Fase 1
4. ⏳ Implementar Fase 2
5. ... (continuar fase por fase)

---

**Pronto para começar a implementação?** 🎯
