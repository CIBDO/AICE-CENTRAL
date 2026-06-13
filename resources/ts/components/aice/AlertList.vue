<script setup lang="ts">
import type { ExecutiveAlert } from '@/types/dashboard'

defineProps<{
  alertes: ExecutiveAlert[]
  loading?: boolean
}>()

const prioriteLabel: Record<ExecutiveAlert['priorite'], string> = {
  critique: 'Critique',
  warning: 'Vigilance',
  info: 'Information',
}
</script>

<template>
  <div v-if="loading">
    <VSkeletonLoader
      v-for="i in 3"
      :key="i"
      type="list-item-two-line"
      class="mb-2"
    />
  </div>

  <div
    v-else-if="!alertes.length"
    class="aice-alert-empty"
  >
    Aucune alerte active pour cette période.
  </div>

  <div
    v-else
    class="aice-alert-list"
  >
    <div
      v-for="alerte in alertes"
      :key="alerte.id"
      class="aice-alert-item"
      :class="`aice-alert-item--${alerte.priorite}`"
    >
      <div class="aice-alert-item__header">
        <span class="aice-alert-item__badge">{{ prioriteLabel[alerte.priorite] }}</span>
        <span class="aice-alert-item__categorie">{{ alerte.categorie }}</span>
      </div>
      <p class="aice-alert-item__titre">
        {{ alerte.titre }}
      </p>
      <p class="aice-alert-item__message">
        {{ alerte.message }}
      </p>
      <p class="aice-alert-item__action">
        {{ alerte.action_recommandee }}
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.aice-alert-empty {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  padding-block: 2rem;
  text-align: center;
}

.aice-alert-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.aice-alert-item {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  border-inline-start-width: 3px;
  padding-block: 0.875rem;
  padding-inline: 1rem;

  &--critique {
    border-inline-start-color: rgb(var(--v-theme-error));
    background: rgba(var(--v-theme-error), 0.04);
  }

  &--warning {
    border-inline-start-color: rgb(var(--v-theme-warning));
    background: rgba(var(--v-theme-warning), 0.04);
  }

  &--info {
    border-inline-start-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-grey-50));
  }

  &__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-block-end: 0.35rem;
  }

  &__badge {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  &__categorie {
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
    font-size: 0.6875rem;
    text-transform: uppercase;
  }

  &__titre {
    font-size: 0.875rem;
    font-weight: 600;
    margin-block: 0 0.25rem;
  }

  &__message,
  &__action {
    font-size: 0.8125rem;
    margin: 0;
  }

  &__message {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  }

  &__action {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    margin-block-start: 0.35rem;
  }
}
</style>
