<script setup lang="ts">
import { useCsvExport } from '@/composables/useCsvExport'

const props = defineProps<{
  path: string
  filename: string
  query: Record<string, string | number | null | undefined>
}>()

const { exporting, download } = useCsvExport()
</script>

<template>
  <VBtn
    variant="outlined"
    color="secondary"
    size="small"
    prepend-icon="tabler-file-spreadsheet"
    :loading="exporting"
    @click="download(path, query, filename)"
  >
    Export Excel
  </VBtn>
</template>
