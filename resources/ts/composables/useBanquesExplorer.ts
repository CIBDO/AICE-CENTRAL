import type { BanqueRow, BanqueStats, PaginationMeta } from '@/types/details'
import { $api } from '@/utils/api'

export interface BanqueFilters {
  region_code?: string | null
  date_debut?: string
  date_fin?: string
  annee?: number
  mois?: number | null
  numero_compte?: string | null
  search?: string
  page?: number
  per_page?: number
}

export function useBanquesExplorer() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<BanqueRow[]>([])
  const stats = ref<BanqueStats | null>(null)
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(filters: BanqueFilters) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: BanqueRow[]
        stats: BanqueStats
        meta: PaginationMeta
      }>('/v1/banques', {
        query: {
          region_code: filters.region_code ?? undefined,
          date_debut: filters.date_debut,
          date_fin: filters.date_fin,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
          numero_compte: filters.numero_compte ?? undefined,
          search: filters.search || undefined,
          page: filters.page ?? 1,
          per_page: filters.per_page ?? 15,
        },
      })

      items.value = response.data
      stats.value = response.stats
      meta.value = response.meta
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Impossible de charger les mouvements bancaires.'
      items.value = []
      stats.value = null
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, items, stats, meta, fetch }
}
