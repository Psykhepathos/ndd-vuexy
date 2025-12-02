import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken)
      options.headers.append('Authorization', `Bearer ${accessToken}`)
  },
  async onResponseError({ response }) {
    if (response.status === 401) {
      const accessTokenCookie = useCookie('accessToken')
      const userDataCookie = useCookie('userData')
      const userAbilityRulesCookie = useCookie('userAbilityRules')

      accessTokenCookie.value = null
      userDataCookie.value = null
      userAbilityRulesCookie.value = null

      if (typeof window !== 'undefined') {
        const router = useRouter()
        router.push('/login')
      }
    }
  },
})
