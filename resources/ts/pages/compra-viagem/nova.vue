<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CompraViagemFormData, WizardStep } from './types'

// Importar componentes dos steps
import CompraViagemStep1Rota from './components/CompraViagemStep1Rota.vue'
import CompraViagemStep2Pacote from './components/CompraViagemStep2Pacote.vue'
import CompraViagemStep3Configuracao from './components/CompraViagemStep3Configuracao.vue'
import CompraViagemStep4Pedagios from './components/CompraViagemStep4Pedagios.vue'
import CompraViagemStep5Confirmacao from './components/CompraViagemStep5Confirmacao.vue'
import CompraViagemMapaFixo from './components/CompraViagemMapaFixo.vue'

// ============================================================================
// WIZARD STEPS CONFIGURATION
// ============================================================================

const wizardSteps: WizardStep[] = [
  {
    title: 'Rota Padrão',
    subtitle: 'Selecione a rota SemParar',
    icon: 'tabler-route',
    value: 0
  },
  {
    title: 'Pacote',
    subtitle: 'Adicione entregas (opcional)',
    icon: 'tabler-package',
    value: 1
  },
  {
    title: 'Configuração',
    subtitle: 'Placa, eixos e datas',
    icon: 'tabler-settings',
    value: 2
  },
  {
    title: 'Pedágios',
    subtitle: 'Cálculo de praças',
    icon: 'tabler-road',
    value: 3
  },
  {
    title: 'Confirmação',
    subtitle: 'Revisar e comprar',
    icon: 'tabler-check',
    value: 4
  }
]

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

const currentStep = ref(0)

const formData = ref<CompraViagemFormData>({
  rotaPadrao: {
    rota: null,
    municipios: []
  },
  pacote: {
    pacote: null,
    entregas: [],
    entregas_com_gps: []
  },
  configuracao: {
    placa: '',
    eixos: 2,
    dataInicio: '',
    dataFim: '',
    itemFin1: ''
  },
  pedagios: {
    pracas: [],
    valorTotal: 0,
    nomeRotaTemporaria: '',
    rotaCadastrada: false,
    custoCalculado: false
  },
  step1Completo: false,
  step2Completo: true, // Pacote é opcional
  step3Completo: false,
  step4Completo: false
})

// Track step completion from child components
const stepCompletionStatus = ref<Record<number, boolean>>({
  0: false,
  1: true,  // Step 2 sempre válido (opcional)
  2: false,
  3: false,
  4: false
})

// ============================================================================
// COMPUTED
// ============================================================================

const canProceed = computed(() => {
  return stepCompletionStatus.value[currentStep.value] === true
})

const isLastStep = computed(() => {
  return currentStep.value === wizardSteps.length - 1
})

const currentStepData = computed(() => {
  return wizardSteps[currentStep.value]
})

// ============================================================================
// METHODS
// ============================================================================

const handleNext = () => {
  if (canProceed.value && currentStep.value < wizardSteps.length - 1) {
    currentStep.value++
  }
}

const handlePrevious = () => {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

const handleStepComplete = (stepIndex: number, isComplete: boolean) => {
  stepCompletionStatus.value[stepIndex] = isComplete
}

const handleCompraRealizada = () => {
  console.log('✅ Compra realizada com sucesso!')
  // Usuário pode clicar em "Nova Compra" ou "Ver Viagens" no Step 5
}

const voltarParaListagem = () => {
  window.location.href = '/compra-viagem'
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-y-4 mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Nova Compra de Viagem SemParar
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Compre viagens com pedágios calculados automaticamente
        </div>
      </div>

      <div class="d-flex gap-4">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          @click="voltarParaListagem"
        >
          Voltar
        </VBtn>
      </div>
    </div>

    <!-- Main Content: Split-Screen Layout -->
    <VCard>
      <VRow no-gutters>
        <!-- LEFT COLUMN: Stepper + Form (4 cols) -->
        <VCol
          cols="12"
          md="4"
          :class="$vuetify.display.smAndDown ? 'border-b' : 'border-e'"
        >
          <VCardText>
            <!-- AppStepper Vertical -->
            <AppStepper
              v-model:current-step="currentStep"
              :items="wizardSteps"
              direction="vertical"
              icon-size="22"
              class="stepper-icon-step-bg mb-6"
            />

            <VDivider class="my-6" />

            <!-- VWindow com conteúdo dos steps -->
            <VWindow
              v-model="currentStep"
              class="disable-tab-transition"
              :touch="false"
            >
              <!-- Step 1: Rota Padrão -->
              <VWindowItem :value="0">
                <CompraViagemStep1Rota
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(0, val)"
                />
              </VWindowItem>

              <!-- Step 2: Pacote (Opcional) -->
              <VWindowItem :value="1">
                <CompraViagemStep2Pacote
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(1, val)"
                />
              </VWindowItem>

              <!-- Step 3: Configuração -->
              <VWindowItem :value="2">
                <CompraViagemStep3Configuracao
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(2, val)"
                />
              </VWindowItem>

              <!-- Step 4: Pedágios -->
              <VWindowItem :value="3">
                <CompraViagemStep4Pedagios
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(3, val)"
                />
              </VWindowItem>

              <!-- Step 5: Confirmação -->
              <VWindowItem :value="4">
                <CompraViagemStep5Confirmacao
                  :form-data="formData"
                  @step-complete="(val) => handleStepComplete(4, val)"
                  @comprar="handleCompraRealizada"
                />
              </VWindowItem>
            </VWindow>

            <!-- Navigation Buttons -->
            <div class="d-flex flex-wrap gap-4 justify-space-between mt-8">
              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="currentStep === 0"
                @click="handlePrevious"
              >
                <VIcon
                  icon="tabler-arrow-left"
                  start
                  class="flip-in-rtl"
                />
                Anterior
              </VBtn>

              <VBtn
                v-if="!isLastStep"
                :disabled="!canProceed"
                @click="handleNext"
              >
                Próximo
                <VIcon
                  icon="tabler-arrow-right"
                  end
                  class="flip-in-rtl"
                />
              </VBtn>

              <div v-else>
                <!-- Botão de compra está dentro do Step 5 -->
              </div>
            </div>

            <!-- Progress Indicator -->
            <div class="mt-6">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption text-medium-emphasis">
                  Progresso
                </span>
                <span class="text-caption font-weight-medium">
                  {{ currentStep + 1 }}/{{ wizardSteps.length }}
                </span>
              </div>

              <VProgressLinear
                :model-value="((currentStep + 1) / wizardSteps.length) * 100"
                color="primary"
                height="6"
                rounded
              />
            </div>
          </VCardText>
        </VCol>

        <!-- RIGHT COLUMN: Mapa Fixo (8 cols) -->
        <VCol
          cols="12"
          md="8"
        >
          <CompraViagemMapaFixo :form-data="formData" />
        </VCol>
      </VRow>
    </VCard>
  </div>
</template>

<style scoped>
/* Ajustes para o stepper */
:deep(.stepper-icon-step-bg) {
  box-shadow: none !important;
}

/* Desabilitar transições pesadas */
.disable-tab-transition {
  transition: none !important;
}

/* Responsividade */
@media (max-width: 960px) {
  .border-e {
    border-inline-end: none !important;
  }

  .border-b {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}
</style>
