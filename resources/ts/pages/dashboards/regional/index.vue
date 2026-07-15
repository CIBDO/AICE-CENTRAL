<script setup lang="ts">
import { useAbility } from '@casl/vue'
import type { GroupStatRow, NatureCeRow, ProgrammeRow } from '@/types/details'
import type { KpiAccent } from '@/types/dashboard'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import { useNatureCeExplorer } from '@/composables/useNatureCeExplorer'
import { useProgrammesExplorer } from '@/composables/useProgrammesExplorer'
import { formatDateFr, formatEvolutionPct, formatFcfa, formatPercent, toIsoDate } from '@/composables/useFormat'
import { useDashboardAutoRefresh } from '@/composables/useDashboardAutoRefresh'
import { useDashboardFilterSync } from '@/composables/useDetailExplorerContext'
import { useDashboardSummary } from '@/composables/useDashboardSummary'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

type DimensionRow = {
  key: string
  label: string
  montant: number
  partPct: number
  evolutionPct: number | null
  tauxExecutionPct: number | null
  admisCount: number | null
  risque: string | null
}

type DeltaRow = {
  key: string
  label: string
  currentAmount: number
  previousAmount: number
  deltaAmount: number
  evolutionPct: number | null
}

const { loading, error, summary, fetchSummary } = useDashboardSummary()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()
const { regionCode, dateDebut, dateFin, periodLabel, detailRoute, dashboardRoute, hydrateFromRoute } = useDashboardFilterSync()
const ability = useAbility()
const canManagePush = computed(() => ability.can('manage', 'gerer_observabilite_push'))
const programmesExplorer = useProgrammesExplorer()
const previousProgrammesExplorer = useProgrammesExplorer()
const natureCeExplorer = useNatureCeExplorer()
const previousNatureCeExplorer = useNatureCeExplorer()

function parseDateOnly(value: string) {
  return new Date(`${value}T00:00:00`)
}

function previousRange(start: string | null, end: string | null) {
  if (!start || !end)
    return null

  const startDate = parseDateOnly(start)
  const endDate = parseDateOnly(end)
  const durationMs = endDate.getTime() - startDate.getTime()
  const previousEnd = new Date(startDate.getTime() - 24 * 60 * 60 * 1000)
  const previousStart = new Date(previousEnd.getTime() - durationMs)

  return {
    date_debut: toIsoDate(previousStart),
    date_fin: toIsoDate(previousEnd),
  }
}

function buildDimensionRows<T>(
  currentRows: T[],
  previousRows: T[],
  totalAmount: number,
  getKey: (row: T) => string,
  getLabel: (row: T) => string,
  getAmount: (row: T) => number,
  getExecution: (row: T) => number | null,
  getAdmisCount: (row: T) => number | null,
  resolveRisk: (row: T, partPct: number) => string | null,
): DimensionRow[] {
  const previousMap = new Map(previousRows.map(row => [getKey(row), row]))

  return currentRows.map((row) => {
    const key = getKey(row)
    const currentAmount = getAmount(row)
    const previousAmount = getAmount(previousMap.get(key) ?? row) || 0
    const evolutionPct = previousMap.has(key) && previousAmount > 0
      ? ((currentAmount - previousAmount) / previousAmount) * 100
      : null
    const partPct = totalAmount > 0 ? (currentAmount / totalAmount) * 100 : 0

    return {
      key,
      label: getLabel(row),
      montant: currentAmount,
      partPct,
      evolutionPct,
      tauxExecutionPct: getExecution(row),
      admisCount: getAdmisCount(row),
      risque: resolveRisk(row, partPct),
    }
  })
}

function buildDeltaRows<T>(
  currentRows: T[],
  previousRows: T[],
  getKey: (row: T) => string,
  getLabel: (row: T) => string,
  getAmount: (row: T) => number,
): DeltaRow[] {
  const currentMap = new Map(currentRows.map(row => [getKey(row), row]))
  const previousMap = new Map(previousRows.map(row => [getKey(row), row]))
  const allKeys = new Set([...currentMap.keys(), ...previousMap.keys()])

  return Array.from(allKeys).map((key) => {
    const current = currentMap.get(key)
    const previous = previousMap.get(key)
    const currentAmount = current ? getAmount(current) : 0
    const previousAmount = previous ? getAmount(previous) : 0

    return {
      key,
      label: current ? getLabel(current) : (previous ? getLabel(previous) : key),
      currentAmount,
      previousAmount,
      deltaAmount: currentAmount - previousAmount,
      evolutionPct: previousAmount > 0 ? ((currentAmount - previousAmount) / previousAmount) * 100 : null,
    }
  })
}

function genericRisk(partPct: number, executionPct: number | null, admisCount: number | null) {
  if (partPct >= 15 && executionPct !== null && executionPct < 40)
    return 'Consommation forte / paiement faible'

  if ((admisCount ?? 0) >= 10)
    return 'Encours admis élevé'

  if (executionPct !== null && executionPct < 55)
    return 'Taux d’exécution sous vigilance'

  return null
}

const previousPeriod = computed(() => previousRange(dateDebut.value, dateFin.value))

const quickLinks = computed(() => {
  const links = [
    { title: 'Mandats', hint: 'Explorateur interactif', icon: 'tabler-file-invoice', to: detailRoute('details-mandats') },
    { title: 'Recettes', hint: 'Clients & encaissements', icon: 'tabler-cash', to: detailRoute('details-recettes') },
    { title: 'Banques', hint: 'Flux trésorerie', icon: 'tabler-building-bank', to: detailRoute('details-banques') },
    { title: 'Programmes', hint: 'Exécution budgétaire', icon: 'tabler-layout-grid', to: detailRoute('details-programmes') },
    { title: 'Natures CE', hint: 'Classification CE', icon: 'tabler-category', to: detailRoute('details-natures-ce') },
    { title: 'Vue centrale', hint: 'Multi-régions', icon: 'tabler-chart-dots-3', to: dashboardRoute('dashboards-central') },
  ]

  if (canManagePush.value) {
    links.push({
      title: 'Observabilité push',
      hint: 'Suivi technique des remontées',
      icon: 'tabler-radar-2',
      to: { name: 'admin-observabilite-push' },
    })
  }

  return links
})

const kpis = computed(() => {
  const data = summary.value?.kpis
  if (!data) {
    return [
      { label: 'Ordonnancé', value: '—', accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
      { label: 'Recouvrements (4121)', value: '—', accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
      { label: 'Payé + Réglé', value: '—', accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
    ]
  }

  return [
    { label: 'Ordonnancé', value: formatFcfa(data.total_ordonnance), accent: 'ordonnance' as KpiAccent, icon: 'tabler-file-invoice' },
    { label: 'Recouvrements (4121)', value: formatFcfa(data.total_recouvrements_4121), accent: 'recouvrements' as KpiAccent, icon: 'tabler-receipt' },
    { label: 'Payé + Réglé', value: formatFcfa(data.total_montant_paye), accent: 'paye' as KpiAccent, icon: 'tabler-circle-check' },
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

const currentProgrammeRows = computed(() => programmesExplorer.stats.value?.programmes ?? [])
const previousProgrammeRows = computed(() => previousProgrammesExplorer.stats.value?.programmes ?? [])
const currentNatureRows = computed(() => natureCeExplorer.stats.value?.natures_ce ?? [])
const previousNatureRows = computed(() => previousNatureCeExplorer.stats.value?.natures_ce ?? [])
const currentChapitreRows = computed(() => {
  const fromProgrammes = programmesExplorer.stats.value?.par_chapitre ?? []
  return fromProgrammes.length ? fromProgrammes : (natureCeExplorer.stats.value?.par_chapitre ?? [])
})
const previousChapitreRows = computed(() => {
  const fromProgrammes = previousProgrammesExplorer.stats.value?.par_chapitre ?? []
  return fromProgrammes.length ? fromProgrammes : (previousNatureCeExplorer.stats.value?.par_chapitre ?? [])
})

const topProgrammes = computed(() => buildDimensionRows<ProgrammeRow>(
  currentProgrammeRows.value.slice(0, 5),
  previousProgrammeRows.value,
  programmesExplorer.stats.value?.totaux.montant_ordonnance ?? 0,
  row => row.code,
  row => row.libelle || row.code,
  row => row.montant_depenses,
  row => row.taux_execution_pct,
  row => row.admis_count,
  (row, partPct) => genericRisk(partPct, row.taux_execution_pct, row.admis_count),
))

const topNaturesCe = computed(() => buildDimensionRows<NatureCeRow>(
  currentNatureRows.value.slice(0, 5),
  previousNatureRows.value,
  natureCeExplorer.stats.value?.totaux.montant_ordonnance ?? 0,
  row => row.code,
  row => row.libelle || row.code,
  row => row.montant_depenses,
  row => row.taux_execution_pct,
  row => row.admis_count,
  (row, partPct) => genericRisk(partPct, row.taux_execution_pct, row.admis_count),
))

const topChapitres = computed(() => buildDimensionRows<GroupStatRow>(
  currentChapitreRows.value.slice(0, 5),
  previousChapitreRows.value,
  currentChapitreRows.value.reduce((sum, row) => sum + row.montant, 0),
  row => row.label,
  row => row.label,
  row => row.montant,
  () => null,
  () => null,
  (_row, partPct) => partPct >= 20 ? 'Poids élevé sur la période' : null,
))

const programmeMovers = computed(() => buildDeltaRows<ProgrammeRow>(
  currentProgrammeRows.value,
  previousProgrammeRows.value,
  row => row.code,
  row => row.libelle || row.code,
  row => row.montant_depenses,
))
const natureMovers = computed(() => buildDeltaRows<NatureCeRow>(
  currentNatureRows.value,
  previousNatureRows.value,
  row => row.code,
  row => row.libelle || row.code,
  row => row.montant_depenses,
))
const chapitreMovers = computed(() => buildDeltaRows<GroupStatRow>(
  currentChapitreRows.value,
  previousChapitreRows.value,
  row => row.label,
  row => row.label,
  row => row.montant,
))

const programmeHausses = computed(() => programmeMovers.value.filter(row => row.deltaAmount > 0).sort((a, b) => b.deltaAmount - a.deltaAmount).slice(0, 3))
const programmeBaisses = computed(() => programmeMovers.value.filter(row => row.deltaAmount < 0).sort((a, b) => a.deltaAmount - b.deltaAmount).slice(0, 3))
const natureHausses = computed(() => natureMovers.value.filter(row => row.deltaAmount > 0).sort((a, b) => b.deltaAmount - a.deltaAmount).slice(0, 3))
const natureBaisses = computed(() => natureMovers.value.filter(row => row.deltaAmount < 0).sort((a, b) => a.deltaAmount - b.deltaAmount).slice(0, 3))
const chapitreHausses = computed(() => chapitreMovers.value.filter(row => row.deltaAmount > 0).sort((a, b) => b.deltaAmount - a.deltaAmount).slice(0, 3))
const chapitreBaisses = computed(() => chapitreMovers.value.filter(row => row.deltaAmount < 0).sort((a, b) => a.deltaAmount - b.deltaAmount).slice(0, 3))

const risquesCibles = computed(() => {
  const rows = [
    ...topProgrammes.value.map(r => ({ ...r, axe: 'Programme' })),
    ...topNaturesCe.value.map(r => ({ ...r, axe: 'Nature CE' })),
    ...topChapitres.value.map(r => ({ ...r, axe: 'Chapitre' })),
  ]

  return rows
    .filter(r => r.risque)
    .slice(0, 6)
})

const heroStats = computed(() => {
  if (!summary.value)
    return []

  return [
    { label: 'Mandats', value: (summary.value.meta.mandats_count ?? 0).toLocaleString('fr-FR') },
    { label: 'Recettes', value: (summary.value.meta.recettes_count ?? 0).toLocaleString('fr-FR') },
    { label: 'Tous mouvements', value: (summary.value.meta.mouvements_count ?? 0).toLocaleString('fr-FR') },
    { label: 'Période', value: periodLabel.value },
  ]
})

const regionLabel = computed(() => summary.value ? `${summary.value.region.nom} (${summary.value.region.code})` : null)
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

const bankBridge = computed(() => summary.value?.banques.pont_tresorerie ?? null)
const bankEvolution = computed(() => summary.value?.banques.evolution.slice(-7) ?? [])
const bankVariations = computed(() => summary.value?.banques.top_variations ?? [])
const bankAnomalies = computed(() => summary.value?.banques.anomalies ?? [])
const bankConfidence = computed(() => summary.value?.banques.confiance ?? null)
const workflowInsights = computed(() => summary.value?.workflow_insights ?? null)
const workflowAgingRows = computed(() => workflowInsights.value?.temps_par_statut ?? [])
const workflowConversions = computed(() => workflowInsights.value?.conversions ?? [])
const workflowImmobilises = computed(() => workflowInsights.value?.immobilises_par_statut ?? [])
const admisAging = computed(() => workflowInsights.value?.aging_admis ?? null)

async function loadDashboard(silent = false) {
  if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
    return

  await fetchSummary({
    region_code: regionCode.value ?? undefined,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  }, { silent })

  if (!regionCode.value && summary.value?.region.code)
    regionCode.value = summary.value.region.code

  const prev = previousPeriod.value

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
    previousProgrammesExplorer.fetch({
      region_code: regionCode.value,
      date_debut: prev?.date_debut,
      date_fin: prev?.date_fin,
      page: 1,
      per_page: 5,
      type: 'depense',
    }),
    previousNatureCeExplorer.fetch({
      region_code: regionCode.value,
      date_debut: prev?.date_debut,
      date_fin: prev?.date_fin,
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
  if (!regionCode.value && regions.value.length)
    regionCode.value = regions.value[0].code

  await loadDashboard()
})
</script>

<template>
  <div class="aice-page aice-regional-dashboard">
     <ExplorerHero
      title="Tableau de bord régional"
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
          v-model="regionCode"
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
      Aucune donnée mandatée ou recette pour {{ regionLabel ?? 'cette région' }} entre le {{ periodLabel }}.
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
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatPercent(row.partPct) }} · {{ row.tauxExecutionPct !== null ? `Exécution ${formatPercent(row.tauxExecutionPct)}` : 'Exécution —' }}
                </div>
                <div
                  v-if="row.risque"
                  class="aice-top-item__risk"
                >
                  {{ row.risque }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant) }}
                <div class="aice-top-item__delta tabular-nums">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
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
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatPercent(row.partPct) }} · {{ row.tauxExecutionPct !== null ? `Exécution ${formatPercent(row.tauxExecutionPct)}` : 'Exécution —' }}
                </div>
                <div
                  v-if="row.risque"
                  class="aice-top-item__risk"
                >
                  {{ row.risque }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant) }}
                <div class="aice-top-item__delta tabular-nums">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
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
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatPercent(row.partPct) }}
                </div>
                <div
                  v-if="row.risque"
                  class="aice-top-item__risk"
                >
                  {{ row.risque }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.montant) }}
                <div class="aice-top-item__delta tabular-nums">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mt-1">
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Top hausses (Programmes)"
          :subtitle="previousPeriod ? `vs ${previousPeriod.date_debut} — ${previousPeriod.date_fin}` : 'vs période précédente'"
        >
          <div
            v-if="!programmeHausses.length"
            class="aice-panel-empty"
          >
            Aucune hausse significative détectée.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in programmeHausses"
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.deltaAmount) }}
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Top baisses (Natures CE)"
          :subtitle="previousPeriod ? `vs ${previousPeriod.date_debut} — ${previousPeriod.date_fin}` : 'vs période précédente'"
        >
          <div
            v-if="!natureBaisses.length"
            class="aice-panel-empty"
          >
            Aucune baisse significative détectée.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in natureBaisses"
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.deltaAmount) }}
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Top hausses (Chapitres)"
          :subtitle="previousPeriod ? `vs ${previousPeriod.date_debut} — ${previousPeriod.date_fin}` : 'vs période précédente'"
        >
          <div
            v-if="!chapitreHausses.length"
            class="aice-panel-empty"
          >
            Aucune hausse significative détectée.
          </div>
          <div
            v-else
            class="aice-top-list"
          >
            <div
              v-for="row in chapitreHausses"
              :key="row.key"
              class="aice-top-item"
            >
              <div class="aice-top-item__label">
                {{ row.label }}
                <div class="aice-top-item__meta">
                  {{ formatEvolutionPct(row.evolutionPct) }}
                </div>
              </div>
              <div class="aice-top-item__value tabular-nums">
                {{ formatFcfa(row.deltaAmount) }}
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mt-1">
      <VCol cols="12">
        <DataPanel
          title="Risques ciblés"
          :subtitle="periodLabel"
        >
          <div
            v-if="!risquesCibles.length"
            class="aice-panel-empty"
          >
            Aucun risque majeur détecté sur les tops.
          </div>
          <VTable
            v-else
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Axe</th>
                <th>Libellé</th>
                <th class="text-end">
                  Part
                </th>
                <th class="text-end">
                  Montant
                </th>
                <th>Signal</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in risquesCibles"
                :key="`${row.axe}-${row.key}`"
              >
                <td>{{ row.axe }}</td>
                <td>{{ row.label }}</td>
                <td class="text-end tabular-nums">
                  {{ formatPercent(row.partPct) }}
                </td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(row.montant) }}
                </td>
                <td>{{ row.risque }}</td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
    </VRow>

    <p class="aice-section-label mb-2 mt-4">
      Banques
    </p>
    <VRow class="mb-1">
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Pont trésorerie"
          :subtitle="periodLabel"
        >
          <VTable
            v-if="bankBridge"
            density="compact"
            class="aice-admin-table"
          >
            <tbody>
              <tr>
                <td>Solde début</td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(bankBridge.solde_debut) }}
                </td>
              </tr>
              <tr>
                <td>Encaissements</td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(bankBridge.encaissements) }}
                </td>
              </tr>
              <tr>
                <td>Décaissements</td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(bankBridge.decaissements) }}
                </td>
              </tr>
              <tr>
                <td>Solde fin</td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(bankBridge.solde_fin) }}
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
        <DataPanel
          title="Évolution flux net"
          :subtitle="periodLabel"
        >
          <ChartWidget
            v-if="bankEvolution.length"
            type="bar"
            :labels="bankEvolution.map(r => r.date)"
            :datasets="[{ label: 'Flux net', data: bankEvolution.map(r => r.flux_net) }]"
            :height="260"
          />
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune donnée bancaire sur la période.
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Top variations comptes"
          :subtitle="periodLabel"
        >
          <VTable
            v-if="bankVariations.length"
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Compte</th>
                <th class="text-end">
                  Variation
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in bankVariations"
                :key="row.numero_compte"
              >
                <td>{{ row.numero_compte }}</td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(row.variation) }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucune variation de compte calculable.
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mb-1">
      <VCol
        cols="12"
        lg="8"
      >
        <DataPanel
          title="Anomalies bancaires"
          :subtitle="periodLabel"
        >
          <div
            v-if="!bankAnomalies.length"
            class="aice-panel-empty"
          >
            Aucune anomalie bancaire détectée.
          </div>
          <VTable
            v-else
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Priorité</th>
                <th>Titre</th>
                <th>Détail</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in bankAnomalies"
                :key="row.type"
              >
                <td class="text-capitalize">
                  {{ row.priorite }}
                </td>
                <td>{{ row.titre }}</td>
                <td>{{ row.detail }}</td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Confiance du chiffre"
          :subtitle="periodLabel"
        >
          <VTable
            v-if="bankConfidence"
            density="compact"
            class="aice-admin-table"
          >
            <tbody>
              <tr>
                <td>Dernier mouvement</td>
                <td class="text-end tabular-nums">
                  {{ bankConfidence.derniere_date_mouvement ?? '—' }}
                </td>
              </tr>
              <tr>
                <td>Comptes inclus</td>
                <td class="text-end tabular-nums">
                  {{ bankConfidence.comptes_inclus.toLocaleString('fr-FR') }}
                </td>
              </tr>
              <tr>
                <td>Lignes incluses</td>
                <td class="text-end tabular-nums">
                  {{ bankConfidence.lignes_incluses.toLocaleString('fr-FR') }}
                </td>
              </tr>
              <tr>
                <td>Lignes exclues</td>
                <td class="text-end tabular-nums">
                  {{ bankConfidence.lignes_exclues.toLocaleString('fr-FR') }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
    </VRow>

    <p class="aice-section-label mb-2 mt-4">
      Workflow
    </p>
    <VRow class="mb-1">
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Aging des admis"
          :subtitle="periodLabel"
        >
          <div
            v-if="!admisAging"
            class="aice-panel-empty"
          >
            Aucune donnée admis disponible.
          </div>
          <div v-else>
            <div class="d-flex flex-column gap-1">
              <div class="d-flex justify-space-between">
                <span>Montant</span>
                <span class="tabular-nums">{{ formatFcfa(admisAging.montant) }}</span>
              </div>
              <div class="d-flex justify-space-between">
                <span>Âge moyen</span>
                <span class="tabular-nums">{{ admisAging.average_days.toLocaleString('fr-FR') }} j</span>
              </div>
              <div class="d-flex justify-space-between">
                <span>Âge max</span>
                <span class="tabular-nums">{{ admisAging.max_days.toLocaleString('fr-FR') }} j</span>
              </div>
            </div>
            <VTable
              v-if="admisAging.buckets.length"
              density="compact"
              class="aice-admin-table mt-3"
            >
              <tbody>
                <tr
                  v-for="bucket in admisAging.buckets"
                  :key="bucket.label"
                >
                  <td>{{ bucket.label }}</td>
                  <td class="text-end tabular-nums">
                    {{ bucket.count.toLocaleString('fr-FR') }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Taux de passage"
          :subtitle="periodLabel"
        >
          <div
            v-if="!workflowConversions.length"
            class="aice-panel-empty"
          >
            Aucune conversion calculable sur la période.
          </div>
          <VTable
            v-else
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Transition</th>
                <th class="text-end">
                  Taux
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in workflowConversions"
                :key="row.key"
              >
                <td>{{ row.label }}</td>
                <td class="text-end tabular-nums">
                  {{ formatPercent(row.taux_pct) }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <div class="text-caption mt-2">
            Base: mandats ayant atteint le statut source au moins une fois.
          </div>
        </DataPanel>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Montant immobilisé"
          :subtitle="periodLabel"
        >
          <div
            v-if="!workflowImmobilises.length"
            class="aice-panel-empty"
          >
            Aucun encours non payé détecté.
          </div>
          <VTable
            v-else
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Statut</th>
                <th class="text-end">
                  Montant
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in workflowImmobilises.slice(0, 6)"
                :key="row.statut"
              >
                <td><StatutChip :statut="row.statut" /></td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(row.montant) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>
    </VRow>

    <VRow class="mb-1">
      <VCol cols="12">
        <DataPanel
          title="Temps moyen par statut"
          :subtitle="periodLabel"
        >
          <div
            v-if="!workflowAgingRows.length"
            class="aice-panel-empty"
          >
            Aucun calcul de durée disponible.
          </div>
          <VTable
            v-else
            density="compact"
            class="aice-admin-table"
          >
            <thead>
              <tr>
                <th>Statut</th>
                <th class="text-end">
                  Observations
                </th>
                <th class="text-end">
                  Âge moyen (j)
                </th>
                <th class="text-end">
                  Âge max (j)
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in workflowAgingRows.slice(0, 8)"
                :key="row.statut"
              >
                <td><StatutChip :statut="row.statut" /></td>
                <td class="text-end tabular-nums">
                  {{ row.count.toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.average_days.toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.max_days.toLocaleString('fr-FR') }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
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
            Aucune répartition des mandats par statut sur cette période.
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
            Aucun type de mandat disponible sur cette période.
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
.aice-section-label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.05em;
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

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
