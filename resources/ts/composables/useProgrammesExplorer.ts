import type { MouvementRow, MouvementStats, PaginationMeta, ProgrammeRow, ProgrammeStats } from '@/types/details'
import { $api } from '@/utils/api'

export interface ProgrammeFilters {
  region_code?: string | null
  date_debut?: string
  date_fin?: string
  annee?: number
  mois?: number | null
  programme?: string | null
  statut?: string | null
  chapitre?: string | null
  type?: string | null
  search?: string
  page?: number
  per_page?: number
}

export function useProgrammesExplorer() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<MouvementRow[]>([])
  const stats = ref<ProgrammeStats | null>(null)
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(filters: ProgrammeFilters) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: MouvementRow[]
        stats: ProgrammeStats
        meta: PaginationMeta
      }>('/v1/programmes', {
        query: {
          region_code: filters.region_code ?? undefined,
          date_debut: filters.date_debut,
          date_fin: filters.date_fin,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
          programme: filters.programme ?? undefined,
          statut: filters.statut ?? undefined,
          chapitre: filters.chapitre ?? undefined,
          type: filters.type ?? 'depense',
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
      error.value = e instanceof Error ? e.message : 'Impossible de charger les programmes.'
      items.value = []
      stats.value = null
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, items, stats, meta, fetch }
}
