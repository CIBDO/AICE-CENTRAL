<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import ExportButton from '@/components/aice/ExportButton.vue'
import SparklineChart from '@/components/aice/SparklineChart.vue'
import { formatFcfa, formatDateOnly, formatDayLabel } from '@/composables/useFormat'
import { queryParam, useExplorerRouteSync } from '@/composables/useDetailExplorerContext'
import { useRecettesExplorer } from '@/composables/useRecettesExplorer'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const {
  regionCode,
  dateDebut,
  dateFin,
  periodLabel,
  periodQuery,
  baseQuery,
  isValidPeriod,
  syncRoute,
  hydrateFromRoute,
} = useExplorerRouteSync(
  () => ({
    search: search.value || undefined,
    client_no: clientFilter.value,
    page: page.value > 1 ? page.value : undefined,
  }),
  (query) => {
    search.value = queryParam(query.search) ?? ''
    clientFilter.value = queryParam(query.client_no) ?? null
    activeClient.value = clientFilter.value
    const p = queryParam(query.page)
    page.value = p ? Number(p) : 1
  },
)
const search = ref('')
const clientFilter = ref<string | null>(null)
const activeClient = ref<string | null>(null)
const page = ref(1)

const { loading, error, items, stats, meta, fetch } = useRecettesExplorer()
const { regions, fetchRegions } = useRegions()

const heroStats = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { label: 'Recettes', value: '—' },
      { label: 'Montant recettes', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Recettes', value: t.count.toLocaleString('fr-FR') },
    { label: 'Montant recettes', value: formatFcfa(t.montant_total) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const exportQuery = computed(() => baseQuery({
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
  return { labels: rows.map(r => formatDayLabel(r.date)), data: rows.map(r => r.montant ?? 0) }
})

const clientsChart = computed(() => ({
  labels: stats.value?.top_clients.map(c => c.client_name.slice(0, 18)) ?? [],
  datasets: [{ label: 'Montant', data: stats.value?.top_clients.map(c => c.montant) ?? [] }],
}))

function load() {
  if (!isValidPeriod())
    return

  fetch({
    region_code: regionCode.value,
    ...periodQuery(),
    client_no: clientFilter.value,
    search: search.value,
    page: page.value,
  })
}

function selectClient(clientNo: string | null) {
  clientFilter.value = clientFilter.value === clientNo ? null : clientNo
  activeClient.value = clientFilter.value
  page.value = 1
}

watch([regionCode, dateDebut, dateFin, clientFilter], () => {
  page.value = 1
  syncRoute()
  load()
})

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    syncRoute()
    load()
  }, 350)
})

watch(page, () => {
  syncRoute()
  load()
})

onMounted(async () => {
  await fetchRegions()
  hydrateFromRoute()
  if (!regionCode.value && regions.value.length)
    regionCode.value = regions.value[0].code
  syncRoute()
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
      subtitle="Analyse des recettes clients. Les mandats sont consultés dans l'explorateur Mandats."
      :stats="heroStats"
    />

    <div class="aice-sticky-toolbar">
      <div class="d-flex flex-wrap align-center gap-3">
        <RegionSelector
          v-model="regionCode"
          :regions="regions"
        />
        <VTextField
          v-model="dateDebut"
          label="Date début"
          type="date"
          density="compact"
          hide-details
          variant="outlined"
          style="max-inline-size: 170px;"
        />
        <VTextField
          v-model="dateFin"
          label="Date fin"
          type="date"
          density="compact"
          hide-details
          variant="outlined"
          style="max-inline-size: 170px;"
        />
        <VTextField
          v-model="search"
          density="compact"
          hide-details
          placeholder="Rechercher un client, une description, un compte GL…"
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
          filename="recettes-clients.csv"
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
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucun encaissement journalier sur cette période.
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <DataPanel
          title="Top clients"
          subtitle="Cliquer pour filtrer les recettes"
        >
          <ChartWidget
            v-if="clientsChart.labels.length"
            type="bar"
            :labels="clientsChart.labels"
            :datasets="clientsChart.datasets"
            :height="200"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucun client avec recettes sur cette période.
          </div>
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

    <DataPanel :title="`Recettes (${meta?.total ?? 0})`">
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="aice-data-table"
        :items-per-page="-1"
        hide-default-footer
        no-data-text="Aucune recette trouvée pour les filtres sélectionnés."
      >
        <template #item.date_posting="{ item }">
          <span class="tabular-nums">{{ formatDateOnly(item.date_posting) }}</span>
        </template>
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

.aice-panel-empty {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  padding-block: 2rem;
  text-align: center;
}
</style>
