<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import loginBackground from '@images/pages/login-background.jpg'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)
const isLoading = ref(false)

const route = useRoute()
const router = useRouter()

const ability = useAbility()

const errors = ref<Record<string, string | undefined>>({
  email: undefined,
  password: undefined,
})

const generalError = ref<string | undefined>(undefined)

const refVForm = ref<VForm>()

const credentials = ref({
  email: '',
  password: '',
})

const rememberMe = ref(false)

const login = async () => {
  isLoading.value = true
  errors.value = { email: undefined, password: undefined }
  generalError.value = undefined

  try {
    const res = await $api('/auth/login', {
      method: 'POST',
      body: {
        email: credentials.value.email,
        password: credentials.value.password,
        remember: rememberMe.value,
      },
      onResponseError({ response }) {
        const data = response._data

        // Erro de validação (422) - erros por campo
        if (response.status === 422 && data?.errors) {
          errors.value = data.errors
        }
        // Rate limit (429)
        else if (response.status === 429) {
          generalError.value = data?.message || 'Muitas tentativas. Aguarde alguns segundos.'
        }
        // Credenciais inválidas (401) ou outros erros
        else if (data?.message) {
          generalError.value = data.message
        }
        else {
          generalError.value = 'Erro ao fazer login. Tente novamente.'
        }
      },
    })

    const { accessToken, userData, userAbilityRules, passwordResetRequired } = res

    // Determinar o path do cookie baseado no base path do router (importante para subdiretórios)
    const basePath = router.options.history.base || '/'

    // Se "Lembrar de mim" estiver marcado, cookies duram 30 dias, senão expiram ao fechar browser
    const cookieOptions = {
      path: basePath,
      ...(rememberMe.value ? { maxAge: 60 * 60 * 24 * 30 } : {}), // 30 dias em segundos
    }

    useCookie('userAbilityRules', cookieOptions).value = userAbilityRules
    ability.update(userAbilityRules)

    useCookie('userData', cookieOptions).value = userData
    useCookie('accessToken', cookieOptions).value = accessToken

    await nextTick(() => {
      // Se precisar trocar a senha, redirecionar para página de alteração
      if (passwordResetRequired) {
        router.replace({ name: 'alterar-senha' })
      } else if (route.query.to) {
        router.replace(String(route.query.to))
      } else {
        router.replace({ name: 'ndd-dashboard' })
      }
    })
  }
  catch (err: any) {
    // Se o erro não foi tratado pelo onResponseError
    if (!generalError.value && !errors.value.email && !errors.value.password) {
      generalError.value = 'Erro de conexão. Verifique sua internet.'
    }
    console.error(err)
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
  <div class="auth-wrapper">
    <!-- Background Image -->
    <div
      class="auth-background"
      :style="{ backgroundImage: `url(${loginBackground})` }"
    />

    <!-- Overlay -->
    <div class="auth-overlay" />

    <!-- Login Card -->
    <VCard
      class="auth-card pa-6 pa-sm-8"
      max-width="450"
    >
      <!-- Logo -->
      <div class="d-flex justify-center mb-6">
        <RouterLink
          :to="{ name: 'ndd-dashboard' }"
          class="d-flex align-center gap-x-3 text-decoration-none"
        >
          <VNodeRenderer :nodes="themeConfig.app.logo" />
          <h1 class="auth-title text-h4">
            {{ themeConfig.app.title }}
          </h1>
        </RouterLink>
      </div>

      <!-- Welcome Text -->
      <VCardText class="text-center pa-0 mb-6">
        <h4 class="text-h5 mb-2">
          Bem-vindo!
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Entre com suas credenciais para acessar o sistema
        </p>
      </VCardText>

      <!-- General Error Alert -->
      <VAlert
        v-if="generalError"
        type="error"
        variant="tonal"
        class="mb-4"
        closable
        @click:close="generalError = undefined"
      >
        {{ generalError }}
      </VAlert>

      <!-- Login Form -->
      <VCardText class="pa-0">
        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Email -->
            <VCol cols="12">
              <AppTextField
                v-model="credentials.email"
                label="E-mail"
                placeholder="usuario@email.com"
                type="email"
                autofocus
                :rules="[requiredValidator, emailValidator]"
                :error-messages="errors.email"
                :disabled="isLoading"
              />
            </VCol>

            <!-- Password -->
            <VCol cols="12">
              <AppTextField
                v-model="credentials.password"
                label="Senha"
                placeholder="Digite sua senha"
                :rules="[requiredValidator]"
                :type="isPasswordVisible ? 'text' : 'password'"
                autocomplete="current-password"
                :error-messages="errors.password"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                :disabled="isLoading"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <!-- Remember Me & Forgot Password -->
            <VCol cols="12">
              <div class="d-flex align-center flex-wrap justify-space-between">
                <VCheckbox
                  v-model="rememberMe"
                  label="Lembrar de mim"
                  :disabled="isLoading"
                />
                <RouterLink
                  class="text-primary text-sm"
                  :to="{ name: 'forgot-password' }"
                >
                  Esqueceu a senha?
                </RouterLink>
              </div>
            </VCol>

            <!-- Submit Button -->
            <VCol cols="12">
              <VBtn
                block
                type="submit"
                size="large"
                :loading="isLoading"
                :disabled="isLoading"
              >
                Entrar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Footer -->
    <div class="auth-footer">
      <span class="text-white text-body-2">
        &copy; {{ new Date().getFullYear() }} Tambasa Atacadista
      </span>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100vh;
  position: relative;
  overflow: hidden;
}

.auth-background {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  z-index: 0;
}

.auth-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1;
}

.auth-card {
  position: relative;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.3);
}

.auth-title {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}

.auth-footer {
  position: absolute;
  inset-block-end: 24px;
  inset-inline: 0;
  text-align: center;
  z-index: 2;
}
</style>
