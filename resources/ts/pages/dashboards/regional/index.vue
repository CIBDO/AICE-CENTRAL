<script setup lang="ts">
import type { KpiAccent } from '@/types/dashboard'
import { formatDateFr, formatFcfa, formatMonthYear } from '@/composables/useFormat'
import { useDashboardSummary } from '@/composables/useDashboardSummary'
import { useRegions } from '@/composables/useRegions'

definePage({
  meta: {
    layout: 'default',
    public: true,
  },
})

const selectedRegion = ref<string | null>(null)
const annee = ref(new Date().getFullYear())
const mois = ref<number | null>(new Date().getMonth() + 1)

const { loading, error, summary, fetchSummary } = useDashboardSummary()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()

const kpis = computed(() => {
  const data = summary.value?.kpis

  if (!data) {
    return [
      { label: 'Recettes', value: '—', accent: 'recettes' as KpiAccent },
      { label: 'Dépenses', value: '—', accent: 'depenses' as KpiAccent },
      { label: 'Solde', value: '—', accent: 'solde' as KpiAccent },
      { label: 'Encaisse', value: '—', accent: 'encaisse' as KpiAccent },
    ]
  }

  return [
    { label: 'Recettes', value: formatFcfa(data.total_recettes), accent: 'recettes' as KpiAccent },
    { label: 'Dépenses', value: formatFcfa(data.total_depenses), accent: 'depenses' as KpiAccent },
    { label: 'Solde', value: formatFcfa(data.solde), accent: 'solde' as KpiAccent },
    { label: 'Encaisse', value: formatFcfa(data.encaisse), accent: 'encaisse' as KpiAccent },
  ]
})

const regionLabel = computed(() => {
  if (!summary.value)
    return null

  return `${summary.value.region.nom} (${summary.value.region.code})`
})

const periodLabel = computed(() => formatMonthYear(annee.value, mois.value))

const lastUpdate = computed(() => formatDateFr(summary.value?.meta.derniere_mise_a_jour))

async function loadDashboard() {
  await fetchSummary({
    region_code: selectedRegion.value ?? undefined,
    annee: annee.value,
    mois: mois.value,
  })

  if (!selectedRegion.value && summary.value?.region.code)
    selectedRegion.value = summary.value.region.code
}

watch([selectedRegion, annee, mois], () => {
  loadDashboard()
})

onMounted(async () => {
  await fetchRegions()
  if (regions.value.length)
    selectedRegion.value = regions.value[0].code

  await loadDashboard()
})
</script>

<template>
  <div class="aice-page">
    <PageHeader
      title="Tableau de bord régional"
      subtitle="Synthèse des mouvements, mandats et soldes de trésorerie."
      :region-label="regionLabel"
      :last-update="summary?.meta.derniere_mise_a_jour ? lastUpdate : null"
    />

    <PeriodToolbar
      v-model:annee="annee"
      v-model:mois="mois"
      @refresh="loadDashboard"
    >
      <template #region>
        <RegionSelector
          v-model="selectedRegion"
          :regions="regions"
          :loading="regionsLoading"
        />
      </template>
    </PeriodToolbar>

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
      v-if="!loading && summary && summary.meta.mouvements_count === 0"
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
        />
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
            class="aice-simple-table"
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
                <td>{{ row.statut }}</td>
                <td class="text-end tabular-nums">
                  {{ row.count.toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ formatFcfa(row.montant) }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <div
            v-else
            class="aice-panel-empty"
          >
            Aucun mouvement enregistré pour cette période.
          </div>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <DataPanel
          title="Mandats par type"
          subtitle="Matériel · Salaire · Reversement"
        >
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
.aice-empty-banner {
  background: rgb(var(--v-theme-grey-50));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
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
