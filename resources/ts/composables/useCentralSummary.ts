import type { CentralSummary } from '@/types/dashboard'
import { $api } from '@/utils/api'

interface CentralQuery {
  annee?: number
  mois?: number | null
}

export function useCentralSummary() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const summary = ref<CentralSummary | null>(null)

  async function fetchSummary(query: CentralQuery = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{ status: string; data: CentralSummary }>('/v1/central/summary', {
        query: {
          annee: query.annee,
          mois: query.mois ?? undefined,
        },
      })

      summary.value = response.data
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Impossible de charger le tableau de bord central.'
      summary.value = null
    }
    finally {
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
