<script setup lang="ts">
import type { RegionOption } from '@/types/dashboard'

interface Props {
  regions: RegionOption[]
  modelValue: string | null
  loading?: boolean
  allowAll?: boolean
  allLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  allowAll: false,
  allLabel: 'Toutes les régions',
})

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const items = computed(() => {
  const regionItems = props.regions.map(region => ({
    title: `${region.nom} (${region.code})`,
    value: region.code,
  }))

  if (!props.allowAll)
    return regionItems

  return [
    { title: props.allLabel, value: null as string | null },
    ...regionItems,
  ]
})
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
