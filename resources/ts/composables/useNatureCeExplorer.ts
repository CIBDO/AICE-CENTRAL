import type { MouvementRow, NatureCeStats, PaginationMeta } from '@/types/details'
import { $api } from '@/utils/api'

export interface NatureCeFilters {
  region_code?: string | null
  date_debut?: string
  date_fin?: string
  annee?: number
  mois?: number | null
  nature_ce?: string | null
  statut?: string | null
  chapitre?: string | null
  search?: string
  page?: number
  per_page?: number
}

export function useNatureCeExplorer() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<MouvementRow[]>([])
  const stats = ref<NatureCeStats | null>(null)
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(filters: NatureCeFilters) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: MouvementRow[]
        stats: NatureCeStats
        meta: PaginationMeta
      }>('/v1/natures-ce', {
        query: {
          region_code: filters.region_code ?? undefined,
          date_debut: filters.date_debut,
          date_fin: filters.date_fin,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
          nature_ce: filters.nature_ce ?? undefined,
          statut: filters.statut ?? undefined,
          chapitre: filters.chapitre ?? undefined,
          type: 'depense',
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
      error.value = e instanceof Error ? e.message : 'Impossible de charger les mandats par nature CE.'
      items.value = []
      stats.value = null
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, items, stats, meta, fetch }
}
