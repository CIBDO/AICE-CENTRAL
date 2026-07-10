<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import DataPanel from '@/components/aice/DataPanel.vue'
import RegionSelector from '@/components/aice/RegionSelector.vue'
import { usePushObservability } from '@/composables/usePushObservability'
import { formatDateFr, formatDateRange, toIsoDate } from '@/composables/useFormat'
import { useRegions } from '@/composables/useRegions'

definePage({
  meta: {
    layout: 'default',
    action: 'manage',
    subject: 'gerer_observabilite_push',
  },
})

const { loading, error, summary, regions: regionRows, topErrors, events, meta, fetchAll } = usePushObservability()
const { loading: regionsLoading, regions, fetchRegions } = useRegions()

const today = new Date()
const defaultStart = toIsoDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 6))
const defaultEnd = toIsoDate(today)

const regionCode = ref<string | null>(null)
const status = ref<'OK' | 'ERROR' | null>(null)
const endpoint = ref<string | null>(null)
const dateDebut = ref(defaultStart)
const dateFin = ref(defaultEnd)
const page = ref(1)
const perPage = ref(20)
const retardMinutes = ref(180)

const statusOptions = [
  { title: 'Tous statuts', value: null as 'OK' | 'ERROR' | null },
  { title: 'OK', value: 'OK' as const },
  { title: 'Erreur', value: 'ERROR' as const },
]

const endpointOptions = computed(() => {
  const values = new Set<string>()
  for (const row of regionRows.value) {
    if (row.last_endpoint)
      values.add(row.last_endpoint)
  }
  for (const row of events.value)
    values.add(row.endpoint)

  return [
    { title: 'Tous endpoints', value: null as string | null },
    ...[...values].sort().map(value => ({ title: value, value })),
  ]
})

const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))
const okCount = computed(() => regionRows.value.filter(row => row.state === 'ok').length)
const lateCount = computed(() => regionRows.value.filter(row => row.state === 'late').length)
const errorCount = computed(() => regionRows.value.filter(row => row.state === 'error').length)
const noDataCount = computed(() => regionRows.value.filter(row => row.state === 'no_data').length)

const heroStats = computed(() => [
  { label: 'Régions OK', value: okCount.value.toLocaleString('fr-FR') },
  { label: 'Régions en retard', value: lateCount.value.toLocaleString('fr-FR') },
  { label: 'Régions en erreur', value: errorCount.value.toLocaleString('fr-FR') },
  { label: 'Événements', value: (summary.value?.total_events ?? 0).toLocaleString('fr-FR') },
  { label: 'Période', value: periodLabel.value },
])

function stateLabel(state: string) {
  return {
    ok: 'OK',
    late: 'Retard',
    error: 'Erreur',
    no_data: 'Aucune donnée',
  }[state] ?? state
}

function stateColor(state: string) {
  return {
    ok: 'success',
    late: 'warning',
    error: 'error',
    no_data: 'default',
  }[state] ?? 'default'
}

function httpColor(statusCode: number | null) {
  if (!statusCode)
    return 'default'
  if (statusCode >= 500)
    return 'error'
  if (statusCode >= 400)
    return 'warning'
  if (statusCode >= 200)
    return 'success'
  return 'default'
}

function formatAge(ageMinutes: number | null) {
  if (ageMinutes === null)
    return '—'
  if (ageMinutes < 60)
    return `${ageMinutes} min`

  const hours = Math.floor(ageMinutes / 60)
  const minutes = ageMinutes % 60

  return minutes > 0 ? `${hours} h ${minutes} min` : `${hours} h`
}

async function load() {
  await fetchAll({
    region_code: regionCode.value ?? undefined,
    status: status.value,
    endpoint: endpoint.value,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
    page: page.value,
    per_page: perPage.value,
    retard_minutes: retardMinutes.value,
  })
}

function refresh() {
  page.value = 1
  return load()
}

watch(page, () => load())

onMounted(async () => {
  await fetchRegions()
  await load()
})
</script>

<template>
  <div class="aice-page">
    <ExplorerHero
      title="Observabilité push"
      :subtitle="`Supervision Admin IT · ${periodLabel}`"
      :stats="heroStats"
    >
      <template #below>
        <div class="aice-dashboard-hero__meta">
          Dernier événement : {{ formatDateFr(summary?.last_received_at) }}
          <template v-if="summary">
            · {{ summary.success_count.toLocaleString('fr-FR') }} OK · {{ summary.errors_count.toLocaleString('fr-FR') }} erreur(s)
          </template>
        </div>
      </template>
    </ExplorerHero>

    <div class="aice-sticky-toolbar mb-4">
      <div class="d-flex flex-wrap align-center gap-3">
        <RegionSelector
          v-model="regionCode"
          :regions="regions"
          :loading="regionsLoading"
          allow-all
        />
        <VSelect
          v-model="status"
          :items="statusOptions"
          label="Statut"
          item-title="title"
          item-value="value"
          density="compact"
          hide-details
          style="min-inline-size: 170px;"
        />
        <VSelect
          v-model="endpoint"
          :items="endpointOptions"
          label="Endpoint"
          item-title="title"
          item-value="value"
          density="compact"
          hide-details
          style="min-inline-size: 260px;"
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
        <VTextField
          v-model.number="retardMinutes"
          label="Seuil retard (min)"
          type="number"
          min="1"
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
          @click="refresh"
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

    <VRow class="mb-1">
      <VCol
        cols="12"
        md="8"
      >
        <DataPanel
          title="État des régions"
          :subtitle="`${regionRows.length} région(s) surveillée(s) · ${noDataCount} sans données`"
        >
          <VTable
            density="compact"
            class="aice-monitoring-table"
          >
            <thead>
              <tr>
                <th>Région</th>
                <th>État</th>
                <th>Dernier push</th>
                <th>Âge</th>
                <th class="text-end">Mandats</th>
                <th class="text-end">Recettes</th>
                <th class="text-end">Banques</th>
                <th class="text-end">Erreurs</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in regionRows"
                :key="row.region.code"
              >
                <td>
                  <div class="font-weight-medium">
                    {{ row.region.code }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ row.region.nom }}
                  </div>
                </td>
                <td>
                  <VChip
                    size="small"
                    :color="stateColor(row.state)"
                    variant="tonal"
                  >
                    {{ stateLabel(row.state) }}
                  </VChip>
                </td>
                <td>
                  <div>{{ formatDateFr(row.last_received_at) }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ row.last_endpoint ?? '—' }}
                  </div>
                </td>
                <td class="tabular-nums">
                  {{ formatAge(row.age_minutes) }}
                </td>
                <td class="text-end tabular-nums">
                  {{ (row.mandats_count ?? 0).toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ (row.recettes_count ?? 0).toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ (row.banques_count ?? 0).toLocaleString('fr-FR') }}
                </td>
                <td class="text-end tabular-nums">
                  {{ row.error_count.toLocaleString('fr-FR') }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </DataPanel>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <DataPanel
          title="Top erreurs"
          :subtitle="`${topErrors.length} signature(s)`"
        >
          <div
            v-if="!topErrors.length"
            class="aice-panel-empty"
          >
            Aucune erreur push sur la période sélectionnée.
          </div>
          <div
            v-else
            class="aice-error-list"
          >
            <div
              v-for="(item, index) in topErrors"
              :key="`${item.http_status}-${item.endpoint}-${index}`"
              class="aice-error-item"
            >
              <div class="d-flex align-center justify-space-between gap-2 mb-1">
                <VChip
                  size="small"
                  :color="httpColor(item.http_status)"
                  variant="tonal"
                >
                  HTTP {{ item.http_status }}
                </VChip>
                <span class="tabular-nums text-caption">{{ item.occurrences.toLocaleString('fr-FR') }} cas</span>
              </div>
              <div class="font-weight-medium text-body-2">
                {{ item.endpoint }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.message_short || 'Erreur sans message.' }}
              </div>
              <div class="text-caption text-medium-emphasis mt-1">
                Vu le {{ formatDateFr(item.last_seen) }}
              </div>
            </div>
          </div>
        </DataPanel>
      </VCol>
    </VRow>

    <DataPanel
      title="Timeline des événements push"
      :subtitle="`${meta?.total ?? 0} événement(s) sur la période`"
    >
      <VTable
        density="compact"
        class="aice-monitoring-table"
      >
        <thead>
          <tr>
            <th>Reçu le</th>
            <th>Région</th>
            <th>Endpoint</th>
            <th>Statut</th>
            <th class="text-end">Durée</th>
            <th class="text-end">Mandats</th>
            <th class="text-end">Recettes</th>
            <th class="text-end">Banques</th>
            <th>Message</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="event in events"
            :key="event.id"
          >
            <td class="tabular-nums">
              {{ formatDateFr(event.received_at) }}
            </td>
            <td>{{ event.region_code ?? '—' }}</td>
            <td>{{ event.endpoint }}</td>
            <td>
              <VChip
                size="small"
                :color="event.status === 'OK' ? 'success' : 'error'"
                variant="tonal"
              >
                {{ event.status }}<template v-if="event.http_status"> · {{ event.http_status }}</template>
              </VChip>
            </td>
            <td class="text-end tabular-nums">
              {{ event.duration_ms !== null ? `${event.duration_ms} ms` : '—' }}
            </td>
            <td class="text-end tabular-nums">
              {{ (event.mandats_count ?? 0).toLocaleString('fr-FR') }}
            </td>
            <td class="text-end tabular-nums">
              {{ (event.recettes_count ?? 0).toLocaleString('fr-FR') }}
            </td>
            <td class="text-end tabular-nums">
              {{ (event.banques_count ?? 0).toLocaleString('fr-FR') }}
            </td>
            <td class="text-caption">
              {{ event.message ?? '—' }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <div class="d-flex justify-end mt-4">
        <VPagination
          v-model="page"
          :length="meta?.last_page ?? 1"
          total-visible="7"
        />
      </div>
    </DataPanel>
  </div>
</template>

<style scoped lang="scss">
.aice-monitoring-table {
  :deep(thead th) {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
  }
}

.aice-error-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.aice-error-item {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
  border-radius: 10px;
  padding: 0.75rem;
}

.aice-panel-empty,
.tabular-nums {
  font-variant-numeric: tabular-nums;
}

.aice-panel-empty {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  padding-block: 1.5rem;
  text-align: center;
}
</style>
