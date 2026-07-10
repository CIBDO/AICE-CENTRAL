<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

interface QuickLink {
  title: string
  hint?: string
  icon: string
  to: RouteLocationRaw
}

defineProps<{ links: QuickLink[] }>()
</script>

<template>
  <div class="aice-quick-grid">
    <RouterLink
      v-for="link in links"
      :key="link.title"
      :to="link.to"
      class="aice-quick-card"
    >
      <div class="aice-quick-card__icon">
        <VIcon
          :icon="link.icon"
          size="22"
        />
      </div>
      <div class="aice-quick-card__label">
        {{ link.title }}
      </div>
      <div
        v-if="link.hint"
        class="aice-quick-card__hint"
      >
        {{ link.hint }}
      </div>
    </RouterLink>
  </div>
</template>
