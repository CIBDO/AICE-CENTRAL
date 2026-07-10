<script setup lang="ts">
import { useAbility } from '@casl/vue'
import type { KpiAccent } from '@/types/dashboard'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import QuickLinkGrid from '@/components/aice/QuickLinkGrid.vue'
import { useNatureCeExplorer } from '@/composables/useNatureCeExplorer'
import { useProgrammesExplorer } from '@/composables/useProgrammesExplorer'
import { formatDateFr, formatFcfa } from '@/composables/useFormat'
import { useDashboardAutoRefresh } from '@/composables/useDashboardAutoRefresh'
import { useDashboardFilterSync } from '@/composables/useDetailExplorerContext'
import { useDashboardSummary } from '@/composables/useDashboardSummary'
import { useRegions } from '@/composables/useRegions'

definePage({ meta: { layout: 'default' } })

const { loading, error, summary, fetchSummary } = useDashboardSummary()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()
const { regionCode, dateDebut, dateFin, periodLabel, detailRoute, dashboardRoute, hydrateFromRoute } = useDashboardFilterSync()
const ability = useAbility()
const canManagePush = computed(() => ability.can('manage', 'gerer_observabilite_push'))
const programmesExplorer = useProgrammesExplorer()
const natureCeExplorer = useNatureCeExplorer()

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

const topProgrammes = computed(() => programmesExplorer.stats.value?.programmes?.slice(0, 5) ?? [])
const topNaturesCe = computed(() => natureCeExplorer.stats.value?.natures_ce?.slice(0, 5) ?? [])
const topChapitres = computed(() => {
  const fromProgrammes = programmesExplorer.stats.value?.par_chapitre ?? []
  if (fromProgrammes.length)
    return fromProgrammes.slice(0, 5)

  return (natureCeExplorer.stats.value?.par_chapitre ?? []).slice(0, 5)
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
