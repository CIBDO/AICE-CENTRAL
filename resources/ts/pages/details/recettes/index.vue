<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import ExportButton from '@/components/aice/ExportButton.vue'
import SparklineChart from '@/components/aice/SparklineChart.vue'
import { formatFcfa, formatMonthYear } from '@/composables/useFormat'
import { useRecettesExplorer } from '@/composables/useRecettesExplorer'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const selectedRegion = ref<string | null>(null)
const annee = ref(new Date().getFullYear())
const mois = ref<number | null>(new Date().getMonth() + 1)
const search = ref('')
const clientFilter = ref<string | null>(null)
const activeClient = ref<string | null>(null)
const page = ref(1)

const { loading, error, items, stats, meta, fetch } = useRecettesExplorer()
const { regions, fetchRegions } = useRegions()

const periodLabel = computed(() => formatMonthYear(annee.value, mois.value))

const heroStats = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { label: 'Opérations', value: '—' },
      { label: 'Total recettes', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Opérations', value: t.count.toLocaleString('fr-FR') },
    { label: 'Total recettes', value: formatFcfa(t.montant_total) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const exportQuery = computed(() => ({
  region_code: selectedRegion.value,
  annee: annee.value,
  mois: mois.value,
  client_no: clientFilter.value,
  search: search.value || undefined,
}))

const kpiCards = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { key: 'total', label: 'Recettes', value: '—', accent: 'recettes' as const, icon: 'tabler-cash' },
      { key: 'clients', label: 'Clients actifs', value: '—', accent: 'neutral' as const, icon: 'tabler-users' },
      { key: 'moyenne', label: 'Montant moyen', value: '—', accent: 'solde' as const, icon: 'tabler-calculator' },
      { key: 'top', label: 'Part top client', value: '—', accent: 'encaisse' as const, icon: 'tabler-chart-pie' },
    ]
  }

  return [
    { key: 'total', label: 'Recettes', value: formatFcfa(t.montant_total), accent: 'recettes' as const, icon: 'tabler-cash' },
    { key: 'clients', label: 'Clients actifs', value: String(t.clients_uniques), accent: 'neutral' as const, icon: 'tabler-users' },
    { key: 'moyenne', label: 'Montant moyen', value: formatFcfa(t.montant_moyen), accent: 'solde' as const, icon: 'tabler-calculator' },
    { key: 'top', label: 'Part top client', value: `${t.top_client_part_pct} %`, accent: 'encaisse' as const, icon: 'tabler-chart-pie' },
  ]
})

const sparkline = computed(() => {
  const rows = stats.value?.par_jour.filter(r => r.date !== 'sans-date') ?? []
  return { labels: rows.map(r => r.date), data: rows.map(r => r.montant ?? 0) }
})

const clientsChart = computed(() => ({
  labels: stats.value?.top_clients.map(c => c.client_name.slice(0, 18)) ?? [],
  datasets: [{ label: 'Montant', data: stats.value?.top_clients.map(c => c.montant) ?? [] }],
}))

function load() {
  fetch({
    region_code: selectedRegion.value,
    annee: annee.value,
    mois: mois.value,
    client_no: clientFilter.value,
    search: search.value,
    page: page.value,
  })
}

function selectClient(clientNo: string | null) {
  clientFilter.value = clientFilter.value === clientNo ? null : clientNo
  activeClient.value = clientFilter.value
  page.value = 1
  load()
}

watch([selectedRegion, annee, mois], () => { page.value = 1; load() })

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})

onMounted(async () => {
  await fetchRegions()
  if (regions.value.length)
    selectedRegion.value = regions.value[0].code
  load()
})

const headers = [
  { title: 'Date', key: 'date_posting', width: '110px' },
  { title: 'Client', key: 'client_name' },
  { title: 'N° client', key: 'client_no', width: '110px' },
  { title: 'Compte GL', key: 'gl_account', width: '100px' },
  { title: 'Montant', key: 'montant', align: 'end' as const },
]
</script>

<template>
  <div class="aice-page aice-explorer">
    <ExplorerHero
      icon="tabler-cash"
      title="Explorateur recettes"
      subtitle="Recettes clients — classement, tendances et filtrage par client."
      :stats="heroStats"
    />

    <div class="aice-sticky-toolbar">
      <div class="d-flex flex-wrap align-center gap-3">
        <RegionSelector
          v-model="selectedRegion"
          :regions="regions"
        />
        <VSelect
          v-model="annee"
          :items="[annee, annee - 1]"
          label="Année"
          density="compact"
          hide-details
          style="max-inline-size: 100px;"
        />
        <VSelect
          v-model="mois"
          :items="Array.from({ length: 12 }, (_, i) => ({ title: new Date(2024, i).toLocaleString('fr-FR', { month: 'long' }), value: i + 1 }))"
          item-title="title"
          item-value="value"
          label="Mois"
          density="compact"
          hide-details
          style="max-inline-size: 150px;"
        />
        <VTextField
          v-model="search"
          density="compact"
          hide-details
          placeholder="Client, description, compte GL…"
          prepend-inner-icon="tabler-search"
          style="min-inline-size: 240px; flex: 1;"
          clearable
        />
        <VSpacer />
        <VBtn
          variant="flat"
          color="primary"
          size="small"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <ExportButton
          path="/v1/recettes/export"
          filename="recettes.csv"
          :query="exportQuery"
        />
      </div>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
      density="compact"
    >
      {{ error }}
    </VAlert>

    <VRow class="mb-1">
      <VCol
        v-for="card in kpiCards"
        :key="card.key"
        cols="12"
        sm="6"
        lg="3"
      >
        <KpiStat
          :label="card.label"
          :value="card.value"
          :accent="card.accent"
          :icon="card.icon"
        />
      </VCol>
    </VRow>

    <VRow
      v-if="stats"
      class="mb-1"
    >
      <VCol
        cols="12"
        lg="6"
      >
        <DataPanel
          title="Encaissements journaliers"
          :subtitle="periodLabel"
        >
          <SparklineChart
            v-if="sparkline.labels.length"
            :labels="sparkline.labels"
            :data="sparkline.data"
            :height="240"
          />
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <DataPanel
          title="Top clients"
          subtitle="Cliquer pour filtrer le tableau"
        >
          <ChartWidget
            v-if="clientsChart.labels.length"
            type="bar"
            :labels="clientsChart.labels"
            :datasets="clientsChart.datasets"
            :height="200"
          />
          <div class="aice-client-tags mt-3">
            <VChip
              v-for="client in stats.top_clients"
              :key="client.client_no"
              size="small"
              :color="activeClient === client.client_no ? 'primary' : undefined"
              :variant="activeClient === client.client_no ? 'flat' : 'outlined'"
              class="me-1 mb-1"
              @click="selectClient(client.client_no)"
            >
              {{ client.client_name }} · {{ formatFcfa(client.montant) }}
            </VChip>
            <VChip
              v-if="clientFilter"
              size="small"
              variant="tonal"
              color="secondary"
              class="mb-1"
              @click="selectClient(null)"
            >
              Effacer filtre client
            </VChip>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <DataPanel :title="`Opérations (${meta?.total ?? 0})`">
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="aice-data-table"
        :items-per-page="-1"
        hide-default-footer
      >
        <template #item.montant="{ item }">
          <span class="tabular-nums font-weight-medium">{{ formatFcfa(item.montant) }}</span>
        </template>
      </VDataTable>
      <div
        v-if="meta && meta.last_page > 1"
        class="d-flex justify-center pa-4"
      >
        <VPagination
          :model-value="page"
          :length="meta.last_page"
          density="compact"
          @update:model-value="(p: number) => { page = p; load() }"
        />
      </div>
    </DataPanel>
  </div>
</template>

<style scoped lang="scss">
.aice-data-table :deep(thead th) {
  background: rgb(var(--v-theme-grey-50));
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
}

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
