<script setup lang="ts">
const model = defineModel<string | null>({ default: null })

defineProps<{
  items: Array<{ label: string; value: string | null }>
}>()
</script>

<template>
  <div class="aice-filter-chips">
    <VChip
      v-for="item in items"
      :key="String(item.value)"
      :color="model === item.value ? 'primary' : undefined"
      :variant="model === item.value ? 'flat' : 'outlined'"
      size="small"
      class="aice-filter-chips__chip"
      @click="model = item.value"
    >
      {{ item.label }}
    </VChip>
  </div>
</template>

<style scoped lang="scss">
.aice-filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;

  &__chip {
    font-size: 0.75rem;
    font-weight: 500;
  }
}
</style>
