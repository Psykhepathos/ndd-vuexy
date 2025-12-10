/**
 * Types for VPO (Vale Pedágio Obrigatório) Emission Wizard
 * Fluxo: Pacote → Transportador → [Motorista] → Veículo → Rota → Confirmação
 *
 * REGRA CRÍTICA: Tipo de transportador é determinado pelo tamanho do documento
 * - CPF (11 dígitos) = Autônomo
 * - CNPJ (14 dígitos) = Empresa (requer seleção de motorista)
 */

// ============================================================================
// STEP 1: PACOTE
// ============================================================================

export interface PacoteVpo {
  codpac: number
  descpac: string
  codtrn: number
  nomtrn: string
  sitpac: string
  datforpac: string
  codmot?: number
  nummot?: string
  placa?: string
}

export interface PacoteData {
  pacote: PacoteVpo | null
  transportador: TransportadorVpo | null
}

// ============================================================================
// STEP 2: TRANSPORTADOR & MOTORISTA
// ============================================================================

export interface TransportadorVpo {
  codtrn: number
  nomtrn: string
  cpf_cnpj: string
  flgautonomo: boolean
  antt_rntrc: string | null
  antt_nome: string | null
  antt_validade: string | null
  antt_status: string | null
  placa: string | null
  veiculo_tipo: string | null
  veiculo_modelo: string | null
  condutor_rg: string | null
  condutor_nome: string | null
  condutor_sexo: 'M' | 'F' | null
  condutor_nome_mae: string | null
  condutor_data_nascimento: string | null
  endereco_rua: string | null
  endereco_bairro: string | null
  endereco_cidade: string | null
  endereco_estado: string | null
  contato_celular: string | null
  contato_email: string | null
  score_qualidade: number
  campos_faltantes: string[]
}

export interface MotoristaEmpresa {
  codtrn: number
  codmot: number
  nommot: string
  numrg: string | null
  nompai: string | null
  nommae: string | null
  codrntrc_progress: string | null
  // Dados do cache SQLite
  cpf: string | null
  rntrc: string | null
  data_nascimento: string | null
  cnh: string | null
  categoria_cnh: string | null
  validade_cnh: string | null
  endereco_logradouro: string | null
  endereco_numero: string | null
  endereco_bairro: string | null
  endereco_cidade: string | null
  endereco_uf: string | null
  endereco_cep: string | null
  // Status
  tem_cache: boolean
  dados_completos: boolean
  campos_faltantes: string[]
}

export interface MotoristaData {
  isEmpresa: boolean
  requerSelecaoMotorista: boolean
  motoristas: MotoristaEmpresa[]
  motoristaSelecionado: MotoristaEmpresa | null
}

// ============================================================================
// STEP 3: VEÍCULO
// ============================================================================

export interface VeiculoVpo {
  placa: string
  descricao: string
  tipo: string
  modelo: string
  eixos: number
  proprietario: string
  tag: string | null
  status_semparar: 'ativo' | 'inativo' | 'pendente' | null
}

export interface VeiculoData {
  veiculo: VeiculoVpo | null
  veiculosDisponiveis: VeiculoVpo[]
}

// ============================================================================
// STEP 4: ROTA
// ============================================================================

export interface RotaVpo {
  sPararRotID: number
  desSPararRot: string
  tempoViagem: number
  flgCD: boolean
  flgRetorno: boolean
}

export interface MunicipioRota {
  sPararMuSeq: number
  codMun: number
  codEst: number
  desMun: string
  desEst: string
  cdibge: string
  cep?: string
  lat?: number
  lon?: number
}

export interface PracaPedagioVpo {
  codigo: string
  nome: string
  rodovia: string
  km: number
  valor: number
  cidade?: string
  uf?: string
  sentido?: string
  concessionaria?: string
  lat?: number
  lon?: number
  // Aliases para compatibilidade com diferentes formatos de resposta
  codigoPraca?: string
  nomePraca?: string
  valorPedagio?: number
}

// Alias para uso simplificado
export type PracaPedagio = PracaPedagioVpo

export interface EntregaVpo {
  numseqped: number
  razcli: string
  cidcli: string
  sigufs: string
  lat: number | null
  lon: number | null
}

export interface RotaData {
  rota: RotaVpo | null
  municipios: MunicipioRota[]
  pracas: PracaPedagioVpo[]
  entregas: EntregaVpo[]  // Primeira e última entrega do pacote
  rotaSugerida: RotaVpo | null
}

// ============================================================================
// STEP 5: PERÍODO E CUSTO
// ============================================================================

export interface PeriodoData {
  dataInicio: string
  dataFim: string
  eixos: number
}

export interface CustoVpo {
  valor_total: number
  pedagios: PracaPedagioVpo[]
  rota_nome: string
  km_total: number
  tempo_estimado: string
}

export interface CustoData {
  custo: CustoVpo | null
  calculado: boolean
  calculando: boolean
}

// ============================================================================
// FORM DATA COMPLETO (todos os steps)
// ============================================================================

export interface VpoEmissaoFormData {
  // Step 1: Pacote
  pacote: PacoteData

  // Step 2: Motorista (condicional)
  motorista: MotoristaData

  // Step 3: Veículo
  veiculo: VeiculoData

  // Step 4: Rota
  rota: RotaData

  // Step 5: Período e Custo
  periodo: PeriodoData
  custo: CustoData

  // UUID da emissão (após iniciar)
  uuid: string | null

  // Status da emissão
  status: VpoEmissaoStatus

  // Controle de conclusão dos steps
  step1Completo: boolean
  step2Completo: boolean
  step3Completo: boolean
  step4Completo: boolean
  step5Completo: boolean
}

// ============================================================================
// ESTADOS DA EMISSÃO
// ============================================================================

export type VpoEmissaoStatus =
  | 'idle'
  | 'iniciado'
  | 'calculando'
  | 'aguardando_confirmacao'
  | 'emitindo'
  | 'concluido'
  | 'erro'
  | 'cancelado'

// ============================================================================
// WIZARD
// ============================================================================

export interface WizardStep {
  title: string
  subtitle: string
  icon: string
  value: number
  conditional?: boolean  // Step condicional (ex: motorista para empresas)
}

// ============================================================================
// VALIDAÇÕES
// ============================================================================

export const CAMPOS_OBRIGATORIOS_VPO = [
  'cpf',
  'rntrc',
  'nommot',
  'nommae',
  'data_nascimento',
] as const

export const CAMPOS_LABELS: Record<string, string> = {
  cpf: 'CPF do Motorista',
  rntrc: 'RNTRC (Registro ANTT)',
  nommot: 'Nome Completo',
  nommae: 'Nome da Mãe',
  data_nascimento: 'Data de Nascimento',
  condutor_rg: 'RG',
  condutor_nome: 'Nome do Condutor',
  condutor_nome_mae: 'Nome da Mãe',
  condutor_data_nascimento: 'Data de Nascimento',
  antt_rntrc: 'RNTRC',
  placa: 'Placa do Veículo',
}

export const SCORE_LEVELS = {
  EXCELENTE: { min: 90, color: 'success', label: 'Excelente' },
  BOM: { min: 70, color: 'info', label: 'Bom' },
  REGULAR: { min: 50, color: 'warning', label: 'Regular' },
  RUIM: { min: 0, color: 'error', label: 'Incompleto' },
} as const

// ============================================================================
// API RESPONSES
// ============================================================================

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  error?: string
  message?: string
  errors?: Record<string, string[]>
}

export interface VerificarMotoristaResponse {
  success: boolean
  codtrn: number
  is_empresa: boolean
  tem_motoristas: boolean
  requer_selecao_motorista: boolean
  mensagem: string
}

export interface SyncTransportadorResponse {
  success: boolean
  data: TransportadorVpo
  message: string
}

export interface ValidarVeiculoResponse {
  success: boolean
  placa: string
  descricao: string
  eixos: number
  proprietario: string
  tag: string | null
  status: string
}

export interface CalcularCustoResponse {
  success: boolean
  uuid: string
  custo: CustoVpo
}

export interface EmitirVpoResponse {
  success: boolean
  uuid: string
  status: 'concluido'
  vpo: {
    codigo_viagem: string
    data_emissao: string
    valor_total: number
    validade_inicio: string
    validade_fim: string
  }
}

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Determina se transportador é empresa (CNPJ) ou autônomo (CPF)
 * REGRA: Usar tamanho do documento, NÃO a flag flgautonomo
 */
export function isEmpresa(cpfCnpj: string): boolean {
  const documento = cpfCnpj.replace(/\D/g, '')
  return documento.length === 14
}

export function isAutonomo(cpfCnpj: string): boolean {
  const documento = cpfCnpj.replace(/\D/g, '')
  return documento.length === 11 || documento.length !== 14
}

/**
 * Retorna o nível de score baseado no valor
 */
export function getScoreLevel(score: number): typeof SCORE_LEVELS[keyof typeof SCORE_LEVELS] {
  if (score >= SCORE_LEVELS.EXCELENTE.min) return SCORE_LEVELS.EXCELENTE
  if (score >= SCORE_LEVELS.BOM.min) return SCORE_LEVELS.BOM
  if (score >= SCORE_LEVELS.REGULAR.min) return SCORE_LEVELS.REGULAR
  return SCORE_LEVELS.RUIM
}

/**
 * Formata CPF: 123.456.789-01
 */
export function formatCpf(cpf: string): string {
  const digits = cpf.replace(/\D/g, '')
  return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
}

/**
 * Formata CNPJ: 12.345.678/0001-90
 */
export function formatCnpj(cnpj: string): string {
  const digits = cnpj.replace(/\D/g, '')
  return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
}

/**
 * Formata placa (antiga ou Mercosul)
 */
export function formatPlaca(placa: string): string {
  const clean = placa.replace(/[^A-Z0-9]/gi, '').toUpperCase()
  if (clean.length === 7) {
    // AAA1234 ou AAA1A23
    return `${clean.slice(0, 3)}-${clean.slice(3)}`
  }
  return clean
}
