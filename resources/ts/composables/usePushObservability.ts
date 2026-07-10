import type {
  PushEventRow,
  PushEventsMeta,
  PushRegionsResponse,
} from '@/types/push-observability'
import { $api } from '@/utils/api'

export interface PushObservabilityQuery {
  region_code?: string
  status?: 'OK' | 'ERROR' | null
  endpoint?: string | null
  date_debut?: string
  date_fin?: string
  page?: number
  per_page?: number
  retard_minutes?: number
}

export function usePushObservability() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const summary = ref<PushRegionsResponse['summary'] | null>(null)
  const regions = ref<PushRegionsResponse['regions']>([])
  const topErrors = ref<PushRegionsResponse['top_errors']>([])
  const events = ref<PushEventRow[]>([])
  const meta = ref<PushEventsMeta | null>(null)

  async function fetchAll(query: PushObservabilityQuery = {}) {
    loading.value = true
    error.value = null

    const params = {
      region_code: query.region_code,
      status: query.status ?? undefined,
      endpoint: query.endpoint ?? undefined,
      date_debut: query.date_debut,
      date_fin: query.date_fin,
      page: query.page ?? 1,
      per_page: query.per_page ?? 20,
      retard_minutes: query.retard_minutes ?? 180,
    }

    try {
      const [regionsRes, eventsRes] = await Promise.all([
        $api<{ status: string; data: PushRegionsResponse }>('/v1/push-events/regions', { query: params }),
        $api<{ status: string; data: PushEventRow[]; meta: PushEventsMeta }>('/v1/push-events', { query: params }),
      ])

      summary.value = regionsRes.data.summary
      regions.value = regionsRes.data.regions
      topErrors.value = regionsRes.data.top_errors
      events.value = eventsRes.data
      meta.value = eventsRes.meta
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Impossible de charger l\'observabilité des push.'
    }
    finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    summary,
    regions,
    topErrors,
    events,
    meta,
    fetchAll,
  }
}
