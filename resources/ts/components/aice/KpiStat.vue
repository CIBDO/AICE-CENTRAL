<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'

interface Props {
  label: string
  value: string
  variation?: string | null
  variationType?: 'up' | 'down' | 'neutral'
  accent?: KpiAccent
}

withDefaults(defineProps<Props>(), {
  variation: null,
  variationType: 'neutral',
  accent: 'neutral',
})
</script>

<template>
  <VCard
    class="aice-kpi-stat"
    :class="`aice-kpi-stat--${accent}`"
    rounded="lg"
  >
    <VCardText class="pa-5">
      <div class="aice-kpi-stat__label">
        {{ label }}
      </div>
      <div class="aice-kpi-stat__value">
        {{ value }}
      </div>
      <div
        v-if="variation"
        class="aice-kpi-stat__variation"
        :class="`aice-kpi-stat__variation--${variationType}`"
      >
        {{ variation }}
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped lang="scss">
.aice-kpi-stat {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  border-inline-start-width: 3px;

  &--recettes {
    border-inline-start-color: rgb(var(--v-theme-success));
  }

  &--depenses {
    border-inline-start-color: rgb(var(--v-theme-error));
  }

  &--solde,
  &--encaisse,
  &--neutral {
    border-inline-start-color: rgb(var(--v-theme-primary));
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    line-height: 1.2;
    margin-block-end: 0.75rem;
    text-transform: uppercase;
  }

  &__value {
    color: rgb(var(--v-theme-on-surface));
    font-size: 1.5rem;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    line-height: 1.25;
  }

  &__variation {
    font-size: 0.75rem;
    margin-block-start: 0.5rem;

    &--up {
      color: rgb(var(--v-theme-success));
    }

    &--down {
      color: rgb(var(--v-theme-error));
    }

    &--neutral {
      color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    }
  }
}
</style>
