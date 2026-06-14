import type {
  ExecutiveAlert,
  ExecutiveAnomaly,
  ExecutiveKpis,
  ExecutivePredictions,
} from '@/types/dashboard'
import { $api } from '@/utils/api'

interface ExecutiveQuery {
  annee?: number
  mois?: number | null
  date_debut?: string
  date_fin?: string
}

interface FetchOptions {
  silent?: boolean
}

export function useExecutiveDashboard() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const kpis = ref<ExecutiveKpis | null>(null)
  const alertes = ref<ExecutiveAlert[]>([])
  const anomalies = ref<ExecutiveAnomaly[]>([])
  const predictions = ref<ExecutivePredictions | null>(null)

  async function fetchAll(query: ExecutiveQuery = {}, options: FetchOptions = {}) {
    const silent = options.silent ?? false

    if (!silent)
      loading.value = true
    if (!silent)
      error.value = null

    const params = {
      annee: query.annee,
      mois: query.mois ?? undefined,
      date_debut: query.date_debut,
      date_fin: query.date_fin,
    }

    try {
      const [kpisRes, alertesRes, anomaliesRes, predictionsRes] = await Promise.all([
        $api<{ status: string; data: ExecutiveKpis }>('/v1/executive/kpis', { query: params }),
        $api<{ status: string; data: ExecutiveAlert[] }>('/v1/executive/alertes', { query: params }),
        $api<{ status: string; data: ExecutiveAnomaly[] }>('/v1/executive/anomalies', { query: params }),
        $api<{ status: string; data: ExecutivePredictions }>('/v1/executive/predictions', { query: params }),
      ])

      kpis.value = kpisRes.data
      alertes.value = alertesRes.data
      anomalies.value = anomaliesRes.data
      predictions.value = predictionsRes.data
    }
    catch (e) {
      if (!silent)
        error.value = e instanceof Error ? e.message : 'Impossible de charger le tableau de bord exécutif.'
    }
    finally {
      if (!silent)
        loading.value = false
    }
  }

  return {
    loading,
    error,
    kpis,
    alertes,
    anomalies,
    predictions,
    fetchAll,
  }
}
