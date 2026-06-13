<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)
const route = useRoute()
const router = useRouter()
const ability = useAbility()
const isLoading = ref(false)

const errors = ref<Record<string, string | undefined>>({
  login: undefined,
  password: undefined,
})

const refVForm = ref<VForm>()

const credentials = ref({
  login: '',
  password: '',
})

const login = async () => {
  isLoading.value = true
  errors.value = { login: undefined, password: undefined }

  try {
    const res = await $api('/v1/auth/login', {
      method: 'POST',
      body: {
        login: credentials.value.login,
        password: credentials.value.password,
      },
      onResponseError({ response }) {
        const data = response._data as { errors?: Record<string, string[]>; message?: string }
        if (data.errors?.login?.[0])
          errors.value.login = data.errors.login[0]
        else if (data.message)
          errors.value.login = data.message
        else
          errors.value.login = 'Connexion impossible.'
      },
    })

    const { accessToken, userData, userAbilityRules } = res as {
      accessToken: string
      userData: Record<string, unknown>
      userAbilityRules: unknown[]
    }

    useCookie('userAbilityRules').value = userAbilityRules as never
    ability.update(userAbilityRules as never)
    useCookie('userData').value = userData as never
    useCookie('accessToken').value = accessToken

    await nextTick(() => {
      if (userData.premiereConnexion)
        router.replace({ name: 'auth-first-login' })
      else
        router.replace(route.query.to ? String(route.query.to) : '/')
    })
  }
  catch {
    // onResponseError handles messages
  }
  finally {
    isLoading.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate()
    .then(({ valid: isValid }) => {
      if (isValid)
        login()
    })
}
</script>

<template>
  <div class="aice-login">
    <div class="aice-login__panel">
      <div class="aice-login__brand">
        <span class="aice-login__mark">DGTCP</span>
        <p class="aice-login__org">
          Direction Générale du Trésor et de la Comptabilité Publique
        </p>
      </div>

      <VCard
        flat
        class="aice-login__card"
      >
        <VCardText class="pb-2">
          <h1 class="aice-login__title">
            Connexion
          </h1>
          <p class="aice-login__subtitle">
            Accès au tableau de bord décisionnel
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
                  v-model="credentials.login"
                  label="Identifiant"
                  placeholder="Votre login"
                  autofocus
                  :rules="[requiredValidator]"
                  :error-messages="errors.login"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="credentials.password"
                  label="Mot de passe"
                  placeholder="············"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                >
                  Se connecter
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>

      <p class="aice-login__footer">
        {{ themeConfig.app.title }} — usage interne
      </p>
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

.aice-login__mark {
  color: rgb(var(--v-theme-primary));
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: 0.06em;
}

.aice-login__org {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  line-height: 1.5;
  margin-block: 0.5rem 0;
  margin-inline: auto;
  max-inline-size: 320px;
}

.aice-login__card {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1));
}

.aice-login__title {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 1.25rem;
  font-weight: 600;
  margin-block-end: 0.25rem;
}

.aice-login__subtitle {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
  margin: 0;
}

.aice-login__footer {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  font-size: 0.75rem;
  margin-block: 1.5rem 0;
  text-align: center;
}
</style>
