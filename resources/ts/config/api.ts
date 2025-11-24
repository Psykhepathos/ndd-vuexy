/**
 * API Configuration
 * Centralized API base URL configuration for deployment flexibility
 */

// Detecta ambiente baseado em import.meta.env (Vite)
const isDevelopment = import.meta.env.DEV
const isProduction = import.meta.env.PROD

// Base URL da API Laravel
// SEMPRE usa window.location.origin para evitar problemas de CORS
// quando acessado via IP da rede (ex: 10.0.3.9:8002)
export const API_BASE_URL = window.location.origin

// Endpoints principais
export const API_ENDPOINTS = {
  // Auth
  login: `${API_BASE_URL}/api/login`,
  logout: `${API_BASE_URL}/api/logout`,
  me: `${API_BASE_URL}/api/me`,

  // Progress Database
  progressTest: `${API_BASE_URL}/api/progress/test-connection`,
  progressQuery: `${API_BASE_URL}/api/progress/query`,

  // Transportes
  transportes: `${API_BASE_URL}/api/transportes`,
  transporte: (id: number) => `${API_BASE_URL}/api/transportes/${id}`,

  // Pacotes
  pacotes: `${API_BASE_URL}/api/pacotes`,
  pacote: (id: number) => `${API_BASE_URL}/api/pacotes/${id}`,
  pacoteItinerario: `${API_BASE_URL}/api/pacotes/itinerario`,
  pacoteAutocomplete: `${API_BASE_URL}/api/pacotes/autocomplete`,

  // Rotas SemParar
  semPararRotas: `${API_BASE_URL}/api/semparar-rotas`,
  semPararRota: (id: number) => `${API_BASE_URL}/api/semparar-rotas/${id}`,
  semPararRotaMunicipios: (id: number) => `${API_BASE_URL}/api/semparar-rotas/${id}/municipios`,

  // Routing & Geocoding
  routingCalculate: `${API_BASE_URL}/api/routing/calculate`,
  geocodingIbge: `${API_BASE_URL}/api/geocoding/ibge`,
  geocodingLote: `${API_BASE_URL}/api/geocoding/lote`,
  osrmRoute: `${API_BASE_URL}/api/osrm/route`,

  // Rotas (autocomplete)
  rotas: `${API_BASE_URL}/api/rotas`,

  // Estados e Municípios
  estados: `${API_BASE_URL}/api/semparar-rotas/estados`,
  municipios: `${API_BASE_URL}/api/semparar-rotas/municipios`,

  // Compra de Viagem (⚠️ MODO DE TESTE - Não faz compras reais)
  compraViagem: {
    initialize: `${API_BASE_URL}/api/compra-viagem/initialize`,
    statistics: `${API_BASE_URL}/api/compra-viagem/statistics`,
    health: `${API_BASE_URL}/api/compra-viagem/health`,
    validarPacote: `${API_BASE_URL}/api/compra-viagem/validar-pacote`,
    validarPlaca: `${API_BASE_URL}/api/compra-viagem/validar-placa`,
    rotas: `${API_BASE_URL}/api/compra-viagem/rotas`,
    verificarPreco: `${API_BASE_URL}/api/compra-viagem/verificar-preco`,
    // TODO: Adicionar endpoints das próximas fases
    // comprar: `${API_BASE_URL}/api/compra-viagem/comprar`,
    // gerarRecibo: `${API_BASE_URL}/api/compra-viagem/gerar-recibo`,
  },
} as const

// Headers padrão para requisições
export const DEFAULT_HEADERS = {
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'X-Requested-With': 'XMLHttpRequest'
} as const

// Helper para fazer fetch com configuração padrão
export async function apiFetch(url: string, options: RequestInit = {}) {
  return fetch(url, {
    ...options,
    headers: {
      ...DEFAULT_HEADERS,
      ...options.headers
    }
  })
}
