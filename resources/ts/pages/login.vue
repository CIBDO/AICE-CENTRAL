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
        <img
          src="/images/dgtcp-logo.png"
          alt="DGTCP"
          class="aice-login__logo"
        >
        <p class="aice-login__eyebrow">
          République du Mali
        </p>
        <p class="aice-login__org">
          Direction Générale du Trésor et de la Comptabilité Publique
        </p>
      </div>

      <div class="aice-login__case">
        <div
          class="aice-login__roof"
          aria-hidden="true"
        >
          <svg
            class="aice-login__roof-svg"
            viewBox="0 0 360 40"
            xmlns="http://www.w3.org/2000/svg"
            preserveAspectRatio="none"
          >
            <defs>
              <linearGradient
                id="aice-login-roof"
                x1="0%"
                y1="0%"
                x2="0%"
                y2="100%"
              >
                <stop
                  offset="0%"
                  stop-color="#067A39"
                />
                <stop
                  offset="100%"
                  stop-color="#08A04B"
                />
              </linearGradient>
            </defs>
            <polygon
              points="180,0 352,38 8,38"
              fill="url(#aice-login-roof)"
            />
            <line
              x1="180"
              y1="0"
              x2="180"
              y2="6"
              stroke="#E7C936"
              stroke-width="2"
              stroke-linecap="round"
            />
            <rect
              x="0"
              y="34"
              width="28"
              height="2.5"
              rx="1"
              fill="#045E2C"
              opacity="0.45"
            />
            <rect
              x="332"
              y="34"
              width="28"
              height="2.5"
              rx="1"
              fill="#045E2C"
              opacity="0.45"
            />
            <rect
              x="42"
              y="36"
              width="18"
              height="2"
              rx="1"
              fill="#045E2C"
              opacity="0.3"
            />
            <rect
              x="300"
              y="36"
              width="18"
              height="2"
              rx="1"
              fill="#045E2C"
              opacity="0.3"
            />
          </svg>
        </div>

        <VCard
          flat
          class="aice-login__card"
        >
        <VCardText class="aice-login__card-head pa-6 pb-4">
          <h1 class="aice-login__title">
            Connexion
          </h1>
          <p class="aice-login__subtitle">
            Accès au tableau de bord décisionnel AICE
          </p>
        </VCardText>

        <VCardText class="pa-6 pt-0">
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <div class="aice-login__fields">
              <AppTextField
                v-model="credentials.login"
                label="Identifiant"
                placeholder="Votre login"
                autofocus
                :rules="[requiredValidator]"
                :error-messages="errors.login"
              />

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

              <VBtn
                block
                size="large"
                type="submit"
                :loading="isLoading"
                class="aice-login__submit"
              >
                Se connecter
              </VBtn>
            </div>
          </VForm>
        </VCardText>
        </VCard>
      </div>

      <p class="aice-login__footer">
        {{ themeConfig.app.title }} — usage interne
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.aice-login {
  align-items: center;
  background: #f5f7fa;
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
  margin-block-end: 1.75rem;
  text-align: center;
}

.aice-login__logo {
  block-size: auto;
  display: inline-block;
  margin-inline: auto;
  max-block-size: 80px;
  max-inline-size: 100%;
  object-fit: contain;
}

.aice-login__eyebrow {
  color: #6b7280;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  margin-block: 1rem 0.35rem;
  text-transform: uppercase;
}

.aice-login__org {
  color: #374151;
  font-size: 0.8125rem;
  line-height: 1.5;
  margin: 0 auto;
  max-inline-size: 320px;
}

.aice-login__case {
  filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.06));
}

.aice-login__roof {
  margin-block-end: -1px;
  margin-inline: 0.75rem;
  position: relative;
  z-index: 1;
}

.aice-login__roof-svg {
  block-size: 40px;
  display: block;
  inline-size: 100%;
}

.aice-login__card {
  background: #fff;
  border: 1px solid #dde3ea;
  border-end-end-radius: 12px;
  border-end-start-radius: 12px;
  border-start-end-radius: 4px;
  border-start-start-radius: 4px;
  box-shadow: none;
  overflow: hidden;
  position: relative;
}

.aice-login__card-head {
  border-block-end: 1px solid #eef1f6;
}

.aice-login__title {
  color: #000;
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  margin: 0 0 0.25rem;
}

.aice-login__subtitle {
  color: #374151;
  font-size: 0.875rem;
  line-height: 1.5;
  margin: 0;
}

.aice-login__fields {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.aice-login__submit {
  font-weight: 600;
  margin-block-start: 0.25rem;
  text-transform: none;
}

.aice-login__footer {
  color: #9ca3af;
  font-size: 0.75rem;
  margin-block: 1.25rem 0;
  text-align: center;
}
</style>
