<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import AlertList from '@/components/aice/AlertList.vue'
import { endOfMonth, formatDateFr, formatDateRange, formatFcfa, formatPercent, startOfMonth } from '@/composables/useFormat'
import { useExecutiveDashboard } from '@/composables/useExecutiveDashboard'

definePage({ meta: { layout: 'default' } })

const dateDebut = ref(startOfMonth())
const dateFin = ref(endOfMonth())

const { loading, error, kpis, alertes, anomalies, predictions, fetchAll } = useExecutiveDashboard()

const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))
const lastUpdate = computed(() => formatDateFr(kpis.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (kpis.value?.meta.regions_avec_donnees ?? 0) > 0)

const heroStats = computed(() => {
  const ind = kpis.value?.indicateurs
  if (!ind) {
    return [
      { label: 'Exécution', value: '—' },
      { label: 'Alertes', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Exécution', value: formatPercent(ind.taux_execution) },
    { label: 'Alertes', value: String(alertes.value.length) },
    { label: 'Période', value: periodLabel.value },
  ]
})

const strategicKpis = computed(() => {
  const ind = kpis.value?.indicateurs
  if (!ind) {
    return [
      { label: 'Taux d\'exécution', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-percentage' },
      { label: 'Taux de rejet', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-alert-triangle' },
      { label: 'Encaisse nationale', value: '—', accent: 'encaisse' as KpiAccent, icon: 'tabler-vault' },
      { label: 'Mandats en attente', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-clock' },
    ]
  }

  return [
    { label: 'Taux d\'exécution', value: formatPercent(ind.taux_execution), accent: 'solde' as KpiAccent, icon: 'tabler-percentage' },
    { label: 'Taux de rejet', value: formatPercent(ind.taux_rejet), accent: 'depenses' as KpiAccent, icon: 'tabler-alert-triangle' },
    { label: 'Encaisse nationale', value: formatFcfa(ind.encaisse_total), accent: 'encaisse' as KpiAccent, icon: 'tabler-vault' },
    { label: 'Mandats en attente', value: ind.mandats_admis.toLocaleString('fr-FR'), accent: 'neutral' as KpiAccent, icon: 'tabler-clock' },
  ]
})

const performanceChart = computed(() => {
  const rows = kpis.value?.performance_regions ?? []
  return {
    labels: rows.map(r => r.region.code),
    datasets: [{ label: 'Score performance', data: rows.map(r => r.score) }],
  }
})

const alertesResume = computed(() => ({
  total: alertes.value.length,
  critiques: alertes.value.filter(a => a.priorite === 'critique').length,
  warnings: alertes.value.filter(a => a.priorite === 'warning').length,
}))

async function loadDashboard() {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchAll({
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  })
}

watch([dateDebut, dateFin], () => loadDashboard())

onMounted(() => loadDashboard())
</script>

<template>
  <div class="aice-page aice-executive-dashboard">
    <ExplorerHero
      icon="tabler-chart-line"
      title="Tableau de bord exécutif"
      subtitle="Indicateurs stratégiques, alertes et synthèse nationale."
      class="aice-dashboard-hero"
      :stats="heroStats"
    >
      <template #below>
        <div
          v-if="kpis?.meta.derniere_mise_a_jour"
          class="aice-dashboard-hero__meta"
        >
          MAJ {{ lastUpdate }}
        </div>
      </template>
    </ExplorerHero>

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
      Aucune donnée nationale entre le {{ periodLabel }}.
      Élargissez la plage de dates si les données proviennent d'une autre période.
    </VAlert>

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
        v-for="kpi in strategicKpis"
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

    <VRow
      v-if="!loading && kpis"
      class="mt-1"
    >
      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Alertes actives"
          :subtitle="`${alertesResume.critiques} critique(s) · ${alertesResume.warnings} vigilance · ${alertesResume.total} total`"
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
          title="Tendance & projection"
          :subtitle="periodLabel"
        >
          <div
            v-if="predictions"
            class="aice-trend-block"
          >
            <p class="aice-trend-block__label">
              Tendance des dépenses
            </p>
            <p class="aice-trend-block__value">
              {{ predictions.tendance_depenses.description }}
            </p>
            <p
              v-if="predictions.tendance_depenses.evolution_pct !== null"
              class="aice-trend-block__evolution"
            >
              {{ predictions.tendance_depenses.evolution_pct > 0 ? '+' : '' }}{{ predictions.tendance_depenses.evolution_pct }} % vs période précédente
            </p>

            <VDivider class="my-4" />

            <p class="aice-trend-block__label">
              Projection fin de période
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

      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Anomalies détectées"
          subtitle="Écarts par région"
        >
          <div
            v-if="!anomalies.length"
            class="aice-panel-empty"
          >
            Aucune anomalie significative pour cette période.
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
              <span class="aice-anomaly-item__region">{{ item.region_code }}</span>
              <p class="aice-anomaly-item__text">
                {{ item.description }}
              </p>
            </div>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow
      v-if="!loading && performanceChart.labels.length"
      class="mt-1"
    >
      <VCol cols="12">
        <DataPanel
          title="Performance régionale"
          subtitle="Score composite (exécution − impact rejets)"
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
  </div>
</template>

<style scoped lang="scss">
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
    margin-block: 0 0.35rem;
  }

  &__evolution {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
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
    margin-block: 0.25rem 0;
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
