<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import loginBackground from '@images/pages/login-background.jpg'
import { $api } from '@/utils/api'
import { API_ENDPOINTS } from '@/config/api'

// IMPORTANTE: Página pública - não requer autenticação
definePage({
  meta: {
    layout: 'blank',
    public: true,
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const router = useRouter()

// State
const loading = ref(true)
const verifying = ref(true)
const submitting = ref(false)
const tokenValid = ref(false)
const tokenExpired = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const user = ref({
  name: '',
  email: '',
})

const form = ref({
  password: '',
  password_confirmation: '',
})

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)

// CASL Ability - inicializar no nível superior (como no login.vue)
const ability = useAbility()

// Validation
const passwordRules = [
  (v: string) => !!v || 'Senha é obrigatória',
  (v: string) => v.length >= 8 || 'A senha deve ter no mínimo 8 caracteres',
  (v: string) => /[a-z]/.test(v) || 'A senha deve conter pelo menos uma letra minúscula',
  (v: string) => /[A-Z]/.test(v) || 'A senha deve conter pelo menos uma letra maiúscula',
  (v: string) => /[0-9]/.test(v) || 'A senha deve conter pelo menos um número',
  (v: string) => /[@$!%*#?&]/.test(v) || 'A senha deve conter pelo menos um caractere especial (@$!%*#?&)',
]

const confirmPasswordRules = [
  (v: string) => !!v || 'Confirme sua senha',
  (v: string) => v === form.value.password || 'As senhas não correspondem',
]

// Password strength
const passwordStrength = computed(() => {
  const password = form.value.password
  if (!password) return { score: 0, label: '', color: 'grey' }

  let score = 0
  if (password.length >= 8) score++
  if (password.length >= 12) score++
  if (/[a-z]/.test(password)) score++
  if (/[A-Z]/.test(password)) score++
  if (/[0-9]/.test(password)) score++
  if (/[@$!%*#?&]/.test(password)) score++

  if (score <= 2) return { score: 25, label: 'Fraca', color: 'error' }
  if (score <= 4) return { score: 50, label: 'Média', color: 'warning' }
  if (score <= 5) return { score: 75, label: 'Boa', color: 'info' }
  return { score: 100, label: 'Forte', color: 'success' }
})

// Verify token on mount
onMounted(async () => {
  const token = route.params.token as string

  if (!token || token.length !== 64) {
    errorMessage.value = 'Token inválido.'
    loading.value = false
    verifying.value = false
    return
  }

  try {
    const data = await $api(API_ENDPOINTS.authVerifySetupToken, {
      method: 'POST',
      body: { token },
    })

    if (data.valid) {
      tokenValid.value = true
      user.value = data.user
    } else {
      errorMessage.value = data.message || 'Token inválido ou não encontrado.'
    }
  } catch (error: any) {
    console.error('Erro ao verificar token:', error)

    // Verificar se é erro 410 (token expirado)
    if (error?.response?.status === 410 || error?.status === 410) {
      tokenExpired.value = true
      errorMessage.value = 'Este link expirou. Solicite um novo link ao administrador.'
    } else {
      errorMessage.value = error?.data?.message || 'Erro ao verificar link. Tente novamente mais tarde.'
    }
  } finally {
    loading.value = false
    verifying.value = false
  }
})

// Submit form
async function handleSubmit() {
  // Validate form
  const passwordErrors = passwordRules.map(rule => rule(form.value.password)).filter(r => r !== true)
  const confirmErrors = confirmPasswordRules.map(rule => rule(form.value.password_confirmation)).filter(r => r !== true)

  if (passwordErrors.length > 0 || confirmErrors.length > 0) {
    return
  }

  submitting.value = true
  errorMessage.value = ''

  try {
    const token = route.params.token as string
    const data = await $api(API_ENDPOINTS.authSetupPassword, {
      method: 'POST',
      body: {
        token,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
      },
    })

    // Save auth data usando cookies (consistente com login.vue)
    // Determinar o path do cookie baseado no base path do router (importante para subdiretórios)
    const basePath = router.options.history.base || '/'
    const cookieOptions = { path: basePath }

    // Salvar cookies de autenticação
    useCookie('userAbilityRules', cookieOptions).value = data.userAbilityRules
    useCookie('userData', cookieOptions).value = data.userData
    useCookie('accessToken', cookieOptions).value = data.accessToken

    // Atualizar ability (CASL) ANTES de navegar
    ability.update(data.userAbilityRules)

    successMessage.value = 'Senha configurada com sucesso! Redirecionando...'

    // Aguardar o próximo tick para garantir que os cookies estejam disponíveis
    await nextTick()

    // Redirect to dashboard usando replace (evita voltar para esta página)
    setTimeout(() => {
      router.replace({ name: 'ndd-dashboard' })
    }, 1500)
  } catch (error: any) {
    console.error('Erro ao configurar senha:', error)
    errorMessage.value = error?.data?.message || 'Erro ao configurar senha. Tente novamente.'
  } finally {
    submitting.value = false
  }
}

function goToLogin() {
  router.push({ name: 'login' })
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

    <!-- Card -->
    <VCard
      class="auth-card pa-6 pa-sm-8"
      max-width="480"
    >
      <!-- Logo -->
      <div class="d-flex justify-center mb-6">
        <RouterLink
          :to="{ name: 'login' }"
          class="d-flex align-center gap-x-3 text-decoration-none"
        >
          <VNodeRenderer :nodes="themeConfig.app.logo" />
          <h1 class="auth-title text-h4">
            {{ themeConfig.app.title }}
          </h1>
        </RouterLink>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-8">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <p class="text-body-1 mt-4">
          Verificando link...
        </p>
      </div>

      <!-- Token Invalid / Expired -->
      <div v-else-if="!tokenValid" class="text-center">
        <VAvatar
          :color="tokenExpired ? 'warning' : 'error'"
          variant="tonal"
          size="80"
          class="mb-4"
        >
          <VIcon
            :icon="tokenExpired ? 'tabler-clock-x' : 'tabler-link-off'"
            size="40"
          />
        </VAvatar>

        <h4 class="text-h4 mb-2">
          {{ tokenExpired ? 'Link Expirado' : 'Link Inválido' }}
        </h4>
        <p class="text-body-1 text-medium-emphasis mb-6">
          {{ errorMessage }}
        </p>

        <VBtn
          color="primary"
          @click="goToLogin"
        >
          <VIcon icon="tabler-arrow-left" class="me-2" />
          Ir para Login
        </VBtn>
      </div>

      <!-- Success -->
      <div v-else-if="successMessage" class="text-center">
        <VAvatar
          color="success"
          variant="tonal"
          size="80"
          class="mb-4"
        >
          <VIcon icon="tabler-check" size="40" />
        </VAvatar>

        <h4 class="text-h4 mb-2">
          Senha Configurada!
        </h4>
        <p class="text-body-1 text-medium-emphasis">
          {{ successMessage }}
        </p>

        <VProgressCircular
          indeterminate
          color="primary"
          size="32"
          class="mt-4"
        />
      </div>

      <!-- Setup Form -->
      <div v-else>
        <VCardText class="text-center pa-0 mb-6">
          <h4 class="text-h5 mb-2">
            Bem-vindo(a), {{ user.name }}!
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Configure sua senha para acessar o sistema
          </p>
        </VCardText>

        <VAlert
          type="info"
          variant="tonal"
          class="mb-6"
        >
          <template #prepend>
            <VIcon icon="tabler-info-circle" />
          </template>
          <strong>Requisitos da senha:</strong>
          <ul class="mt-1 mb-0 pl-4">
            <li>Mínimo 8 caracteres</li>
            <li>1 letra minúscula</li>
            <li>1 letra maiúscula</li>
            <li>1 número</li>
            <li>1 caractere especial (@$!%*#?&)</li>
          </ul>
        </VAlert>

        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="errorMessage = ''"
        >
          {{ errorMessage }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.password"
                label="Nova Senha"
                placeholder="Digite sua nova senha"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                :rules="passwordRules"
                :disabled="submitting"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
              <div v-if="form.password" class="mt-2">
                <VProgressLinear
                  :model-value="passwordStrength.score"
                  :color="passwordStrength.color"
                  height="4"
                  rounded
                />
                <span class="text-caption" :class="`text-${passwordStrength.color}`">
                  Força: {{ passwordStrength.label }}
                </span>
              </div>
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.password_confirmation"
                label="Confirmar Senha"
                placeholder="Digite novamente sua senha"
                :type="isConfirmPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                :rules="confirmPasswordRules"
                :disabled="submitting"
                @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                block
                type="submit"
                size="large"
                :loading="submitting"
                :disabled="submitting"
              >
                <VIcon icon="tabler-check" class="me-2" />
                Configurar Senha e Entrar
              </VBtn>
            </VCol>

            <VCol cols="12" class="text-center">
              <RouterLink
                class="text-primary text-sm"
                :to="{ name: 'login' }"
              >
                <VIcon icon="tabler-arrow-left" size="16" class="me-1" />
                Voltar para Login
              </RouterLink>
            </VCol>
          </VRow>
        </VForm>
      </div>
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
