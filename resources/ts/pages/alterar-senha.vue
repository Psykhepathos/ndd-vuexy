<script setup lang="ts">
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const router = useRouter()

const isLoading = ref(false)
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const errorMessage = ref('')

const form = ref({
  password: '',
  password_confirmation: '',
})

const rules = {
  password: [
    (v: string) => !!v || 'A nova senha é obrigatória',
    (v: string) => v.length >= 8 || 'A senha deve ter no mínimo 8 caracteres',
    (v: string) => /[a-z]/.test(v) || 'Deve conter pelo menos uma letra minúscula',
    (v: string) => /[A-Z]/.test(v) || 'Deve conter pelo menos uma letra maiúscula',
    (v: string) => /[0-9]/.test(v) || 'Deve conter pelo menos um número',
    (v: string) => /[@$!%*#?&]/.test(v) || 'Deve conter pelo menos um caractere especial (@$!%*#?&)',
  ],
  password_confirmation: [
    (v: string) => !!v || 'A confirmação de senha é obrigatória',
    (v: string) => v === form.value.password || 'As senhas não correspondem',
  ],
}

const changePassword = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await $api('/auth/change-password', {
      method: 'POST',
      body: {
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
        is_reset: true,
      },
    })

    if (response.success) {
      // Redirecionar para o dashboard
      router.push({ name: 'ndd-dashboard' })
    }
  } catch (error: any) {
    console.error('Erro ao alterar senha:', error)
    errorMessage.value = error?.data?.message || 'Erro ao alterar a senha. Tente novamente mais tarde.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      class="auth-card pa-4"
      max-width="460"
    >
      <VCardItem class="justify-center">
        <VCardTitle class="text-2xl font-weight-bold">
          Alteração de Senha Obrigatória
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VAlert
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          <template #prepend>
            <VIcon icon="tabler-alert-triangle" />
          </template>
          Sua senha foi resetada pelo administrador. Por favor, crie uma nova senha para continuar.
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

        <VForm @submit.prevent="changePassword">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.password"
                label="Nova Senha"
                placeholder="Digite sua nova senha"
                :type="showPassword ? 'text' : 'password'"
                :rules="rules.password"
                :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="showPassword = !showPassword"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.password_confirmation"
                label="Confirmar Nova Senha"
                placeholder="Confirme sua nova senha"
                :type="showConfirmPassword ? 'text' : 'password'"
                :rules="rules.password_confirmation"
                :append-inner-icon="showConfirmPassword ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="showConfirmPassword = !showConfirmPassword"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                block
                type="submit"
                :loading="isLoading"
                :disabled="isLoading"
              >
                Alterar Senha
              </VBtn>
            </VCol>
          </VRow>
        </VForm>

        <VDivider class="my-4" />

        <div class="text-body-2 text-medium-emphasis">
          <p class="mb-2">A senha deve conter:</p>
          <ul class="ps-4">
            <li>No mínimo 8 caracteres</li>
            <li>Pelo menos uma letra minúscula</li>
            <li>Pelo menos uma letra maiúscula</li>
            <li>Pelo menos um número</li>
            <li>Pelo menos um caractere especial (@$!%*#?&)</li>
          </ul>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
.auth-wrapper {
  min-block-size: 100vh;
  background: rgb(var(--v-theme-surface));
}

.auth-card {
  max-inline-size: 460px;
  inline-size: 100%;
}
</style>
