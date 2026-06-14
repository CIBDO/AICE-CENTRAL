<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import StatutChip from '@/components/aice/StatutChip.vue'
import { formatFcfa, formatDateOnly, formatDateRange } from '@/composables/useFormat'
import { queryParam, useDetailExplorerContext } from '@/composables/useDetailExplorerContext'
import { useMouvementDetail } from '@/composables/useMouvementDetail'
import type { MouvementRow } from '@/types/details'

definePage({ meta: { layout: 'default' } })

const route = useRoute('details-mandats-id')
const router = useRouter()
const { regionCode, dateDebut, dateFin, periodQuery, baseQuery, applyBaseQuery } = useDetailExplorerContext()

const id = computed(() => Number(route.params.id))

const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))

const { loading, error, mouvement, related, context, fetch } = useMouvementDetail()

function listQuery() {
  return baseQuery({
    statut: queryParam(route.query.statut),
    programme: queryParam(route.query.programme),
    search: queryParam(route.query.search),
    page: queryParam(route.query.page),
  })
}

const detailFields = computed(() => {
  const m = mouvement.value
  if (!m)
    return []

  return [
    { label: 'N° mandat', value: m.source_numero_mandat ?? '—' },
    { label: 'Bénéficiaire', value: m.beneficiaire ?? '—' },
    { label: 'Programme', value: m.code_programme ? `${m.code_programme} — ${m.programme ?? ''}` : '—' },
    { label: 'Chapitre', value: m.chapitre ?? '—' },
    { label: 'Nature CE', value: m.nature_ce ?? '—' },
    { label: 'Nature', value: m.nature ?? '—' },
    { label: 'Type mandat', value: m.type_mandat_libelle ?? m.type_mandat ?? '—' },
    { label: 'Type mouvement', value: m.type ?? '—' },
    { label: 'Date', value: formatDateOnly(m.date_mouvement) },
    { label: 'Période', value: periodLabel.value },
    { label: 'Région', value: context.value?.region_nom ?? context.value?.region_code ?? '—' },
    { label: 'Source ID', value: m.source_id ?? '—' },
  ]
})

function goBack() {
  router.push({
    name: 'details-mandats',
    query: listQuery(),
  })
}

function openProgramme() {
  const code = mouvement.value?.code_programme
  if (!code)
    return

  router.push({
    name: 'details-programmes',
    query: baseQuery({
      programme: code,
    }),
  })
}

function openRelated(item: MouvementRow) {
  router.push({
    name: 'details-mandats-id',
    params: { id: item.id },
    query: listQuery(),
  })
}

watch(() => route.query, (query) => {
  applyBaseQuery(query)
}, { immediate: true })

watch(id, () => {
  fetch(id.value, {
    region_code: regionCode.value,
    ...periodQuery(),
  })
}, { immediate: true })

const relatedHeaders = [
  { title: 'Date', key: 'date_mouvement', width: '110px' },
  { title: 'Libellé', key: 'libelle' },
  { title: 'Statut', key: 'statut', width: '120px' },
  { title: 'Montant', key: 'montant', align: 'end' as const },
]
</script>

<template>
  <div class="aice-page aice-mandat-fiche">
    <div class="d-flex align-center gap-3 mb-4">
      <VBtn
        variant="text"
        prepend-icon="tabler-arrow-left"
        @click="goBack"
      >
        Retour aux mandats
      </VBtn>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ error }}
    </VAlert>

    <VSkeletonLoader
      v-if="loading && !mouvement"
      type="article, table"
    />

    <ExplorerHero
      v-if="mouvement"
      icon="tabler-file-invoice"
      :title="mouvement.libelle"
      :subtitle="`${mouvement.beneficiaire ?? 'Bénéficiaire non renseigné'}${mouvement.source_numero_mandat ? ` · N° ${mouvement.source_numero_mandat}` : ''}`"
      :stats="[
        { label: 'Montant', value: formatFcfa(mouvement.montant) },
        { label: 'Programme', value: mouvement.code_programme ?? '—' },
        { label: 'Période', value: periodLabel },
      ]"
    >
      <template #below>
        <div class="d-flex flex-wrap align-center gap-2 mt-3">
          <StatutChip :statut="mouvement.statut ?? '—'" />
          <VChip
            size="small"
            variant="outlined"
          >
            {{ mouvement.type === 'recette' ? 'Recette' : 'Dépense' }}
          </VChip>
        </div>
      </template>
      <template #actions>
        <VBtn
          v-if="mouvement.code_programme"
          variant="outlined"
          size="small"
          prepend-icon="tabler-layout-grid"
          @click="openProgramme"
        >
          Programme
        </VBtn>
      </template>
    </ExplorerHero>

    <VRow v-if="mouvement">
        <VCol
          cols="12"
          lg="7"
        >
          <DataPanel title="Informations détaillées">
            <VList
              density="compact"
              class="aice-detail-list"
            >
              <VListItem
                v-for="field in detailFields"
                :key="field.label"
              >
                <template #prepend>
                  <span class="aice-detail-label">{{ field.label }}</span>
                </template>
                <VListItemTitle class="text-body-2">
                  {{ field.value }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </DataPanel>
        </VCol>
        <VCol
          cols="12"
          lg="5"
        >
          <DataPanel
            title="Mandats liés"
            :subtitle="mouvement.code_programme ? `Même programme (${mouvement.code_programme})` : 'Autres mandats de la période'"
          >
            <VDataTable
              v-if="related.length"
              :headers="relatedHeaders"
              :items="related"
              density="compact"
              class="aice-data-table aice-data-table--clickable"
              :items-per-page="5"
              hide-default-footer
              @click:row="(_ev: Event, ctx: { item: MouvementRow }) => openRelated(ctx.item)"
            >
              <template #item.date_mouvement="{ item }">
                <span class="tabular-nums">{{ formatDateOnly(item.date_mouvement) }}</span>
              </template>
              <template #item.statut="{ item }">
                <StatutChip :statut="item.statut ?? '—'" />
              </template>
              <template #item.montant="{ item }">
                <span class="tabular-nums">{{ formatFcfa(item.montant) }}</span>
              </template>
            </VDataTable>
            <div
              v-else
              class="aice-panel-empty"
            >
              Aucun mandat connexe.
            </div>
          </DataPanel>
        </VCol>
      </VRow>
  </div>
</template>

<style scoped lang="scss">
.aice-fiche-header {
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
}

.aice-detail-list {
  :deep(.v-list-item) {
    min-block-size: 40px;
  }
}

.aice-detail-label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  inline-size: 140px;
  text-transform: uppercase;
}

.aice-data-table--clickable :deep(tbody tr) {
  cursor: pointer;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

.aice-panel-empty {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  padding-block: 2rem;
  text-align: center;
}

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
