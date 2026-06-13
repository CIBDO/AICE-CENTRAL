<script setup lang="ts">
const props = defineProps<{
  statut: string
}>()

const colorMap: Record<string, string> = {
  'Payé': 'success',
  'Réglé': 'success',
  'Admis': 'warning',
}

const chipColor = computed(() => {
  if (!props.statut || props.statut === '—')
    return 'secondary'
  if (props.statut.includes('Rejet'))
    return 'error'

  return colorMap[props.statut] ?? 'secondary'
})
</script>

<template>
  <VChip
    v-if="statut && statut !== '—'"
    :color="chipColor"
    size="x-small"
    variant="tonal"
    class="aice-statut-chip"
  >
    {{ statut }}
  </VChip>
  <span
    v-else
    class="text-medium-emphasis"
  >—</span>
</template>

<style scoped lang="scss">
.aice-statut-chip {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}
</style>
