<script setup lang="ts">
import type { RegionOption } from '@/types/dashboard'

interface Props {
  regions: RegionOption[]
  modelValue: string | null
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const items = computed(() =>
  props.regions.map(region => ({
    title: `${region.nom} (${region.code})`,
    value: region.code,
  })),
)
</script>

<template>
  <VSelect
    :model-value="modelValue"
    :items="items"
    :loading="loading"
    item-title="title"
    item-value="value"
    label="Région"
    density="compact"
    hide-details
    style="min-inline-size: 240px;"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
