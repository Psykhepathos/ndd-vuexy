# VPO Business Logic & Data Flow

> Lógica de negócio e fluxo de dados do sistema VPO

---

## 1. Conceitos de Negócio

### O que é VPO?

**Vale Pedágio Obrigatório** é um sistema onde a empresa (Tambasa) antecipa o pagamento de pedágios para transportadores. Funciona assim:

1. Tambasa compra créditos de pedágio via **NDD Cargo** (integração SemParar)
2. Emite um **Vale Pedágio** vinculado a uma placa e período
3. O veículo passa nos pedágios e o valor é debitado automaticamente
4. Elimina necessidade de dinheiro em espécie para os motoristas

### Atores

```
┌─────────────────────────────────────────────────────────────┐
│                       TAMBASA                                │
│               (Operador do Sistema VPO)                      │
│                         │                                    │
│    ┌───────────────────┼───────────────────┐                │
│    ▼                   ▼                   ▼                │
│ [Pacote]         [Transportador]       [NDD Cargo]          │
│ (carga)          (CPF ou CNPJ)         (SOAP API)           │
│    │                   │                   │                │
│    │              ┌────┴────┐              │                │
│    │              │         │              │                │
│    │         [Autônomo] [Empresa]          │                │
│    │         (CPF=11)   (CNPJ=14)         │                │
│    │              │         │              │                │
│    │              │    [Motoristas]        │                │
│    │              │    (trnmot)            │                │
│    │              │         │              │                │
│    └──────────────┴────┬────┴──────────────┘                │
│                        ▼                                     │
│                   [EMISSÃO VPO]                              │
│                        │                                     │
│                        ▼                                     │
│            [Pedágios Pagos Automaticamente]                 │
└─────────────────────────────────────────────────────────────┘
```

### Tipos de Transportadores

| Tipo | Documento | Motorista | Veículo | Dados VPO |
|------|-----------|-----------|---------|-----------|
| **Autônomo** | CPF (11 dígitos) | Ele mesmo | `transporte.numpla` | No próprio `transporte` |
| **Empresa** | CNPJ (14 dígitos) | Múltiplos (`trnmot`) | Múltiplos (`trnvei`) | Precisa completar via cache |

### Problema: Dados Incompletos

O Progress (banco principal) foi desenhado há décadas e **não tem todos os campos necessários para VPO**:

**Campos do VPO que faltam no Progress:**
- CPF do motorista (empresas)
- RNTRC do motorista (empresas)
- Nome da mãe (geralmente vazio)
- Data de nascimento (motoristas)

**Solução implementada:**
1. Cache SQLite (`motorista_empresa_cache`) armazena dados complementares
2. Usuário completa dados faltantes uma única vez
3. Sistema mescla dados Progress + Cache automaticamente

---

## 2. Fluxo de Dados

### 2.1 Sincronização de Transportador

```
┌─────────────┐     ┌───────────────┐     ┌─────────────────┐
│   Progress  │────▶│ VpoDataSync   │────▶│ VpoTransportador│
│   (JDBC)    │     │   Service     │     │     Cache       │
└─────────────┘     └───────────────┘     └─────────────────┘
       │                   │                      │
       │                   ▼                      │
       │            ┌─────────────┐               │
       │            │  ANTT API   │               │
       │            │ (opcional)  │               │
       │            └─────────────┘               │
       │                   │                      │
       │                   ▼                      │
       │            ┌─────────────┐               │
       └───────────▶│ Motorista   │◀──────────────┘
         (trnmot)   │ Empresa     │    (merge)
                    │   Cache     │
                    └─────────────┘
```

### 2.2 Fluxo de Emissão VPO

```
[1. PACOTE]
     │
     ▼
┌─────────────┐
│ API: GET    │───▶ PUB.pacote ───▶ { codpac, codtrn, destino }
│ /pacotes/ID │
└─────────────┘
     │
     ▼
[2. TRANSPORTADOR]
     │
     ▼
┌─────────────┐
│ API: POST   │───▶ syncTransportador() ───▶ Progress + ANTT + Cache
│ /vpo/sync   │
└─────────────┘
     │
     ▼
[2.5 MOTORISTA (se empresa)]
     │
     ▼
┌─────────────┐
│ API: GET    │───▶ is_empresa? ───▶ tem_motoristas?
│ /verificar  │          │                 │
└─────────────┘          │                 ▼
     │                   │      ┌─────────────────┐
     │                   │      │ Listar trnmot   │
     │                   │      │ + merge cache   │
     │                   │      └─────────────────┘
     │                   │                 │
     │                   │                 ▼
     │                   │      ┌─────────────────┐
     │                   │      │ dados_completos?│
     │                   │      │    │      │     │
     │                   │      │   SIM    NÃO    │
     │                   │      │    │      │     │
     │                   │      │    ▼      ▼     │
     │                   │      │ usar  formulário │
     │                   │      └─────────────────┘
     │                   │                 │
     ▼                   ▼                 ▼
[3. VEÍCULO]
     │
     ▼
┌─────────────┐
│ API: POST   │───▶ NDD Cargo: statusVeiculo(placa)
│ /emissao    │
│   /iniciar  │
└─────────────┘
     │
     ▼
[4. ROTA]
     │
     ▼
┌─────────────┐
│ API: GET    │───▶ PUB.semPararRot + semPararRotMu
│ /rotas      │
└─────────────┘
     │
     ▼
[5. PERÍODO E CUSTO]
     │
     ▼
┌─────────────┐
│ API: POST   │───▶ NDD Cargo: roteirizarPracasPedagio()
│ /emissao    │                         │
│   /calcular │                         ▼
└─────────────┘              NDD Cargo: cadastrarRotaTemporaria()
     │                                  │
     │                                  ▼
     │                       NDD Cargo: obterCustoRota()
     │                                  │
     ▼                                  ▼
┌─────────────┐              { valor_total, pedagios[] }
│ CONFIRMAR?  │
└─────────────┘
     │
     ▼
[6. EMISSÃO]
     │
     ▼
┌─────────────┐
│ API: POST   │───▶ NDD Cargo: comprarViagem() ───▶ $$$
│ /emissao    │                         │
│  /confirmar │                         ▼
└─────────────┘              { codigo_viagem, valor, validade }
     │
     ▼
┌─────────────┐
│ API: POST   │───▶ NDD Cargo: gerarRecibo() ───▶ PDF
│ /emissao    │                         │
│   /recibo   │                         ▼
└─────────────┘              WhatsApp / Email
```

---

## 3. Regras de Negócio

### 3.1 Determinação Autônomo vs Empresa

```typescript
// REGRA: Usar tamanho do documento, NÃO a flag flgautonomo
// O campo flgautonomo no Progress é inconsistente/não confiável

function determinarTipoTransportador(codcnpjcpf: string): 'autonomo' | 'empresa' {
  const documento = codcnpjcpf.replace(/\D/g, '')  // Remove não-dígitos

  if (documento.length === 14) {
    return 'empresa'  // CNPJ
  }

  return 'autonomo'  // CPF (11 dígitos) ou documento inválido
}
```

### 3.2 Campos Obrigatórios para VPO

```typescript
// Campos que o NDD Cargo exige para emitir VPO
const CAMPOS_OBRIGATORIOS = {
  // Identificação
  cpf_cnpj: true,           // CPF do condutor (para empresas)

  // ANTT
  antt_rntrc: true,         // Registro Nacional de Transportadores

  // Condutor
  condutor_nome: true,      // Nome completo
  condutor_nome_mae: true,  // Nome da mãe (validação identidade)
  condutor_data_nascimento: true,  // Data de nascimento
}

// Para empresas com motoristas, validar no MotoristaEmpresaCache
const CAMPOS_MOTORISTA_VPO = ['cpf', 'rntrc', 'nommot', 'nommae', 'data_nascimento']
```

### 3.3 Score de Qualidade

O score indica a completude dos dados para VPO:

```typescript
const PESOS_SCORE = {
  // Críticos (50 pontos)
  cpf_cnpj: 15,
  antt_rntrc: 15,
  condutor_nome_mae: 10,
  condutor_data_nascimento: 10,

  // Importantes (25 pontos)
  placa: 10,
  condutor_nome: 10,
  condutor_rg: 5,

  // Complementares (25 pontos)
  endereco_rua: 5,
  endereco_cidade: 5,
  endereco_estado: 5,
  contato_celular: 5,
  contato_email: 5,
}

// Total: 100 pontos

// Interpretação:
// 90-100: Excelente - Todos dados presentes
// 70-89:  Bom - Pode emitir VPO
// 50-69:  Regular - Funcional mas com lacunas
// 0-49:   Incompleto - Precisa completar dados
```

### 3.4 Fluxo de Completar Dados de Motorista

```typescript
// Quando empresa é selecionada:

// 1. Verificar se precisa seleção
const verificacao = await api.get(`/vpo/motoristas/${codtrn}/verificar`)

if (!verificacao.is_empresa) {
  // Autônomo - usar dados do transporte
  return usarDadosTransporte(codtrn)
}

if (!verificacao.tem_motoristas) {
  // Empresa sem motoristas - usar fallback
  return usarDadosTransporteFallback(codtrn)
}

// 2. Listar motoristas e verificar completude
const motoristas = await api.get(`/vpo/motoristas/${codtrn}`)

// 3. Filtrar prontos para VPO
const completos = motoristas.data.filter(m => m.dados_completos)

if (completos.length === 0) {
  // Nenhum completo - usuário DEVE completar dados
  throw new Error('Complete os dados de pelo menos um motorista')
}

// 4. Deixar usuário selecionar ou completar
```

### 3.5 Prioridade de Dados (Merge)

Quando temos dados de múltiplas fontes:

```
┌─────────────────────────────────────────────────────────┐
│              PRIORIDADE DE DADOS                         │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  1º Cache Motorista (SQLite)   ◀─── Usuário editou      │
│           │                                              │
│           ▼                                              │
│  2º Progress trnmot            ◀─── Dados originais     │
│           │                                              │
│           ▼                                              │
│  3º Progress transporte        ◀─── Fallback            │
│           │                                              │
│           ▼                                              │
│  4º Valor padrão              ◀─── Último recurso       │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

Implementação:
```typescript
// No mapEmpresaData():

// CPF: Cache > Progress (Progress geralmente não tem)
const cpf = cache?.cpf || motorista.codcpf || ''

// RNTRC: Cache > trnmot > transporte
const rntrc = cache?.rntrc || motorista.codrntrc || transporte.cdantt

// Nome mãe: Cache > Progress
const nomeMae = cache?.nommae || motorista.nommae || ''

// Data nascimento: Cache > Progress
const dataNasc = cache?.data_nascimento || motorista.datnas || ''
```

---

## 4. Integração NDD Cargo

### 4.1 Métodos SOAP

| Método | Fase | Descrição | Custo |
|--------|------|-----------|-------|
| `autenticarUsuario` | Auth | Obtém token | Gratuito |
| `statusVeiculo` | Validação | Verifica placa | Gratuito |
| `roteirizarPracasPedagio` | Rota | Busca praças | Gratuito |
| `cadastrarRotaTemporaria` | Rota | Registra rota | Gratuito |
| `obterCustoRota` | Custo | Calcula valor | Gratuito |
| **`comprarViagem`** | Emissão | **COMPRA VPO** | **$$$** |
| `obterRecibo` | Recibo | Dados XML | Gratuito |
| `gerarRecibo` | Recibo | PDF/WhatsApp | Gratuito |
| `cancelarViagem` | Gestão | Cancela VPO | Estorno |
| `reemitirViagem` | Gestão | Altera placa | Gratuito |
| `consultarViagens` | Consulta | Lista VPOs | Gratuito |

### 4.2 Flags de Segurança

```env
# .env

# Permite queries SOAP (consultas, validações)
ALLOW_SOAP_QUERIES=true

# Permite compra real de VPO ($$$ DINHEIRO REAL $$$)
ALLOW_SOAP_PURCHASE=false  # CUIDADO! Só ativar em produção
```

### 4.3 Estrutura XML dos Pedágios

```xml
<!-- Retorno de roteirizarPracasPedagio -->
<PracasPedagio>
  <PracaPedagio>
    <Codigo>P001</Codigo>
    <Nome>Praça São Paulo Sul</Nome>
    <Rodovia>BR-116</Rodovia>
    <Km>350</Km>
    <Cidade>São Paulo</Cidade>
    <UF>SP</UF>
    <Latitude>-23.5505</Latitude>
    <Longitude>-46.6333</Longitude>
  </PracaPedagio>
</PracasPedagio>
```

---

## 5. Estados e Transições

### 5.1 Estados da Emissão

```
                    ┌─────────────────┐
                    │      IDLE       │
                    │   (inicial)     │
                    └────────┬────────┘
                             │ iniciar()
                             ▼
                    ┌─────────────────┐
       ┌───────────│    INICIADO     │
       │           │                 │
       │           └────────┬────────┘
       │                    │ calcular()
       │                    ▼
       │           ┌─────────────────┐
       │           │   CALCULANDO    │
       │           │                 │
       │           └────────┬────────┘
       │                    │ sucesso
       │                    ▼
       │           ┌─────────────────┐
       │           │   AGUARDANDO    │◀─┐
       │           │   CONFIRMAÇÃO   │  │
       │           └───┬─────────┬───┘  │
       │    confirmar()│         │recalcular
       │               ▼         │      │
       │      ┌────────────────┐ │      │
       │      │    EMITINDO    │─┘      │
       │      └───────┬────────┘        │
       │              │ sucesso         │
       │              ▼                 │
       │      ┌─────────────────┐       │
       │      │    CONCLUÍDO    │       │
       │      │   (final OK)    │       │
       │      └─────────────────┘       │
       │                                │
       │ cancelar()    ┌─────────────────┐
       └──────────────▶│   CANCELADO     │
                       │   (final)       │
                       └─────────────────┘

                       ┌─────────────────┐
        erro em ──────▶│      ERRO       │───▶ reiniciar()
        qualquer etapa │                 │
                       └─────────────────┘
```

### 5.2 Estados do Motorista

```
┌─────────────────────────────────────────────────────────┐
│                   ESTADOS DO MOTORISTA                   │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  [SEM CACHE]                                            │
│      │                                                   │
│      │ usuário salva dados                              │
│      ▼                                                   │
│  [COM CACHE - INCOMPLETO]                               │
│  campos_faltantes.length > 0                            │
│      │                                                   │
│      │ usuário completa todos campos                    │
│      ▼                                                   │
│  [COM CACHE - COMPLETO]                                 │
│  dados_completos = true                                 │
│  campos_faltantes = []                                  │
│      │                                                   │
│      │ pode emitir VPO                                  │
│      ▼                                                   │
│  [USADO EM VPO]                                         │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 6. Tabelas do Sistema

### 6.1 Progress (Somente Leitura para VPO)

```sql
-- Transportadores
PUB.transporte
├── codtrn (PK)
├── nomtrn
├── codcnpjcpf      -- CPF (11) ou CNPJ (14)
├── flgautonomo     -- NÃO USAR! Usar length(codcnpjcpf)
├── numpla          -- Placa (autônomos)
├── cdantt          -- RNTRC (pode estar vazio)
├── datnas, numrg, nommae, etc.

-- Motoristas de Empresas
PUB.trnmot
├── codtrn (FK)
├── codmot (PK composta)
├── nommot          -- Nome
├── numrg           -- RG
├── nompai, nommae  -- Filiação
├── codrntrc        -- RNTRC (geralmente vazio)
├── datnas          -- Data nascimento

-- Veículos de Empresas
PUB.trnvei
├── codtrn (FK)
├── numpla (PK composta)
├── tipcam
├── modvei

-- Pacotes
PUB.pacote
├── codpac (PK)
├── codtrn (FK)
├── codmot
├── sitpac, datforpac, etc.
```

### 6.2 SQLite (Cache Local)

```sql
-- Cache de Transportadores para VPO
vpo_transportadores_cache
├── id (PK)
├── codtrn, codmot, numpla
├── flgautonomo
├── cpf_cnpj, antt_rntrc, antt_nome, etc. (19 campos VPO)
├── fontes_dados (JSON)
├── score_qualidade
├── campos_faltantes (JSON)
├── editado_manualmente
├── created_at, updated_at

-- Cache de Motoristas de Empresas
motorista_empresa_cache
├── id (PK)
├── codtrn, codmot (UNIQUE)
├── nommot, numrg, nompai, nommae  -- Espelho Progress
├── cpf, rntrc, data_nascimento    -- Completados pelo usuário
├── cnh, categoria_cnh, validade_cnh
├── endereco_*                      -- Endereço
├── dados_completos                 -- Flag automático
├── sincronizado_progress           -- Para futura sync
├── created_by, updated_by
├── created_at, updated_at
```

---

## 7. Checklist de Implementação Frontend

### Páginas

- [ ] `/vpo` - Dashboard VPO
- [ ] `/vpo/emissao` - Lista de emissões
- [ ] `/vpo/emissao/nova` - Wizard de emissão
- [ ] `/vpo/transportadores` - Lista transportadores
- [ ] `/vpo/transportadores/:codtrn` - Detalhes/edição
- [ ] `/vpo/motoristas/:codtrn` - Motoristas da empresa
- [ ] `/vpo/historico` - Histórico de VPOs

### Componentes

- [ ] `VpoWizardSteps` - Indicador de etapas
- [ ] `VpoStep1Pacote` - Seleção de pacote
- [ ] `VpoStep2Transportador` - Info transportador
- [ ] `VpoStep2Motorista` - Seleção motorista (empresas)
- [ ] `VpoStep3Veiculo` - Seleção veículo
- [ ] `VpoStep4Rota` - Seleção rota
- [ ] `VpoStep5Periodo` - Período e cálculo
- [ ] `VpoStep6Confirmacao` - Resumo e confirmação
- [ ] `VpoMotoristaForm` - Formulário dados motorista
- [ ] `VpoMotoristaCard` - Card de motorista
- [ ] `VpoScoreBadge` - Badge de score
- [ ] `VpoCamposFaltantes` - Lista campos faltantes

### Composables

- [ ] `useVpoEmissao` - Lógica do wizard
- [ ] `useMotoristaEmpresa` - Motoristas de empresas
- [ ] `useVpoTransportadores` - Lista/cache transportadores

### Validações

- [ ] CPF (11 dígitos, algoritmo)
- [ ] CNPJ (14 dígitos, algoritmo)
- [ ] RNTRC (8-14 dígitos)
- [ ] Placa (antiga ou Mercosul)
- [ ] Data nascimento (18-80 anos)

---

**Última atualização:** 2025-12-09
**Versão:** 1.0.0
