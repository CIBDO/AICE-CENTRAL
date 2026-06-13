<script setup lang="ts">
const annee = defineModel<number>('annee', { default: () => new Date().getFullYear() })
const mois = defineModel<number | null>('mois', { default: () => new Date().getMonth() + 1 })

const moisOptions = [
  { title: 'Janvier', value: 1 },
  { title: 'Février', value: 2 },
  { title: 'Mars', value: 3 },
  { title: 'Avril', value: 4 },
  { title: 'Mai', value: 5 },
  { title: 'Juin', value: 6 },
  { title: 'Juillet', value: 7 },
  { title: 'Août', value: 8 },
  { title: 'Septembre', value: 9 },
  { title: 'Octobre', value: 10 },
  { title: 'Novembre', value: 11 },
  { title: 'Décembre', value: 12 },
]

const anneeOptions = computed(() => {
  const current = new Date().getFullYear()

  return Array.from({ length: 6 }, (_, i) => ({
    title: String(current - i),
    value: current - i,
  }))
})

const emit = defineEmits<{
  refresh: []
}>()
</script>

<template>
  <VCard
    class="aice-period-toolbar"
    rounded="0"
    elevation="0"
  >
    <VCardText class="d-flex flex-wrap align-center gap-4 pa-4">
      <slot name="region" />

      <div class="aice-period-toolbar__group">
        <span class="aice-period-toolbar__label">Période</span>
        <div class="d-flex flex-wrap gap-3">
          <VSelect
            v-model="annee"
            :items="anneeOptions"
            item-title="title"
            item-value="value"
            label="Année"
            density="compact"
            hide-details
            style="max-inline-size: 120px;"
          />

          <VSelect
            v-model="mois"
            :items="moisOptions"
            item-title="title"
            item-value="value"
            label="Mois"
            density="compact"
            hide-details
            clearable
            style="max-inline-size: 160px;"
          />
        </div>
      </div>

      <VSpacer />

      <VBtn
        variant="outlined"
        color="secondary"
        size="small"
        prepend-icon="tabler-refresh"
        @click="emit('refresh')"
      >
        Actualiser
      </VBtn>
    </VCardText>
  </VCard>
</template>

<style scoped lang="scss">
.aice-period-toolbar {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  margin-block-end: 1.5rem;

  &__group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }
}
</style>
