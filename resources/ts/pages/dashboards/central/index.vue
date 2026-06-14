<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import type { KpiAccent } from '@/types/dashboard'
import { endOfMonth, formatDateFr, formatDateRange, formatFcfa, startOfMonth } from '@/composables/useFormat'
import { useCentralSummary } from '@/composables/useCentralSummary'

definePage({ meta: { layout: 'default' } })

const dateDebut = ref(startOfMonth())
const dateFin = ref(endOfMonth())

const { loading, error, summary, fetchSummary } = useCentralSummary()

const quickLinks = [
  { title: 'Vue régionale', hint: 'Par région', icon: 'tabler-chart-bar', to: { name: 'dashboards-regional' } },
  { title: 'Vue exécutive', hint: 'Indicateurs stratégiques', icon: 'tabler-chart-line', to: { name: 'dashboards-executive' } },
  { title: 'Mandats', hint: 'Explorateur interactif', icon: 'tabler-file-invoice', to: { name: 'details-mandats' } },
]

const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))
const lastUpdate = computed(() => formatDateFr(summary.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (summary.value?.meta.regions_avec_donnees ?? 0) > 0)

const heroStats = computed(() => {
  if (!summary.value) {
    return [
      { label: 'Régions actives', value: '—' },
      { label: 'Avec données', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Régions actives', value: String(summary.value.meta.regions_actives) },
    { label: 'Avec données', value: String(summary.value.meta.regions_avec_donnees) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const globalKpis = computed(() => {
  const data = summary.value?.global

  if (!data) {
    return [
      { label: 'Ordonnancé', value: '—', accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
      { label: 'Recouvrements (4121)', value: '—', accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
      { label: 'Payé + Réglé', value: '—', accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
      { label: 'Trésorerie réelle', value: '—', accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
      { label: 'Écart (4121 − ord.)', value: '—', accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
    ]
  }

  return [
    { label: 'Ordonnancé', value: formatFcfa(data.total_ordonnance), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { label: 'Recouvrements (4121)', value: formatFcfa(data.total_recouvrements_4121), accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
    { label: 'Payé + Réglé', value: formatFcfa(data.total_montant_paye), accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
    { label: 'Trésorerie réelle', value: formatFcfa(data.tresorerie_reelle), accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
    { label: 'Écart (4121 − ord.)', value: formatFcfa(data.solde), accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
  ]
})

const recettesChart = computed(() => {
  const rows = summary.value?.regions.filter(r => r.meta.has_data) ?? []
  return {
    labels: rows.map(r => r.region.code),
    datasets: [{ label: 'Recouvrements (4121)', data: rows.map(r => r.kpis.total_recouvrements_4121) }],
  }
})

async function loadDashboard() {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchSummary({
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  })
}

watch([dateDebut, dateFin], () => loadDashboard())

onMounted(() => loadDashboard())
</script>

<template>
  <div class="aice-page aice-central-dashboard">
    <ExplorerHero
      icon="tabler-chart-dots-3"
      title="Tableau de bord central"
      subtitle="Vue agrégée de toutes les régions actives."
      class="aice-dashboard-hero"
      :stats="heroStats"
    >
      <template #below>
        <div
          v-if="summary?.meta.derniere_mise_a_jour"
          class="aice-dashboard-hero__meta"
        >
          MAJ {{ lastUpdate }}
        </div>
      </template>
    </ExplorerHero>

    <QuickLinkGrid :links="quickLinks" />

    <div class="aice-sticky-toolbar">
      <div class="d-flex flex-wrap align-center gap-3">
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
      Aucune donnée reçue entre le {{ periodLabel }}.
      Élargissez la plage de dates si les données proviennent d'une autre période.
    </VAlert>

    <VRow v-if="loading">
      <VCol
        v-for="i in 5"
        :key="i"
        cols="12"
        sm="6"
        lg="4"
        xl="2"
      >
        <VSkeletonLoader type="card" />
      </VCol>
    </VRow>

    <VRow v-else>
      <VCol
        v-for="kpi in globalKpis"
        :key="kpi.label"
        cols="12"
        sm="6"
        lg="4"
        xl="2"
      >
        <KpiStat
          :label="kpi.label"
          :value="kpi.value"
          :accent="kpi.accent"
          :icon="kpi.icon"
        />
      </VCol>
    </VRow>

    <VRow class="mt-1">
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel
          title="Recouvrements (4121) par région"
          :subtitle="periodLabel"
        >
          <ChartWidget
            v-if="recettesChart.labels.length"
            type="bar"
            :labels="recettesChart.labels"
            :datasets="recettesChart.datasets"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune donnée graphique pour cette période.
          </div>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        lg="7"
      >
        <DataPanel
          title="Synthèse par région"
          :subtitle="`${summary?.meta.regions_avec_donnees ?? 0} / ${summary?.meta.regions_actives ?? 0} régions avec données`"
        >
          <VTable
            v-if="summary?.regions.length"
            density="compact"
            class="aice-simple-table"
          >
            <thead>
              <tr>
                <th>Région</th>
                <th class="text-end">
                  Recouv. (4121)
                </th>
                <th class="text-end">
                  Ordonnancé
                </th>
                <th class="text-end">
                  Payé + Réglé
                </th>
                <th class="text-end">
                  Écart
                </th>
                <th class="text-end">
                  Trésorerie
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in summary.regions"
                :key="row.region.code"
                :class="{ 'aice-row--empty': !row.meta.has_data }"
              >
                <td>
                  <span class="font-weight-medium">{{ row.region.nom }}</span>
                  <span class="text-medium-emphasis ms-1">({{ row.region.code }})</span>
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.total_recouvrements_4121) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.total_ordonnance) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.total_montant_paye) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.solde) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.tresorerie_reelle) : '—' }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped lang="scss">
.aice-simple-table {
  :deep(thead th) {
    background: rgb(var(--v-theme-grey-50));
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  :deep(tbody td) {
    font-size: 0.8125rem;
  }
}

.aice-row--empty {
  opacity: 0.55;
}

.aice-panel-empty,
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
