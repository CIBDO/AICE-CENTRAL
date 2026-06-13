import type { RegionOption } from '@/types/dashboard'
import { $api } from '@/utils/api'

export function useRegions() {
  const loading = ref(false)
  const regions = ref<RegionOption[]>([])

  async function fetchRegions() {
    loading.value = true
    try {
      const response = await $api<{ status: string; data: RegionOption[] }>('/v1/regions')
      regions.value = response.data
    }
    finally {
      loading.value = false
    }
  }

  return {
    loading,
    regions,
    fetchRegions,
  }
}
