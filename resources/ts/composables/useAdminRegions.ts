import { $api } from '@/utils/api'

export interface AdminRegion {
  id: number
  code: string
  nom: string
  actif: boolean
  ordre: number
  token_masked: string | null
  derniere_connexion: string | null
  source_type: string | null
}

export function useAdminRegions() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const regions = ref<AdminRegion[]>([])

  async function fetch() {
    loading.value = true
    error.value = null
    try {
      const response = await $api<{ data: AdminRegion[] }>('/v1/regions/admin')
      regions.value = response.data
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Erreur chargement régions.'
    }
    finally {
      loading.value = false
    }
  }

  async function update(id: number, payload: { nom?: string, actif?: boolean, ordre?: number }) {
    await $api(`/v1/regions/${id}`, { method: 'PUT', body: payload })
  }

  return { loading, error, regions, fetch, update }
}
