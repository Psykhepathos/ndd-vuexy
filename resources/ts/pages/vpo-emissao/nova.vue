<script setup lang="ts">
// @ts-nocheck - $vuetify type not available in this context
import { ref, computed, watch } from 'vue'
import type { VpoEmissaoFormData, WizardStep } from './types'
import { isEmpresa } from './types'

// Importar componentes dos steps
import VpoStep1Pacote from './components/VpoStep1Pacote.vue'
import VpoStep2Motorista from './components/VpoStep2Motorista.vue'
import VpoStep3Veiculo from './components/VpoStep3Veiculo.vue'
import VpoStep4Rota from './components/VpoStep4Rota.vue'
import VpoStep5Confirmacao from './components/VpoStep5Confirmacao.vue'

// Importar componente do mapa
import VpoMapaRota from './components/VpoMapaRota.vue'

// ============================================================================
// WIZARD STEPS CONFIGURATION
// ============================================================================

const getWizardSteps = (showMotoristaStep: boolean): WizardStep[] => {
  const steps: WizardStep[] = [
    {
      title: 'Pacote',
      subtitle: 'Selecione o pacote',
      icon: 'tabler-package',
      value: 0,
    },
  ]

  // Step 2: Motorista (CONDICIONAL - apenas para empresas)
  if (showMotoristaStep) {
    steps.push({
      title: 'Motorista',
      subtitle: 'Selecione o motorista',
      icon: 'tabler-user',
      value: 1,
      conditional: true,
    })
  }

  // Steps restantes
  steps.push(
    {
      title: 'Veículo',
      subtitle: 'Valide a placa',
      icon: 'tabler-car',
      value: showMotoristaStep ? 2 : 1,
    },
    {
      title: 'Rota',
      subtitle: 'Escolha a rota',
      icon: 'tabler-route',
      value: showMotoristaStep ? 3 : 2,
    },
    {
      title: 'Confirmação',
      subtitle: 'Revisar e emitir',
      icon: 'tabler-check',
      value: showMotoristaStep ? 4 : 3,
    }
  )

  return steps
}

// ============================================================================
// HELPERS
// ============================================================================

const getDataHoje = () => {
  const hoje = new Date()
  return hoje.toISOString().split('T')[0]
}

const getDataFutura = (dias: number) => {
  const futuro = new Date()
  futuro.setDate(futuro.getDate() + dias)
  return futuro.toISOString().split('T')[0]
}

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

const currentStep = ref(0)
const showMotoristaStep = ref(false)

const formData = ref<VpoEmissaoFormData>({
  pacote: {
    pacote: null,
    transportador: null,
  },
  motorista: {
    isEmpresa: false,
    requerSelecaoMotorista: false,
    motoristas: [],
    motoristaSelecionado: null,
  },
  veiculo: {
    veiculo: null,
    veiculosDisponiveis: [],
  },
  rota: {
    rota: null,
    municipios: [],
    pracas: [],
    entregas: [],
    rotaSugerida: null,
  },
  periodo: {
    dataInicio: getDataHoje(),
    dataFim: getDataFutura(7),
    eixos: 2,
  },
  custo: {
    custo: null,
    calculado: false,
    calculando: false,
  },
  uuid: null,
  status: 'idle',
  step1Completo: false,
  step2Completo: false,
  step3Completo: false,
  step4Completo: false,
  step5Completo: false,
})

// Track step completion
const stepCompletionStatus = ref<Record<number, boolean>>({
  0: false,
  1: false,
  2: false,
  3: false,
  4: false,
})

// ============================================================================
// COMPUTED
// ============================================================================

const wizardSteps = computed(() => getWizardSteps(showMotoristaStep.value))

const totalSteps = computed(() => wizardSteps.value.length)

const canProceed = computed(() => {
  return stepCompletionStatus.value[currentStep.value] === true
})

const isLastStep = computed(() => {
  return currentStep.value === totalSteps.value - 1
})

const currentStepData = computed(() => {
  return wizardSteps.value[currentStep.value]
})

const progressPercent = computed(() => {
  return ((currentStep.value + 1) / totalSteps.value) * 100
})

// ============================================================================
// WATCHERS
// ============================================================================

// Watch para atualizar showMotoristaStep baseado no transportador
watch(
  () => formData.value.pacote.transportador,
  (transportador) => {
    if (transportador) {
      const empresa = isEmpresa(transportador.cpf_cnpj)
      showMotoristaStep.value = empresa

      // Atualizar motorista data
      formData.value.motorista.isEmpresa = empresa
      formData.value.motorista.requerSelecaoMotorista = empresa

      // Se mudou de empresa para autônomo, resetar step 2
      if (!empresa) {
        formData.value.motorista.motoristaSelecionado = null
        formData.value.motorista.motoristas = []
        stepCompletionStatus.value[1] = true // Auto-complete para autônomo
      } else {
        stepCompletionStatus.value[1] = false
      }
    } else {
      showMotoristaStep.value = false
    }
  },
  { immediate: true }
)

// ============================================================================
// METHODS
// ============================================================================

const handleNext = () => {
  if (canProceed.value && currentStep.value < totalSteps.value - 1) {
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

const handleEmissaoRealizada = (result: any) => {
  console.log('VPO emitido com sucesso!', result)
  formData.value.status = 'concluido'
}

const voltarParaListagem = () => {
  window.location.href = '/vpo-emissao'
}

// Debug watcher para step atual
watch(currentStep, (step) => {
  console.log('=== STEP CHANGED ===')
  console.log('currentStep:', step)
  console.log('showMotoristaStep:', showMotoristaStep.value)
  console.log('stepComponent:', getStepComponent(step))
  console.log('canProceed:', canProceed.value)
  console.log('stepCompletionStatus:', stepCompletionStatus.value)
})

const novaEmissao = () => {
  // Reset form
  currentStep.value = 0
  showMotoristaStep.value = false

  formData.value = {
    pacote: { pacote: null, transportador: null },
    motorista: {
      isEmpresa: false,
      requerSelecaoMotorista: false,
      motoristas: [],
      motoristaSelecionado: null,
    },
    veiculo: { veiculo: null, veiculosDisponiveis: [] },
    rota: { rota: null, municipios: [], pracas: [], rotaSugerida: null },
    periodo: { dataInicio: getDataHoje(), dataFim: getDataFutura(7), eixos: 2 },
    custo: { custo: null, calculado: false, calculando: false },
    uuid: null,
    status: 'idle',
    step1Completo: false,
    step2Completo: false,
    step3Completo: false,
    step4Completo: false,
    step5Completo: false,
  }

  stepCompletionStatus.value = { 0: false, 1: false, 2: false, 3: false, 4: false }
}

// Determinar qual componente de step renderizar baseado no índice atual
const getStepComponent = (stepIndex: number) => {
  if (showMotoristaStep.value) {
    // Com step de motorista: 0=Pacote, 1=Motorista, 2=Veículo, 3=Rota, 4=Confirmação
    switch (stepIndex) {
      case 0: return 'pacote'
      case 1: return 'motorista'
      case 2: return 'veiculo'
      case 3: return 'rota'
      case 4: return 'confirmacao'
      default: return 'pacote'
    }
  } else {
    // Sem step de motorista: 0=Pacote, 1=Veículo, 2=Rota, 3=Confirmação
    switch (stepIndex) {
      case 0: return 'pacote'
      case 1: return 'veiculo'
      case 2: return 'rota'
      case 3: return 'confirmacao'
      default: return 'pacote'
    }
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-y-4 mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Nova Emissão VPO
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Vale Pedágio Obrigatório - Emissão integrada com NDD Cargo
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

    <!-- Main Content -->
    <VCard>
      <VRow no-gutters>
        <!-- LEFT COLUMN: Stepper + Form -->
        <VCol
          cols="12"
          md="5"
          lg="4"
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

            <!-- Step Content -->
            <VWindow
              v-model="currentStep"
              class="disable-tab-transition"
              :touch="false"
            >
              <!-- Step 1: Pacote -->
              <VWindowItem :value="0">
                <VpoStep1Pacote
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(0, val)"
                />
              </VWindowItem>

              <!-- Step 2: Motorista (Condicional) -->
              <VWindowItem v-if="showMotoristaStep" :value="1">
                <VpoStep2Motorista
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(1, val)"
                />
              </VWindowItem>

              <!-- Step 3/2: Veículo -->
              <VWindowItem :value="showMotoristaStep ? 2 : 1">
                <VpoStep3Veiculo
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(showMotoristaStep ? 2 : 1, val)"
                />
              </VWindowItem>

              <!-- Step 4/3: Rota -->
              <VWindowItem :value="showMotoristaStep ? 3 : 2">
                <VpoStep4Rota
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(showMotoristaStep ? 3 : 2, val)"
                />
              </VWindowItem>

              <!-- Step 5/4: Confirmação -->
              <VWindowItem :value="showMotoristaStep ? 4 : 3">
                <VpoStep5Confirmacao
                  v-model:form-data="formData"
                  @step-complete="(val) => handleStepComplete(showMotoristaStep ? 4 : 3, val)"
                  @emissao-realizada="handleEmissaoRealizada"
                  @nova-emissao="novaEmissao"
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
                <VIcon icon="tabler-arrow-left" start class="flip-in-rtl" />
                Anterior
              </VBtn>

              <VBtn
                v-if="!isLastStep"
                :disabled="!canProceed"
                @click="handleNext"
              >
                Próximo
                <VIcon icon="tabler-arrow-right" end class="flip-in-rtl" />
              </VBtn>

              <div v-else>
                <!-- Botão de emissão está dentro do Step 5 -->
              </div>
            </div>

            <!-- Progress Indicator -->
            <div class="mt-6">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption text-medium-emphasis">
                  Progresso
                </span>
                <span class="text-caption font-weight-medium">
                  {{ currentStep + 1 }}/{{ totalSteps }}
                </span>
              </div>

              <VProgressLinear
                :model-value="progressPercent"
                color="primary"
                height="6"
                rounded
              />
            </div>
          </VCardText>
        </VCol>

        <!-- RIGHT COLUMN: Map + Summary -->
        <VCol cols="12" md="7" lg="8">
          <div class="d-flex flex-column h-100">
            <!-- Summary Cards Row -->
            <div class="pa-4 pb-2 summary-row">
              <VRow dense>
                <!-- Pacote Selecionado -->
                <VCol v-if="formData.pacote.pacote" cols="12" sm="6" lg="4">
                  <VCard variant="tonal" color="primary" class="h-100">
                    <VCardItem class="py-2">
                      <template #prepend>
                        <VIcon icon="tabler-package" size="24" />
                      </template>
                      <VCardTitle class="text-body-1">
                        Pacote #{{ formData.pacote.pacote.codpac }}
                      </VCardTitle>
                      <VCardSubtitle class="text-caption">
                        {{ formData.pacote.pacote.nomtrn }}
                      </VCardSubtitle>
                    </VCardItem>
                  </VCard>
                </VCol>

                <!-- Transportador -->
                <VCol v-if="formData.pacote.transportador" cols="12" sm="6" lg="4">
                  <VCard variant="outlined" class="h-100">
                    <VCardItem class="py-2">
                      <template #prepend>
                        <VAvatar :color="formData.motorista.isEmpresa ? 'info' : 'success'" size="32" variant="tonal">
                          <VIcon :icon="formData.motorista.isEmpresa ? 'tabler-building' : 'tabler-user'" size="18" />
                        </VAvatar>
                      </template>
                      <VCardTitle class="text-body-2">
                        {{ formData.pacote.transportador.antt_nome || formData.pacote.transportador.nomtrn }}
                      </VCardTitle>
                      <VCardSubtitle class="text-caption">
                        <VChip
                          :color="formData.motorista.isEmpresa ? 'info' : 'success'"
                          size="x-small"
                          class="me-1"
                        >
                          {{ formData.motorista.isEmpresa ? 'Empresa' : 'Autônomo' }}
                        </VChip>
                        Score: {{ formData.pacote.transportador.score_qualidade }}%
                      </VCardSubtitle>
                    </VCardItem>
                  </VCard>
                </VCol>

                <!-- Veículo -->
                <VCol v-if="formData.veiculo.veiculo" cols="12" sm="6" lg="4">
                  <VCard variant="outlined" class="h-100">
                    <VCardItem class="py-2">
                      <template #prepend>
                        <VAvatar color="info" size="32" variant="tonal">
                          <VIcon icon="tabler-car" size="18" />
                        </VAvatar>
                      </template>
                      <VCardTitle class="text-body-2">
                        {{ formData.veiculo.veiculo.placa }}
                      </VCardTitle>
                      <VCardSubtitle class="text-caption">
                        {{ formData.veiculo.veiculo.modelo }} • {{ formData.veiculo.veiculo.eixos }} eixos
                      </VCardSubtitle>
                    </VCardItem>
                  </VCard>
                </VCol>

                <!-- Rota -->
                <VCol v-if="formData.rota.rota" cols="12" sm="6" lg="4">
                  <VCard variant="outlined" class="h-100">
                    <VCardItem class="py-2">
                      <template #prepend>
                        <VAvatar color="warning" size="32" variant="tonal">
                          <VIcon icon="tabler-route" size="18" />
                        </VAvatar>
                      </template>
                      <VCardTitle class="text-body-2">
                        {{ formData.rota.rota.desSPararRot }}
                      </VCardTitle>
                      <VCardSubtitle class="text-caption">
                        {{ formData.rota.municipios.length }} municípios • {{ formData.rota.rota.tempoViagem }}h
                      </VCardSubtitle>
                    </VCardItem>
                  </VCard>
                </VCol>

                <!-- Custo -->
                <VCol v-if="formData.custo.calculado && formData.custo.custo" cols="12" sm="6" lg="4">
                  <VCard variant="tonal" color="success" class="h-100">
                    <VCardItem class="py-2">
                      <template #prepend>
                        <VIcon icon="tabler-cash" size="24" color="success" />
                      </template>
                      <VCardTitle class="text-body-1 text-success">
                        R$ {{ formData.custo.custo.valor_total.toFixed(2) }}
                      </VCardTitle>
                      <VCardSubtitle class="text-caption">
                        {{ formData.custo.custo.pedagios.length }} pedágios • {{ formData.custo.custo.km_total }} km
                      </VCardSubtitle>
                    </VCardItem>
                  </VCard>
                </VCol>
              </VRow>
            </div>

            <!-- Map Component -->
            <div class="flex-grow-1 map-wrapper">
              <VpoMapaRota :form-data="formData" />
            </div>
          </div>
        </VCol>
      </VRow>
    </VCard>
  </div>
</template>

<style scoped>
:deep(.stepper-icon-step-bg) {
  box-shadow: none !important;
}

.disable-tab-transition {
  transition: none !important;
}

/* Map wrapper styles */
.map-wrapper {
  min-height: 400px;
  height: calc(100vh - 350px);
  max-height: 700px;
  padding: 0 16px 16px 16px;
}

.map-wrapper > * {
  height: 100%;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Summary row styles */
.summary-row {
  background: rgba(var(--v-theme-surface), 0.5);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 960px) {
  .border-e {
    border-inline-end: none !important;
  }

  .border-b {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }

  .map-wrapper {
    min-height: 300px;
    height: 350px;
  }
}
</style>
