<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import ExportButton from '@/components/aice/ExportButton.vue'
import FilterChipBar from '@/components/aice/FilterChipBar.vue'
import SparklineChart from '@/components/aice/SparklineChart.vue'
import StatutChip from '@/components/aice/StatutChip.vue'
import { formatFcfa, formatDateOnly, formatDayLabel } from '@/composables/useFormat'
import { queryParam, useExplorerRouteSync } from '@/composables/useDetailExplorerContext'
import { useMouvementsExplorer } from '@/composables/useMouvementsExplorer'
import { useRegions } from '@/composables/useRegions'
import type { MouvementRow } from '@/types/details'

definePage({ meta: { layout: 'default' } })

const router = useRouter()

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
    statut: statutFilter.value,
    programme: programmeFilter.value,
    search: search.value || undefined,
    page: page.value > 1 ? page.value : undefined,
  }),
  (query) => {
    statutFilter.value = queryParam(query.statut) ?? null
    programmeFilter.value = queryParam(query.programme) ?? null
    search.value = queryParam(query.search) ?? ''
    const p = queryParam(query.page)
    page.value = p ? Number(p) : 1
    const statut = statutFilter.value
    if (statut === 'Payé')
      activeKpi.value = 'paye'
    else if (statut === 'Admis')
      activeKpi.value = 'admis'
    else
      activeKpi.value = null
  },
)
const search = ref('')
const statutFilter = ref<string | null>(null)
const typeFilter = ref<string | null>('depense')
const programmeFilter = ref<string | null>(null)
const activeKpi = ref<string | null>(null)
const page = ref(1)
const expanded = ref<string[]>([])

const { loading, error, items, stats, meta, fetch } = useMouvementsExplorer()
const { regions, fetchRegions } = useRegions()

function effectiveNatureCe(item: MouvementRow) {
  return item.nature_ce ?? item.nature ?? '—'
}

const heroStats = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { label: 'Mandats', value: '—' },
      { label: 'Montant dépenses', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Mandats', value: t.depenses_count.toLocaleString('fr-FR') },
    { label: 'Montant ordonnancé', value: formatFcfa(t.montant_ordonnance) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const exportQuery = computed(() => ({
  ...baseQuery({
    type: typeFilter.value,
    statut: statutFilter.value,
    programme: programmeFilter.value,
    search: search.value || undefined,
  }),
}))

const statutChips = [
  { label: 'Tous', value: null },
  { label: 'Payé', value: 'Payé' },
  { label: 'Admis', value: 'Admis' },
  { label: 'Rejeté', value: 'Rejeté' },
]

const kpiCards = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { key: 'all', label: 'Mandats', value: '—', accent: 'neutral' as const, icon: 'tabler-file-invoice' },
      { key: 'montant', label: 'Montant dépenses', value: '—', accent: 'depenses' as const, icon: 'tabler-currency-franc' },
      { key: 'paye', label: 'Payés', value: '—', accent: 'recettes' as const, icon: 'tabler-check' },
      { key: 'admis', label: 'En attente', value: '—', accent: 'solde' as const, icon: 'tabler-clock' },
    ]
  }

  const payes = stats.value?.par_statut.find(s => s.label === 'Payé')?.count ?? 0
  const admis = stats.value?.par_statut.find(s => s.label === 'Admis')?.count ?? 0

  return [
    { key: 'all', label: 'Mandats', value: t.depenses_count.toLocaleString('fr-FR'), accent: 'neutral' as const, icon: 'tabler-file-invoice' },
    { key: 'montant', label: 'Montant ordonnancé', value: formatFcfa(t.montant_ordonnance), accent: 'depenses' as const, icon: 'tabler-currency-franc' },
    { key: 'paye', label: 'Payés', value: payes.toLocaleString('fr-FR'), accent: 'recettes' as const, icon: 'tabler-check' },
    { key: 'admis', label: 'En attente', value: admis.toLocaleString('fr-FR'), accent: 'solde' as const, icon: 'tabler-clock' },
  ]
})

const sparkline = computed(() => {
  const rows = stats.value?.par_jour.filter(r => r.date !== 'sans-date') ?? []
  return {
    labels: rows.map(r => formatDayLabel(r.date)),
    data: rows.map(r => r.montant ?? 0),
  }
})

const statutChart = computed(() => ({
  labels: stats.value?.par_statut.map(s => s.label) ?? [],
  datasets: [{ data: stats.value?.par_statut.map(s => s.count) ?? [] }],
}))

const programmeChart = computed(() => ({
  labels: stats.value?.par_programme.map(p => p.label) ?? [],
  datasets: [{ label: 'Montant', data: stats.value?.par_programme.map(p => p.montant) ?? [] }],
}))

function load() {
  if (!isValidPeriod())
    return

  fetch({
    region_code: regionCode.value,
    ...periodQuery(),
    type: typeFilter.value,
    statut: statutFilter.value,
    programme: programmeFilter.value,
    search: search.value,
    page: page.value,
    per_page: 15,
  })
}

function onKpiSelect(key: string) {
  activeKpi.value = activeKpi.value === key ? null : key
  programmeFilter.value = null

  if (key === 'all' || activeKpi.value === null) {
    statutFilter.value = null
  }
  else if (key === 'paye') {
    statutFilter.value = 'Payé'
  }
  else if (key === 'admis') {
    statutFilter.value = 'Admis'
  }
  else {
    statutFilter.value = null
  }

  page.value = 1
}

function onProgrammeClick(label: string) {
  programmeFilter.value = programmeFilter.value === label ? null : label
  page.value = 1
}

function onPageChange(newPage: number) {
  page.value = newPage
}

function mandatListQuery() {
  return baseQuery({
    statut: statutFilter.value,
    programme: programmeFilter.value,
    search: search.value || undefined,
    page: page.value > 1 ? page.value : undefined,
  })
}

function openMandat(item: MouvementRow) {
  router.push({
    name: 'details-mandats-id',
    params: { id: item.id },
    query: mandatListQuery(),
  })
}

watch([regionCode, dateDebut, dateFin], () => {
  page.value = 1
  syncRoute()
  load()
})

watch(statutFilter, () => {
  page.value = 1
  syncRoute()
  load()
})

watch(programmeFilter, () => {
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
  { title: 'Libellé', key: 'libelle' },
  { title: 'Statut', key: 'statut', width: '120px' },
  { title: 'Programme', key: 'code_programme', width: '100px' },
  { title: 'Montant', key: 'montant', align: 'end' as const, width: '140px' },
  { title: '', key: 'actions', width: '48px', sortable: false },
]
</script>

<template>
  <div class="aice-page aice-explorer">
    <ExplorerHero
      icon="tabler-file-invoice"
      title="Explorateur mandats"
      subtitle="Analyse interactive des mandats. Les recettes sont consultées dans l'explorateur Recettes."
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
          placeholder="Rechercher un libellé, un bénéficiaire, un n° mandat…"
          prepend-inner-icon="tabler-search"
          style="min-inline-size: 260px; flex: 1;"
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
          path="/v1/mouvements/export"
          filename="mandats.csv"
          :query="exportQuery"
        />
      </div>
    </div>

    <div class="mb-4">
      <FilterChipBar
        v-model="statutFilter"
        :items="statutChips"
      />
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
          selectable
          :active="activeKpi === card.key"
          @select="onKpiSelect(card.key)"
        />
      </VCol>
    </VRow>

    <VRow
      v-if="stats"
      class="mb-1"
    >
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel
          title="Flux journalier des mandats"
          :subtitle="periodLabel"
        >
          <SparklineChart
            v-if="sparkline.labels.length"
            :labels="sparkline.labels"
            :data="sparkline.data"
            label="Montant"
            :height="220"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucun flux journalier de mandats sur cette période.
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="3"
      >
        <DataPanel title="Statuts des mandats">
          <ChartWidget
            v-if="statutChart.labels.length"
            type="doughnut"
            :labels="statutChart.labels"
            :datasets="statutChart.datasets"
            :height="220"
          />
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Top programmes"
          subtitle="Cliquer une barre pour filtrer les mandats"
        >
          <div
            v-if="programmeChart.labels.length"
            class="aice-chart-clickable"
          >
            <ChartWidget
              type="bar"
              :labels="programmeChart.labels"
              :datasets="programmeChart.datasets"
              :height="220"
            />
            <div class="aice-programme-tags mt-2">
              <VChip
                v-for="prog in stats.par_programme"
                :key="prog.label"
                size="x-small"
                :color="programmeFilter === prog.label ? 'primary' : undefined"
                :variant="programmeFilter === prog.label ? 'flat' : 'outlined'"
                class="me-1 mb-1"
                @click="onProgrammeClick(prog.label)"
              >
                {{ prog.label }} · {{ formatFcfa(prog.montant) }}
              </VChip>
            </div>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <DataPanel
      :title="`Mandats (${meta?.total ?? 0})`"
      subtitle="Cliquez une ligne pour ouvrir la fiche mandat"
    >
      <VDataTable
        v-model:expanded="expanded"
        :headers="headers"
        :items="items"
        :loading="loading"
        :item-value="(item: MouvementRow) => String(item.id)"
        show-expand
        density="compact"
        class="aice-data-table aice-data-table--clickable"
        :items-per-page="-1"
        hide-default-footer
        no-data-text="Aucun mandat trouvé pour les filtres sélectionnés."
        @click:row="(_ev: Event, ctx: { item: MouvementRow }) => openMandat(ctx.item)"
      >
        <template #item.date_mouvement="{ item }">
          <span class="tabular-nums">{{ formatDateOnly(item.date_mouvement) }}</span>
        </template>
        <template #item.statut="{ item }">
          <StatutChip :statut="item.statut ?? '—'" />
        </template>
        <template #item.montant="{ item }">
          <span class="tabular-nums font-weight-medium">{{ formatFcfa(item.montant) }}</span>
        </template>
        <template #item.actions>
          <VIcon
            icon="tabler-chevron-right"
            size="18"
          />
        </template>
        <template #expanded-row="{ columns, item }: { columns: unknown[]; item: MouvementRow }">
          <tr>
            <td :colspan="columns.length">
              <div class="aice-expand-detail">
                <div><strong>Bénéficiaire :</strong> {{ item.beneficiaire ?? '—' }}</div>
                <div><strong>N° mandat :</strong> {{ item.source_numero_mandat ?? '—' }}</div>
                <div><strong>Type :</strong> {{ item.type_mandat_libelle ?? item.type_mandat ?? '—' }}</div>
                <div><strong>Nature CE :</strong> {{ effectiveNatureCe(item) }}</div>
                <VBtn
                  size="x-small"
                  variant="text"
                  prepend-icon="tabler-file-description"
                  class="mt-1 pa-0"
                  @click.stop="openMandat(item)"
                >
                  Ouvrir la fiche complète
                </VBtn>
              </div>
            </td>
          </tr>
        </template>
      </VDataTable>

      <div
        v-if="meta && meta.last_page > 1"
        class="d-flex justify-center pa-4"
      >
        <VPagination
          :model-value="page"
          :length="meta.last_page"
          :total-visible="7"
          density="compact"
          @update:model-value="onPageChange"
        />
      </div>
    </DataPanel>
  </div>
</template>

<style scoped lang="scss">
.aice-data-table {
  :deep(thead th) {
    background: rgb(var(--v-theme-grey-50));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  :deep(tbody td) {
    font-size: 0.8125rem;
  }

  &--clickable :deep(tbody tr) {
    cursor: pointer;

    &:hover {
      background: rgba(var(--v-theme-primary), 0.04);
    }
  }
}

.aice-expand-detail {
  display: grid;
  font-size: 0.8125rem;
  gap: 0.35rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.aice-panel-empty {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  padding-block: 2rem;
  text-align: center;
}

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
