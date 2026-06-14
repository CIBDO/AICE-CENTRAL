<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import ExportButton from '@/components/aice/ExportButton.vue'
import SparklineChart from '@/components/aice/SparklineChart.vue'
import { formatFcfa, formatDateOnly, formatDayLabel } from '@/composables/useFormat'
import { queryParam, useExplorerRouteSync } from '@/composables/useDetailExplorerContext'
import { useBanquesExplorer } from '@/composables/useBanquesExplorer'
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
    numero_compte: compteFilter.value,
    page: page.value > 1 ? page.value : undefined,
  }),
  (query) => {
    search.value = queryParam(query.search) ?? ''
    compteFilter.value = queryParam(query.numero_compte) ?? null
    const p = queryParam(query.page)
    page.value = p ? Number(p) : 1
  },
)
const search = ref('')
const compteFilter = ref<string | null>(null)
const page = ref(1)

const { loading, error, items, stats, meta, fetch } = useBanquesExplorer()
const { regions, fetchRegions } = useRegions()

const heroStats = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { label: 'Opérations', value: '—' },
      { label: 'Flux net', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Opérations', value: t.count.toLocaleString('fr-FR') },
    { label: 'Flux net', value: formatFcfa(t.flux_net) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const exportQuery = computed(() => baseQuery({
  numero_compte: compteFilter.value,
  search: search.value || undefined,
}))

const kpiCards = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { key: 'debit', label: 'Total débits', value: '—', accent: 'depenses' as const, icon: 'tabler-arrow-down-right' },
      { key: 'credit', label: 'Total crédits', value: '—', accent: 'recettes' as const, icon: 'tabler-arrow-up-right' },
      { key: 'net', label: 'Flux net', value: '—', accent: 'solde' as const, icon: 'tabler-arrows-exchange' },
      { key: 'ops', label: 'Opérations', value: '—', accent: 'neutral' as const, icon: 'tabler-list-numbers' },
    ]
  }

  return [
    { key: 'debit', label: 'Total débits', value: formatFcfa(t.total_debit), accent: 'depenses' as const, icon: 'tabler-arrow-down-right' },
    { key: 'credit', label: 'Total crédits', value: formatFcfa(t.total_credit), accent: 'recettes' as const, icon: 'tabler-arrow-up-right' },
    { key: 'net', label: 'Flux net', value: formatFcfa(t.flux_net), accent: 'solde' as const, icon: 'tabler-arrows-exchange' },
    { key: 'ops', label: 'Opérations', value: t.count.toLocaleString('fr-FR'), accent: 'neutral' as const, icon: 'tabler-list-numbers' },
  ]
})

const fluxChart = computed(() => {
  const rows = stats.value?.par_jour.filter(r => r.date !== 'sans-date') ?? []
  return {
    labels: rows.map(r => formatDayLabel(r.date)),
    datasets: [
      { label: 'Crédits', data: rows.map(r => r.credit ?? 0), backgroundColor: '#08A04B' },
      { label: 'Débits', data: rows.map(r => r.debit ?? 0), backgroundColor: '#E53935' },
    ],
  }
})

const creditSparkline = computed(() => {
  const rows = stats.value?.par_jour.filter(r => r.date !== 'sans-date') ?? []
  return { labels: rows.map(r => formatDayLabel(r.date)), data: rows.map(r => (r.credit ?? 0) - (r.debit ?? 0)) }
})

function load() {
  if (!isValidPeriod())
    return

  fetch({
    region_code: regionCode.value,
    ...periodQuery(),
    numero_compte: compteFilter.value,
    search: search.value,
    page: page.value,
  })
}

function selectCompte(numero: string | null) {
  compteFilter.value = compteFilter.value === numero ? null : numero
  page.value = 1
}

watch([regionCode, dateDebut, dateFin, compteFilter], () => {
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
  { title: 'Date', key: 'date_mouvement', width: '110px' },
  { title: 'Compte', key: 'numero_compte', width: '130px' },
  { title: 'Libellé', key: 'libelle' },
  { title: 'Débit', key: 'debit', align: 'end' as const, width: '120px' },
  { title: 'Crédit', key: 'credit', align: 'end' as const, width: '120px' },
  { title: 'Solde', key: 'solde', align: 'end' as const, width: '120px' },
]
</script>

<template>
  <div class="aice-page aice-explorer">
    <ExplorerHero
      icon="tabler-building-bank"
      title="Explorateur trésorerie"
      subtitle="Mouvements bancaires — flux débits/credits, soldes par compte."
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
          placeholder="Compte, référence, libellé…"
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
          path="/v1/banques/export"
          filename="banques.csv"
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
        lg="7"
      >
        <DataPanel
          title="Flux débits / crédits"
          :subtitle="periodLabel"
        >
          <ChartWidget
            v-if="fluxChart.labels.length"
            type="bar"
            :labels="fluxChart.labels"
            :datasets="fluxChart.datasets"
            :height="260"
          />
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel title="Flux net journalier">
          <SparklineChart
            v-if="creditSparkline.labels.length"
            :labels="creditSparkline.labels"
            :data="creditSparkline.data"
            label="Flux net"
            :height="260"
          />
        </DataPanel>
      </VCol>
    </VRow>

    <VRow
      v-if="stats?.par_compte.length"
      class="mb-1"
    >
      <VCol cols="12">
        <DataPanel
          title="Comptes"
          subtitle="Cliquer pour filtrer les opérations"
        >
          <div class="aice-comptes-grid">
            <VCard
              v-for="compte in stats.par_compte"
              :key="compte.numero_compte"
              class="aice-compte-card"
              :class="{ 'aice-compte-card--active': compteFilter === compte.numero_compte }"
              variant="outlined"
              @click="selectCompte(compte.numero_compte)"
            >
              <VCardText class="pa-3">
                <div class="aice-compte-card__num">
                  {{ compte.numero_compte }}
                </div>
                <div class="aice-compte-card__lib">
                  {{ compte.libelle }}
                </div>
                <div class="aice-compte-card__row">
                  <span class="text-success">+{{ formatFcfa(compte.credit) }}</span>
                  <span class="text-error">−{{ formatFcfa(compte.debit) }}</span>
                </div>
                <div class="aice-compte-card__solde tabular-nums">
                  Solde {{ formatFcfa(compte.solde) }}
                </div>
              </VCardText>
            </VCard>
          </div>
          <VChip
            v-if="compteFilter"
            class="mt-3"
            size="small"
            variant="tonal"
            color="secondary"
            @click="selectCompte(null)"
          >
            Effacer filtre compte
          </VChip>
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
        <template #item.date_mouvement="{ item }">
          <span class="tabular-nums">{{ formatDateOnly(item.date_mouvement) }}</span>
        </template>
        <template #item.debit="{ item }">
          <span class="tabular-nums text-error">{{ item.debit ? formatFcfa(item.debit) : '—' }}</span>
        </template>
        <template #item.credit="{ item }">
          <span class="tabular-nums text-success">{{ item.credit ? formatFcfa(item.credit) : '—' }}</span>
        </template>
        <template #item.solde="{ item }">
          <span class="tabular-nums font-weight-medium">{{ formatFcfa(item.solde) }}</span>
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
.aice-comptes-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.aice-compte-card {
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;

  &:hover {
    background: rgb(var(--v-theme-grey-50));
  }

  &--active {
    background: rgba(var(--v-theme-primary), 0.06);
    border-color: rgb(var(--v-theme-primary));
  }

  &__num {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.03em;
  }

  &__lib {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.75rem;
    margin-block: 0.25rem 0.5rem;
  }

  &__row {
    display: flex;
    font-size: 0.75rem;
    gap: 1rem;
    justify-content: space-between;
  }

  &__solde {
    font-size: 0.8125rem;
    font-weight: 600;
    margin-block-start: 0.35rem;
  }
}

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
