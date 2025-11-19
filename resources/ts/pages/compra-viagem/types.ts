/**
 * Types for Compra de Viagem SemParar
 *
 * Este arquivo centraliza todas as interfaces TypeScript usadas no módulo
 * de compra de viagem, garantindo type safety em todo o fluxo.
 */

// ============================================================================
// ROTA PADRÃO
// ============================================================================

export interface SemPararRota {
  sPararRotID: number
  desSPararRot: string
  tempoViagem: number
  flgCD: boolean
  flgRetorno: boolean
  datAtu: string | null
  resAtu: string | null
}

export interface Municipio {
  sPararMuSeq: number
  codMun: number
  codEst: number
  desMun: string
  desEst: string
  cdibge: string
  lat?: number
  lon?: number
}

export interface RotaPadraoData {
  rota: SemPararRota | null
  municipios: Municipio[]
}

// ============================================================================
// PACOTE
// ============================================================================

export interface PacoteBasico {
  codpac: number
  desobs: string
  sitpac: string
  datforpac: string
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

export interface PacoteData {
  pacote: PacoteBasico | null
  entregas: EntregaPacote[]
  entregas_com_gps: EntregaPacote[]
}

// ============================================================================
// CONFIGURAÇÃO DA VIAGEM
// ============================================================================

export interface ConfiguracaoViagemData {
  placa: string
  eixos: number
  dataInicio: string
  dataFim: string
  itemFin1: string
}

// ============================================================================
// PEDÁGIOS
// ============================================================================

export interface PracaPedagio {
  id: number
  nome: string
  cidade: string
  uf: string
  valor: number
  lat?: number
  lon?: number
}

export interface PedagiosData {
  pracas: PracaPedagio[]
  valorTotal: number
  nomeRotaTemporaria: string
  rotaCadastrada: boolean
  custoCalculado: boolean
}

// ============================================================================
// FORM DATA COMPLETO (todos os steps)
// ============================================================================

export interface CompraViagemFormData {
  // Step 1: Rota Padrão
  rotaPadrao: RotaPadraoData

  // Step 2: Pacote (opcional)
  pacote: PacoteData

  // Step 3: Configuração
  configuracao: ConfiguracaoViagemData

  // Step 4: Pedágios
  pedagios: PedagiosData

  // Metadados
  step1Completo: boolean
  step2Completo: boolean
  step3Completo: boolean
  step4Completo: boolean
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

export interface GeocodingResponse {
  success: boolean
  data?: {
    codigo_ibge: string
    nome_municipio: string
    uf: string
    coordenadas: {
      lat: number
      lon: number
    }
  }
}

export interface RoteirizacaoResponse {
  success: boolean
  data?: {
    pracas: PracaPedagio[]
    total_pracas: number
  }
  message?: string
}

export interface CompraViagemResponse {
  success: boolean
  data?: {
    cod_viagem: string
    status: number
    progress_saved?: boolean
  }
  message?: string
  error?: string
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
