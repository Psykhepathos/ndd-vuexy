<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import logo from '@images/logo.png'

const route = useRoute()
const router = useRouter()
const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api'

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
    const response = await fetch(`${API_BASE}/auth/verify-setup-token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ token }),
    })

    const data = await response.json()

    if (response.status === 410) {
      tokenExpired.value = true
      errorMessage.value = 'Este link expirou. Solicite um novo link ao administrador.'
    } else if (!response.ok || !data.valid) {
      errorMessage.value = data.message || 'Token inválido ou não encontrado.'
    } else {
      tokenValid.value = true
      user.value = data.user
    }
  } catch (error) {
    console.error('Erro ao verificar token:', error)
    errorMessage.value = 'Erro ao verificar link. Tente novamente mais tarde.'
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
    const response = await fetch(`${API_BASE}/auth/setup-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        token,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
      }),
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || 'Erro ao configurar senha.')
    }

    // Save auth data
    localStorage.setItem('accessToken', data.accessToken)
    localStorage.setItem('userData', JSON.stringify(data.userData))
    localStorage.setItem('userAbilityRules', JSON.stringify(data.userAbilityRules))

    successMessage.value = 'Senha configurada com sucesso! Redirecionando...'

    // Redirect to dashboard
    setTimeout(() => {
      router.push({ name: 'ndd-dashboard' })
    }, 2000)
  } catch (error: any) {
    console.error('Erro ao configurar senha:', error)
    errorMessage.value = error.message || 'Erro ao configurar senha. Tente novamente.'
  } finally {
    submitting.value = false
  }
}

function goToLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      class="auth-card pa-4 pt-7"
      max-width="448"
    >
      <VCardItem class="justify-center">
        <template #prepend>
          <div class="d-flex">
            <img
              :src="logo"
              alt="Logo NDD"
              height="50"
            >
          </div>
        </template>
      </VCardItem>

      <VCardText class="pt-2">
        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">Verificando link...</p>
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

          <h4 class="text-h4 mb-2">Senha Configurada!</h4>
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
          <h4 class="text-h4 mb-1 text-center">
            Bem-vindo(a), {{ user.name }}!
          </h4>
          <p class="text-body-1 text-center mb-6">
            Configure sua senha para acessar o sistema
          </p>

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

          <VForm @submit.prevent="handleSubmit">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Nova Senha"
                  placeholder="Digite sua nova senha"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :rules="passwordRules"
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
                <VTextField
                  v-model="form.password_confirmation"
                  label="Confirmar Senha"
                  placeholder="Digite novamente sua senha"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :rules="confirmPasswordRules"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>

              <VCol cols="12">
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

                <VBtn
                  block
                  color="primary"
                  type="submit"
                  :loading="submitting"
                  :disabled="submitting"
                >
                  <VIcon icon="tabler-check" class="me-2" />
                  Configurar Senha e Entrar
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
