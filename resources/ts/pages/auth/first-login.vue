<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'

definePage({
  meta: {
    layout: 'blank',
  },
})

const router = useRouter()
const isLoading = ref(false)

const errors = ref<Record<string, string | undefined>>({
  login: undefined,
  password: undefined,
  password_confirmation: undefined,
})

const refVForm = ref<VForm>()

const form = ref({
  login: '',
  password: '',
  password_confirmation: '',
})

const userData = useCookie<{ login?: string; premiereConnexion?: boolean } | null>('userData')

onMounted(() => {
  if (!useCookie('accessToken').value) {
    router.replace('/login')
    return
  }

  if (userData.value?.login && !userData.value?.premiereConnexion) {
    router.replace('/')
    return
  }

  form.value.login = userData.value?.login ?? ''
})

const submit = async () => {
  isLoading.value = true
  errors.value = {}

  try {
    const res = await $api('/v1/auth/first-login', {
      method: 'PUT',
      body: form.value,
      onResponseError({ response }) {
        const data = response._data as { errors?: Record<string, string[]> }
        for (const [key, messages] of Object.entries(data.errors ?? {}))
          errors.value[key] = messages[0]
      },
    })

    const { userData: updatedUser } = res as { userData: Record<string, unknown> }
    userData.value = updatedUser as never

    await router.replace('/')
  }
  catch {
    // handled via onResponseError
  }
  finally {
    isLoading.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid }) => {
      if (valid)
        submit()
    })
}
</script>

<template>
  <div class="aice-login">
    <div class="aice-login__panel">
      <div class="aice-login__brand">
        <img
          src="/images/dgtcp-logo.png"
          alt="DGTCP"
          class="aice-login__logo"
        >
        <p class="aice-login__org">
          Configuration du compte — première connexion
        </p>
      </div>

      <VCard
        flat
        class="aice-login__card"
      >
        <VCardText class="pb-2">
          <h1 class="aice-login__title">
            Finaliser votre accès
          </h1>
          <p class="aice-login__subtitle">
            Choisissez votre identifiant définitif et un nouveau mot de passe.
          </p>
        </VCardText>

        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.login"
                  label="Identifiant"
                  :rules="[requiredValidator]"
                  :error-messages="errors.login"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Nouveau mot de passe"
                  type="password"
                  autocomplete="new-password"
                  :rules="[requiredValidator, passwordValidator]"
                  :error-messages="errors.password"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="form.password_confirmation"
                  label="Confirmer le mot de passe"
                  type="password"
                  autocomplete="new-password"
                  :rules="[requiredValidator, (v: string) => confirmedValidator(v, form.password)]"
                  :error-messages="errors.password_confirmation"
                />
              </VCol>

              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                >
                  Enregistrer et continuer
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style scoped lang="scss">
.aice-login {
  align-items: center;
  background: rgb(var(--v-theme-background));
  display: flex;
  justify-content: center;
  min-block-size: 100dvh;
  padding: 1.5rem;
}

.aice-login__panel {
  inline-size: 100%;
  max-inline-size: 420px;
}

.aice-login__brand {
  margin-block-end: 2rem;
  text-align: center;
}

.aice-login__logo {
  block-size: auto;
  display: inline-block;
  margin-inline: auto;
  max-block-size: 88px;
  max-inline-size: 100%;
  object-fit: contain;
}

.aice-login__org {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  margin-block: 0.5rem 0;
}

.aice-login__card {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
}

.aice-login__title {
  font-size: 1.25rem;
  font-weight: 600;
}

.aice-login__subtitle {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
  margin: 0;
}
</style>
