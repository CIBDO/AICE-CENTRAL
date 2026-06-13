<script setup lang="ts">
import LineChart from '@core/libs/chartjs/components/LineChart'

const props = defineProps<{
  labels: string[]
  data: number[]
  label?: string
  height?: number
}>()

const chartData = computed(() => ({
  labels: props.labels.map(d => d.slice(8)),
  datasets: [{
    label: props.label ?? 'Montant',
    data: props.data,
    borderColor: '#08A04B',
    backgroundColor: 'rgba(8, 160, 75, 0.12)',
    fill: true,
    tension: 0.35,
    pointRadius: 2,
    pointHoverRadius: 4,
  }],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 8 } },
    y: { grid: { color: '#E2E8F0' }, ticks: { font: { size: 10 } } },
  },
}))
</script>

<template>
  <div
    class="aice-sparkline"
    :style="{ blockSize: `${height ?? 200}px` }"
  >
    <LineChart
      :chart-data="chartData"
      :chart-options="chartOptions"
      :height="height ?? 200"
    />
  </div>
</template>

<style scoped lang="scss">
.aice-sparkline {
  position: relative;
}
</style>
