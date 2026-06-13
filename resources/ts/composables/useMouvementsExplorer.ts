import type { MouvementRow, MouvementStats, PaginationMeta } from '@/types/details'
import { $api } from '@/utils/api'

export interface MouvementFilters {
  region_code?: string | null
  annee?: number
  mois?: number | null
  type?: string | null
  statut?: string | null
  type_mandat?: string | null
  programme?: string | null
  search?: string
  page?: number
  per_page?: number
}

export function useMouvementsExplorer() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<MouvementRow[]>([])
  const stats = ref<MouvementStats | null>(null)
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(filters: MouvementFilters) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: MouvementRow[]
        stats: MouvementStats
        meta: PaginationMeta
      }>('/v1/mouvements', {
        query: {
          region_code: filters.region_code ?? undefined,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
          type: filters.type ?? undefined,
          statut: filters.statut ?? undefined,
          type_mandat: filters.type_mandat ?? undefined,
          programme: filters.programme ?? undefined,
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
      error.value = e instanceof Error ? e.message : 'Impossible de charger les mandats.'
      items.value = []
      stats.value = null
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, items, stats, meta, fetch }
}
