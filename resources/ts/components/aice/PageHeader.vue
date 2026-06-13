<script setup lang="ts">
interface Props {
  title: string
  subtitle?: string
  regionLabel?: string | null
  lastUpdate?: string | null
}

defineProps<Props>()
</script>

<template>
  <header class="aice-page-header">
    <div class="aice-page-header__main">
      <div class="aice-page-header__eyebrow">
        Direction Générale du Trésor
      </div>
      <h1 class="aice-page-header__title">
        {{ title }}
      </h1>
      <p
        v-if="subtitle"
        class="aice-page-header__subtitle"
      >
        {{ subtitle }}
      </p>
    </div>

    <div
      v-if="regionLabel || lastUpdate"
      class="aice-page-header__meta"
    >
      <div
        v-if="regionLabel"
        class="aice-page-header__region"
      >
        {{ regionLabel }}
      </div>
      <div
        v-if="lastUpdate"
        class="aice-page-header__updated"
      >
        Dernière mise à jour : {{ lastUpdate }}
      </div>
    </div>
  </header>
</template>

<style scoped lang="scss">
.aice-page-header {
  align-items: flex-start;
  border-block-end: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: space-between;
  margin-block-end: 1.5rem;
  padding-block-end: 1.25rem;

  &__eyebrow {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    margin-block-end: 0.35rem;
    text-transform: uppercase;
  }

  &__title {
    color: rgb(var(--v-theme-on-surface));
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.25;
    margin: 0;
  }

  &__subtitle {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.875rem;
    margin-block: 0.35rem 0;
    margin-inline: 0;
    max-inline-size: 42rem;
  }

  &__meta {
    text-align: end;
  }

  &__region {
    color: rgb(var(--v-theme-primary));
    font-size: 0.875rem;
    font-weight: 600;
  }

  &__updated {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.75rem;
    margin-block-start: 0.25rem;
  }
}
</style>
