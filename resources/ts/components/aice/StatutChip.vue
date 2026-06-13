<script setup lang="ts">
const statut = defineModel<string>('')

const colorMap: Record<string, string> = {
  'Payé': 'success',
  'Réglé': 'success',
  'Admis': 'warning',
}

const chipColor = computed(() => {
  if (!statut.value)
    return 'secondary'
  if (statut.value.includes('Rejet'))
    return 'error'

  return colorMap[statut.value] ?? 'secondary'
})
</script>

<template>
  <VChip
    v-if="statut"
    :color="chipColor"
    size="x-small"
    variant="tonal"
    class="aice-statut-chip"
  >
    {{ statut }}
  </VChip>
</template>

<style scoped lang="scss">
.aice-statut-chip {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}
</style>
