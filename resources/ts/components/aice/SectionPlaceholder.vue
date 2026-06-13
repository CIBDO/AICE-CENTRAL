<script setup lang="ts">
interface Props {
  title: string
  description?: string
}

withDefaults(defineProps<Props>(), {
  description: 'Cette section sera disponible lors de la prochaine phase d\'implémentation.',
})

definePage({
  meta: {
    layout: 'default',
  },
})
</script>

<template>
  <div class="aice-page">
    <header class="aice-page__header">
      <h1 class="aice-page__title">
        {{ title }}
      </h1>
      <p
        v-if="description"
        class="aice-page__subtitle"
      >
        {{ description }}
      </p>
    </header>

    <VCard
      class="aice-panel"
      rounded="lg"
    >
      <VCardText class="aice-panel__empty">
        Contenu en cours de développement.
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped lang="scss">
.aice-page {
  &__header {
    margin-block-end: 1.5rem;
  }

  &__title {
    color: rgb(var(--v-theme-on-surface));
    font-size: 1.375rem;
    font-weight: 600;
    line-height: 1.3;
    margin: 0;
  }

  &__subtitle {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.875rem;
    margin-block: 0.25rem 0;
    margin-inline: 0;
  }
}

.aice-panel {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));

  &__empty {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    padding-block: 3rem;
    text-align: center;
  }
}
</style>
