<script setup lang="ts">
import { useAbility } from '@casl/vue'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import type { KpiAccent } from '@/types/dashboard'
import { useNatureCeExplorer } from '@/composables/useNatureCeExplorer'
import { useProgrammesExplorer } from '@/composables/useProgrammesExplorer'
import { formatDateFr, formatFcfa } from '@/composables/useFormat'
import { useDashboardAutoRefresh } from '@/composables/useDashboardAutoRefresh'
import { useDashboardFilterSync } from '@/composables/useDetailExplorerContext'
import { useCentralSummary } from '@/composables/useCentralSummary'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const { regionCode, dateDebut, dateFin, periodLabel, baseQuery, detailRoute, dashboardRoute, hydrateFromRoute } = useDashboardFilterSync()
const { loading, error, summary, fetchSummary } = useCentralSummary()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()
const ability = useAbility()
const canManagePush = computed(() => ability.can('manage', 'gerer_observabilite_push'))
const programmesExplorer = useProgrammesExplorer()
const natureCeExplorer = useNatureCeExplorer()

const quickLinks = computed(() => {
  const links = [
    { title: 'Vue régionale', hint: 'Par région', icon: 'tabler-chart-bar', to: dashboardRoute('dashboards-regional') },
    { title: 'Vue exécutive', hint: 'Indicateurs stratégiques', icon: 'tabler-chart-line', to: dashboardRoute('dashboards-executive') },
    { title: 'Mandats', hint: 'Explorateur interactif', icon: 'tabler-file-invoice', to: detailRoute('details-mandats') },
  ]

  if (canManagePush.value) {
    links.push({
      title: 'Observabilité push',
      hint: 'Supervision IT des régions',
      icon: 'tabler-radar-2',
      to: { name: 'admin-observabilite-push' },
    })
  }

  return links
})

const lastUpdate = computed(() => formatDateFr(summary.value?.meta.derniere_mise_a_jour))
const hasData = computed(() => (summary.value?.meta.regions_avec_donnees ?? 0) > 0)
const silentRegionsCount = computed(() => {
  const meta = summary.value?.meta
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
    ? `Vue agrégée · ${regionLabel.value}`
    : 'Vue agrégée de toutes les régions actives.',
)

const heroStats = computed(() => {
  if (!summary.value) {
    return [
      { label: 'Régions actives', value: '—' },
      { label: 'Mandats', value: '—' },
      { label: 'Recettes', value: '—' },
      { label: 'Tous mouvements', value: '—' },
      { label: 'Période', value: periodLabel.value },
    ]
  }

  return [
    { label: 'Régions actives', value: String(summary.value.meta.regions_actives) },
    { label: 'Mandats', value: summary.value.meta.mandats_count.toLocaleString('fr-FR') },
    { label: 'Recettes', value: summary.value.meta.recettes_count.toLocaleString('fr-FR') },
    { label: 'Tous mouvements', value: summary.value.meta.mouvements_count.toLocaleString('fr-FR') },
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
      { label: 'Solde bancaire filtré', value: '—', accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
      { label: 'Écart (4121 − ord.)', value: '—', accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
    ]
  }

  return [
    { label: 'Ordonnancé', value: formatFcfa(data.total_ordonnance), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { label: 'Recouvrements (4121)', value: formatFcfa(data.total_recouvrements_4121), accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
    { label: 'Payé + Réglé', value: formatFcfa(data.total_montant_paye), accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
    { label: 'Solde bancaire filtré', value: formatFcfa(data.tresorerie_reelle), accent: 'tresorerie' as KpiAccent, icon: 'tabler-building-bank' },
    { label: 'Écart (4121 − ord.)', value: formatFcfa(data.solde), accent: 'solde' as KpiAccent, icon: 'tabler-scale' },
  ]
})

const workflowKpis = computed(() => {
  const workflow = summary.value?.workflow
  if (!workflow) {
    return [
      { key: 'admis', label: 'Admis', value: '—', variation: null, accent: 'solde' as KpiAccent, icon: 'tabler-hourglass-high' },
      { key: 'autres', label: 'Autres non payés', value: '—', variation: null, accent: 'neutral' as KpiAccent, icon: 'tabler-loader-2' },
      { key: 'total', label: 'Cumul hors rejeté', value: '—', variation: null, accent: 'ordonnance' as KpiAccent, icon: 'tabler-stack-2' },
    ]
  }

  return [
    {
      key: 'admis',
      label: 'Admis',
      value: formatFcfa(workflow.admis.montant),
      variation: `${workflow.admis.count.toLocaleString('fr-FR')} mandat(s)`,
      accent: 'solde' as KpiAccent,
      icon: 'tabler-hourglass-high',
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
      key: 'total',
      label: 'Cumul hors rejeté',
      value: formatFcfa(workflow.total_hors_rejet.montant),
      variation: `${workflow.total_hors_rejet.count.toLocaleString('fr-FR')} mandat(s)`,
      accent: 'ordonnance' as KpiAccent,
      icon: 'tabler-stack-2',
    },
  ]
})

const recettesChart = computed(() => {
  const rows = summary.value?.regions.filter(r => r.meta.has_data) ?? []
  return {
    labels: rows.map(r => r.region.code),
    datasets: [{ label: 'Recouvrements (4121)', data: rows.map(r => r.kpis.total_recouvrements_4121) }],
  }
})

const topOrdonnanceRegions = computed(() => {
  const rows = summary.value?.regions.filter(r => r.meta.has_data) ?? []
  return [...rows]
    .sort((a, b) => b.kpis.total_ordonnance - a.kpis.total_ordonnance)
    .slice(0, 5)
})

const topRecouvrementRegions = computed(() => {
  const rows = summary.value?.regions.filter(r => r.meta.has_data) ?? []
  return [...rows]
    .sort((a, b) => b.kpis.total_recouvrements_4121 - a.kpis.total_recouvrements_4121)
    .slice(0, 5)
})

const kpiHeatmapRows = computed(() => summary.value?.regions.filter(r => r.meta.has_data) ?? [])
const maxHeatmapOrdonnance = computed(() => Math.max(1, ...kpiHeatmapRows.value.map(r => r.kpis.total_ordonnance)))
const maxHeatmapRecouvrement = computed(() => Math.max(1, ...kpiHeatmapRows.value.map(r => r.kpis.total_recouvrements_4121)))

function heatBg(value: number, max: number) {
  const ratio = Math.max(0, Math.min(1, value / max))
  const alpha = 0.08 + ratio * 0.18
  return { backgroundColor: `rgba(var(--v-theme-primary), ${alpha})` }
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

const topProgrammes = computed(() => programmesExplorer.stats.value?.programmes?.slice(0, 5) ?? [])
const topNaturesCe = computed(() => natureCeExplorer.stats.value?.natures_ce?.slice(0, 5) ?? [])
const topChapitres = computed(() => {
  const fromProgrammes = programmesExplorer.stats.value?.par_chapitre ?? []
  if (fromProgrammes.length)
    return fromProgrammes.slice(0, 5)

  return (natureCeExplorer.stats.value?.par_chapitre ?? []).slice(0, 5)
})

async function loadDashboard(silent = false) {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchSummary({
    region_code: regionCode.value ?? undefined,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  }, { silent })

  await Promise.all([
    programmesExplorer.fetch({
      region_code: regionCode.value,
      date_debut: dateDebut.value,
      date_fin: dateFin.value,
      page: 1,
      per_page: 5,
      type: 'depense',
    }),
    natureCeExplorer.fetch({
      region_code: regionCode.value,
      date_debut: dateDebut.value,
      date_fin: dateFin.value,
      page: 1,
      per_page: 5,
    }),
  ])
}

watch([regionCode, dateDebut, dateFin], () => loadDashboard())

useDashboardAutoRefresh(() => loadDashboard(true))

onMounted(async () => {
  hydrateFromRoute()
  await fetchRegions()
  await loadDashboard()
})
</script>

<template>
  <div class="aice-page aice-central-dashboard">
    <ExplorerHero
      title="Tableau de bord central"
      :subtitle="heroSubtitle"
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
      Aucune donnée mandatée ou recette reçue entre le {{ periodLabel }}.
      Élargissez la plage de dates si les données proviennent d'une autre période.
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
        Vérifiez l'observabilité push pour identifier la cause du silence ou du retard.
      </template>
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

    <p class="aice-section-label mb-2">
      Reste à payer workflow
    </p>
    <VRow class="mb-1">
      <VCol
        v-for="kpi in workflowKpis"
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

    <VRow class="mt-1">
      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Top Programmes"
          :subtitle="periodLabel"
        >
          <div
            v-if="!topProgrammes.length"
            class="aice-panel-empty"
          >
            Aucune donnée programme sur cette période.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in topProgrammes"
              :key="row.code"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.libelle || row.code }}
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant_depenses) }}
              </div>
            </div>
          </div>
          <div class="aice-top-actions">
            <VBtn
              size="small"
              variant="text"
              color="primary"
              :to="detailRoute('details-programmes')"
            >
              Voir détail
            </VBtn>
          </div>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Top Natures CE"
          :subtitle="periodLabel"
        >
          <div
            v-if="!topNaturesCe.length"
            class="aice-panel-empty"
          >
            Aucune nature CE disponible sur cette période.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in topNaturesCe"
              :key="row.code"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.libelle || row.code }}
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant_depenses) }}
              </div>
            </div>
          </div>
          <div class="aice-top-actions">
            <VBtn
              size="small"
              variant="text"
              color="primary"
              :to="detailRoute('details-natures-ce')"
            >
              Voir détail
            </VBtn>
          </div>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Top Chapitres"
          :subtitle="periodLabel"
        >
          <div
            v-if="!topChapitres.length"
            class="aice-panel-empty"
          >
            Aucun chapitre disponible sur cette période.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in topChapitres"
              :key="row.label"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant) }}
              </div>
            </div>
          </div>
        </DataPanel>
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
            Aucun recouvrement régional disponible sur cette période.
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
                <th class="text-end">
                  Actions
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
            Aucune région avec données pour cette période.
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mt-1">
      <VCol
        cols="12"
        lg="5"
      >
        <DataPanel
          title="Top contributeurs"
          :subtitle="periodLabel"
        >
          <div
            v-if="!topOrdonnanceRegions.length"
            class="aice-panel-empty"
          >
            Aucune région contributrice sur cette période.
          </div>
          <div v-else>
            <p class="aice-subtitle mb-2">
              Ordonnancé
            </p>
            <div class="aice-top-list mb-4">
              <div
                v-for="row in topOrdonnanceRegions"
                :key="`ord-${row.region.code}`"
                class="aice-top-item"
              >
                <div class="aice-top-item__label">
                  {{ row.region.nom }} ({{ row.region.code }})
                </div>
                <div class="aice-top-item__value tabular-nums">
                  {{ formatFcfa(row.kpis.total_ordonnance) }}
                </div>
              </div>
            </div>

            <p class="aice-subtitle mb-2">
              Recouvrements (4121)
            </p>
            <div class="aice-top-list">
              <div
                v-for="row in topRecouvrementRegions"
                :key="`rec-${row.region.code}`"
                class="aice-top-item"
              >
                <div class="aice-top-item__label">
                  {{ row.region.nom }} ({{ row.region.code }})
                </div>
                <div class="aice-top-item__value tabular-nums">
                  {{ formatFcfa(row.kpis.total_recouvrements_4121) }}
                </div>
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        lg="7"
      >
        <DataPanel
          title="Heatmap KPI (ordre/4121)"
          :subtitle="periodLabel"
        >
          <VTable
            v-if="kpiHeatmapRows.length"
            density="compact"
            class="aice-simple-table"
          >
            <thead>
              <tr>
                <th>Région</th>
                <th class="text-end">
                  Ordonnancé
                </th>
                <th class="text-end">
                  Recouv. 4121
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in kpiHeatmapRows"
                :key="`heat-${row.region.code}`"
              >
                <td>
                  <span class="font-weight-medium">{{ row.region.code }}</span>
                  <span class="text-medium-emphasis ms-1">{{ row.region.nom }}</span>
                </td>
                <td
                  class="text-end tabular-nums"
                  :style="heatBg(row.kpis.total_ordonnance, maxHeatmapOrdonnance)"
                >
                  {{ formatFcfa(row.kpis.total_ordonnance) }}
                </td>
                <td
                  class="text-end tabular-nums"
                  :style="heatBg(row.kpis.total_recouvrements_4121, maxHeatmapRecouvrement)"
                >
                  {{ formatFcfa(row.kpis.total_recouvrements_4121) }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune donnée pour la heatmap sur cette période.
          </div>
        </DataPanel>
      </VCol>
    </VRow>
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

.aice-table-actions {
  display: inline-flex;
  gap: 0.25rem;
}

.aice-subtitle {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  margin: 0;
  text-transform: uppercase;
}

.aice-top-actions {
  display: flex;
  justify-content: flex-end;
  margin-block-start: 0.5rem;
}

.aice-top-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.aice-top-item {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;

  &__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__value {
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
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
