<script setup lang="ts">
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import { useAdminPermissions } from '@/composables/useAdminPermissions'
import { useAdminRoles, type RolePayload } from '@/composables/useAdminRoles'

definePage({ meta: { layout: 'default' } })

const { loading, error, roles, fetch, show, create, update, remove } = useAdminRoles()
const { permissions, fetch: fetchPermissions } = useAdminPermissions()

const dialog = ref(false)
const editingId = ref<number | null>(null)
const form = ref<RolePayload>({ nom: '', description: '', permission_ids: [] })
const saving = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

const totalPermissions = computed(() =>
  roles.value.reduce((sum, role) => sum + role.permissions_count, 0),
)

interface PermissionGroup {
  key: string
  label: string
  items: typeof permissions.value
}

const permissionGroups = computed<PermissionGroup[]>(() => {
  const labels: Record<string, string> = {
    utilisateurs: 'Utilisateurs',
    roles: 'Rôles',
    permissions: 'Permissions',
    mandats: 'Mandats',
    recettes: 'Recettes',
    banques: 'Banques',
    dashboard: 'Tableau de bord',
  }

  const groups = new Map<string, PermissionGroup>()

  for (const permission of permissions.value) {
    const module = permission.nom.replace(/^(voir|gerer)_/, '')
    const key = module
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        label: labels[module] ?? module,
        items: [],
      })
    }
    groups.get(key)!.items.push(permission)
  }

  return [...groups.values()].map(group => ({
    ...group,
    items: [...group.items].sort((a, b) => a.nom.localeCompare(b.nom)),
  }))
})

function permissionLabel(nom: string): string {
  if (nom.startsWith('gerer_'))
    return 'Gérer'
  if (nom.startsWith('voir_'))
    return 'Voir'
  return nom
}

function openCreate() {
  editingId.value = null
  form.value = { nom: '', description: '', permission_ids: [] }
  dialog.value = true
}

async function openEdit(role: typeof roles.value[0]) {
  editingId.value = role.id
  saving.value = true
  try {
    const detail = await show(role.id)
    form.value = {
      nom: detail.nom,
      description: detail.description ?? '',
      permission_ids: [...detail.permission_ids],
    }
    dialog.value = true
  }
  finally {
    saving.value = false
  }
}

function togglePermission(id: number, checked: boolean) {
  if (checked) {
    if (!form.value.permission_ids.includes(id))
      form.value.permission_ids.push(id)
  }
  else {
    form.value.permission_ids = form.value.permission_ids.filter(pid => pid !== id)
  }
}

async function save() {
  saving.value = true
  try {
    const payload: RolePayload = {
      nom: form.value.nom.trim(),
      description: form.value.description?.trim() || null,
      permission_ids: form.value.permission_ids,
    }

    if (editingId.value) {
      await update(editingId.value, payload)
      snackbarText.value = `Rôle ${payload.nom} mis à jour.`
    }
    else {
      await create(payload)
      snackbarText.value = `Rôle ${payload.nom} créé.`
    }

    snackbar.value = true
    dialog.value = false
    await fetch()
  }
  finally {
    saving.value = false
  }
}

async function onDelete(role: typeof roles.value[0]) {
  if (!confirm(`Supprimer le rôle « ${role.nom} » ?`))
    return

  try {
    await remove(role.id)
    snackbarText.value = `Rôle ${role.nom} supprimé.`
    snackbar.value = true
    await fetch()
  }
  catch (e) {
    error.value = e instanceof Error ? e.message : 'Impossible de supprimer ce rôle.'
  }
}

onMounted(async () => {
  await Promise.all([fetchPermissions(), fetch()])
})
</script>

<template>
  <div class="aice-page">
    <ExplorerHero
      icon="tabler-shield"
      title="Rôles et permissions"
      subtitle="Profils d'accès et matrice des droits sur le hub central DGTCP."
      :stats="[
        { label: 'Rôles', value: String(roles.length) },
        { label: 'Permissions actives', value: String(totalPermissions) },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouveau rôle
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

    <VRow>
      <VCol
        v-for="role in roles"
        :key="role.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <VCard
          class="aice-quick-card"
          elevation="0"
        >
          <VCardText class="pa-5">
            <div class="d-flex align-center gap-3 mb-3">
              <div class="aice-quick-card__icon">
                <VIcon
                  icon="tabler-shield"
                  size="22"
                />
              </div>
              <div class="flex-grow-1">
                <div class="text-subtitle-1 font-weight-bold">
                  {{ role.nom }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ role.permissions_count }} permission(s) · {{ role.users_count }} utilisateur(s)
                </div>
              </div>
              <VBtn
                icon
                variant="text"
                size="x-small"
                @click="openEdit(role)"
              >
                <VIcon icon="tabler-pencil" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="x-small"
                color="error"
                :disabled="role.users_count > 0"
                @click="onDelete(role)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
            <p
              v-if="role.description"
              class="text-body-2 text-medium-emphasis mb-0"
            >
              {{ role.description }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSkeletonLoader
      v-if="loading && !roles.length"
      type="card@3"
    />

    <VDialog
      v-model="dialog"
      max-width="640"
      scrollable
    >
      <VCard :title="editingId ? `Modifier — ${form.nom}` : 'Nouveau rôle'">
        <VCardText class="d-flex flex-column gap-4">
          <VTextField
            v-model="form.nom"
            label="Nom du rôle"
            placeholder="Comptable régional"
          />
          <VTextField
            v-model="form.description"
            label="Description"
            placeholder="Agent comptable avec accès mandats et recettes"
          />

          <div>
            <div class="text-subtitle-2 mb-3">
              Permissions
            </div>
            <div
              v-for="group in permissionGroups"
              :key="group.key"
              class="aice-permission-group mb-4"
            >
              <div class="text-body-2 font-weight-medium mb-2">
                {{ group.label }}
              </div>
              <div class="d-flex flex-wrap gap-2">
                <VCheckbox
                  v-for="permission in group.items"
                  :key="permission.id"
                  :model-value="form.permission_ids.includes(permission.id)"
                  :label="permissionLabel(permission.nom)"
                  density="compact"
                  hide-details
                  @update:model-value="togglePermission(permission.id, !!$event)"
                />
              </div>
              <div class="text-caption text-medium-emphasis mt-1">
                {{ group.items.map(p => p.description).filter(Boolean).join(' · ') }}
              </div>
            </div>
          </div>

          <VAlert
            v-if="!form.permission_ids.length"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-0"
          >
            Sélectionnez au moins une permission.
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!form.nom.trim() || !form.permission_ids.length"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar"
      color="success"
      :timeout="5000"
      location="bottom end"
    >
      {{ snackbarText }}
      <template #actions>
        <VBtn
          variant="text"
          @click="snackbar = false"
        >
          Fermer
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped lang="scss">
.aice-permission-group {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
