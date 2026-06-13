<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import { endOfMonth, formatDateFr, formatDateRange, formatFcfa, startOfMonth } from '@/composables/useFormat'
import { useDashboardSummary } from '@/composables/useDashboardSummary'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const selectedRegion = ref<string | null>(null)
const dateDebut = ref(startOfMonth())
const dateFin = ref(endOfMonth())

const { loading, error, summary, fetchSummary } = useDashboardSummary()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()

const quickLinks = [
  { title: 'Mandats', hint: 'Explorateur interactif', icon: 'tabler-file-invoice', to: { name: 'details-mandats' } },
  { title: 'Recettes', hint: 'Clients & encaissements', icon: 'tabler-cash', to: { name: 'details-recettes' } },
  { title: 'Banques', hint: 'Flux trésorerie', icon: 'tabler-building-bank', to: { name: 'details-banques' } },
  { title: 'Programmes', hint: 'Exécution budgétaire', icon: 'tabler-layout-grid', to: { name: 'details-programmes' } },
  { title: 'Natures CE', hint: 'Classification CE', icon: 'tabler-category', to: { name: 'details-natures-ce' } },
  { title: 'Vue centrale', hint: 'Multi-régions', icon: 'tabler-chart-dots-3', to: { name: 'dashboards-central' } },
]

const kpis = computed(() => {
  const data = summary.value?.kpis
  if (!data) {
    return [
      { label: 'Recettes', value: '—', accent: 'recettes' as KpiAccent, icon: 'tabler-trending-up' },
      { label: 'Dépenses', value: '—', accent: 'depenses' as KpiAccent, icon: 'tabler-trending-down' },
      { label: 'Solde', value: '—', accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
      { label: 'Encaisse', value: '—', accent: 'encaisse' as KpiAccent, icon: 'tabler-vault' },
    ]
  }
  return [
    { label: 'Recettes', value: formatFcfa(data.total_recettes), accent: 'recettes' as KpiAccent, icon: 'tabler-trending-up' },
    { label: 'Dépenses', value: formatFcfa(data.total_depenses), accent: 'depenses' as KpiAccent, icon: 'tabler-trending-down' },
    { label: 'Solde', value: formatFcfa(data.solde), accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
    { label: 'Encaisse', value: formatFcfa(data.encaisse), accent: 'encaisse' as KpiAccent, icon: 'tabler-vault' },
  ]
})

const heroStats = computed(() => {
  if (!summary.value)
    return []
  return [
    { label: 'Mouvements', value: (summary.value.meta.mouvements_count ?? 0).toLocaleString('fr-FR') },
    { label: 'Période', value: periodLabel.value },
  ]
})

const regionLabel = computed(() => summary.value ? `${summary.value.region.nom} (${summary.value.region.code})` : null)
const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))
const lastUpdate = computed(() => formatDateFr(summary.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (summary.value?.meta.mouvements_count ?? 0) > 0)

const statutsChart = computed(() => ({
  labels: summary.value?.statuts_mandats.map(r => r.statut) ?? [],
  datasets: [{ data: summary.value?.statuts_mandats.map(r => r.count) ?? [] }],
}))

const mandatsTypeChart = computed(() => ({
  labels: summary.value?.mandats_par_type.map(r => r.libelle) ?? [],
  datasets: [{ label: 'Montant (FCFA)', data: summary.value?.mandats_par_type.map(r => r.montant) ?? [] }],
}))

async function loadDashboard() {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchSummary({
    region_code: selectedRegion.value ?? undefined,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  })

  if (!selectedRegion.value && summary.value?.region.code)
    selectedRegion.value = summary.value.region.code
}

watch([selectedRegion, dateDebut, dateFin], () => loadDashboard())

onMounted(async () => {
  await fetchRegions()
  if (regions.value.length)
    selectedRegion.value = regions.value[0].code
  await loadDashboard()
})
</script>

<template>
  <div class="aice-page aice-regional-dashboard">
    <ExplorerHero
      icon="tabler-chart-bar"
      title="Tableau de bord régional"
      subtitle="Synthèse des mouvements, mandats et soldes de trésorerie — données Push en temps réel."
      class="aice-dashboard-hero"
      :stats="heroStats"
    >
      <template #below>
        <div
          v-if="regionLabel"
          class="aice-dashboard-hero__meta"
        >
          {{ regionLabel }}{{ lastUpdate ? ` · MAJ ${lastUpdate}` : '' }}
        </div>
      </template>
    </ExplorerHero>

    <QuickLinkGrid :links="quickLinks" />

    <div class="aice-sticky-toolbar">
      <div class="d-flex flex-wrap align-center gap-3">
        <RegionSelector
          v-model="selectedRegion"
          :regions="regions"
          :loading="regionsLoading"
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
        <VSpacer />
        <VBtn
          variant="flat"
          color="primary"
          size="small"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadDashboard"
        >
          Actualiser
        </VBtn>
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

    <VAlert
      v-else-if="!loading && !hasData"
      type="info"
      variant="tonal"
      class="mb-4"
      density="compact"
    >
      Aucune donnée pour {{ regionLabel ?? 'cette région' }} entre le {{ periodLabel }}.
      Élargissez la plage de dates si les données proviennent d'une autre période.
    </VAlert>

    <VRow
      v-if="loading"
      class="aice-kpi-grid"
    >
      <VCol
        v-for="i in 4"
        :key="i"
        cols="12"
        sm="6"
        lg="3"
      >
        <VSkeletonLoader type="card" />
      </VCol>
    </VRow>

    <VRow
      v-else
      class="aice-kpi-grid"
    >
      <VCol
        v-for="kpi in kpis"
        :key="kpi.label"
        cols="12"
        sm="6"
        lg="3"
      >
        <KpiStat
          :label="kpi.label"
          :value="kpi.value"
          :accent="kpi.accent"
          :icon="kpi.icon"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel
          title="Répartition par statut"
          :subtitle="periodLabel"
        >
          <ChartWidget
            v-if="hasData && statutsChart.labels.length"
            type="doughnut"
            :labels="statutsChart.labels"
            :datasets="statutsChart.datasets"
            :height="260"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune donnée pour cette période.
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="7"
      >
        <DataPanel
          title="Mandats par type"
          subtitle="Matériel · Salaire · Reversement"
        >
          <ChartWidget
            v-if="hasData && mandatsTypeChart.labels.length"
            type="bar"
            :labels="mandatsTypeChart.labels"
            :datasets="mandatsTypeChart.datasets"
            :height="260"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune donnée pour cette période.
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mt-1">
      <VCol
        cols="12"
        lg="8"
      >
        <DataPanel
          title="Statuts des mandats"
          :subtitle="periodLabel"
        >
          <VTable
            v-if="summary?.statuts_mandats.length"
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Statut</th>
                <th class="text-end">
                  Nombre
                </th>
                <th class="text-end">
                  Montant
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in summary.statuts_mandats"
                :key="row.statut"
              >
                <td><StatutChip :statut="row.statut" /></td>
                <td class="text-end tabular-nums">
                  {{ row.count.toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(row.montant) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel title="Types de mandats">
          <MandatsTypeTable
            :rows="summary?.mandats_par_type ?? []"
            :loading="loading"
          />
        </DataPanel>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped lang="scss">
.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
