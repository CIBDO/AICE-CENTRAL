<script setup lang="ts">
import DataPanel from '@/components/aice/DataPanel.vue'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import { formatDateFr } from '@/composables/useFormat'
import { useAdminRegions } from '@/composables/useAdminRegions'

definePage({ meta: { layout: 'default' } })

const { loading, error, regions, fetch, create, update, regenerateToken } = useAdminRegions()

const editDialog = ref(false)
const createDialog = ref(false)
const tokenDialog = ref(false)
const revealedToken = ref('')
const revealedRegionCode = ref('')
const tokenHint = ref('')
const editForm = ref({ id: 0, code: '', nom: '', actif: true, ordre: 0 })
const createForm = ref({ code: '', nom: '', actif: true, ordre: 0 })
const saving = ref(false)
const regenerating = ref(false)
const copied = ref(false)

const headers = [
  { title: 'Code', key: 'code', width: '90px' },
  { title: 'Nom', key: 'nom' },
  { title: 'Ordre', key: 'ordre', width: '80px' },
  { title: 'Token', key: 'token_masked', width: '140px' },
  { title: 'Dernière connexion', key: 'derniere_connexion' },
  { title: 'Statut', key: 'actif', width: '100px' },
  { title: '', key: 'actions', width: '88px', sortable: false },
]

const activeCount = computed(() => regions.value.filter(r => r.actif).length)

function showTokenPlain(code: string, token: string, hint: string) {
  revealedRegionCode.value = code
  revealedToken.value = token
  tokenHint.value = hint
  copied.value = false
  tokenDialog.value = true
}

function openCreate() {
  createForm.value = { code: '', nom: '', actif: true, ordre: regions.value.length + 1 }
  createDialog.value = true
}

function openEdit(region: typeof regions.value[0]) {
  editForm.value = {
    id: region.id,
    code: region.code,
    nom: region.nom,
    actif: region.actif,
    ordre: region.ordre,
  }
  editDialog.value = true
}

async function saveCreate() {
  saving.value = true
  try {
    const response = await create({
      code: createForm.value.code.trim().toUpperCase(),
      nom: createForm.value.nom.trim(),
      actif: createForm.value.actif,
      ordre: createForm.value.ordre,
      source_type: 'api',
    })
    createDialog.value = false
    await fetch()
    showTokenPlain(
      response.data.code,
      response.token_plain,
      'Token généré à la création — copiez-le dans AICE-API/.env (CENTRAL_API_TOKEN).',
    )
  }
  finally {
    saving.value = false
  }
}

async function saveEdit() {
  saving.value = true
  try {
    await update(editForm.value.id, {
      nom: editForm.value.nom,
      actif: editForm.value.actif,
      ordre: editForm.value.ordre,
    })
    editDialog.value = false
    await fetch()
  }
  finally {
    saving.value = false
  }
}

async function onRegenerateToken() {
  if (!confirm(`Régénérer le token de ${editForm.value.code} ? L'ancien token AICE-API ne fonctionnera plus.`))
    return

  regenerating.value = true
  try {
    const response = await regenerateToken(editForm.value.id)
    editDialog.value = false
    await fetch()
    showTokenPlain(
      response.data.code,
      response.token_plain,
      'Nouveau token — mettez à jour CENTRAL_API_TOKEN dans AICE-API/.env.',
    )
  }
  finally {
    regenerating.value = false
  }
}

async function copyToken() {
  await navigator.clipboard.writeText(revealedToken.value)
  copied.value = true
}

onMounted(() => fetch())
</script>

<template>
  <div class="aice-page">
    <ExplorerHero
      icon="tabler-map-pin"
      title="Régions connectées"
      subtitle="Configuration des points d'entrée Push et suivi de connectivité."
      :stats="[{ label: 'Régions actives', value: String(activeCount) }]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouvelle région
        </VBtn>
      </template>
    </ExplorerHero>

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
        <template #item.token_masked="{ item }">
          <code
            v-if="item.token_masked"
            class="aice-region-token"
          >{{ item.token_masked }}</code>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>
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

    <!-- Création -->
    <VDialog
      v-model="createDialog"
      max-width="480"
    >
      <VCard title="Nouvelle région">
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="createForm.code"
            label="Code région"
            placeholder="SAN"
            hint="Identifiant unique (ex. SAN, RGF) — génère automatiquement le token Push"
            persistent-hint
            @update:model-value="createForm.code = createForm.code.toUpperCase()"
          />
          <VTextField
            v-model="createForm.nom"
            label="Nom"
            placeholder="Région SAN"
          />
          <VTextField
            v-model.number="createForm.ordre"
            label="Ordre d'affichage"
            type="number"
          />
          <VSwitch
            v-model="createForm.actif"
            label="Région active"
            color="primary"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="createDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!createForm.code.trim() || !createForm.nom.trim()"
            @click="saveCreate"
          >
            Créer et générer le token
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Édition -->
    <VDialog
      v-model="editDialog"
      max-width="480"
    >
      <VCard :title="`Région ${editForm.code}`">
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            :model-value="editForm.code"
            label="Code"
            disabled
          />
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
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-0"
          >
            Le token Push est masqué dans la liste. Régénérez-le si AICE-API doit être reconfiguré.
          </VAlert>
        </VCardText>
        <VCardActions>
          <VBtn
            color="warning"
            variant="tonal"
            :loading="regenerating"
            prepend-icon="tabler-refresh"
            @click="onRegenerateToken"
          >
            Régénérer le token
          </VBtn>
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
            @click="saveEdit"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Token en clair (affiché une seule fois) -->
    <VDialog
      v-model="tokenDialog"
      max-width="560"
      persistent
    >
      <VCard :title="`Token Push — ${revealedRegionCode}`">
        <VCardText class="d-flex flex-column gap-3">
          <VAlert
            type="warning"
            variant="tonal"
            density="compact"
          >
            {{ tokenHint }}
          </VAlert>
          <VTextarea
            :model-value="revealedToken"
            readonly
            rows="3"
            auto-grow
            class="aice-region-token-plain"
          />
          <p class="text-caption text-medium-emphasis mb-0">
            Variable AICE-API : <code>CENTRAL_API_TOKEN={{ revealedToken }}</code>
          </p>
        </VCardText>
        <VCardActions>
          <VBtn
            variant="tonal"
            prepend-icon="tabler-copy"
            @click="copyToken"
          >
            {{ copied ? 'Copié' : 'Copier le token' }}
          </VBtn>
          <VSpacer />
          <VBtn
            color="primary"
            @click="tokenDialog = false"
          >
            J'ai copié le token
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped lang="scss">
.aice-region-token {
  font-family: var(--dgtcp-font-family, 'JetBrains Mono', monospace);
  font-size: 0.75rem;
}

.aice-region-token-plain :deep(textarea) {
  font-family: var(--dgtcp-font-family, 'JetBrains Mono', monospace);
  font-size: 0.8125rem;
}
</style>
