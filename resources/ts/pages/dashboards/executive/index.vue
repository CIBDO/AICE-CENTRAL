<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import AlertList from '@/components/aice/AlertList.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import {
  formatDateFr,
  formatEvolutionPct,
  formatFcfa,
  formatPercent,
} from '@/composables/useFormat'
import { useDashboardFilterSync } from '@/composables/useDetailExplorerContext'
import { useExecutiveDashboard } from '@/composables/useExecutiveDashboard'

definePage({ meta: { layout: 'default' } })

const { dateDebut, dateFin, periodLabel, detailRoute, dashboardRoute, hydrateFromRoute } = useDashboardFilterSync()
const { loading, error, kpis, alertes, anomalies, predictions, fetchAll } = useExecutiveDashboard()

const lastUpdate = computed(() => formatDateFr(kpis.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (kpis.value?.meta.regions_avec_donnees ?? 0) > 0)

const quickLinks = computed(() => [
  { title: 'Vue centrale', hint: 'Toutes les régions', icon: 'tabler-chart-dots-3', to: dashboardRoute('dashboards-central') },
  { title: 'Vue régionale', hint: 'Détail par région', icon: 'tabler-chart-bar', to: dashboardRoute('dashboards-regional') },
  { title: 'Mandats', hint: 'Explorateur', icon: 'tabler-file-invoice', to: detailRoute('details-mandats') },
  { title: 'Recettes', hint: 'Encaissements 4121', icon: 'tabler-cash', to: detailRoute('details-recettes') },
])

const heroStats = computed(() => {
  const ind = kpis.value?.indicateurs
  const meta = kpis.value?.meta
  if (!ind || !meta) {
    return [
      { label: 'Régions', value: '—' },
      { label: 'Mandats', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Régions actives', value: `${meta.regions_avec_donnees} / ${meta.regions_actives}` },
    { label: 'Mandats', value: ind.mandats_total.toLocaleString('fr-FR') },
    { label: 'Ordonnancé', value: formatFcfa(ind.ordonnance_total) },
  ]
})

const strategicKpis = computed(() => {
  const ind = kpis.value?.indicateurs
  if (!ind) {
    return [
      { key: 'exec', label: 'Taux d\'exécution', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-percentage' },
      { key: 'rejet', label: 'Taux de rejet', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-alert-triangle' },
      { key: 'mandats', label: 'Mandats traités', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-file-invoice' },
      { key: 'attente', label: 'Mandats admis', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-clock' },
    ]
  }

  return [
    { key: 'exec', label: 'Taux d\'exécution', value: formatPercent(ind.taux_execution), accent: 'solde' as KpiAccent, icon: 'tabler-percentage' },
    { key: 'rejet', label: 'Taux de rejet', value: formatPercent(ind.taux_rejet), accent: 'depenses' as KpiAccent, icon: 'tabler-alert-triangle' },
    { key: 'mandats', label: 'Mandats traités', value: ind.mandats_total.toLocaleString('fr-FR'), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { key: 'attente', label: 'Mandats admis', value: ind.mandats_admis.toLocaleString('fr-FR'), accent: 'neutral' as KpiAccent, icon: 'tabler-clock' },
  ]
})

const financialKpis = computed(() => {
  const ind = kpis.value?.indicateurs
  if (!ind) {
    return [
      { key: 'ord', label: 'Ordonnancé national', value: '—', accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
      { key: 'rec', label: 'Recouvrements (4121)', value: '—', accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
      { key: 'paye', label: 'Payé + Réglé', value: '—', accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
      { key: 'treso', label: 'Solde bancaire NAV', value: '—', accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
      { key: 'ecart', label: 'Écart (4121 − ord.)', value: '—', accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
    ]
  }

  return [
    { key: 'ord', label: 'Ordonnancé national', value: formatFcfa(ind.ordonnance_total), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { key: 'rec', label: 'Recouvrements (4121)', value: formatFcfa(ind.recouvrements_4121_total), accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
    { key: 'paye', label: 'Payé + Réglé', value: formatFcfa(ind.montant_paye_total), accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
    { key: 'treso', label: 'Solde bancaire NAV', value: formatFcfa(ind.tresorerie_reelle_total), accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
    { key: 'ecart', label: 'Écart (4121 − ord.)', value: formatFcfa(ind.solde_total), accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
  ]
})

const comparaisonRows = computed(() => {
  const cmp = kpis.value?.comparaison_mois_precedent
  const ind = kpis.value?.indicateurs
  if (!cmp || !ind)
    return []

  return [
    {
      label: 'Ordonnancé',
      valeur: formatFcfa(ind.ordonnance_total),
      evolution: cmp.ordonnance_evolution_pct,
    },
    {
      label: 'Recouvrements 4121',
      valeur: formatFcfa(ind.recouvrements_4121_total),
      evolution: cmp.recouvrements_evolution_pct,
    },
    {
      label: 'Volume mandats',
      valeur: ind.mandats_total.toLocaleString('fr-FR'),
      evolution: cmp.mandats_evolution_pct,
    },
    {
      label: 'Mandats rejetés',
      valeur: ind.mandats_rejetes.toLocaleString('fr-FR'),
      evolution: null,
    },
  ]
})

const performanceChart = computed(() => {
  const rows = kpis.value?.performance_regions ?? []
  return {
    labels: rows.map(r => r.region.code),
    datasets: [{ label: 'Score performance', data: rows.map(r => r.score) }],
  }
})

const performanceRegions = computed(() => kpis.value?.performance_regions ?? [])

const alertesResume = computed(() => ({
  total: alertes.value.length,
  critiques: alertes.value.filter(a => a.priorite === 'critique').length,
  warnings: alertes.value.filter(a => a.priorite === 'warning').length,
}))

const tendanceIcon = computed(() => {
  const type = predictions.value?.tendance_depenses.type
  if (type === 'hausse')
    return 'tabler-trending-up'
  if (type === 'baisse')
    return 'tabler-trending-down'
  return 'tabler-minus'
})

const tendanceColor = computed(() => {
  const type = predictions.value?.tendance_depenses.type
  if (type === 'hausse')
    return 'text-error'
  if (type === 'baisse')
    return 'text-success'
  return 'text-medium-emphasis'
})

function evolutionClass(value: number | null | undefined) {
  if (value === null || value === undefined)
    return ''
  if (value > 0)
    return 'text-error'
  if (value < 0)
    return 'text-success'
  return 'text-medium-emphasis'
}

async function loadDashboard() {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchAll({
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  })
}

watch([dateDebut, dateFin], () => loadDashboard())

onMounted(async () => {
  hydrateFromRoute()
  await loadDashboard()
})
</script>

<template>
  <div class="aice-page aice-executive-dashboard">
    <ExplorerHero
      icon="tabler-chart-line"
      title="Tableau de bord exécutif"
      :subtitle="`Synthèse nationale · ${periodLabel}`"
      class="aice-dashboard-hero"
      :stats="heroStats"
    >
      <template #below>
        <div
          v-if="kpis?.meta.derniere_mise_a_jour"
          class="aice-dashboard-hero__meta"
        >
          {{ kpis.meta.regions_avec_donnees }} région(s) avec données
          <template v-if="lastUpdate">
            · MAJ {{ lastUpdate }}
          </template>
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
      Aucune donnée nationale pour {{ periodLabel }}.
      Élargissez la plage ou vérifiez les pushs régionaux (AICE-API).
    </VAlert>

    <template v-if="loading">
      <VRow>
        <VCol
          v-for="i in 8"
          :key="i"
          cols="12"
          sm="6"
          lg="3"
        >
          <VSkeletonLoader type="card" />
        </VCol>
      </VRow>
    </template>

    <template v-else-if="kpis">
      <p class="aice-section-label mb-2">
        Pilotage mandats
      </p>
      <VRow class="mb-1">
        <VCol
          v-for="kpi in strategicKpis"
          :key="kpi.key"
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

      <p class="aice-section-label mb-2">
        Agrégats financiers nationaux
      </p>
      <VRow class="mb-1">
        <VCol
          v-for="kpi in financialKpis"
          :key="kpi.key"
          cols="12"
          sm="6"
          md="4"
          lg="4"
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
          md="4"
        >
          <DataPanel
            title="Alertes actives"
            :subtitle="`${alertesResume.critiques} critique(s) · ${alertesResume.warnings} vigilance`"
          >
            <AlertList
              :alertes="alertes"
              :loading="loading"
            />
          </DataPanel>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <DataPanel
            title="Évolution vs période précédente"
            :subtitle="periodLabel"
          >
            <div
              v-if="!comparaisonRows.length"
              class="aice-panel-empty"
            >
              Données insuffisantes pour comparer.
            </div>
            <div
              v-else
              class="aice-compare-list"
            >
              <div
                v-for="row in comparaisonRows"
                :key="row.label"
                class="aice-compare-item"
              >
                <div class="aice-compare-item__head">
                  <span class="aice-compare-item__label">{{ row.label }}</span>
                  <span
                    v-if="row.evolution !== null"
                    class="aice-compare-item__evo tabular-nums"
                    :class="evolutionClass(row.evolution)"
                  >
                    {{ formatEvolutionPct(row.evolution) }}
                  </span>
                </div>
                <p class="aice-compare-item__value tabular-nums">
                  {{ row.valeur }}
                </p>
              </div>
            </div>
          </DataPanel>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <DataPanel
            title="Tendance & projection"
            :subtitle="periodLabel"
          >
            <div
              v-if="predictions"
              class="aice-trend-block"
            >
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon
                  :icon="tendanceIcon"
                  size="20"
                  :class="tendanceColor"
                />
                <p class="aice-trend-block__value mb-0">
                  {{ predictions.tendance_depenses.description }}
                </p>
              </div>
              <p
                v-if="predictions.tendance_depenses.evolution_pct !== null"
                class="aice-trend-block__evolution tabular-nums"
                :class="evolutionClass(predictions.tendance_depenses.evolution_pct)"
              >
                Ordonnancé : {{ formatEvolutionPct(predictions.tendance_depenses.evolution_pct) }} vs période précédente
              </p>

              <VDivider class="my-4" />

              <p class="aice-trend-block__label">
                Projection ordonnancé (fin de période)
              </p>
              <p class="aice-trend-block__amount tabular-nums">
                {{ formatFcfa(predictions.projection_depenses_fin_mois) }}
              </p>
              <p class="aice-trend-block__hint">
                Réalisé à ce jour : {{ formatFcfa(predictions.depenses_mois_courant) }}
              </p>
            </div>
          </DataPanel>
        </VCol>
      </VRow>

      <VRow class="mt-1">
        <VCol
          cols="12"
          lg="7"
        >
          <DataPanel
            title="Performance par région"
            subtitle="Classement par score composite"
          >
            <VTable
              v-if="performanceRegions.length"
              density="compact"
              class="aice-perf-table"
            >
              <thead>
                <tr>
                  <th>Région</th>
                  <th class="text-end">
                    Mandats
                  </th>
                  <th class="text-end">
                    Exécution
                  </th>
                  <th class="text-end">
                    Rejet
                  </th>
                  <th class="text-end">
                    Score
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in performanceRegions"
                  :key="row.region.code"
                >
                  <td>
                    <span class="font-weight-medium">{{ row.region.code }}</span>
                    <span class="text-medium-emphasis text-caption ms-1">{{ row.region.nom }}</span>
                  </td>
                  <td class="text-end tabular-nums">
                    {{ row.mandats_total.toLocaleString('fr-FR') }}
                  </td>
                  <td class="text-end tabular-nums">
                    {{ formatPercent(row.taux_execution) }}
                  </td>
                  <td class="text-end tabular-nums text-error">
                    {{ formatPercent(row.taux_rejet) }}
                  </td>
                  <td class="text-end tabular-nums font-weight-medium">
                    {{ row.score }}
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div
              v-else
              class="aice-panel-empty"
            >
              Aucune région avec données mandats sur cette période.
            </div>
          </DataPanel>
        </VCol>

        <VCol
          cols="12"
          lg="5"
        >
          <DataPanel
            title="Anomalies régionales"
            subtitle="Écarts par rapport à la moyenne nationale"
          >
            <div
              v-if="!anomalies.length"
              class="aice-panel-empty"
            >
              Aucune anomalie significative détectée.
            </div>
            <div
              v-else
              class="aice-anomaly-list"
            >
              <div
                v-for="(item, index) in anomalies"
                :key="`${item.region_code}-${item.type}-${index}`"
                class="aice-anomaly-item"
              >
                <div class="d-flex align-center gap-2 mb-1">
                  <span class="aice-anomaly-item__region">{{ item.region_code }}</span>
                  <VChip
                    size="x-small"
                    :color="item.severite === 'elevee' ? 'error' : 'warning'"
                    variant="tonal"
                  >
                    {{ item.severite === 'elevee' ? 'Élevée' : 'Modérée' }}
                  </VChip>
                </div>
                <p class="aice-anomaly-item__text">
                  {{ item.description }}
                </p>
                <p class="aice-anomaly-item__value tabular-nums">
                  {{ formatPercent(item.valeur) }}
                </p>
              </div>
            </div>
          </DataPanel>
        </VCol>
      </VRow>

      <VRow
        v-if="performanceChart.labels.length"
        class="mt-1"
      >
        <VCol cols="12">
          <DataPanel
            title="Score de performance régionale"
            subtitle="Exécution − impact des rejets (0–100)"
          >
            <ChartWidget
              type="bar"
              :labels="performanceChart.labels"
              :datasets="performanceChart.datasets"
              :height="280"
            />
          </DataPanel>
        </VCol>
      </VRow>
    </template>
  </div>
</template>

<style scoped lang="scss">
.aice-section-label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.aice-compare-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.aice-compare-item {
  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-block-end: 0.2rem;
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  &__evo {
    font-size: 0.75rem;
    font-weight: 600;
  }

  &__value {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
  }
}

.aice-perf-table {
  :deep(thead th) {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
  }
}

.aice-trend-block {
  &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    margin-block-end: 0.25rem;
    text-transform: uppercase;
  }

  &__value {
    font-size: 0.875rem;
  }

  &__evolution {
    font-size: 0.8125rem;
    margin: 0;
  }

  &__amount {
    font-size: 1.25rem;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    margin-block: 0 0.25rem;
  }

  &__hint {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    margin: 0;
  }
}

.aice-anomaly-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.aice-anomaly-item {
  border-block-end: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  padding-block-end: 0.75rem;

  &:last-child {
    border-block-end: none;
    padding-block-end: 0;
  }

  &__region {
    color: rgb(var(--v-theme-primary));
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
  }

  &__text {
    font-size: 0.8125rem;
    margin-block: 0.15rem;
  }

  &__value {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.75rem;
    margin: 0;
  }
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
