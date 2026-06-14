<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import ExportButton from '@/components/aice/ExportButton.vue'
import FilterChipBar from '@/components/aice/FilterChipBar.vue'
import SparklineChart from '@/components/aice/SparklineChart.vue'
import StatutChip from '@/components/aice/StatutChip.vue'
import { formatFcfa, formatMonthYear, formatPercent } from '@/composables/useFormat'
import { useNatureCeExplorer } from '@/composables/useNatureCeExplorer'
import { useRegions } from '@/composables/useRegions'
import type { MouvementRow, NatureCeRow } from '@/types/details'

definePage({ meta: { layout: 'default' } })

const route = useRoute()
const router = useRouter()

const selectedRegion = ref<string | null>(null)
const annee = ref(new Date().getFullYear())
const mois = ref<number | null>(new Date().getMonth() + 1)
const search = ref('')
const natureCeFilter = ref<string | null>(null)
const statutFilter = ref<string | null>(null)
const chapitreFilter = ref<string | null>(null)
const activeKpi = ref<string | null>(null)
const page = ref(1)

const { loading, error, items, stats, meta, fetch } = useNatureCeExplorer()
const { regions, fetchRegions } = useRegions()

const periodLabel = computed(() => formatMonthYear(annee.value, mois.value))

const heroStats = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { label: 'Natures CE', value: '—' },
      { label: 'Mandats', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Natures CE', value: String(t.natures_ce_count) },
    { label: 'Mandats', value: t.mandats_count.toLocaleString('fr-FR') },
    { label: 'Période', value: periodLabel.value },
  ]
})

const statutChips = [
  { label: 'Tous', value: null },
  { label: 'Payé', value: 'Payé' },
  { label: 'Admis', value: 'Admis' },
  { label: 'Rejeté', value: 'Rejeté' },
]

const exportQuery = computed(() => ({
  region_code: selectedRegion.value,
  annee: annee.value,
  mois: mois.value,
  nature_ce: natureCeFilter.value,
  statut: statutFilter.value,
  chapitre: chapitreFilter.value,
  type: 'depense',
  search: search.value || undefined,
}))

const kpiCards = computed(() => {
  const t = stats.value?.totaux
  if (!t) {
    return [
      { key: 'natures', label: 'Natures CE actives', value: '—', accent: 'neutral' as const, icon: 'tabler-category' },
      { key: 'mandats', label: 'Mandats', value: '—', accent: 'depenses' as const, icon: 'tabler-file-invoice' },
      { key: 'montant', label: 'Montant dépenses', value: '—', accent: 'solde' as const, icon: 'tabler-currency-franc' },
      { key: 'execution', label: 'Taux exécution', value: '—', accent: 'recettes' as const, icon: 'tabler-percentage' },
    ]
  }

  return [
    { key: 'natures', label: 'Natures CE actives', value: String(t.natures_ce_count), accent: 'neutral' as const, icon: 'tabler-category' },
    { key: 'mandats', label: 'Mandats', value: t.mandats_count.toLocaleString('fr-FR'), accent: 'depenses' as const, icon: 'tabler-file-invoice' },
    { key: 'montant', label: 'Montant ordonnancé', value: formatFcfa(t.montant_ordonnance), accent: 'solde' as const, icon: 'tabler-currency-franc' },
    { key: 'execution', label: 'Taux exécution', value: formatPercent(t.taux_execution_pct), accent: 'recettes' as const, icon: 'tabler-percentage' },
  ]
})

const selectedNatureCe = computed(() =>
  stats.value?.natures_ce.find(n => n.code === natureCeFilter.value) ?? null,
)

const sparkline = computed(() => {
  const rows = stats.value?.par_jour.filter(r => r.date !== 'sans-date') ?? []
  return { labels: rows.map(r => r.date), data: rows.map(r => r.montant ?? 0) }
})

const statutChart = computed(() => ({
  labels: stats.value?.par_statut.map(s => s.label) ?? [],
  datasets: [{ data: stats.value?.par_statut.map(s => s.count) ?? [] }],
}))

const chapitreChart = computed(() => ({
  labels: stats.value?.par_chapitre.map(c => c.label) ?? [],
  datasets: [{ label: 'Montant', data: stats.value?.par_chapitre.map(c => c.montant) ?? [] }],
}))

function load() {
  fetch({
    region_code: selectedRegion.value,
    annee: annee.value,
    mois: mois.value,
    nature_ce: natureCeFilter.value,
    statut: statutFilter.value,
    chapitre: chapitreFilter.value,
    search: search.value,
    page: page.value,
  })
}

function selectNatureCe(nature: NatureCeRow) {
  natureCeFilter.value = natureCeFilter.value === nature.code ? null : nature.code
  chapitreFilter.value = null
  page.value = 1
  load()
}

function onKpiSelect(key: string) {
  activeKpi.value = activeKpi.value === key ? null : key

  if (key === 'execution' && activeKpi.value) {
    statutFilter.value = 'Payé'
  }
  else if (key === 'mandats' && activeKpi.value) {
    statutFilter.value = null
    natureCeFilter.value = null
  }
  else if (activeKpi.value === null) {
    statutFilter.value = null
  }

  page.value = 1
  load()
}

function onChapitreClick(label: string) {
  chapitreFilter.value = chapitreFilter.value === label ? null : label
  page.value = 1
  load()
}

function openMandat(item: MouvementRow) {
  router.push({
    name: 'details-mandats-id',
    params: { id: item.id },
    query: {
      region_code: selectedRegion.value ?? undefined,
      annee: annee.value,
      mois: mois.value ?? undefined,
    },
  })
}

watch([selectedRegion, annee, mois], () => { page.value = 1; load() })
watch(statutFilter, () => { page.value = 1; load() })

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})

onMounted(async () => {
  await fetchRegions()
  if (regions.value.length)
    selectedRegion.value = regions.value[0].code

  if (route.query.region_code)
    selectedRegion.value = String(route.query.region_code)
  if (route.query.annee)
    annee.value = Number(route.query.annee)
  if (route.query.mois)
    mois.value = Number(route.query.mois)
  if (route.query.nature_ce)
    natureCeFilter.value = String(route.query.nature_ce)

  load()
})

const headers = [
  { title: 'Date', key: 'date_mouvement', width: '110px' },
  { title: 'Libellé', key: 'libelle' },
  { title: 'Statut', key: 'statut', width: '120px' },
  { title: 'Nature CE', key: 'nature_ce', width: '100px' },
  { title: 'Montant', key: 'montant', align: 'end' as const, width: '140px' },
  { title: '', key: 'actions', width: '48px', sortable: false },
]
</script>

<template>
  <div class="aice-page aice-explorer">
    <ExplorerHero
      icon="tabler-category"
      title="Explorateur natures CE"
      subtitle="Vue agrégée par nature de crédit d'engagement — sélection, exécution et drill-down mandats."
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
          :items="[annee, annee - 1, annee - 2]"
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
          placeholder="Nature CE, chapitre, libellé…"
          prepend-inner-icon="tabler-search"
          style="min-inline-size: 220px; flex: 1;"
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
          path="/v1/natures-ce/export"
          filename="natures-ce-mandats.csv"
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

    <DataPanel
      v-if="stats?.natures_ce.length"
      title="Natures CE"
      :subtitle="natureCeFilter ? `Filtre : ${natureCeFilter}` : 'Cliquez une carte pour filtrer les mandats'"
      class="mb-4"
    >
      <VRow>
        <VCol
          v-for="nature in stats.natures_ce"
          :key="nature.code"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            :class="['aice-nature-ce-card', { 'aice-nature-ce-card--active': natureCeFilter === nature.code }]"
            elevation="0"
            @click="selectNatureCe(nature)"
          >
            <VCardText class="pa-4">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-h6 font-weight-bold">{{ nature.code }}</span>
                <VChip
                  size="x-small"
                  :color="nature.taux_execution_pct >= 70 ? 'success' : nature.taux_execution_pct >= 40 ? 'warning' : 'error'"
                  variant="tonal"
                >
                  {{ nature.taux_execution_pct }} %
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis mb-2 text-truncate">
                {{ nature.libelle }}
              </div>
              <div class="d-flex justify-space-between text-body-2">
                <span>{{ nature.count }} mandats</span>
                <span class="font-weight-medium">{{ formatFcfa(nature.montant_depenses) }}</span>
              </div>
              <VProgressLinear
                :model-value="nature.taux_execution_pct"
                color="primary"
                height="4"
                rounded
                class="mt-3"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </DataPanel>

    <VAlert
      v-if="selectedNatureCe"
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
      closable
      @click:close="selectNatureCe(selectedNatureCe)"
    >
      Nature CE <strong>{{ selectedNatureCe.code }}</strong> — {{ selectedNatureCe.paye_count }} payés / {{ selectedNatureCe.count }} mandats
    </VAlert>

    <VRow
      v-if="stats"
      class="mb-1"
    >
      <VCol
        cols="12"
        lg="6"
      >
        <DataPanel
          title="Exécution journalière"
          :subtitle="periodLabel"
        >
          <SparklineChart
            v-if="sparkline.labels.length"
            :labels="sparkline.labels"
            :data="sparkline.data"
            label="Montant"
            :height="220"
          />
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="6"
      >
        <DataPanel title="Par statut">
          <ChartWidget
            v-if="statutChart.labels.length"
            type="doughnut"
            :labels="statutChart.labels"
            :datasets="statutChart.datasets"
            :height="220"
          />
        </DataPanel>
      </VCol>
    </VRow>

    <VRow
      v-if="stats?.par_chapitre.length"
      class="mb-4"
    >
      <VCol cols="12">
        <DataPanel
          title="Répartition par chapitre"
          subtitle="Cliquer un chapitre pour filtrer"
        >
          <div class="d-flex flex-wrap gap-2 mb-3">
            <VChip
              v-for="ch in stats.par_chapitre"
              :key="ch.label"
              size="small"
              :color="chapitreFilter === ch.label ? 'primary' : undefined"
              :variant="chapitreFilter === ch.label ? 'flat' : 'outlined'"
              @click="onChapitreClick(ch.label)"
            >
              {{ ch.label }} · {{ formatFcfa(ch.montant) }}
            </VChip>
          </div>
          <ChartWidget
            type="bar"
            :labels="chapitreChart.labels"
            :datasets="chapitreChart.datasets"
            :height="160"
          />
        </DataPanel>
      </VCol>
    </VRow>

    <DataPanel
      :title="`Mandats par nature CE (${meta?.total ?? 0})`"
      subtitle="Cliquez une ligne pour ouvrir la fiche mandat"
    >
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        density="compact"
        class="aice-data-table aice-data-table--clickable"
        :items-per-page="-1"
        hide-default-footer
        @click:row="(_ev: Event, ctx: { item: MouvementRow }) => openMandat(ctx.item)"
      >
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
          @update:model-value="(p: number) => { page = p; load() }"
        />
      </div>
    </DataPanel>
  </div>
</template>

<style scoped lang="scss">
.aice-nature-ce-card {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.12);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

.aice-data-table {
  :deep(thead th) {
    background: rgb(var(--v-theme-grey-50));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  &--clickable :deep(tbody tr) {
    cursor: pointer;

    &:hover {
      background: rgba(var(--v-theme-primary), 0.04);
    }
  }
}

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
