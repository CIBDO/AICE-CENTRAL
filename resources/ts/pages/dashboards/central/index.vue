<script setup lang="ts">
import DashboardHero from '@/components/aice/DashboardHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import type { KpiAccent } from '@/types/dashboard'
import { formatDateFr, formatFcfa, formatMonthYear } from '@/composables/useFormat'
import { useCentralSummary } from '@/composables/useCentralSummary'

definePage({ meta: { layout: 'default' } })

const annee = ref(new Date().getFullYear())
const mois = ref<number | null>(new Date().getMonth() + 1)

const { loading, error, summary, fetchSummary } = useCentralSummary()

const quickLinks = [
  { title: 'Vue régionale', hint: 'Par région', icon: 'tabler-chart-bar', to: { name: 'dashboards-regional' } },
  { title: 'Vue exécutive', hint: 'Indicateurs stratégiques', icon: 'tabler-chart-line', to: { name: 'dashboards-executive' } },
  { title: 'Mandats', hint: 'Explorateur interactif', icon: 'tabler-file-invoice', to: { name: 'details-mandats' } },
]

const periodLabel = computed(() => formatMonthYear(annee.value, mois.value))

const lastUpdate = computed(() => formatDateFr(summary.value?.meta.derniere_mise_a_jour))

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

const recettesChart = computed(() => {
  const rows = summary.value?.regions.filter(r => r.meta.has_data) ?? []
  return {
    labels: rows.map(r => r.region.code),
    datasets: [{ label: 'Recettes', data: rows.map(r => r.kpis.total_recettes) }],
  }
})

async function loadDashboard() {
  await fetchSummary({ annee: annee.value, mois: mois.value })
}

watch([annee, mois], () => loadDashboard())

onMounted(() => loadDashboard())
</script>

<template>
  <div class="aice-page">
    <DashboardHero
      title="Tableau de bord central"
      subtitle="Vue agrégée de toutes les régions actives."
      :meta="summary?.meta.derniere_mise_a_jour ? `MAJ ${lastUpdate}` : null"
      :stats="heroStats"
    />

    <QuickLinkGrid :links="quickLinks" />

    <div class="aice-sticky-toolbar">
      <div class="d-flex flex-wrap align-center gap-3">
        <VSelect
          v-model="annee"
          :items="[annee, annee - 1, annee - 2, annee - 3, annee - 4, annee - 5]"
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

    <div
      v-if="!loading && summary && summary.meta.regions_avec_donnees === 0"
      class="aice-empty-banner mb-4"
    >
      Aucune donnée reçue pour {{ periodLabel }}. Les régions doivent pousser leurs données via l'API Push.
    </div>

    <VRow v-if="loading">
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

    <VRow v-else>
      <VCol
        v-for="kpi in globalKpis"
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

    <VRow class="mt-1">
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel
          title="Recettes par région"
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
                  Recettes
                </th>
                <th class="text-end">
                  Dépenses
                </th>
                <th class="text-end">
                  Solde
                </th>
                <th class="text-end">
                  Encaisse
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
                  {{ row.meta.has_data ? formatFcfa(row.kpis.total_recettes) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.total_depenses) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.solde) : '—' }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.meta.has_data ? formatFcfa(row.kpis.encaisse) : '—' }}
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
.aice-empty-banner {
  background: rgb(var(--v-theme-grey-50));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
  font-size: 0.8125rem;
  padding-block: 0.875rem;
  padding-inline: 1rem;
}

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
