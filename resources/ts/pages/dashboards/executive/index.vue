<script setup lang="ts">
import { useAbility } from '@casl/vue'
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
import { useDashboardAutoRefresh } from '@/composables/useDashboardAutoRefresh'
import { queryParam, useExplorerRouteSync } from '@/composables/useDetailExplorerContext'
import { useExecutiveDashboard } from '@/composables/useExecutiveDashboard'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const compareMode = ref<'mois_precedent' | 'periode_precedente'>('mois_precedent')
const slaWarningDays = ref(7)
const slaCriticalDays = ref(15)

const { regionCode, dateDebut, dateFin, periodLabel, baseQuery, detailRoute, dashboardRoute, syncRoute, hydrateFromRoute } = useExplorerRouteSync(
  () => ({
    compare_mode: compareMode.value,
    sla_warning_days: slaWarningDays.value,
    sla_critical_days: slaCriticalDays.value,
  }),
  (query) => {
    const mode = queryParam(query.compare_mode)
    compareMode.value = mode === 'periode_precedente' ? 'periode_precedente' : 'mois_precedent'

    const warning = Number(queryParam(query.sla_warning_days) ?? 7)
    const critical = Number(queryParam(query.sla_critical_days) ?? 15)
    slaWarningDays.value = Number.isFinite(warning) && warning > 0 ? warning : 7
    slaCriticalDays.value = Number.isFinite(critical) && critical > slaWarningDays.value ? critical : Math.max(slaWarningDays.value + 1, 15)
  },
)
const { loading, error, kpis, alertes, anomalies, predictions, fetchAll } = useExecutiveDashboard()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()
const ability = useAbility()
const canManagePush = computed(() => ability.can('manage', 'gerer_observabilite_push'))

const compareModeOptions = [
  { title: 'Mois précédent', value: 'mois_precedent' },
  { title: 'Période précédente', value: 'periode_precedente' },
]

const lastUpdate = computed(() => formatDateFr(kpis.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (kpis.value?.meta.regions_avec_donnees ?? 0) > 0)
const silentRegionsCount = computed(() => {
  const meta = kpis.value?.meta
  if (!meta)
    return 0

  return Math.max(0, meta.regions_actives - meta.regions_avec_donnees)
})

const regionLabel = computed(() => {
  if (!regionCode.value)
    return 'Toutes les régions'

  const match = regions.value.find(r => r.code === regionCode.value)

  return match ? `${match.nom} (${match.code})` : regionCode.value
})

const heroSubtitle = computed(() =>
  regionCode.value
    ? `${regionLabel.value} · ${periodLabel}`
    : `Synthèse nationale · ${periodLabel}`,
)

const compareLabel = computed(() => kpis.value?.parametres.compare_label ?? (compareMode.value === 'periode_precedente' ? 'période précédente' : 'mois précédent'))
const slaLabel = computed(() => `SLA workflow > ${slaWarningDays.value}j / > ${slaCriticalDays.value}j`)

const quickLinks = computed(() => {
  const links = [
    { title: 'Vue centrale', hint: 'Toutes les régions', icon: 'tabler-chart-dots-3', to: dashboardRoute('dashboards-central') },
    { title: 'Vue régionale', hint: 'Détail par région', icon: 'tabler-chart-bar', to: dashboardRoute('dashboards-regional') },
    { title: 'Mandats', hint: 'Explorateur', icon: 'tabler-file-invoice', to: detailRoute('details-mandats') },
    { title: 'Recettes', hint: 'Encaissements 4121', icon: 'tabler-cash', to: detailRoute('details-recettes') },
  ]

  if (canManagePush.value) {
    links.push({
      title: 'Observabilité push',
      hint: 'Exceptions et retards de push',
      icon: 'tabler-radar-2',
      to: { name: 'admin-observabilite-push' },
    })
  }

  return links
})

const heroStats = computed(() => {
  const ind = kpis.value?.indicateurs
  const meta = kpis.value?.meta
  if (!ind || !meta) {
    return [
      { label: 'Régions actives', value: '—' },
      { label: 'Mandats NAV', value: '—' },
      { label: 'Recettes', value: '—' },
      { label: 'Tous mouvements', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Régions actives', value: `${meta.regions_avec_donnees} / ${meta.regions_actives}` },
    { label: 'Mandats NAV', value: ind.mandats_total.toLocaleString('fr-FR') },
    { label: 'Recettes', value: meta.recettes_count.toLocaleString('fr-FR') },
    { label: 'Tous mouvements', value: meta.mouvements_count.toLocaleString('fr-FR') },
    { label: 'Période', value: periodLabel.value },
  ]
})

const strategicKpis = computed(() => {
  const ind = kpis.value?.indicateurs
  const workflow = kpis.value?.workflow
  if (!ind || !workflow) {
    return [
      { key: 'exec', label: 'Taux d\'exécution', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-percentage' },
      { key: 'rejet', label: 'Taux de rejet', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-alert-triangle' },
      { key: 'mandats', label: 'Mandats NAV', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-file-invoice' },
      { key: 'attente', label: 'Admis', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-clock' },
      { key: 'autres', label: 'Autres non payés', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-loader-2' },
      { key: 'cumul', label: 'Cumul hors rejeté', value: '—', accent: 'neutral' as KpiAccent, icon: 'tabler-stack-2' },
    ]
  }

  return [
    { key: 'exec', label: 'Taux d\'exécution', value: formatPercent(ind.taux_execution), accent: 'solde' as KpiAccent, icon: 'tabler-percentage' },
    { key: 'rejet', label: 'Taux de rejet', value: formatPercent(ind.taux_rejet), accent: 'depenses' as KpiAccent, icon: 'tabler-alert-triangle' },
    { key: 'mandats', label: 'Mandats NAV', value: ind.mandats_total.toLocaleString('fr-FR'), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    {
      key: 'attente',
      label: 'Admis',
      value: formatFcfa(workflow.admis.montant),
      variation: `${workflow.admis.count.toLocaleString('fr-FR')} mandat(s)`,
      accent: 'solde' as KpiAccent,
      icon: 'tabler-clock',
    },
    {
      key: 'autres',
      label: 'Autres non payés',
      value: formatFcfa(workflow.autres_non_payes.montant),
      variation: `${workflow.autres_non_payes.count.toLocaleString('fr-FR')} mandat(s)`,
      accent: 'neutral' as KpiAccent,
      icon: 'tabler-loader-2',
    },
    {
      key: 'cumul',
      label: 'Cumul hors rejeté',
      value: formatFcfa(workflow.total_hors_rejet.montant),
      variation: `${workflow.total_hors_rejet.count.toLocaleString('fr-FR')} mandat(s)`,
      accent: 'ordonnance' as KpiAccent,
      icon: 'tabler-stack-2',
    },
  ]
})

const financialKpis = computed(() => {
  const ind = kpis.value?.indicateurs
  if (!ind) {
    return [
      { key: 'ord', label: 'Ordonnancé national', value: '—', accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
      { key: 'rec', label: 'Recouvrements (4121)', value: '—', accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
      { key: 'paye', label: 'Payé + Réglé', value: '—', accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
      { key: 'treso', label: 'Solde bancaire filtré', value: '—', accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
      { key: 'ecart', label: 'Écart (4121 − ord.)', value: '—', accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
    ]
  }

  return [
    { key: 'ord', label: 'Ordonnancé national', value: formatFcfa(ind.ordonnance_total), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { key: 'rec', label: 'Recouvrements (4121)', value: formatFcfa(ind.recouvrements_4121_total), accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
    { key: 'paye', label: 'Payé + Réglé', value: formatFcfa(ind.montant_paye_total), accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
    { key: 'treso', label: 'Solde bancaire filtré', value: formatFcfa(ind.tresorerie_reelle_total), accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
    { key: 'ecart', label: 'Écart (4121 − ord.)', value: formatFcfa(ind.solde_total), accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
  ]
})

const comparaisonRows = computed(() => {
  const cmp = kpis.value?.comparaison_reference
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
      label: 'Volume mandats NAV',
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

const workflowAging = computed(() => kpis.value?.workflow_aging ?? null)

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

function regionDashboardRoute(code: string) {
  return {
    name: 'dashboards-regional',
    query: baseQuery({ region_code: code }),
  }
}

function regionMandatsRoute(code: string) {
  return detailRoute('details-mandats', { region_code: code })
}

async function loadDashboard(silent = false) {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  if (slaCriticalDays.value <= slaWarningDays.value)
    slaCriticalDays.value = slaWarningDays.value + 1

  await fetchAll({
    region_code: regionCode.value ?? undefined,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
    compare_mode: compareMode.value,
    sla_warning_days: slaWarningDays.value,
    sla_critical_days: slaCriticalDays.value,
  }, { silent })
}

watch([regionCode, dateDebut, dateFin], () => loadDashboard())
watch([compareMode, slaWarningDays, slaCriticalDays], () => {
  syncRoute()
  loadDashboard()
})

useDashboardAutoRefresh(() => loadDashboard(true))

onMounted(async () => {
  hydrateFromRoute()
  await fetchRegions()
  await loadDashboard()
})
</script>

<template>
  <div class="aice-page aice-executive-dashboard">
    <ExplorerHero
      title="Tableau de bord exécutif"
      :subtitle="heroSubtitle"
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
        <RegionSelector
          v-model="regionCode"
          :regions="regions"
          :loading="regionsLoading"
          allow-all
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
        <VSelect
          v-model="compareMode"
          label="Comparaison"
          :items="compareModeOptions"
          density="compact"
          hide-details
          variant="outlined"
          style="max-inline-size: 220px;"
        />
        <VTextField
          v-model.number="slaWarningDays"
          label="SLA warning (j)"
          type="number"
          min="1"
          density="compact"
          hide-details
          variant="outlined"
          style="max-inline-size: 140px;"
        />
        <VTextField
          v-model.number="slaCriticalDays"
          label="SLA critique (j)"
          type="number"
          :min="Math.max(2, slaWarningDays + 1)"
          density="compact"
          hide-details
          variant="outlined"
          style="max-inline-size: 140px;"
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

    <div class="aice-toolbar-meta mb-4">
      <VChip
        size="small"
        variant="tonal"
        color="primary"
      >
        Comparaison : {{ compareLabel }}
      </VChip>
      <VChip
        size="small"
        variant="tonal"
        color="warning"
      >
        {{ slaLabel }}
      </VChip>
      <VChip
        v-if="workflowAging"
        size="small"
        variant="tonal"
        color="secondary"
      >
        Âge encours moyen/max : {{ workflowAging.average_days }}j / {{ workflowAging.max_days }}j
      </VChip>
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
      Aucune donnée nationale mandatée ou recette pour {{ periodLabel }}.
      Élargissez la plage ou vérifiez les pushs régionaux (AICE-API).
    </VAlert>

    <VAlert
      v-else-if="silentRegionsCount > 0"
      type="warning"
      variant="tonal"
      class="mb-4"
      density="compact"
    >
      {{ silentRegionsCount.toLocaleString('fr-FR') }} région(s) sont silencieuses sur la période {{ periodLabel }}.
      <template v-if="canManagePush">
        Passez par l'observabilité push pour traiter les retards et les absences de remontée.
      </template>
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
          lg="4"
        >
          <KpiStat
            :label="kpi.label"
            :value="kpi.value"
            :variation="kpi.variation"
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
            :subtitle="`${alertesResume.critiques} critique(s) · ${alertesResume.warnings} vigilance · ${slaLabel}`"
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
            title="Évolution comparative"
            :subtitle="`${periodLabel} · Référence : ${compareLabel}`"
          >
            <div
              v-if="!comparaisonRows.length"
              class="aice-panel-empty"
            >
              Données insuffisantes pour comparer les indicateurs de pilotage.
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
                Ordonnancé : {{ formatEvolutionPct(predictions.tendance_depenses.evolution_pct) }} vs {{ predictions.reference_label }}
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
            <div
              v-else
              class="aice-panel-empty"
            >
              Aucune projection disponible sur cette période.
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
                    Mandats NAV
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
                  <th class="text-end">
                    Actions
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
                  <td class="text-end">
                    <div class="aice-table-actions">
                      <VBtn
                        size="x-small"
                        variant="text"
                        color="primary"
                        prepend-icon="tabler-chart-bar"
                        :to="regionDashboardRoute(row.region.code)"
                      >
                        Vue
                      </VBtn>
                      <VBtn
                        size="x-small"
                        variant="text"
                        color="primary"
                        prepend-icon="tabler-file-invoice"
                        :to="regionMandatsRoute(row.region.code)"
                      >
                        Mandats
                      </VBtn>
                    </div>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div
              v-else
              class="aice-panel-empty"
            >
              Aucune région avec données mandats NAV sur cette période.
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
              Aucune anomalie régionale significative détectée.
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

.aice-toolbar-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
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

.aice-table-actions {
  display: inline-flex;
  gap: 0.25rem;
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
