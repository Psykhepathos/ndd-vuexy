<script setup lang="ts">
// @ts-nocheck - $vuetify type not available in this context
import { ref, computed } from 'vue'
import type { CompraViagemFormData, WizardStep } from './types'

// Importar componentes dos steps
import CompraViagemStep1Pacote from './components/CompraViagemStep1Pacote.vue'
import CompraViagemStep2Placa from './components/CompraViagemStep2Placa.vue'
import CompraViagemStep3Rota from './components/CompraViagemStep3Rota.vue'
import CompraViagemStep4Preco from './components/CompraViagemStep4Preco.vue'
import CompraViagemStep5Confirmacao from './components/CompraViagemStep5Confirmacao.vue'
import CompraViagemMapaFixo from './components/CompraViagemMapaFixo.vue'

// ============================================================================
// WIZARD STEPS CONFIGURATION
// ============================================================================

const wizardSteps: WizardStep[] = [
  {
    title: 'Pacote',
    subtitle: 'Selecione o pacote',
    icon: 'tabler-package',
    value: 0
  },
  {
    title: 'Veículo',
    subtitle: 'Valide a placa',
    icon: 'tabler-car',
    value: 1
  },
  {
    title: 'Rota',
    subtitle: 'Escolha a rota SemParar',
    icon: 'tabler-route',
    value: 2
  },
  {
    title: 'Preço',
    subtitle: 'Cálculo automático',
    icon: 'tabler-cash',
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

// Helper functions
const getDataHoje = () => {
  const hoje = new Date()
  return hoje.toISOString().split('T')[0]
}

const getDataFutura = (dias: number) => {
  const futuro = new Date()
  futuro.setDate(futuro.getDate() + dias)
  return futuro.toISOString().split('T')[0]
}

const currentStep = ref(0)

const formData = ref<CompraViagemFormData>({
  pacote: {
    pacote: null,
    entregas: [],
    entregas_com_gps: []
  },
  placa: {
    placa: '',
    descricao: '',
    eixos: 2,
    proprietario: '',
    tag: ''
  },
  rota: {
    rota: null,
    municipios: [],
    modoCD: false,
    modoRetorno: false
  },
  preco: {
    valor: 0,
    numeroViagem: '',
    nomeRotaSemParar: '',
    codRotaSemParar: '',
    pracas: [],
    calculado: false
  },
  configuracao: {
    dataInicio: getDataHoje(),
    dataFim: getDataFutura(7)
  },
  step1Completo: false,
  step2Completo: false,
  step3Completo: false,
  step4Completo: false,
  step5Completo: false
})

// Track step completion from child components
const stepCompletionStatus = ref<Record<number, boolean>>({
  0: false,  // Step 1: Pacote (obrigatório)
  1: false,  // Step 2: Placa (obrigatório)
  2: false,  // Step 3: Rota (obrigatório)
  3: false,  // Step 4: Preço (auto-calculado)
  4: false   // Step 5: Confirmação
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
              <!-- Step 1: Pacote -->
              <VWindowItem :value="0">
                <CompraViagemStep1Pacote
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(0, val)"
                />
              </VWindowItem>

              <!-- Step 2: Placa -->
              <VWindowItem :value="1">
                <CompraViagemStep2Placa
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(1, val)"
                />
              </VWindowItem>

              <!-- Step 3: Rota -->
              <VWindowItem :value="2">
                <CompraViagemStep3Rota
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(2, val)"
                  @rota-validada="() => { currentStep = 3 }"
                />
              </VWindowItem>

              <!-- Step 4: Preço -->
              <VWindowItem :value="3">
                <CompraViagemStep4Preco
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
