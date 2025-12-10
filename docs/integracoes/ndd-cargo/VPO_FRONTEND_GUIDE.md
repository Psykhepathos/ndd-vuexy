# VPO Frontend Development Guide

> Documentação completa para desenvolvimento do frontend do sistema de Vale Pedágio Obrigatório (VPO)

---

## Sumário

1. [Visão Geral do Negócio](#1-visão-geral-do-negócio)
2. [Arquitetura do Sistema](#2-arquitetura-do-sistema)
3. [APIs Disponíveis](#3-apis-disponíveis)
4. [Fluxos de Usuário](#4-fluxos-de-usuário)
5. [Estruturas de Dados](#5-estruturas-de-dados)
6. [Componentes Frontend Necessários](#6-componentes-frontend-necessários)
7. [Validações e Regras de Negócio](#7-validações-e-regras-de-negócio)
8. [Estados e Transições](#8-estados-e-transições)
9. [Tratamento de Erros](#9-tratamento-de-erros)
10. [Exemplos de Implementação](#10-exemplos-de-implementação)

---

## 1. Visão Geral do Negócio

### O que é VPO?

**Vale Pedágio Obrigatório (VPO)** é um sistema de pagamento antecipado de pedágios para transportadoras. A Tambasa (empresa) compra créditos de pedágio para seus transportadores (autônomos ou empresas) antes das viagens.

### Atores do Sistema

| Ator | Descrição |
|------|-----------|
| **Transportador Autônomo (CPF)** | Pessoa física que faz transporte. Dados do motorista = dados do transportador |
| **Transportador Empresa (CNPJ)** | Empresa de transporte com múltiplos motoristas e veículos |
| **Motorista** | Condutor vinculado a uma empresa (trnmot no Progress) |
| **Operador** | Usuário do sistema que emite os VPOs |

### Tipos de Transportadores

```
┌─────────────────────────────────────────────────────────────────┐
│                    TRANSPORTADORES                               │
├────────────────────────────┬────────────────────────────────────┤
│     AUTÔNOMO (CPF)         │         EMPRESA (CNPJ)             │
├────────────────────────────┼────────────────────────────────────┤
│ • Documento: 11 dígitos    │ • Documento: 14 dígitos            │
│ • 1 motorista (ele mesmo)  │ • Múltiplos motoristas (trnmot)    │
│ • 1 veículo (numpla)       │ • Múltiplos veículos (trnvei)      │
│ • Dados completos          │ • Dados incompletos (cache SQLite) │
│ • Fluxo simples            │ • Requer seleção de motorista      │
└────────────────────────────┴────────────────────────────────────┘
```

### Fluxo Macro de Emissão VPO

```
[Operador seleciona Pacote]
         ↓
[Sistema identifica Transportador]
         ↓
    ┌────┴────┐
    │         │
[AUTÔNOMO] [EMPRESA]
    │         │
    │    [Seleciona Motorista]
    │    [Completa dados faltantes]
    │         │
    └────┬────┘
         ↓
[Seleciona Veículo/Placa]
         ↓
[Seleciona Rota]
         ↓
[Calcula Custo (NDD Cargo)]
         ↓
[Confirma e Emite VPO]
         ↓
[Gera Recibo PDF/WhatsApp/Email]
```

---

## 2. Arquitetura do Sistema

### Stack Tecnológico

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
│  Vue 3.5 + TypeScript 5.8 + Vuexy + Vuetify 3.8                │
│  Port: 5173/5174/5176 (Vite dev)                               │
├─────────────────────────────────────────────────────────────────┤
│                         BACKEND                                  │
│  Laravel 12 + Sanctum Auth                                      │
│  Port: 8002 (OBRIGATÓRIO!)                                      │
├─────────────────────────────────────────────────────────────────┤
│                        DATABASES                                 │
│  Progress OpenEdge (JDBC) │ SQLite (Cache local)               │
│  192.168.80.113           │ database.sqlite                     │
├─────────────────────────────────────────────────────────────────┤
│                     EXTERNAL APIS                                │
│  NDD Cargo SOAP  │  ANTT Dados Abertos  │  Google Geocoding    │
└─────────────────────────────────────────────────────────────────┘
```

### URL Base da API

```typescript
// SEMPRE usar esta URL!
const API_BASE = 'http://localhost:8002/api'

// Endpoints VPO
const VPO_API = `${API_BASE}/vpo`
const VPO_EMISSAO_API = `${API_BASE}/vpo/emissao`
```

---

## 3. APIs Disponíveis

### 3.1 VPO - Sincronização de Transportadores

#### `GET /api/vpo/transportadores`
Lista transportadores com cache VPO.

**Query Params:**
```typescript
interface TransportadoresQuery {
  page?: number           // Default: 1
  per_page?: number       // Default: 15, Max: 100
  search?: string         // Busca por nome ou documento
  tipo?: 'autonomo' | 'empresa' | null  // Filtro por tipo
  dados_completos?: boolean  // Filtro por dados VPO completos
  score_minimo?: number   // Filtro por score de qualidade (0-100)
}
```

**Response:**
```typescript
interface TransportadoresResponse {
  success: boolean
  data: VpoTransportadorCache[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
```

#### `POST /api/vpo/sync/transportador`
Sincroniza dados de um transportador do Progress para cache.

**Request:**
```typescript
interface SyncRequest {
  codtrn: number          // Código do transportador no Progress
  codmot?: number         // Código do motorista (apenas empresas)
  placa?: string          // Placa do veículo (opcional)
  force_antt?: boolean    // Forçar atualização ANTT
}
```

**Response:**
```typescript
interface SyncResponse {
  success: boolean
  data: VpoTransportadorCache
  message: string  // "Sincronização concluída com sucesso (score: 85/100)"
}
```

#### `GET /api/vpo/transportadores/{codtrn}`
Busca transportador específico no cache.

#### `PUT /api/vpo/transportadores/{codtrn}`
Atualiza dados do transportador (edição manual).

**Request:**
```typescript
interface UpdateRequest {
  // Campos editáveis pelo usuário
  antt_rntrc?: string
  antt_validade?: string  // YYYY-MM-DD
  condutor_nome_mae?: string
  condutor_data_nascimento?: string  // YYYY-MM-DD
  endereco_rua?: string
  endereco_bairro?: string
  endereco_cidade?: string
  endereco_estado?: string  // UF (2 letras)
  endereco_cep?: string
  contato_celular?: string  // 11 dígitos
  contato_email?: string
}
```

---

### 3.2 VPO - Motoristas de Empresas (CNPJ)

#### `GET /api/vpo/motoristas/{codtrn}/verificar`
Verifica se transportador é empresa e precisa seleção de motorista.

**Response:**
```typescript
interface VerificarResponse {
  success: boolean
  codtrn: number
  is_empresa: boolean           // true se CNPJ (14 dígitos)
  tem_motoristas: boolean       // true se tem registros em trnmot
  requer_selecao_motorista: boolean  // true se is_empresa && tem_motoristas
  mensagem: string
}
```

**Uso no Frontend:**
```typescript
// Após selecionar transportador
const verificacao = await fetch(`/api/vpo/motoristas/${codtrn}/verificar`)
const data = await verificacao.json()

if (data.requer_selecao_motorista) {
  // Mostrar tela de seleção de motorista
  showMotoristaSelection(codtrn)
} else {
  // Prosseguir direto para seleção de veículo
  proceedToVehicleSelection()
}
```

#### `GET /api/vpo/motoristas/{codtrn}`
Lista todos motoristas de uma empresa.

**Response:**
```typescript
interface MotoristasResponse {
  success: boolean
  data: MotoristaEmpresa[]
  total: number
  completos: number  // Quantos têm dados completos para VPO
}

interface MotoristaEmpresa {
  codtrn: number
  codmot: number

  // Dados do Progress (trnmot)
  nommot: string           // Nome do motorista
  numrg: string            // RG
  nompai: string           // Nome do pai
  nommae: string           // Nome da mãe (pode estar vazio)
  codrntrc_progress: string  // RNTRC do Progress (geralmente vazio)

  // Dados do Cache SQLite (preenchidos pelo usuário)
  cpf: string              // CPF do motorista (obrigatório VPO)
  rntrc: string            // RNTRC (obrigatório VPO)
  data_nascimento: string  // YYYY-MM-DD (obrigatório VPO)
  cnh: string
  categoria_cnh: string    // A, B, C, D, E
  validade_cnh: string     // YYYY-MM-DD

  // Endereço
  endereco_logradouro: string
  endereco_numero: string
  endereco_bairro: string
  endereco_cidade: string
  endereco_uf: string      // UF (2 letras)
  endereco_cep: string

  // Status
  tem_cache: boolean           // true se já tem dados no SQLite
  dados_completos: boolean     // true se todos campos VPO preenchidos
  campos_faltantes: string[]   // Lista de campos faltantes
}
```

#### `GET /api/vpo/motoristas/{codtrn}/{codmot}`
Busca motorista específico.

#### `POST /api/vpo/motoristas/{codtrn}/{codmot}`
Salva/atualiza dados complementares do motorista.

**Request:**
```typescript
interface SalvarMotoristaRequest {
  cpf?: string              // 11 dígitos
  rntrc?: string            // Código RNTRC
  nommae?: string           // Nome da mãe
  data_nascimento?: string  // YYYY-MM-DD
  cnh?: string
  categoria_cnh?: string    // A, B, C, D, E
  validade_cnh?: string     // YYYY-MM-DD
  endereco_logradouro?: string
  endereco_numero?: string
  endereco_bairro?: string
  endereco_cidade?: string
  endereco_uf?: string      // UF (2 letras)
  endereco_cep?: string
}
```

**Response:**
```typescript
interface SalvarMotoristaResponse {
  success: boolean
  message: string
  data: MotoristaEmpresa
  dados_completos: boolean
  campos_faltantes: string[]
}
```

#### `GET /api/vpo/motoristas/{codtrn}/completos`
Lista apenas motoristas com dados completos (prontos para VPO).

---

### 3.3 VPO - Emissão (NDD Cargo)

#### `POST /api/vpo/emissao/iniciar`
Inicia processo de emissão de VPO.

**Request:**
```typescript
interface IniciarEmissaoRequest {
  codpac: number          // Código do pacote
  codtrn: number          // Código do transportador
  codmot?: number         // Código do motorista (empresas)
  placa: string           // Placa do veículo
  rota_id?: number        // ID da rota SemParar (opcional)
}
```

**Response:**
```typescript
interface IniciarEmissaoResponse {
  success: boolean
  uuid: string            // UUID da emissão (usar nas próximas chamadas)
  status: 'iniciado'
  data: {
    pacote: PacoteResumo
    transportador: TransportadorResumo
    motorista?: MotoristaResumo
    veiculo: VeiculoResumo
    rota?: RotaResumo
  }
}
```

#### `GET /api/vpo/emissao/{uuid}/status`
Consulta status da emissão.

**Response:**
```typescript
interface StatusEmissaoResponse {
  success: boolean
  uuid: string
  status: 'iniciado' | 'calculando' | 'aguardando_confirmacao' |
          'emitindo' | 'concluido' | 'erro' | 'cancelado'
  etapa_atual: number     // 1-5
  etapa_nome: string
  data: EmissaoData
  erro?: string
}
```

#### `POST /api/vpo/emissao/{uuid}/calcular`
Calcula custo do VPO via NDD Cargo.

**Request:**
```typescript
interface CalcularRequest {
  data_inicio: string     // YYYY-MM-DD (início da viagem)
  data_fim: string        // YYYY-MM-DD (fim da viagem)
  quantidade_eixos: number  // 2-9
}
```

**Response:**
```typescript
interface CalcularResponse {
  success: boolean
  uuid: string
  custo: {
    valor_total: number   // Valor em R$
    pedagios: PracaPedagio[]
    rota_nome: string
    km_total: number
    tempo_estimado: string  // "4h 30min"
  }
}

interface PracaPedagio {
  codigo: string
  nome: string
  rodovia: string
  km: number
  valor: number
  cidade: string
  uf: string
}
```

#### `POST /api/vpo/emissao/{uuid}/confirmar`
Confirma e emite o VPO.

**Request:**
```typescript
interface ConfirmarRequest {
  confirmar: boolean      // true para confirmar
  observacoes?: string    // Observações opcionais
}
```

**Response:**
```typescript
interface ConfirmarResponse {
  success: boolean
  uuid: string
  status: 'concluido'
  vpo: {
    codigo_viagem: string   // Código do VPO no NDD Cargo
    data_emissao: string
    valor_total: number
    validade_inicio: string
    validade_fim: string
  }
}
```

#### `POST /api/vpo/emissao/{uuid}/recibo`
Gera recibo do VPO.

**Request:**
```typescript
interface ReciboRequest {
  enviar_whatsapp?: boolean
  enviar_email?: boolean
  telefone?: string       // Obrigatório se enviar_whatsapp
  email?: string          // Obrigatório se enviar_email
}
```

**Response:**
```typescript
interface ReciboResponse {
  success: boolean
  uuid: string
  recibo: {
    pdf_url?: string      // URL do PDF gerado
    whatsapp_enviado: boolean
    email_enviado: boolean
  }
}
```

#### `POST /api/vpo/emissao/{uuid}/cancelar`
Cancela emissão em andamento.

---

### 3.4 Rotas SemParar

#### `GET /api/semparar-rotas`
Lista rotas cadastradas.

**Query Params:**
```typescript
interface RotasQuery {
  page?: number
  per_page?: number
  search?: string         // Busca por nome
  flg_cd?: boolean        // Filtro CD
  flg_retorno?: boolean   // Filtro retorno
}
```

#### `GET /api/semparar-rotas/{id}/municipios`
Busca rota com municípios.

**Response:**
```typescript
interface RotaComMunicipios {
  sPararRotID: number
  desSPararRot: string
  flgCD: boolean
  flgRetorno: boolean
  tempoViagem: number
  municipios: Municipio[]
}

interface Municipio {
  sPararMuSeq: number     // Ordem na rota
  codMun: number
  codEst: number
  desMun: string
  desEst: string
  cdibge: number
  lat?: number            // Coordenada
  lon?: number            // Coordenada
}
```

---

### 3.5 Pacotes

#### `GET /api/pacotes`
Lista pacotes para seleção.

**Query Params:**
```typescript
interface PacotesQuery {
  page?: number
  per_page?: number
  search?: string
  codigo?: string
  transportador?: string
  situacao?: string
  apenas_recentes?: boolean  // codpac > 800000
  data_inicio?: string
  data_fim?: string
}
```

#### `GET /api/pacotes/{id}`
Detalhes do pacote.

---

## 4. Fluxos de Usuário

### 4.1 Fluxo Completo de Emissão VPO

```
┌─────────────────────────────────────────────────────────────────┐
│                    WIZARD DE EMISSÃO VPO                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [STEP 1: PACOTE]                                               │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Buscar pacote por código                               │   │
│  │ • Mostrar dados do pacote (destino, carga, data)        │   │
│  │ • Validar se pacote permite VPO                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 2: TRANSPORTADOR]                                        │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Carregar transportador do pacote (codtrn)             │   │
│  │ • Verificar tipo (CPF/CNPJ)                              │   │
│  │ • Se CNPJ: verificar motoristas                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 2.5: MOTORISTA] (apenas empresas CNPJ)                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Listar motoristas da empresa                           │   │
│  │ • Mostrar status de cada um (completo/incompleto)       │   │
│  │ • Permitir completar dados faltantes                     │   │
│  │ • Selecionar motorista para a viagem                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 3: VEÍCULO]                                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Mostrar veículos disponíveis                           │   │
│  │ • Validar placa no NDD Cargo                             │   │
│  │ • Informar quantidade de eixos                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 4: ROTA]                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Sugerir rota baseada no destino                        │   │
│  │ • Listar rotas disponíveis                               │   │
│  │ • Mostrar mapa com a rota                                │   │
│  │ • Permitir criar rota temporária                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 5: PERÍODO E CUSTO]                                      │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Selecionar data início/fim da viagem                   │   │
│  │ • Calcular custo via NDD Cargo                           │   │
│  │ • Mostrar praças de pedágio e valores                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           ↓                                      │
│  [STEP 6: CONFIRMAÇÃO]                                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Resumo completo da emissão                             │   │
│  │ • Botão de confirmação                                   │   │
│  │ • Gerar recibo (PDF/WhatsApp/Email)                      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Fluxo de Completar Dados do Motorista

```typescript
// 1. Verificar se é empresa
const verificacao = await api.get(`/vpo/motoristas/${codtrn}/verificar`)

if (verificacao.requer_selecao_motorista) {
  // 2. Listar motoristas
  const motoristas = await api.get(`/vpo/motoristas/${codtrn}`)

  // 3. Mostrar lista com status
  motoristas.data.forEach(m => {
    console.log(`${m.nommot} - ${m.dados_completos ? '✅' : '⚠️ Incompleto'}`)
    if (!m.dados_completos) {
      console.log(`  Faltam: ${m.campos_faltantes.join(', ')}`)
    }
  })

  // 4. Se usuário selecionar motorista incompleto, abrir formulário
  if (!motoristaSelected.dados_completos) {
    showFormularioComplementar(motoristaSelected)
  }

  // 5. Salvar dados complementares
  const response = await api.post(`/vpo/motoristas/${codtrn}/${codmot}`, {
    cpf: '12345678901',
    rntrc: '00000012345',
    nommae: 'Maria da Silva',
    data_nascimento: '1990-05-15'
  })

  // 6. Verificar se agora está completo
  if (response.dados_completos) {
    proceedToVehicleSelection()
  } else {
    showError(`Ainda faltam: ${response.campos_faltantes.join(', ')}`)
  }
}
```

---

## 5. Estruturas de Dados

### 5.1 VpoTransportadorCache (Cache Principal)

```typescript
interface VpoTransportadorCache {
  id: number

  // Chaves Progress
  codtrn: number
  codmot: number | null
  numpla: string | null
  flgautonomo: boolean

  // Identificação
  cpf_cnpj: string

  // ANTT/RNTRC
  antt_rntrc: string | null
  antt_nome: string | null
  antt_validade: string | null  // YYYY-MM-DD
  antt_status: 'Ativo' | 'Inativo' | 'Suspenso'
  antt_fonte: 'dados_abertos' | 'fallback' | 'manual' | 'none'

  // Veículo
  placa: string | null
  veiculo_tipo: string | null
  veiculo_modelo: string | null

  // Condutor
  condutor_rg: string | null
  condutor_nome: string | null
  condutor_sexo: 'M' | 'F'
  condutor_nome_mae: string | null
  condutor_data_nascimento: string | null  // YYYY-MM-DD

  // Endereço
  endereco_rua: string | null
  endereco_bairro: string | null
  endereco_cidade: string | null
  endereco_estado: string | null  // UF

  // Contato
  contato_celular: string | null
  contato_email: string | null

  // Metadados
  fontes_dados: {
    progress_transporte: boolean
    progress_trnmot: boolean
    progress_trnvei: boolean
    progress_tipcam: boolean
    cache_motorista: boolean      // Se usou cache de motorista empresa
    cache_motorista_id?: number   // ID do cache usado
    fallback_to_transporte: boolean
  }
  ultima_sync_progress: string
  ultima_sync_antt: string

  // Qualidade
  score_qualidade: number  // 0-100
  campos_faltantes: string[]
  avisos: string[] | null

  // Edição manual
  editado_manualmente: boolean
  data_edicao_manual: string | null

  // Uso
  ultimo_uso: string | null
  total_usos: number

  // Timestamps
  created_at: string
  updated_at: string
}
```

### 5.2 Campos Obrigatórios para VPO

```typescript
// Campos obrigatórios para emissão de VPO
const CAMPOS_OBRIGATORIOS_VPO = [
  'cpf',              // CPF do condutor (11 dígitos)
  'rntrc',            // Registro ANTT
  'nommot',           // Nome do condutor
  'nommae',           // Nome da mãe (validação)
  'data_nascimento',  // Data de nascimento
] as const

// Mapeamento de labels para UI
const CAMPOS_LABELS: Record<string, string> = {
  cpf: 'CPF do Motorista',
  rntrc: 'RNTRC (Registro ANTT)',
  nommot: 'Nome Completo',
  nommae: 'Nome da Mãe',
  data_nascimento: 'Data de Nascimento',
}
```

### 5.3 Score de Qualidade

```typescript
// Como o score é calculado
interface ScoreCalculation {
  // Campos críticos (peso maior)
  cpf_cnpj: 15,           // CPF/CNPJ preenchido
  antt_rntrc: 15,         // RNTRC válido
  condutor_nome_mae: 10,  // Nome da mãe
  condutor_data_nascimento: 10,  // Data nascimento

  // Campos importantes
  placa: 10,
  condutor_nome: 10,
  condutor_rg: 5,

  // Campos complementares
  endereco_rua: 5,
  endereco_cidade: 5,
  endereco_estado: 5,
  contato_celular: 5,
  contato_email: 5,
}

// Interpretação do score
const SCORE_LEVELS = {
  EXCELENTE: { min: 90, color: 'success', label: 'Excelente' },
  BOM: { min: 70, color: 'info', label: 'Bom' },
  REGULAR: { min: 50, color: 'warning', label: 'Regular' },
  RUIM: { min: 0, color: 'error', label: 'Incompleto' },
}
```

---

## 6. Componentes Frontend Necessários

### 6.1 Páginas Principais

```
resources/ts/pages/vpo/
├── index.vue                    # Dashboard VPO
├── emissao/
│   ├── index.vue               # Lista de emissões
│   └── nova.vue                # Wizard de nova emissão
├── transportadores/
│   ├── index.vue               # Lista transportadores
│   └── [codtrn].vue            # Detalhes/edição
├── motoristas/
│   └── [codtrn].vue            # Motoristas da empresa
└── historico/
    └── index.vue               # Histórico de VPOs
```

### 6.2 Componentes do Wizard

```
resources/ts/pages/vpo/emissao/components/
├── VpoWizardSteps.vue          # Indicador de etapas
├── VpoStep1Pacote.vue          # Seleção de pacote
├── VpoStep2Transportador.vue   # Info do transportador
├── VpoStep2Motorista.vue       # Seleção de motorista (empresas)
├── VpoStep3Veiculo.vue         # Seleção de veículo
├── VpoStep4Rota.vue            # Seleção de rota
├── VpoStep5Periodo.vue         # Período e cálculo
├── VpoStep6Confirmacao.vue     # Resumo e confirmação
├── VpoMotoristaForm.vue        # Formulário dados motorista
├── VpoMotoristaCard.vue        # Card de motorista na lista
├── VpoScoreBadge.vue           # Badge de score
└── VpoCamposFaltantes.vue      # Lista campos faltantes
```

### 6.3 Composables Sugeridos

```typescript
// resources/ts/composables/useVpo.ts
export function useVpoEmissao() {
  const uuid = ref<string | null>(null)
  const status = ref<EmissaoStatus>('idle')
  const etapaAtual = ref(1)
  const dados = ref<EmissaoData | null>(null)

  async function iniciar(params: IniciarEmissaoRequest) { ... }
  async function calcular(params: CalcularRequest) { ... }
  async function confirmar() { ... }
  async function cancelar() { ... }

  return { uuid, status, etapaAtual, dados, iniciar, calcular, confirmar, cancelar }
}

// resources/ts/composables/useMotoristaEmpresa.ts
export function useMotoristaEmpresa(codtrn: number) {
  const motoristas = ref<MotoristaEmpresa[]>([])
  const loading = ref(false)
  const isEmpresa = ref(false)
  const requerSelecao = ref(false)

  async function verificar() { ... }
  async function listar() { ... }
  async function salvar(codmot: number, dados: SalvarMotoristaRequest) { ... }

  return { motoristas, loading, isEmpresa, requerSelecao, verificar, listar, salvar }
}
```

---

## 7. Validações e Regras de Negócio

### 7.1 Validações de Campos

```typescript
// CPF
const validarCPF = (cpf: string): boolean => {
  cpf = cpf.replace(/\D/g, '')
  if (cpf.length !== 11) return false
  // Algoritmo de validação CPF...
  return true
}

// CNPJ
const validarCNPJ = (cnpj: string): boolean => {
  cnpj = cnpj.replace(/\D/g, '')
  if (cnpj.length !== 14) return false
  // Algoritmo de validação CNPJ...
  return true
}

// RNTRC
const validarRNTRC = (rntrc: string): boolean => {
  rntrc = rntrc.replace(/\D/g, '')
  return rntrc.length >= 8 && rntrc.length <= 14
}

// Placa
const validarPlaca = (placa: string): boolean => {
  placa = placa.replace(/[^A-Z0-9]/gi, '').toUpperCase()
  // Placa antiga: AAA1234
  // Placa Mercosul: AAA1A23
  const regexAntiga = /^[A-Z]{3}[0-9]{4}$/
  const regexMercosul = /^[A-Z]{3}[0-9][A-Z][0-9]{2}$/
  return regexAntiga.test(placa) || regexMercosul.test(placa)
}

// Data de nascimento (maior de 18)
const validarDataNascimento = (data: string): boolean => {
  const nascimento = new Date(data)
  const hoje = new Date()
  const idade = hoje.getFullYear() - nascimento.getFullYear()
  return idade >= 18 && idade <= 80
}
```

### 7.2 Regras de Negócio

```typescript
// Determinação de tipo de transportador
const isEmpresa = (documento: string): boolean => {
  const doc = documento.replace(/\D/g, '')
  return doc.length === 14  // CNPJ
}

const isAutonomo = (documento: string): boolean => {
  const doc = documento.replace(/\D/g, '')
  return doc.length === 11  // CPF
}

// Verificar se pode emitir VPO
const podeEmitirVPO = (transportador: VpoTransportadorCache): boolean => {
  // Score mínimo
  if (transportador.score_qualidade < 50) return false

  // Campos obrigatórios
  if (!transportador.cpf_cnpj) return false
  if (!transportador.antt_rntrc) return false
  if (!transportador.condutor_nome_mae) return false
  if (!transportador.condutor_data_nascimento) return false

  return true
}

// Verificar se motorista pode ser usado para VPO
const motoristaProntoParaVPO = (motorista: MotoristaEmpresa): boolean => {
  return motorista.dados_completos && motorista.campos_faltantes.length === 0
}
```

---

## 8. Estados e Transições

### 8.1 Estados da Emissão

```typescript
type EmissaoStatus =
  | 'idle'                    // Inicial
  | 'iniciado'                // Emissão criada
  | 'calculando'              // Calculando custo
  | 'aguardando_confirmacao'  // Custo calculado, aguarda confirmação
  | 'emitindo'                // Enviando para NDD Cargo
  | 'concluido'               // VPO emitido com sucesso
  | 'erro'                    // Erro na emissão
  | 'cancelado'               // Cancelado pelo usuário

// Transições permitidas
const TRANSICOES: Record<EmissaoStatus, EmissaoStatus[]> = {
  idle: ['iniciado'],
  iniciado: ['calculando', 'cancelado'],
  calculando: ['aguardando_confirmacao', 'erro'],
  aguardando_confirmacao: ['emitindo', 'calculando', 'cancelado'],
  emitindo: ['concluido', 'erro'],
  concluido: [],  // Estado final
  erro: ['iniciado', 'cancelado'],  // Pode reiniciar
  cancelado: [],  // Estado final
}
```

### 8.2 Estados do Wizard

```typescript
interface WizardState {
  etapaAtual: number  // 1-6
  etapasCompletas: number[]
  dados: {
    pacote?: PacoteResumo
    transportador?: TransportadorResumo
    motorista?: MotoristaResumo  // Apenas empresas
    veiculo?: VeiculoResumo
    rota?: RotaResumo
    periodo?: {
      dataInicio: string
      dataFim: string
      eixos: number
    }
    custo?: CustoResumo
  }
}

// Navegação
const podeAvancar = (state: WizardState): boolean => {
  switch (state.etapaAtual) {
    case 1: return !!state.dados.pacote
    case 2: return !!state.dados.transportador &&
                   (!state.dados.transportador.isEmpresa || !!state.dados.motorista)
    case 3: return !!state.dados.veiculo
    case 4: return !!state.dados.rota
    case 5: return !!state.dados.custo
    default: return false
  }
}
```

---

## 9. Tratamento de Erros

### 9.1 Códigos de Erro da API

```typescript
interface ApiError {
  success: false
  error: string
  code: string
  details?: Record<string, string[]>
}

// Códigos comuns
const ERROR_CODES = {
  // Validação
  'VALIDATION_ERROR': 'Dados inválidos',
  'MISSING_REQUIRED_FIELD': 'Campo obrigatório não preenchido',

  // Transportador
  'TRANSPORTADOR_NOT_FOUND': 'Transportador não encontrado',
  'TRANSPORTADOR_INCOMPLETO': 'Dados do transportador incompletos',

  // Motorista
  'MOTORISTA_NOT_FOUND': 'Motorista não encontrado',
  'MOTORISTA_DADOS_INCOMPLETOS': 'Complete os dados do motorista antes de prosseguir',

  // NDD Cargo
  'NDD_CARGO_CONNECTION_ERROR': 'Erro de conexão com NDD Cargo',
  'NDD_CARGO_AUTH_ERROR': 'Erro de autenticação NDD Cargo',
  'NDD_CARGO_PLACA_INVALIDA': 'Placa não cadastrada no sistema',
  'NDD_CARGO_ROTA_INVALIDA': 'Rota não encontrada',

  // Emissão
  'EMISSAO_JA_CONCLUIDA': 'Esta emissão já foi concluída',
  'EMISSAO_CANCELADA': 'Esta emissão foi cancelada',
}
```

### 9.2 Tratamento no Frontend

```typescript
// Interceptor de erros
api.interceptors.response.use(
  response => response,
  error => {
    const { response } = error

    if (response?.status === 401) {
      // Redirecionar para login
      router.push('/login')
      return
    }

    if (response?.status === 422) {
      // Erros de validação
      const errors = response.data.details
      Object.entries(errors).forEach(([field, messages]) => {
        toast.error(`${field}: ${messages.join(', ')}`)
      })
      return
    }

    if (response?.status === 500) {
      toast.error('Erro interno do servidor. Tente novamente.')
      return
    }

    // Erro genérico
    toast.error(response?.data?.error || 'Erro desconhecido')
    return Promise.reject(error)
  }
)
```

---

## 10. Exemplos de Implementação

### 10.1 Componente de Seleção de Motorista

```vue
<!-- VpoMotoristaSelector.vue -->
<template>
  <div>
    <v-alert v-if="loading" type="info">
      Carregando motoristas...
    </v-alert>

    <v-alert v-else-if="!isEmpresa" type="success">
      Transportador autônomo - não precisa selecionar motorista.
    </v-alert>

    <template v-else>
      <v-alert v-if="motoristas.length === 0" type="warning">
        Nenhum motorista cadastrado para esta empresa.
      </v-alert>

      <div v-else class="motoristas-grid">
        <VpoMotoristaCard
          v-for="m in motoristas"
          :key="m.codmot"
          :motorista="m"
          :selected="selectedCodmot === m.codmot"
          @select="selectMotorista(m)"
          @edit="editMotorista(m)"
        />
      </div>

      <v-alert v-if="completosCount === 0" type="error" class="mt-4">
        Nenhum motorista com dados completos.
        Complete os dados de pelo menos um motorista para continuar.
      </v-alert>
    </template>

    <!-- Dialog de edição -->
    <v-dialog v-model="editDialog" max-width="600">
      <VpoMotoristaForm
        v-if="editingMotorista"
        :motorista="editingMotorista"
        @save="saveMotorista"
        @cancel="editDialog = false"
      />
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useMotoristaEmpresa } from '@/composables/useMotoristaEmpresa'

const props = defineProps<{
  codtrn: number
}>()

const emit = defineEmits<{
  (e: 'select', motorista: MotoristaEmpresa): void
}>()

const {
  motoristas,
  loading,
  isEmpresa,
  verificar,
  listar,
  salvar
} = useMotoristaEmpresa(props.codtrn)

const selectedCodmot = ref<number | null>(null)
const editDialog = ref(false)
const editingMotorista = ref<MotoristaEmpresa | null>(null)

const completosCount = computed(() =>
  motoristas.value.filter(m => m.dados_completos).length
)

onMounted(async () => {
  await verificar()
  if (isEmpresa.value) {
    await listar()
  }
})

function selectMotorista(m: MotoristaEmpresa) {
  if (!m.dados_completos) {
    editMotorista(m)
    return
  }
  selectedCodmot.value = m.codmot
  emit('select', m)
}

function editMotorista(m: MotoristaEmpresa) {
  editingMotorista.value = m
  editDialog.value = true
}

async function saveMotorista(dados: SalvarMotoristaRequest) {
  if (!editingMotorista.value) return

  const result = await salvar(editingMotorista.value.codmot, dados)

  if (result.success && result.dados_completos) {
    editDialog.value = false
    await listar()  // Recarregar lista
    selectMotorista(result.data)
  }
}
</script>
```

### 10.2 Formulário de Dados do Motorista

```vue
<!-- VpoMotoristaForm.vue -->
<template>
  <v-card>
    <v-card-title>
      {{ motorista.nommot }}
      <v-chip v-if="motorista.dados_completos" color="success" size="small" class="ml-2">
        Completo
      </v-chip>
      <v-chip v-else color="warning" size="small" class="ml-2">
        Incompleto
      </v-chip>
    </v-card-title>

    <v-card-text>
      <v-alert v-if="motorista.campos_faltantes.length > 0" type="warning" class="mb-4">
        Campos obrigatórios faltantes:
        <strong>{{ motorista.campos_faltantes.map(c => CAMPOS_LABELS[c]).join(', ') }}</strong>
      </v-alert>

      <v-form ref="formRef" @submit.prevent="submit">
        <v-row>
          <!-- CPF -->
          <v-col cols="12" md="6">
            <v-text-field
              v-model="form.cpf"
              label="CPF *"
              v-mask="'###.###.###-##'"
              :rules="[rules.required, rules.cpf]"
              :error="motorista.campos_faltantes.includes('cpf')"
            />
          </v-col>

          <!-- RNTRC -->
          <v-col cols="12" md="6">
            <v-text-field
              v-model="form.rntrc"
              label="RNTRC *"
              :rules="[rules.required, rules.rntrc]"
              :error="motorista.campos_faltantes.includes('rntrc')"
            />
          </v-col>

          <!-- Nome da Mãe -->
          <v-col cols="12">
            <v-text-field
              v-model="form.nommae"
              label="Nome da Mãe *"
              :rules="[rules.required]"
              :error="motorista.campos_faltantes.includes('nommae')"
            />
          </v-col>

          <!-- Data Nascimento -->
          <v-col cols="12" md="6">
            <v-text-field
              v-model="form.data_nascimento"
              label="Data de Nascimento *"
              type="date"
              :rules="[rules.required, rules.dataNascimento]"
              :error="motorista.campos_faltantes.includes('data_nascimento')"
            />
          </v-col>

          <!-- CNH (opcional) -->
          <v-col cols="12" md="6">
            <v-text-field
              v-model="form.cnh"
              label="CNH"
            />
          </v-col>
        </v-row>

        <!-- Dados do Progress (readonly) -->
        <v-divider class="my-4" />
        <p class="text-subtitle-2 text-medium-emphasis">Dados do Progress (somente leitura)</p>

        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              :model-value="motorista.numrg"
              label="RG"
              readonly
              variant="plain"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              :model-value="motorista.nompai"
              label="Nome do Pai"
              readonly
              variant="plain"
            />
          </v-col>
        </v-row>
      </v-form>
    </v-card-text>

    <v-card-actions>
      <v-spacer />
      <v-btn @click="emit('cancel')">Cancelar</v-btn>
      <v-btn color="primary" :loading="saving" @click="submit">
        Salvar
      </v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { validarCPF, validarRNTRC, validarDataNascimento } from '@/utils/validations'

const props = defineProps<{
  motorista: MotoristaEmpresa
}>()

const emit = defineEmits<{
  (e: 'save', dados: SalvarMotoristaRequest): void
  (e: 'cancel'): void
}>()

const CAMPOS_LABELS: Record<string, string> = {
  cpf: 'CPF',
  rntrc: 'RNTRC',
  nommot: 'Nome',
  nommae: 'Nome da Mãe',
  data_nascimento: 'Data de Nascimento',
}

const formRef = ref()
const saving = ref(false)

const form = reactive({
  cpf: props.motorista.cpf || '',
  rntrc: props.motorista.rntrc || '',
  nommae: props.motorista.nommae || '',
  data_nascimento: props.motorista.data_nascimento || '',
  cnh: props.motorista.cnh || '',
})

const rules = {
  required: (v: string) => !!v || 'Campo obrigatório',
  cpf: (v: string) => validarCPF(v) || 'CPF inválido',
  rntrc: (v: string) => validarRNTRC(v) || 'RNTRC inválido',
  dataNascimento: (v: string) => validarDataNascimento(v) || 'Data inválida (motorista deve ter entre 18 e 80 anos)',
}

async function submit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  saving.value = true
  emit('save', {
    cpf: form.cpf.replace(/\D/g, ''),
    rntrc: form.rntrc.replace(/\D/g, ''),
    nommae: form.nommae,
    data_nascimento: form.data_nascimento,
    cnh: form.cnh || undefined,
  })
  saving.value = false
}
</script>
```

### 10.3 Store/Composable de Emissão

```typescript
// resources/ts/composables/useVpoEmissao.ts
import { ref, computed } from 'vue'
import { api } from '@/utils/api'

export function useVpoEmissao() {
  const uuid = ref<string | null>(null)
  const status = ref<EmissaoStatus>('idle')
  const etapaAtual = ref(1)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const dados = ref<{
    pacote?: any
    transportador?: any
    motorista?: any
    veiculo?: any
    rota?: any
    periodo?: any
    custo?: any
  }>({})

  // Computed
  const isEmpresa = computed(() =>
    dados.value.transportador?.cpf_cnpj?.length === 14
  )

  const podeAvancar = computed(() => {
    switch (etapaAtual.value) {
      case 1: return !!dados.value.pacote
      case 2: return !!dados.value.transportador &&
                     (!isEmpresa.value || !!dados.value.motorista)
      case 3: return !!dados.value.veiculo
      case 4: return !!dados.value.rota
      case 5: return !!dados.value.custo
      default: return false
    }
  })

  // Actions
  async function iniciar(params: {
    codpac: number
    codtrn: number
    codmot?: number
    placa: string
    rota_id?: number
  }) {
    loading.value = true
    error.value = null

    try {
      const response = await api.post('/vpo/emissao/iniciar', params)
      uuid.value = response.data.uuid
      status.value = 'iniciado'
      dados.value = response.data.data
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.error || 'Erro ao iniciar emissão'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function calcular(params: {
    data_inicio: string
    data_fim: string
    quantidade_eixos: number
  }) {
    if (!uuid.value) throw new Error('Emissão não iniciada')

    loading.value = true
    error.value = null
    status.value = 'calculando'

    try {
      const response = await api.post(`/vpo/emissao/${uuid.value}/calcular`, params)
      dados.value.periodo = params
      dados.value.custo = response.data.custo
      status.value = 'aguardando_confirmacao'
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.error || 'Erro ao calcular custo'
      status.value = 'erro'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function confirmar(observacoes?: string) {
    if (!uuid.value) throw new Error('Emissão não iniciada')

    loading.value = true
    error.value = null
    status.value = 'emitindo'

    try {
      const response = await api.post(`/vpo/emissao/${uuid.value}/confirmar`, {
        confirmar: true,
        observacoes,
      })
      status.value = 'concluido'
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.error || 'Erro ao confirmar emissão'
      status.value = 'erro'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function gerarRecibo(params: {
    enviar_whatsapp?: boolean
    enviar_email?: boolean
    telefone?: string
    email?: string
  }) {
    if (!uuid.value) throw new Error('Emissão não iniciada')

    loading.value = true

    try {
      const response = await api.post(`/vpo/emissao/${uuid.value}/recibo`, params)
      return response.data
    } finally {
      loading.value = false
    }
  }

  async function cancelar() {
    if (!uuid.value) return

    loading.value = true

    try {
      await api.post(`/vpo/emissao/${uuid.value}/cancelar`)
      status.value = 'cancelado'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    uuid.value = null
    status.value = 'idle'
    etapaAtual.value = 1
    loading.value = false
    error.value = null
    dados.value = {}
  }

  function setEtapa(etapa: number) {
    etapaAtual.value = etapa
  }

  function setDados(key: string, value: any) {
    dados.value[key as keyof typeof dados.value] = value
  }

  return {
    // State
    uuid,
    status,
    etapaAtual,
    loading,
    error,
    dados,

    // Computed
    isEmpresa,
    podeAvancar,

    // Actions
    iniciar,
    calcular,
    confirmar,
    gerarRecibo,
    cancelar,
    reset,
    setEtapa,
    setDados,
  }
}
```

---

## Anexos

### A. Tabelas Progress Relevantes

| Tabela | Descrição | Uso |
|--------|-----------|-----|
| `PUB.transporte` | Transportadores | Dados base |
| `PUB.trnmot` | Motoristas de empresas | codtrn + codmot |
| `PUB.trnvei` | Veículos de empresas | Placas |
| `PUB.pacote` | Pacotes de entrega | Seleção inicial |
| `PUB.semPararRot` | Rotas SemParar | Rotas pré-cadastradas |

### B. Tabelas SQLite (Cache)

| Tabela | Descrição |
|--------|-----------|
| `vpo_transportadores_cache` | Cache de dados sincronizados |
| `motorista_empresa_cache` | Dados complementares de motoristas |

### C. Endpoints NDD Cargo (SOAP)

- `autenticarUsuario` - Autenticação
- `statusVeiculo` - Validar placa
- `roteirizarPracasPedagio` - Calcular rota
- `cadastrarRotaTemporaria` - Criar rota
- `obterCustoRota` - Custo do pedágio
- `comprarViagem` - Emitir VPO
- `obterRecibo` - Dados do recibo
- `gerarRecibo` - PDF do recibo

---

**Última atualização:** 2025-12-09
**Versão:** 1.0.0
