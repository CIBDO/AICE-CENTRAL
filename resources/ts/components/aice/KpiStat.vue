<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'

interface Props {
  label: string
  value: string
  variation?: string | null
  variationType?: 'up' | 'down' | 'neutral'
  accent?: KpiAccent
  active?: boolean
  selectable?: boolean
  icon?: string
}

withDefaults(defineProps<Props>(), {
  variation: null,
  variationType: 'neutral',
  accent: 'neutral',
  active: false,
  selectable: false,
  icon: undefined,
})

const emit = defineEmits<{ select: [] }>()
</script>

<template>
  <VCard
    class="aice-kpi-stat"
    :class="[
      `aice-kpi-stat--${accent}`,
      { 'aice-kpi-stat--active': active, 'aice-kpi-stat--selectable': selectable },
    ]"
    rounded="lg"
    @click="selectable ? emit('select') : undefined"
  >
    <VCardText class="pa-5">
      <div class="d-flex align-start justify-space-between gap-2 mb-2">
        <div class="aice-kpi-stat__label">
          {{ label }}
        </div>
        <VIcon
          v-if="icon"
          :icon="icon"
          size="20"
          class="aice-kpi-stat__icon"
        />
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

  &--recouvrements,
  &--recettes {
    border-inline-start-color: rgb(var(--v-theme-success));
  }

  &--ordonnance,
  &--depenses {
    border-inline-start-color: rgb(var(--v-theme-error));
  }

  &--solde {
    border-inline-start-color: rgb(var(--v-theme-primary));
  }

  &--tresorerie,
  &--encaisse {
    border-inline-start-color: rgb(var(--v-theme-warning));
  }

  &--paye {
    border-inline-start-color: rgb(var(--v-theme-info));
  }

  &--neutral {
    border-inline-start-color: rgb(var(--v-theme-grey-300));
  }

  &--selectable {
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;

    &:hover {
      background: rgb(var(--v-theme-grey-50));
      box-shadow: 0 6px 20px rgba(8, 160, 75, 0.1);
      transform: translateY(-1px);
    }
  }

  &__icon {
    color: rgba(var(--v-theme-on-surface), 0.35);
    opacity: 0.85;
  }

  &--active {
    background: rgba(var(--v-theme-primary), 0.06);
    border-color: rgb(var(--v-theme-primary));
    box-shadow: inset 0 0 0 1px rgba(var(--v-theme-primary), 0.25);
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
