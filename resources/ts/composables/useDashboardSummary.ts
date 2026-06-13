import type { DashboardSummary } from '@/types/dashboard'
import { $api } from '@/utils/api'

interface SummaryQuery {
  region_code?: string
  annee?: number
  mois?: number | null
}

export function useDashboardSummary() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const summary = ref<DashboardSummary | null>(null)

  async function fetchSummary(query: SummaryQuery = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{ status: string; data: DashboardSummary }>('/v1/dashboards/summary', {
        query: {
          region_code: query.region_code,
          annee: query.annee,
          mois: query.mois ?? undefined,
        },
      })

      summary.value = response.data
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Impossible de charger le tableau de bord.'
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
