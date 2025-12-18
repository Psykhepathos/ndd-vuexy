import { ofetch } from 'ofetch'
import { router } from '@/plugins/1.router'

let isRedirecting = false

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

    // 401 Não Autorizado - Token inválido/expirado
    if (status === 401) {
      const accessTokenCookie = useCookie('accessToken')
      const userDataCookie = useCookie('userData')
      const userAbilityRulesCookie = useCookie('userAbilityRules')

      accessTokenCookie.value = null
      userDataCookie.value = null
      userAbilityRulesCookie.value = null

      if (typeof window !== 'undefined' && !isRedirecting) {
        isRedirecting = true

        if (!window.location.pathname.includes('/login')) {
          router.push({ name: 'login' })
        }

        setTimeout(() => {
          isRedirecting = false
        }, 1000)
      }
    }

    // 403 Proibido - Sem permissão para acessar recurso
    else if (status === 403) {
      console.error('Acesso negado: Você não tem permissão para acessar este recurso')

      // Notificar usuário via toast (se disponível)
      if (typeof window !== 'undefined' && (window as any).$toast) {
        (window as any).$toast.error('Acesso negado. Você não tem permissão para esta ação.')
      }
    }

    // 500 Erro Interno do Servidor
    else if (status === 500) {
      console.error('Erro no servidor:', response._data?.message || 'Erro interno do servidor')

      if (typeof window !== 'undefined' && (window as any).$toast) {
        (window as any).$toast.error('Erro no servidor. Tente novamente mais tarde.')
      }
    }

    // 503 Serviço Indisponível
    else if (status === 503) {
      console.error('Serviço temporariamente indisponível')

      if (typeof window !== 'undefined' && (window as any).$toast) {
        (window as any).$toast.error('Serviço temporariamente indisponível. Aguarde alguns instantes.')
      }
    }

    // Log de outros erros não tratados
    else if (status >= 400) {
      console.error(`Erro HTTP ${status}:`, response._data?.message || 'Erro desconhecido')
    }
  },
})
