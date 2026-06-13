import { $api } from '@/utils/api'

export interface PermissionRow {
  id: number
  nom: string
  description: string | null
}

export function useAdminPermissions() {
  const loading = ref(false)
  const permissions = ref<PermissionRow[]>([])

  async function fetch() {
    loading.value = true
    try {
      const response = await $api<{ data: PermissionRow[] }>('/v1/permissions')
      permissions.value = response.data
    }
    finally {
      loading.value = false
    }
  }

  return { loading, permissions, fetch }
}
