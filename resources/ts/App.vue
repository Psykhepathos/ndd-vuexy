<script setup lang="ts">
import { onErrorCaptured, onMounted, ref } from 'vue'
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()

// ============================================================================
// ERROR BOUNDARY - Global Error Handling
// ============================================================================

const hasError = ref(false)
const errorInfo = ref<{ message: string; stack?: string; timestamp: string } | null>(null)

/**
 * Captura erros de componentes filhos Vue
 * Previne crash completo da aplicação
 */
onErrorCaptured((err, instance, info) => {
  console.error('🚨 [Error Boundary] Erro capturado em componente:', {
    error: err,
    component: instance?.$options?.name || 'Unknown',
    info,
    timestamp: new Date().toISOString()
  })

  // Armazenar informações do erro
  hasError.value = true
  errorInfo.value = {
    message: err.message || 'Erro desconhecido',
    stack: err.stack,
    timestamp: new Date().toISOString()
  }

  // Log para monitoramento (pode enviar para Sentry/LogRocket)
  // Exemplo: Sentry.captureException(err)

  // Retornar false previne propagação do erro para Vue
  // Retornar true permite que o erro continue propagando
  return false // Previne crash da aplicação
})

/**
 * Captura erros globais JavaScript (não capturados por Vue)
 * Ex: erros assíncronos, promises não tratadas
 */
onMounted(() => {
  window.onerror = (message, source, lineno, colno, error) => {
    console.error('🚨 [Global Error Handler] Erro JavaScript global:', {
      message,
      source,
      line: lineno,
      column: colno,
      error,
      timestamp: new Date().toISOString()
    })

    hasError.value = true
    errorInfo.value = {
      message: typeof message === 'string' ? message : 'Erro JavaScript global',
      stack: error?.stack,
      timestamp: new Date().toISOString()
    }

    // Previne comportamento padrão do navegador
    return true
  }

  // Captura promises rejeitadas não tratadas
  window.onunhandledrejection = (event) => {
    console.error('🚨 [Unhandled Promise Rejection]:', {
      reason: event.reason,
      promise: event.promise,
      timestamp: new Date().toISOString()
    })

    hasError.value = true
    errorInfo.value = {
      message: event.reason?.message || 'Promise rejeitada sem tratamento',
      stack: event.reason?.stack,
      timestamp: new Date().toISOString()
    }

    // Previne log no console do navegador (já logamos acima)
    event.preventDefault()
  }
})

/**
 * Função para recuperar do erro e recarregar a página
 */
const handleReload = () => {
  window.location.reload()
}
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <!-- Error Fallback UI -->
      <div v-if="hasError" class="error-boundary-fallback">
        <VContainer class="fill-height">
          <VRow align="center" justify="center">
            <VCol cols="12" md="6" class="text-center">
              <VIcon icon="tabler-alert-triangle" size="80" color="error" class="mb-4" />
              <h1 class="text-h4 mb-4">Ops! Algo deu errado</h1>
              <p class="text-body-1 mb-6 text-medium-emphasis">
                Ocorreu um erro inesperado na aplicação. Não se preocupe, seus dados estão seguros.
              </p>

              <VCard class="mb-6 text-left" variant="outlined">
                <VCardText>
                  <p class="text-caption text-medium-emphasis mb-2">Detalhes técnicos:</p>
                  <p class="text-body-2 mb-1"><strong>Mensagem:</strong> {{ errorInfo?.message }}</p>
                  <p class="text-caption text-disabled">{{ errorInfo?.timestamp }}</p>
                  <VExpansionPanels v-if="errorInfo?.stack" class="mt-4">
                    <VExpansionPanel>
                      <VExpansionPanelTitle>Stack Trace</VExpansionPanelTitle>
                      <VExpansionPanelText>
                        <pre class="text-caption">{{ errorInfo.stack }}</pre>
                      </VExpansionPanelText>
                    </VExpansionPanel>
                  </VExpansionPanels>
                </VCardText>
              </VCard>

              <VBtn
                color="primary"
                size="large"
                @click="handleReload"
              >
                <VIcon icon="tabler-reload" class="me-2" />
                Recarregar Página
              </VBtn>
            </VCol>
          </VRow>
        </VContainer>
      </div>

      <!-- Normal App Content -->
      <template v-else>
        <RouterView />
        <ScrollToTop />
      </template>
    </VApp>
  </VLocaleProvider>
</template>

<style scoped>
.error-boundary-fallback {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

pre {
  overflow-x: auto;
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
