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
 * Base URL para chamadas API (apenas o path, sem origin)
 * Usa VITE_API_BASE_URL do .env ou fallback para '/api'
 *
 * IMPORTANTE: NÃO usar window.location.origin - usar apenas paths relativos
 */
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api'

/**
 * Endpoints da API (SEM /api - usar com getApiUrl() ou $api())
 *
 * IMPORTANTE: Estes endpoints NÃO incluem /api porque:
 * - $api() já usa API_BASE_URL como baseURL
 * - getApiUrl() já combina com API_BASE_URL
 *
 * @example
 * // Com $api (recomendado):
 * $api(API_ENDPOINTS.pacotes)
 *
 * // Com getApiUrl + fetch:
 * fetch(getApiUrl(API_ENDPOINTS.pacotes))
 */
export const API_ENDPOINTS = {
  // Pacotes
  pacotes: '/pacotes',
  pacoteAutocomplete: '/pacotes/autocomplete',
  pacoteItinerario: '/pacotes/itinerario',

  // Transportes
  transportes: '/transportes',

  // Rotas
  semPararRotas: '/semparar-rotas',

  // Geocoding
  geocodingIbge: '/geocoding/ibge',
  geocodingLote: '/geocoding/lote',

  // Routing
  routingRoute: '/routing/route',

  // Auth
  authLogin: '/auth/login',
  authLogout: '/auth/logout',
  authVerifySetupToken: '/auth/verify-setup-token',
  authSetupPassword: '/auth/setup-password',
} as const

/**
 * Obtém a URL para chamadas API
 * Combina API_BASE_URL com o endpoint
 *
 * @param endpoint - O endpoint da API (ex: '/pacotes', '/auth/login')
 * @returns URL para a API (path relativo)
 *
 * @example
 * getApiUrl('/pacotes') // '/api/pacotes'
 * getApiUrl('pacotes')  // '/api/pacotes'
 */
export const getApiUrl = (endpoint: string): string => {
  // Remove barra inicial do endpoint para evitar duplicação
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint

  // Remove barra final do API_BASE_URL se existir
  const cleanBase = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL

  return `${cleanBase}/${cleanEndpoint}`
}

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
 * // GET request com path relativo
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
