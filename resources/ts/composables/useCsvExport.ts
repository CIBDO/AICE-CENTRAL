export function useCsvExport() {
  const exporting = ref(false)
  const error = ref<string | null>(null)

  async function download(
    path: string,
    query: Record<string, string | number | null | undefined>,
    filename: string,
  ) {
    exporting.value = true
    error.value = null

    try {
      const accessToken = useCookie('accessToken').value
      const params = new URLSearchParams()

      for (const [key, value] of Object.entries(query)) {
        if (value != null && value !== '')
          params.set(key, String(value))
      }

      const base = import.meta.env.VITE_API_BASE_URL || '/api'
      const url = `${base}${path}?${params.toString()}`

      const response = await fetch(url, {
        credentials: 'include',
        headers: accessToken ? { Authorization: `Bearer ${accessToken}` } : {},
      })

      if (!response.ok)
        throw new Error('Export impossible.')

      const blob = await response.blob()
      const objectUrl = URL.createObjectURL(blob)
      const anchor = document.createElement('a')

      anchor.href = objectUrl
      anchor.download = filename
      anchor.click()
      URL.revokeObjectURL(objectUrl)
    }
    catch (e) {
      error.value = e instanceof Error ? e.message : 'Export impossible.'
    }
    finally {
      exporting.value = false
    }
  }

  return { exporting, error, download }
}
