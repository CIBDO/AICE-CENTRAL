<script setup lang="ts">
interface HeroStat {
  label: string
  value: string
}

interface Props {
  eyebrow?: string
  title: string
  subtitle?: string
  meta?: string | null
  stats?: HeroStat[]
}

withDefaults(defineProps<Props>(), {
  eyebrow: 'Direction Générale du Trésor',
  subtitle: undefined,
  meta: null,
  stats: () => [],
})
</script>

<template>
  <section class="aice-hero">
    <div class="aice-hero__inner">
      <div class="d-flex flex-wrap align-start justify-space-between gap-4">
        <div>
          <div class="aice-hero__eyebrow">
            {{ eyebrow }}
          </div>
          <h1 class="aice-hero__title">
            {{ title }}
          </h1>
          <p
            v-if="subtitle"
            class="aice-hero__subtitle"
          >
            {{ subtitle }}
          </p>
          <div
            v-if="meta"
            class="aice-hero__meta"
          >
            {{ meta }}
          </div>
          <div
            v-if="$slots.actions"
            class="aice-hero__actions"
          >
            <slot name="actions" />
          </div>
        </div>
        <div
          v-if="stats.length"
          class="d-flex flex-wrap gap-6"
        >
          <div
            v-for="stat in stats"
            :key="stat.label"
          >
            <div class="text-caption text-white text-opacity-70 text-uppercase font-weight-bold mb-1">
              {{ stat.label }}
            </div>
            <div class="text-h5 font-weight-bold tabular-nums">
              {{ stat.value }}
            </div>
          </div>
        </div>
      </div>
      <slot />
    </div>
  </section>
</template>

<style scoped lang="scss">
.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
