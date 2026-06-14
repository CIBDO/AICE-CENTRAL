<script setup lang="ts">
import DataPanel from '@/components/aice/DataPanel.vue'
import ExplorerHero from '@/components/aice/ExplorerHero.vue'
import { useAdminUsers, type UserPayload } from '@/composables/useAdminUsers'
import { useAdminRoles } from '@/composables/useAdminRoles'

definePage({ meta: { layout: 'default' } })

const { loading, error, users, meta, fetch, create, update, resetPassword, setActive, remove } = useAdminUsers()
const { roles, fetch: fetchRoles } = useAdminRoles()

const currentUserId = computed(() => {
  const data = useCookie<{ id?: number } | null>('userData').value
  return data?.id ?? null
})

const search = ref('')
const page = ref(1)
const dialog = ref(false)
const editingId = ref<number | null>(null)
const form = ref<UserPayload>({ login: '', nom: '', prenom: '', email: '', password: '', role_id: null, actif: true })
const saving = ref(false)
const actionLoadingId = ref<number | null>(null)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error'>('success')

const headers = [
  { title: 'Login', key: 'login' },
  { title: 'Nom complet', key: 'nom_complet' },
  { title: 'Rôle', key: 'role.nom' },
  { title: 'Statut', key: 'actif', width: '100px' },
  { title: '', key: 'actions', width: '120px', sortable: false },
]

const activeCount = computed(() => users.value.filter(u => u.actif).length)

function openCreate() {
  editingId.value = null
  form.value = { login: '', nom: '', prenom: '', email: '', password: '', role_id: roles.value[0]?.id ?? null, actif: true }
  dialog.value = true
}

function openEdit(user: typeof users.value[0]) {
  editingId.value = user.id
  form.value = { login: user.login, nom: user.nom, prenom: user.prenom, email: user.email ?? '', role_id: user.role_id, actif: user.actif }
  dialog.value = true
}

async function save() {
  saving.value = true
  try {
    if (editingId.value) {
      await update(editingId.value, form.value)
    }
    else {
      const response = await create(form.value)
      showSnackbar(response.message ?? `Utilisateur ${response.data.login} créé.`)
    }
    dialog.value = false
    await fetch(page.value, search.value)
  }
  finally {
    saving.value = false
  }
}

async function onDelete(id: number) {
  if (!confirm('Supprimer cet utilisateur ?'))
    return
  await remove(id)
  await fetch(page.value, search.value)
}

function showSnackbar(message: string, color: 'success' | 'error' = 'success') {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

async function onResetPassword(user: typeof users.value[0]) {
  if (!user.email) {
    showSnackbar('Aucune adresse e-mail associée à ce compte.', 'error')
    return
  }

  if (!confirm(`Réinitialiser le mot de passe de ${user.login} ?\nUn e-mail sera envoyé à ${user.email}.`))
    return

  actionLoadingId.value = user.id
  try {
    const response = await resetPassword(user.id)
    showSnackbar(response.message)
    await fetch(page.value, search.value)
  }
  catch (e) {
    showSnackbar(e instanceof Error ? e.message : 'Réinitialisation impossible.', 'error')
  }
  finally {
    actionLoadingId.value = null
  }
}

async function onToggleActive(user: typeof users.value[0]) {
  if (user.id === currentUserId.value) {
    showSnackbar('Vous ne pouvez pas modifier le statut de votre propre compte.', 'error')
    return
  }

  const nextActive = !user.actif
  const label = nextActive ? 'réactiver' : 'désactiver'

  if (!confirm(`${label.charAt(0).toUpperCase()}${label.slice(1)} le compte ${user.login} ?`))
    return

  actionLoadingId.value = user.id
  try {
    await setActive(user.id, nextActive)
    showSnackbar(nextActive
      ? `Compte ${user.login} réactivé.`
      : `Compte ${user.login} désactivé.`)
    await fetch(page.value, search.value)
  }
  catch (e) {
    showSnackbar(e instanceof Error ? e.message : 'Modification du statut impossible.', 'error')
  }
  finally {
    actionLoadingId.value = null
  }
}

let timer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; fetch(page.value, search.value) }, 350)
})

onMounted(async () => {
  await fetchRoles()
  await fetch()
})
</script>

<template>
  <div class="aice-page">
    <ExplorerHero
      icon="tabler-users"
      title="Gestion des utilisateurs"
      subtitle="Comptes, rôles et accès au hub central DGTCP."
      :stats="[
        { label: 'Comptes', value: String(meta?.total ?? users.length) },
        { label: 'Actifs', value: String(activeCount) },
      ]"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-user-plus"
          @click="openCreate"
        >
          Nouvel utilisateur
        </VBtn>
      </template>
    </ExplorerHero>

    <div class="aice-sticky-toolbar mb-4">
      <VTextField
        v-model="search"
        density="compact"
        hide-details
        placeholder="Rechercher login, nom…"
        prepend-inner-icon="tabler-search"
        style="max-inline-size: 320px;"
        clearable
      />
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ error }}
    </VAlert>

    <DataPanel title="Utilisateurs">
      <VDataTable
        :headers="headers"
        :items="users.map(u => ({ ...u, nom_complet: `${u.prenom} ${u.nom}` }))"
        :loading="loading"
        density="compact"
        class="aice-admin-table"
        :items-per-page="-1"
        hide-default-footer
      >
        <template #item.actif="{ item }">
          <VChip
            size="x-small"
            :color="item.actif ? 'success' : 'error'"
            variant="tonal"
          >
            {{ item.actif ? 'Actif' : 'Inactif' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="x-small"
            @click="openEdit(item)"
          >
            <VIcon icon="tabler-pencil" />
          </VBtn>
          <VMenu location="bottom end">
            <template #activator="{ props }">
              <VBtn
                icon
                variant="text"
                size="x-small"
                v-bind="props"
                :loading="actionLoadingId === item.id"
              >
                <VIcon icon="tabler-dots-vertical" />
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                prepend-icon="tabler-key"
                :disabled="!item.email"
                @click="onResetPassword(item)"
              >
                <VListItemTitle>Réinitialiser le mot de passe</VListItemTitle>
              </VListItem>
              <VListItem
                :prepend-icon="item.actif ? 'tabler-user-off' : 'tabler-user-check'"
                :disabled="item.id === currentUserId"
                @click="onToggleActive(item)"
              >
                <VListItemTitle>{{ item.actif ? 'Désactiver le compte' : 'Réactiver le compte' }}</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem
                prepend-icon="tabler-trash"
                class="text-error"
                :disabled="item.id === currentUserId"
                @click="onDelete(item.id)"
              >
                <VListItemTitle>Supprimer</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>
      </VDataTable>
      <div
        v-if="meta && meta.last_page > 1"
        class="d-flex justify-center pa-4"
      >
        <VPagination
          v-model="page"
          :length="meta.last_page"
          density="compact"
          @update:model-value="fetch(page, search)"
        />
      </div>
    </DataPanel>

    <VDialog
      v-model="dialog"
      max-width="480"
    >
      <VCard :title="editingId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'">
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.login"
            label="Login"
            :disabled="!!editingId"
          />
          <VTextField
            v-model="form.prenom"
            label="Prénom"
          />
          <VTextField
            v-model="form.nom"
            label="Nom"
          />
          <VTextField
            v-model="form.email"
            label="Email"
            type="email"
            required
          />
          <VTextField
            v-if="!editingId"
            v-model="form.password"
            label="Mot de passe"
            type="password"
          />
          <VSelect
            v-model="form.role_id"
            :items="roles"
            item-title="nom"
            item-value="id"
            label="Rôle"
          />
          <VSwitch
            v-model="form.actif"
            label="Compte actif"
            color="primary"
          />
          <VAlert
            v-if="!editingId"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-0"
          >
            Un email de bienvenue avec les identifiants sera envoyé à l'utilisateur.
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
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      :timeout="6000"
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
