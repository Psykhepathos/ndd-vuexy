import { ofetch } from 'ofetch'

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
    if (response.status === 401) {
      const accessTokenCookie = useCookie('accessToken')
      const userDataCookie = useCookie('userData')
      const userAbilityRulesCookie = useCookie('userAbilityRules')

      accessTokenCookie.value = null
      userDataCookie.value = null
      userAbilityRulesCookie.value = null

      if (typeof window !== 'undefined' && !isRedirecting) {
        isRedirecting = true

        if (!window.location.pathname.includes('/login')) {
          window.location.href = '/login'
        }

        setTimeout(() => {
          isRedirecting = false
        }, 1000)
      }
    }
  },
})
