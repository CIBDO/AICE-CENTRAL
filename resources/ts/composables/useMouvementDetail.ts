import type { MouvementDetail, MouvementRow } from '@/types/details'
import { $api } from '@/utils/api'

export interface MouvementDetailContext {
  region_code: string | null
  region_nom: string | null
  annee: number | null
  mois: number | null
}

export function useMouvementDetail() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const mouvement = ref<MouvementDetail | null>(null)
  const related = ref<MouvementRow[]>([])
  const context = ref<MouvementDetailContext | null>(null)

  async function fetch(id: number, filters: { region_code?: string | null, annee?: number, mois?: number | null }) {
    loading.value = true
    error.value = null

    try {
      const response = await $api<{
        status: string
        data: MouvementDetail
        related: MouvementRow[]
        context: MouvementDetailContext
      }>(`/v1/mouvements/${id}`, {
        query: {
          region_code: filters.region_code ?? undefined,
          annee: filters.annee,
          mois: filters.mois ?? undefined,
        },
      })

      mouvement.value = response.data
      related.value = response.related
      context.value = response.context
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Mandat introuvable.'
      mouvement.value = null
      related.value = []
    }
    finally {
      loading.value = false
    }
  }

  return { loading, error, mouvement, related, context, fetch }
}
