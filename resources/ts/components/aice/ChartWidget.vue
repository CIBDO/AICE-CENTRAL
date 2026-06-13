<script setup lang="ts">
import BarChart from '@core/libs/chartjs/components/BarChart'
import DoughnutChart from '@core/libs/chartjs/components/DoughnutChart'

const props = defineProps<{
  type: 'bar' | 'doughnut'
  labels: string[]
  datasets: Array<{
    label?: string
    data: number[]
    backgroundColor?: string | string[]
  }>
  height?: number
}>()

const chartColors = {
  primary: '#1E3A5F',
  secondary: '#4A5568',
  success: '#276749',
  error: '#C53030',
  warning: '#B7791F',
  muted: '#CBD5E0',
}

const defaultPalette = [
  chartColors.primary,
  chartColors.secondary,
  chartColors.success,
  chartColors.warning,
  chartColors.error,
  chartColors.muted,
]

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map((dataset, index) => ({
    ...dataset,
    backgroundColor: dataset.backgroundColor ?? (
      props.type === 'doughnut'
        ? defaultPalette.slice(0, props.labels.length)
        : chartColors.primary
    ),
    borderWidth: props.type === 'doughnut' ? 1 : 0,
    borderColor: '#FFFFFF',
    maxBarThickness: 40,
  })),
}))

const chartOptions = computed(() => {
  const base = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: props.type === 'doughnut',
        position: 'bottom' as const,
        labels: {
          boxWidth: 10,
          padding: 12,
          font: { size: 11 },
        },
      },
    },
  }

  if (props.type === 'bar') {
    return {
      ...base,
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } },
        },
        y: {
          beginAtZero: true,
          grid: { color: '#E2E8F0' },
          ticks: { font: { size: 11 } },
        },
      },
    }
  }

  return {
    ...base,
    cutout: '55%',
  }
})
</script>

<template>
  <div
    class="aice-chart"
    :style="{ blockSize: `${height ?? 260}px` }"
  >
    <BarChart
      v-if="type === 'bar'"
      :chart-data="chartData"
      :chart-options="chartOptions"
      :height="height ?? 260"
    />
    <DoughnutChart
      v-else
      :chart-data="chartData"
      :chart-options="chartOptions"
      :height="height ?? 260"
    />
  </div>
</template>

<style scoped lang="scss">
.aice-chart {
  position: relative;
}
</style>
