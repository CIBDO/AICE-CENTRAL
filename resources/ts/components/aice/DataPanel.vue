<script setup lang="ts">
interface Props {
  title: string
  subtitle?: string
}

defineProps<Props>()
</script>

<template>
  <VCard
    class="aice-data-panel"
    rounded="lg"
  >
    <div class="aice-data-panel__head">
      <div>
        <div class="aice-data-panel__title">
          {{ title }}
        </div>
        <div
          v-if="subtitle"
          class="aice-data-panel__subtitle"
        >
          {{ subtitle }}
        </div>
      </div>
      <div
        v-if="$slots.actions"
        class="aice-data-panel__actions"
      >
        <slot name="actions" />
      </div>
    </div>
    <div class="aice-data-panel__body">
      <slot />
    </div>
  </VCard>
</template>

<style scoped lang="scss">
.aice-data-panel {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));

  &__head {
    align-items: flex-start;
    border-block-end: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding-block: 1rem;
    padding-inline: 1.25rem;
  }

  &__title {
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.875rem;
    font-weight: 600;
  }

  &__subtitle {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.75rem;
    margin-block-start: 0.15rem;
  }

  &__body {
    padding: 1.25rem;
  }
}
</style>
