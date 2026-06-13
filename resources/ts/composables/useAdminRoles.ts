import { $api } from '@/utils/api'

export interface RoleRow {
  id: number
  nom: string
  description: string | null
  permissions_count: number
  users_count: number
}

export interface RoleDetail extends RoleRow {
  permission_ids: number[]
}

export interface RolePayload {
  nom: string
  description?: string | null
  permission_ids: number[]
}

export function useAdminRoles() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const roles = ref<RoleRow[]>([])

  async function fetch() {
    loading.value = true
    error.value = null
    try {
      const response = await $api<{ data: RoleRow[] }>('/v1/roles')
      roles.value = response.data
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Erreur chargement des rôles.'
    }
    finally {
      loading.value = false
    }
  }

  async function show(id: number) {
    const response = await $api<{ data: RoleDetail }>(`/v1/roles/${id}`)
    return response.data
  }

  async function create(payload: RolePayload) {
    return await $api<{ data: RoleDetail }>('/v1/roles', { method: 'POST', body: payload })
  }

  async function update(id: number, payload: Partial<RolePayload>) {
    return await $api<{ data: RoleDetail }>(`/v1/roles/${id}`, { method: 'PUT', body: payload })
  }

  async function remove(id: number) {
    await $api(`/v1/roles/${id}`, { method: 'DELETE' })
  }

  return { loading, error, roles, fetch, show, create, update, remove }
}
