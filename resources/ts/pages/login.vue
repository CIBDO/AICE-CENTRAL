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
        <p class="aice-login__org">
          Direction Générale du Trésor et de la Comptabilité Publique
        </p>
        <div
          class="aice-login__flag-rule"
          aria-hidden="true"
        >
          <span /><span /><span />
        </div>
      </div>

      <div class="aice-login__case">
        <div
          class="aice-login__roof"
          aria-hidden="true"
        >
          <svg
            class="aice-login__roof-svg"
            viewBox="0 0 400 52"
            xmlns="http://www.w3.org/2000/svg"
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
                  stop-color="#045E2C"
                />
                <stop
                  offset="45%"
                  stop-color="#067A39"
                />
                <stop
                  offset="100%"
                  stop-color="#08A04B"
                />
              </linearGradient>
              <clipPath id="aice-login-roof-clip">
                <polygon points="200,4 388,48 12,48" />
              </clipPath>
            </defs>
            <polygon
              points="200,4 388,48 12,48"
              fill="url(#aice-login-roof)"
            />
            <g
              clip-path="url(#aice-login-roof-clip)"
              opacity="0.14"
            >
              <line
                x1="0"
                y1="22"
                x2="400"
                y2="22"
                stroke="#fff"
                stroke-width="1.5"
              />
              <line
                x1="0"
                y1="30"
                x2="400"
                y2="30"
                stroke="#fff"
                stroke-width="1.5"
              />
              <line
                x1="0"
                y1="38"
                x2="400"
                y2="38"
                stroke="#fff"
                stroke-width="1.5"
              />
              <line
                x1="0"
                y1="44"
                x2="400"
                y2="44"
                stroke="#fff"
                stroke-width="1"
              />
            </g>
            <circle
              cx="200"
              cy="4"
              r="3.5"
              fill="#E7C936"
            />
            <line
              x1="200"
              y1="7"
              x2="200"
              y2="14"
              stroke="#E7C936"
              stroke-width="1.5"
              stroke-linecap="round"
            />
            <rect
              x="-4"
              y="44"
              width="36"
              height="3"
              rx="1.5"
              fill="#045E2C"
              opacity="0.55"
            />
            <rect
              x="368"
              y="44"
              width="36"
              height="3"
              rx="1.5"
              fill="#045E2C"
              opacity="0.55"
            />
            <rect
              x="28"
              y="47"
              width="22"
              height="2.5"
              rx="1"
              fill="#045E2C"
              opacity="0.35"
            />
            <rect
              x="350"
              y="47"
              width="22"
              height="2.5"
              rx="1"
              fill="#045E2C"
              opacity="0.35"
            />
            <rect
              x="56"
              y="49"
              width="14"
              height="2"
              rx="1"
              fill="#045E2C"
              opacity="0.25"
            />
            <rect
              x="330"
              y="49"
              width="14"
              height="2"
              rx="1"
              fill="#045E2C"
              opacity="0.25"
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
  background:
    radial-gradient(ellipse 90% 55% at 50% -5%, rgba(8, 160, 75, 0.07), transparent 55%),
    radial-gradient(ellipse 50% 35% at 100% 100%, rgba(231, 201, 54, 0.06), transparent),
    #f5f7fa;
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
  filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.06));
  margin-inline: auto;
  max-block-size: 88px;
  max-inline-size: 100%;
  object-fit: contain;
}

.aice-login__org {
  color: #374151;
  font-size: 0.8125rem;
  line-height: 1.55;
  margin-block: 1.125rem 0;
  margin-inline: auto;
  max-inline-size: 320px;
}

.aice-login__flag-rule {
  border-radius: 2px;
  display: flex;
  inline-size: 3rem;
  margin-block: 1rem 0;
  margin-inline: auto;
  overflow: hidden;

  span {
    block-size: 3px;
    flex: 1;

    &:nth-child(1) {
      background: #08a04b;
    }

    &:nth-child(2) {
      background: #e7c936;
    }

    &:nth-child(3) {
      background: #e53935;
    }
  }
}

.aice-login__case {
  filter: drop-shadow(0 10px 28px rgba(4, 94, 44, 0.1));
}

.aice-login__roof {
  margin-block-end: -2px;
  margin-inline: -10px;
  position: relative;
  z-index: 1;
}

.aice-login__roof-svg {
  block-size: auto;
  display: block;
  inline-size: 100%;
}

.aice-login__card {
  background: #fff;
  border: 1px solid #dde3ea;
  border-block-start: none;
  border-end-end-radius: 14px;
  border-end-start-radius: 14px;
  border-start-end-radius: 2px;
  border-start-start-radius: 2px;
  box-shadow: none;
  overflow: hidden;
  position: relative;

  &::before,
  &::after {
    background: linear-gradient(180deg, #e8ece6 0%, #f7f8f6 100%);
    block-size: 100%;
    content: "";
    inline-size: 6px;
    inset-block: 0;
    opacity: 0.65;
    pointer-events: none;
    position: absolute;
    z-index: 0;
  }

  &::before {
    inset-inline-start: 0;
  }

  &::after {
    inset-inline-end: 0;
  }

  :deep(.v-card-text) {
    position: relative;
    z-index: 1;
  }
}

.aice-login__card-head {
  background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
  border-block-end: 1px solid #eef1f6;
  position: relative;

  &::before {
    background: linear-gradient(180deg, #08a04b, #067a39);
    block-size: 100%;
    content: "";
    inline-size: 3px;
    inset-block-start: 0;
    inset-inline-start: 0;
    position: absolute;
  }
}

.aice-login__title {
  color: #000;
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  margin: 0 0 0.25rem;
  padding-inline-start: 0.5rem;
}

.aice-login__subtitle {
  color: #374151;
  font-size: 0.875rem;
  line-height: 1.5;
  margin: 0;
  padding-inline-start: 0.5rem;
}

.aice-login__fields {
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

.aice-login__submit {
  font-weight: 600;
  letter-spacing: 0.01em;
  margin-block-start: 0.5rem;
  text-transform: none;
}

.aice-login__footer {
  color: #9ca3af;
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  margin-block: 1.5rem 0;
  text-align: center;
}
</style>
