import type { PaginationMeta, RecetteRow, RecetteStats } from '@/types/details'
import { $api } from '@/utils/api'

export interface RecetteFilters {
  region_code?: string | null
  annee?: number
  mois?: number | null
  client_no?: string | null
  search?: string
  page?: number
  per_page?: number
}

export function useRecettesExplorer() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<RecetteRow[]>([])
  const stats = ref<RecetteStats | null>(null)
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(filters: RecetteFilters) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: RecetteRow[]
        stats: RecetteStats
        meta: PaginationMeta
      }>('/v1/recettes', {
        query: {
          region_code: filters.region_code ?? undefined,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
          client_no: filters.client_no ?? undefined,
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
      error.value = e instanceof Error ? e.message : 'Impossible de charger les recettes.'
      items.value = []
      stats.value = null
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, items, stats, meta, fetch }
}
