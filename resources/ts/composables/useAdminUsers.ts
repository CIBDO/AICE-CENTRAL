import type { PaginationMeta } from '@/types/details'
import { $api } from '@/utils/api'

export interface AdminUser {
  id: number
  login: string
  nom: string
  prenom: string
  email: string | null
  actif: boolean
  premiere_connexion: boolean
  role_id: number | null
  role?: { id: number, nom: string } | null
}

export interface UserPayload {
  login: string
  nom: string
  prenom: string
  email?: string
  password?: string
  role_id?: number | null
  actif?: boolean
}

export function useAdminUsers() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const users = ref<AdminUser[]>([])
  const meta = ref<PaginationMeta | null>(null)

  async function fetch(page = 1, search = '') {
    loading.value = true
    error.value = null
    try {
      const response = await $api<{ data: AdminUser[] }>('/v1/users', {
        query: { search: search || undefined },
      })
      users.value = response.data
      meta.value = { current_page: 1, last_page: 1, per_page: response.data.length, total: response.data.length }
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Erreur chargement utilisateurs.'
    }
    finally {
      loading.value = false
    }
  }

  async function create(payload: UserPayload) {
    return await $api<{ message?: string; data: AdminUser }>('/v1/users', { method: 'POST', body: payload })
  }

  async function update(id: number, payload: Partial<UserPayload>) {
    return await $api<{ data: AdminUser }>(`/v1/users/${id}`, { method: 'PUT', body: payload })
  }

  async function resetPassword(id: number) {
    return await $api<{ message: string; data: AdminUser }>(`/v1/users/${id}/reset-password`, { method: 'POST' })
  }

  async function setActive(id: number, actif: boolean) {
    return await update(id, { actif })
  }

  async function remove(id: number) {
    await $api(`/v1/users/${id}`, { method: 'DELETE' })
  }

  return { loading, error, users, meta, fetch, create, update, resetPassword, setActive, remove }
}
