import type { RouteNamedMap, _RouterTyped } from 'unplugin-vue-router'
import { canNavigate } from '@layouts/plugins/casl'

export const setupGuards = (router: _RouterTyped<RouteNamedMap & { [key: string]: any }>) => {
  // 👉 router.beforeEach
  // Docs: https://router.vuejs.org/guide/advanced/navigation-guards.html#global-before-guards
  router.beforeEach(to => {
    /*
     * If it's a public route, continue navigation. This kind of pages are allowed to visited by login & non-login users. Basically, without any restrictions.
     * Examples of public routes are, 404, under maintenance, etc.
     */
    if (to.meta.public)
      return

    /**
     * Check if user is logged in by checking if BOTH token AND user data exist
     * Se apenas um existir, consideramos como sessão inválida/expirada
     */
    const userData = useCookie('userData').value
    const accessToken = useCookie('accessToken').value
    const isLoggedIn = !!(userData && accessToken)

    /*
      If user is logged in and is trying to access login like page, redirect to home
      else allow visiting the page
      (WARN: Don't allow executing further by return statement because next code will check for permissions)
     */
    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return { name: 'ndd-dashboard' }
      else
        return undefined
    }

    // Se não está logado (sem token ou sem userData), sempre vai para login
    // Isso evita mostrar "not-authorized" quando a sessão expirou
    if (!isLoggedIn) {
      return {
        name: 'login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }

    // Usuário está logado mas pode não ter permissão para a rota específica
    if (!canNavigate(to) && to.matched.length) {
      return { name: 'not-authorized' }
    }
  })
}
