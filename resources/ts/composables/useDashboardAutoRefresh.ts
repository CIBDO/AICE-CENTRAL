import { useIntervalFn } from '@vueuse/core'

/** Push régional AICE-API : `everyFiveMinutes` */
export const DASHBOARD_PUSH_INTERVAL_MS = 5 * 60 * 1000

/** Rafraîchissement UI : 1 min après le push pour récupérer les données reçues */
export const DASHBOARD_AUTO_REFRESH_INTERVAL_MS = 6 * 60 * 1000

/**
 * Actualise automatiquement un tableau de bord toutes les 6 minutes
 * (juste après le push automatique de 5 minutes).
 */
export function useDashboardAutoRefresh(refresh: () => void | Promise<void>) {
  useIntervalFn(async () => {
    if (document.visibilityState !== 'visible')
      return

    await refresh()
  }, DASHBOARD_AUTO_REFRESH_INTERVAL_MS)
}
