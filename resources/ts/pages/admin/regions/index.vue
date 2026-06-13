<script setup lang="ts">
import DashboardHero from '@/components/aice/DashboardHero.vue'
import { formatDateFr } from '@/composables/useFormat'
import { useAdminRegions } from '@/composables/useAdminRegions'

definePage({ meta: { layout: 'default' } })

const { loading, error, regions, fetch, update } = useAdminRegions()
const editDialog = ref(false)
const editForm = ref({ id: 0, nom: '', actif: true, ordre: 0 })
const saving = ref(false)

const headers = [
  { title: 'Code', key: 'code', width: '90px' },
  { title: 'Nom', key: 'nom' },
  { title: 'Ordre', key: 'ordre', width: '80px' },
  { title: 'Token', key: 'token_masked', width: '120px' },
  { title: 'Dernière connexion', key: 'derniere_connexion' },
  { title: 'Statut', key: 'actif', width: '100px' },
  { title: '', key: 'actions', width: '60px', sortable: false },
]

function openEdit(region: typeof regions.value[0]) {
  editForm.value = { id: region.id, nom: region.nom, actif: region.actif, ordre: region.ordre }
  editDialog.value = true
}

async function save() {
  saving.value = true
  try {
    await update(editForm.value.id, { nom: editForm.value.nom, actif: editForm.value.actif, ordre: editForm.value.ordre })
    editDialog.value = false
    await fetch()
  }
  finally {
    saving.value = false
  }
}

onMounted(() => fetch())
</script>

<template>
  <div class="aice-page">
    <DashboardHero
      title="Régions connectées"
      subtitle="Configuration des points d'entrée Push et suivi de connectivité."
      :stats="[{ label: 'Régions actives', value: String(regions.filter(r => r.actif).length) }]"
    />

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ error }}
    </VAlert>

    <DataPanel title="Registre des régions">
      <VDataTable
        :headers="headers"
        :items="regions"
        :loading="loading"
        density="compact"
        class="aice-admin-table"
        :items-per-page="-1"
        hide-default-footer
      >
        <template #item.derniere_connexion="{ item }">
          {{ item.derniere_connexion ? formatDateFr(item.derniere_connexion) : '—' }}
        </template>
        <template #item.actif="{ item }">
          <VChip
            size="x-small"
            :color="item.actif ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.actif ? 'Active' : 'Inactive' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="x-small"
            @click="openEdit(item)"
          >
            <VIcon icon="tabler-settings" />
          </VBtn>
        </template>
      </VDataTable>
    </DataPanel>

    <VDialog
      v-model="editDialog"
      max-width="420"
    >
      <VCard title="Modifier la région">
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="editForm.nom"
            label="Nom"
          />
          <VTextField
            v-model.number="editForm.ordre"
            label="Ordre d'affichage"
            type="number"
          />
          <VSwitch
            v-model="editForm.actif"
            label="Région active"
            color="primary"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="editDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
