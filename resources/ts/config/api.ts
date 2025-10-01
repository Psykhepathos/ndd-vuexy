/**
 * API Configuration
 * Centralized API base URL configuration for deployment flexibility
 */

// Detecta ambiente baseado em import.meta.env (Vite)
const isDevelopment = import.meta.env.DEV
const isProduction = import.meta.env.PROD

// Base URL da API Laravel
export const API_BASE_URL = isDevelopment
  ? 'http://localhost:8002'  // Desenvolvimento
  : window.location.origin    // Produção (usa o mesmo domínio)

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

  // Rotas (autocomplete)
  rotas: `${API_BASE_URL}/api/rotas`,

  // Estados e Municípios
  estados: `${API_BASE_URL}/api/semparar-rotas/estados`,
  municipios: `${API_BASE_URL}/api/semparar-rotas/municipios`,
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
