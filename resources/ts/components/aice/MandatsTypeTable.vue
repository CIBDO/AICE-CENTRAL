<script setup lang="ts">
import type { MandatTypeRow } from '@/types/dashboard'
import { formatFcfa } from '@/composables/useFormat'

interface Props {
  rows: MandatTypeRow[]
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>

<template>
  <div class="aice-mandats-table">
    <VTable
      v-if="rows.length"
      density="compact"
      class="aice-mandats-table__table"
    >
      <thead>
        <tr>
          <th>Type</th>
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
          v-for="row in rows"
          :key="row.code"
        >
          <td>{{ row.libelle }}</td>
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
      v-else-if="!loading"
      class="aice-mandats-table__empty"
    >
      Aucune donnée de mandat pour la période sélectionnée.
    </div>

    <div
      v-else
      class="aice-mandats-table__empty"
    >
      Chargement…
    </div>
  </div>
</template>

<style scoped lang="scss">
.aice-mandats-table {
  &__table {
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

  &__empty {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    padding-block: 2rem;
    text-align: center;
  }
}

.tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
