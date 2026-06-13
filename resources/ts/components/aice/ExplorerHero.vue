<script setup lang="ts">
interface HeroStat {
  label: string
  value: string
}

interface Props {
  icon: string
  title: string
  subtitle?: string
  stats?: HeroStat[]
}

withDefaults(defineProps<Props>(), {
  subtitle: undefined,
  stats: () => [],
})
</script>

<template>
  <header class="aice-explorer-hero">
    <div>
      <div class="aice-explorer-hero__icon">
        <VIcon
          :icon="icon"
          size="26"
        />
      </div>
      <h1 class="aice-explorer-hero__title">
        {{ title }}
      </h1>
      <p
        v-if="subtitle"
        class="aice-explorer-hero__subtitle"
      >
        {{ subtitle }}
      </p>
      <slot name="below" />
    </div>
    <div
      v-if="stats.length"
      class="aice-explorer-hero__stats"
    >
      <div
        v-for="stat in stats"
        :key="stat.label"
      >
        <div class="aice-explorer-hero__stat-label">
          {{ stat.label }}
        </div>
        <div class="aice-explorer-hero__stat-value">
          {{ stat.value }}
        </div>
      </div>
    </div>
    <div v-if="$slots.actions">
      <slot name="actions" />
    </div>
  </header>
</template>
