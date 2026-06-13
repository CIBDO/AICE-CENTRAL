import { $api } from '@/utils/api'

export interface RoleRow {
  id: number
  nom: string
  description: string | null
  permissions_count: number
}

export function useAdminRoles() {
  const loading = ref(false)
  const roles = ref<RoleRow[]>([])

  async function fetch() {
    loading.value = true
    try {
      const response = await $api<{ data: RoleRow[] }>('/v1/roles')
      roles.value = response.data
    }
    finally {
      loading.value = false
    }
  }

  return { loading, roles, fetch }
}
