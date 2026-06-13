import { $api } from '@/utils/api'

export interface AdminRegion {
  id: number
  code: string
  nom: string
  actif: boolean
  ordre: number
  token: string | null
  token_masked: string | null
  derniere_connexion: string | null
  source_type: string | null
}

export interface RegionFormPayload {
  code?: string
  nom: string
  actif?: boolean
  ordre?: number
  source_type?: string
}

export interface RegionTokenResponse {
  data: AdminRegion
  token_plain: string
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

  async function create(payload: RegionFormPayload): Promise<RegionTokenResponse> {
    return await $api<RegionTokenResponse>('/v1/regions', {
      method: 'POST',
      body: payload,
    })
  }

  async function update(id: number, payload: { nom?: string, actif?: boolean, ordre?: number }) {
    await $api(`/v1/regions/${id}`, { method: 'PUT', body: payload })
  }

  async function regenerateToken(id: number): Promise<RegionTokenResponse> {
    return await $api<RegionTokenResponse>(`/v1/regions/${id}/regenerate-token`, {
      method: 'POST',
    })
  }

  return { loading, error, regions, fetch, create, update, regenerateToken }
}
