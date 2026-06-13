import type { RouteNamedMap, _RouterTyped } from 'unplugin-vue-router'
import { canNavigate } from '@layouts/plugins/casl'

interface UserCookie {
  premiereConnexion?: boolean
}

export const setupGuards = (router: _RouterTyped<RouteNamedMap & { [key: string]: any }>) => {
  router.beforeEach(to => {
    if (to.meta.public)
      return

    const userData = useCookie<UserCookie | null>('userData')
    const isLoggedIn = !!(userData.value && useCookie('accessToken').value)

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return userData.value?.premiereConnexion ? { name: 'auth-first-login' } : '/'
      else
        return undefined
    }

    if (!isLoggedIn) {
      return {
        name: 'login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }

    if (userData.value?.premiereConnexion && to.name !== 'auth-first-login')
      return { name: 'auth-first-login' }

    if (!userData.value?.premiereConnexion && to.name === 'auth-first-login')
      return '/'

    if (!canNavigate(to) && to.matched.length)
      return { name: 'not-authorized' }
  })
}
