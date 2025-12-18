/**
 * API Configuration
 * Centralized API base URL configuration for deployment flexibility
 *
 * IMPORTANTE: Para deploy em subdiretório:
 * 1. Configure ASSET_URL=/valepedagio no .env do servidor
 * 2. Configure VITE_API_BASE_URL=/valepedagio/api no .env do servidor
 * 3. O Vite vai configurar automaticamente import.meta.env.BASE_URL
 */

/**
 * Obtém o path base da aplicação (sem /build)
 * Usado para cookies, redirects, e outras URLs que precisam do path base
 *
 * Em desenvolvimento: '/'
 * Em produção com ASSET_URL=/valepedagio: '/valepedagio'
 */
export const getAppBasePath = (): string => {
  const baseUrl = import.meta.env.BASE_URL || '/'

  // Remove '/build' se presente (Vite adiciona em alguns casos)
  return baseUrl.replace(/\/build\/?$/, '') || '/'
}

/**
 * Obtém a URL completa para chamadas API
 * Sempre usa origin + path configurado no VITE_API_BASE_URL
 *
 * @param endpoint - O endpoint da API (ex: '/pacotes', '/auth/login')
 * @returns URL completa para a API
 *
 * @example
 * getApiUrl('/pacotes') // 'http://192.168.19.34/valepedagio/api/pacotes'
 * getApiUrl('/auth/login') // 'http://192.168.19.34/valepedagio/api/auth/login'
 */
export const getApiUrl = (endpoint: string): string => {
  // VITE_API_BASE_URL já contém o path completo (ex: /valepedagio/api)
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/api'

  // Remove barra inicial do endpoint se apiBase terminar com barra
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`

  return `${window.location.origin}${apiBase}${cleanEndpoint}`
}

/**
 * @deprecated Use getApiUrl() em vez disso
 * Mantido para compatibilidade - será removido em versão futura
 */
export const API_BASE_URL = (() => {
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/api'

  // Remove '/api' do final se presente para manter compatibilidade
  const basePath = apiBase.replace(/\/api\/?$/, '')

  return `${window.location.origin}${basePath}`
})()

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

  // Routing & Geocoding (OSRM gratuito)
  // routingCalculate: DEPRECATED - Use osrmRoute instead (Google Directions removido)
  geocodingIbge: `${API_BASE_URL}/api/geocoding/ibge`,
  geocodingLote: `${API_BASE_URL}/api/geocoding/lote`,
  osrmRoute: `${API_BASE_URL}/api/routing/route`, // Proxy OSRM gratuito

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

/**
 * Helper para fazer fetch com configuração padrão + autenticação
 * Adiciona automaticamente:
 * - Headers JSON padrão (Accept, Content-Type, X-Requested-With)
 * - Token de autenticação Bearer (se disponível)
 *
 * @example
 * // GET request
 * const response = await apiFetch('/api/pacotes')
 *
 * // POST request com body
 * const response = await apiFetch('/api/compra-viagem/comprar', {
 *   method: 'POST',
 *   body: JSON.stringify({ pacote: 123 })
 * })
 */
export async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
  // Obter token de autenticação do cookie
  const accessToken = useCookie('accessToken').value

  // Construir headers com autenticação se disponível
  const headers: Record<string, string> = {
    ...DEFAULT_HEADERS,
    ...(options.headers as Record<string, string> || {})
  }

  if (accessToken) {
    headers['Authorization'] = `Bearer ${accessToken}`
  }

  return fetch(url, {
    ...options,
    headers
  })
}

/**
 * Helper adicional para requisições com JSON body
 * Automaticamente faz JSON.stringify do body
 *
 * @example
 * const response = await apiPost('/api/compra-viagem/validar-pacote', {
 *   cod_pac: 12345,
 *   flg_cd: true
 * })
 */
export async function apiPost(url: string, body: any): Promise<Response> {
  return apiFetch(url, {
    method: 'POST',
    body: JSON.stringify(body)
  })
}

/**
 * Helper para requisições PUT
 */
export async function apiPut(url: string, body: any): Promise<Response> {
  return apiFetch(url, {
    method: 'PUT',
    body: JSON.stringify(body)
  })
}

/**
 * Helper para requisições DELETE
 */
export async function apiDelete(url: string): Promise<Response> {
  return apiFetch(url, {
    method: 'DELETE'
  })
}
