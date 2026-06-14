import type { CentralSummary } from '@/types/dashboard'
import { $api } from '@/utils/api'

interface CentralQuery {
  annee?: number
  mois?: number | null
  date_debut?: string
  date_fin?: string
}

interface FetchOptions {
  silent?: boolean
}

export function useCentralSummary() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const summary = ref<CentralSummary | null>(null)

  async function fetchSummary(query: CentralQuery = {}, options: FetchOptions = {}) {
    const silent = options.silent ?? false

    if (!silent)
      loading.value = true
    if (!silent)
      error.value = null

    try {
      const response = await $api<{ status: string; data: CentralSummary }>('/v1/central/summary', {
        query: {
          annee: query.annee,
          mois: query.mois ?? undefined,
          date_debut: query.date_debut,
          date_fin: query.date_fin,
        },
      })

      summary.value = response.data
    }
    catch (e) {
      if (!silent) {
        error.value = e instanceof Error ? e.message : 'Impossible de charger le tableau de bord central.'
        summary.value = null
      }
    }
    finally {
      if (!silent)
        loading.value = false
    }
  }

  return {
    loading,
    error,
    summary,
    fetchSummary,
  }
}
