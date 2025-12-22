import { ofetch, FetchError } from 'ofetch'
import { router } from '@/plugins/1.router'
import { useToast } from '@/composables/useToast'

let isRedirecting = false

// Singleton do toast para uso global
const { showError, showWarning, showSuccess, showInfo } = useToast()

// Exportar para uso em componentes
export { showError, showWarning, showSuccess, showInfo }

/**
 * Extrai mensagem de erro da resposta da API
 */
export const getErrorMessage = (error: any): string => {
  // Erro do ofetch/FetchError
  if (error?.data) {
    // Laravel validation errors (422)
    if (error.data.errors) {
      const errors = error.data.errors
      const firstKey = Object.keys(errors)[0]
      if (firstKey && Array.isArray(errors[firstKey])) {
        return errors[firstKey][0]
      }
    }
    // Mensagem direta
    if (error.data.message) {
      return error.data.message
    }
  }

  // Response _data (formato antigo)
  if (error?.response?._data) {
    if (error.response._data.errors) {
      const errors = error.response._data.errors
      const firstKey = Object.keys(errors)[0]
      if (firstKey && Array.isArray(errors[firstKey])) {
        return errors[firstKey][0]
      }
    }
    if (error.response._data.message) {
      return error.response._data.message
    }
  }

  // Mensagem genérica do erro
  if (error?.message) {
    return error.message
  }

  return 'Erro desconhecido. Tente novamente.'
}

/**
 * Handler de erro padronizado para uso em componentes
 * Mostra toast com a mensagem de erro e retorna a mensagem
 */
export const handleApiError = (error: any, defaultMessage?: string): string => {
  const message = getErrorMessage(error) || defaultMessage || 'Erro ao processar requisição'
  showError(message)
  return message
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken) {
      options.headers = options.headers || new Headers()
      options.headers.append('Authorization', `Bearer ${accessToken}`)
    }
  },
  async onResponseError({ response }) {
    const status = response.status
    const data = response._data

    // 401 Não Autorizado - Token inválido/expirado
    if (status === 401) {
      const accessTokenCookie = useCookie('accessToken')
      const userDataCookie = useCookie('userData')
      const userAbilityRulesCookie = useCookie('userAbilityRules')

      // Limpar todos os cookies de autenticação
      accessTokenCookie.value = null
      userDataCookie.value = null
      userAbilityRulesCookie.value = null

      if (typeof window !== 'undefined' && !isRedirecting) {
        isRedirecting = true

        // Verifica se já está na página de login (suporta subdiretório)
        const isLoginPage = window.location.pathname.endsWith('/login') ||
                            window.location.pathname.includes('/login/')
        if (!isLoginPage) {
          showWarning('Sessão expirada. Faça login novamente.')

          // Usar hard redirect para garantir que cookies são limpos antes da navegação
          // Isso evita que o router guard veja cookies antigos e redirecione para not-authorized
          const baseUrl = window.location.origin + (import.meta.env.BASE_URL || '/')
          const loginUrl = baseUrl.replace(/\/+$/, '') + '/login'
          window.location.href = loginUrl
        }

        setTimeout(() => {
          isRedirecting = false
        }, 2000)
      }
    }

    // 403 Proibido - Sem permissão para acessar recurso
    else if (status === 403) {
      const message = data?.message || 'Acesso negado. Você não tem permissão para esta ação.'
      console.error('Acesso negado:', message)
      showError(message)
    }

    // 422 Erro de Validação - Mostrar mensagem específica
    else if (status === 422) {
      let message = 'Erro de validação'

      // Extrair primeira mensagem de erro do Laravel
      if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0]
        if (firstKey && Array.isArray(data.errors[firstKey])) {
          message = data.errors[firstKey][0]
        }
      } else if (data?.message) {
        message = data.message
      }

      console.error('Erro de validação:', message)
      showError(message)
    }

    // 500 Erro Interno do Servidor
    else if (status === 500) {
      const message = data?.message || 'Erro interno do servidor. Tente novamente mais tarde.'
      console.error('Erro no servidor:', message)
      showError(message)
    }

    // 503 Serviço Indisponível
    else if (status === 503) {
      console.error('Serviço temporariamente indisponível')
      showError('Serviço temporariamente indisponível. Aguarde alguns instantes.')
    }

    // Outros erros 4xx/5xx
    else if (status >= 400) {
      const message = data?.message || `Erro HTTP ${status}`
      console.error(`Erro HTTP ${status}:`, message)
      showError(message)
    }
  },
})
