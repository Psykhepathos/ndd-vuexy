/**
 * Types for Compra de Viagem SemParar
 * Seguindo fluxo original: Pacote → Placa → Rota → Preço → Compra
 */

// ============================================================================
// STEP 1: PACOTE (obrigatório)
// ============================================================================

export interface PacoteCompraViagem {
  codpac: number
  descpac: string
  codtrn: number
  nomtrn: string
  sitpac: string
  datforpac: string
}

export interface PacoteData {
  pacote: PacoteCompraViagem | null
  entregas: EntregaPacote[]
  entregas_com_gps: EntregaPacote[]
}

export interface EntregaPacote {
  numseqped: number
  razcli: string
  endcli: string
  baicli: string
  cidcli: string
  sigufs: string
  cepcli: string
  gps_lat: string | null
  gps_lon: string | null
  lat?: number
  lon?: number
  tipo: 'entrega'
}

// ============================================================================
// STEP 2: PLACA
// ============================================================================

export interface PlacaData {
  placa: string
  descricao: string
  eixos: number
  proprietario: string
  tag: string
}

// ============================================================================
// STEP 3: ROTA
// ============================================================================

export interface RotaCompraViagem {
  sPararRotID: number
  desSPararRot: string
  tempoViagem: number
  flgCD: boolean
  flgRetorno: boolean
}

export interface RotaData {
  rota: RotaCompraViagem | null
  municipios: MunicipioRota[]
  modoCD: boolean
  modoRetorno: boolean
}

export interface MunicipioRota {
  sPararMuSeq: number
  codMun: number
  codEst: number
  desMun: string
  desEst: string
  cdibge: string
  lat?: number
  lon?: number
}

// ============================================================================
// STEP 4: PREÇO
// ============================================================================

export interface PrecoData {
  valor: number
  numeroViagem: string
  nomeRotaSemParar: string
  codRotaSemParar: string
  pracas: PracaPedagio[]
  calculado: boolean
}

export interface PracaPedagio {
  id: number
  nome: string
  cidade: string
  uf: string
  valor: number
  lat?: number
  lon?: number
}

// ============================================================================
// STEP 5: CONFIGURAÇÃO E DATAS
// ============================================================================

export interface ConfiguracaoData {
  dataInicio: string
  dataFim: string
}

// ============================================================================
// FORM DATA COMPLETO (todos os steps)
// ============================================================================

export interface CompraViagemFormData {
  // Step 1: Pacote
  pacote: PacoteData

  // Step 2: Placa
  placa: PlacaData

  // Step 3: Rota
  rota: RotaData

  // Step 4: Preço
  preco: PrecoData

  // Step 5: Configuração (datas)
  configuracao: ConfiguracaoData

  // Controle de conclusão dos steps
  step1Completo: boolean
  step2Completo: boolean
  step3Completo: boolean
  step4Completo: boolean
  step5Completo: boolean
}

// ============================================================================
// WIZARD STEPS
// ============================================================================

export interface WizardStep {
  title: string
  subtitle: string
  icon: string
  value: number
}

// ============================================================================
// MAPA
// ============================================================================

export interface MapMarker {
  id: string
  lat: number
  lon: number
  tipo: 'municipio' | 'entrega' | 'pedagio'
  label: string
  sequencia?: number
  popup?: string
}

export interface MapRoute {
  coordinates: Array<[number, number]>
  distance_km: number
  color: string
}

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

export interface ValidarPlacaResponse {
  descricao: string
  eixos: number
  proprietario: string
  tag: string
}

export interface ValidarRotaResponse {
  data_inicio: string
  data_fim: string
}

export interface VerificarPrecoResponse {
  valor: number
  numero_viagem: string
  nome_rota: string
  cod_rota: string
}

export interface ComprarViagemResponse {
  cod_viagem: string
  numero_viagem: string
  success: boolean
}

// ============================================================================
// VALIDAÇÃO
// ============================================================================

export interface ValidationErrors {
  [key: string]: string[]
}

export interface StepValidation {
  isValid: boolean
  errors: ValidationErrors
}
