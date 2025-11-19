<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { CompraViagemFormData } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: CompraViagemFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const errors = ref<Record<string, string>>({})

// Computed
const configuracao = computed(() => props.formData.configuracao)

const isStepValid = computed(() => {
  return (
    validarPlaca(configuracao.value.placa) === '' &&
    configuracao.value.eixos >= 2 &&
    configuracao.value.eixos <= 9 &&
    configuracao.value.dataInicio !== '' &&
    configuracao.value.dataFim !== '' &&
    new Date(configuracao.value.dataFim) >= new Date(configuracao.value.dataInicio)
  )
})

const eixosDisponiveis = computed(() => {
  return Array.from({ length: 8 }, (_, i) => ({
    value: i + 2,
    title: `${i + 2} eixos`
  }))
})

const dataMinima = computed(() => {
  const hoje = new Date()
  return hoje.toISOString().split('T')[0]
})

const dataFimMinima = computed(() => {
  return configuracao.value.dataInicio || dataMinima.value
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

watch(() => configuracao.value.placa, (novaPlaca) => {
  errors.value.placa = validarPlaca(novaPlaca)
})

watch(() => configuracao.value.dataInicio, () => {
  // Se data fim for menor que início, ajustar
  if (configuracao.value.dataFim && configuracao.value.dataFim < configuracao.value.dataInicio) {
    updateField('dataFim', configuracao.value.dataInicio)
  }
})

// Methods
const updateField = (field: keyof typeof configuracao.value, value: any) => {
  const updated: CompraViagemFormData = {
    ...props.formData,
    configuracao: {
      ...configuracao.value,
      [field]: value
    },
    step3Completo: false // Será true quando validação passar
  }

  // Atualizar step3Completo baseado na validação
  const willBeValid = (
    (field === 'placa' ? validarPlaca(value) === '' : validarPlaca(updated.configuracao.placa) === '') &&
    (field === 'eixos' ? value >= 2 && value <= 9 : updated.configuracao.eixos >= 2 && updated.configuracao.eixos <= 9) &&
    (field === 'dataInicio' ? value !== '' : updated.configuracao.dataInicio !== '') &&
    (field === 'dataFim' ? value !== '' : updated.configuracao.dataFim !== '') &&
    new Date(updated.configuracao.dataFim) >= new Date(updated.configuracao.dataInicio)
  )

  updated.step3Completo = willBeValid

  emit('update:formData', updated)
}

const validarPlaca = (placa: string): string => {
  if (!placa) return 'Placa é obrigatória'

  // Remover espaços e traços
  const placaLimpa = placa.replace(/[\s-]/g, '').toUpperCase()

  // Validar tamanho (7 ou 8 caracteres para Mercosul)
  if (placaLimpa.length < 7 || placaLimpa.length > 8) {
    return 'Placa deve ter 7 ou 8 caracteres'
  }

  // Validar formato brasileiro (ABC1234 ou ABC1D23)
  const formatoBrasileiro = /^[A-Z]{3}\d{4}$/
  const formatoMercosul = /^[A-Z]{3}\d[A-Z]\d{2}$/

  if (!formatoBrasileiro.test(placaLimpa) && !formatoMercosul.test(placaLimpa)) {
    return 'Formato inválido (use ABC1234 ou ABC1D23)'
  }

  return ''
}

const formatarPlaca = (valor: string) => {
  const limpa = valor.replace(/[\s-]/g, '').toUpperCase()
  updateField('placa', limpa)
}
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Configuração da Viagem
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Informe os dados do veículo e período da viagem
    </p>

    <VRow>
      <!-- Placa do Veículo -->
      <VCol cols="12">
        <AppTextField
          :model-value="configuracao.placa"
          label="Placa do Veículo"
          placeholder="ABC1234"
          prepend-inner-icon="tabler-car"
          :error-messages="errors.placa"
          required
          @update:model-value="formatarPlaca"
          @blur="formatarPlaca(configuracao.placa)"
        >
          <template #append-inner>
            <VTooltip location="top">
              <template #activator="{ props: tooltipProps }">
                <VIcon
                  v-bind="tooltipProps"
                  icon="tabler-info-circle"
                  size="small"
                  class="text-medium-emphasis"
                />
              </template>
              Formato: ABC1234 (padrão) ou ABC1D23 (Mercosul)
            </VTooltip>
          </template>
        </AppTextField>
      </VCol>

      <!-- Número de Eixos -->
      <VCol cols="12" md="6">
        <AppSelect
          :model-value="configuracao.eixos"
          :items="eixosDisponiveis"
          label="Número de Eixos"
          prepend-inner-icon="tabler-truck"
          required
          @update:model-value="(val) => updateField('eixos', val)"
        />
      </VCol>

      <!-- Item Financeiro 1 (Opcional) -->
      <VCol cols="12" md="6">
        <AppTextField
          :model-value="configuracao.itemFin1"
          label="Item Financeiro 1"
          placeholder="PEDAGIO"
          prepend-inner-icon="tabler-receipt"
          @update:model-value="(val) => updateField('itemFin1', val)"
        >
          <template #append-inner>
            <VChip size="x-small" color="info" variant="tonal">
              Opcional
            </VChip>
          </template>
        </AppTextField>
      </VCol>

      <!-- Data Início -->
      <VCol cols="12" md="6">
        <AppDateTimePicker
          :model-value="configuracao.dataInicio"
          label="Data Início"
          placeholder="Selecione a data"
          prepend-inner-icon="tabler-calendar"
          :config="{
            dateFormat: 'Y-m-d',
            minDate: dataMinima
          }"
          required
          @update:model-value="(val) => updateField('dataInicio', val)"
        />
      </VCol>

      <!-- Data Fim -->
      <VCol cols="12" md="6">
        <AppDateTimePicker
          :model-value="configuracao.dataFim"
          label="Data Fim"
          placeholder="Selecione a data"
          prepend-inner-icon="tabler-calendar"
          :config="{
            dateFormat: 'Y-m-d',
            minDate: dataFimMinima
          }"
          required
          :disabled="!configuracao.dataInicio"
          @update:model-value="(val) => updateField('dataFim', val)"
        />
      </VCol>
    </VRow>

    <!-- Resumo -->
    <VCard
      v-if="configuracao.placa && configuracao.dataInicio"
      variant="tonal"
      color="info"
      class="mt-6"
    >
      <VCardText>
        <div class="d-flex align-center gap-4">
          <VIcon icon="tabler-info-circle" color="info" size="24" />

          <div>
            <div class="text-caption text-medium-emphasis mb-1">
              Resumo da Configuração
            </div>
            <div class="text-body-2">
              Veículo <strong>{{ configuracao.placa }}</strong> com
              <strong>{{ configuracao.eixos }} eixos</strong>
              {{ configuracao.dataInicio && configuracao.dataFim
                ? `de ${new Date(configuracao.dataInicio).toLocaleDateString('pt-BR')}
                   até ${new Date(configuracao.dataFim).toLocaleDateString('pt-BR')}`
                : ''
              }}
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Validação -->
    <VAlert
      v-if="configuracao.placa && !isStepValid"
      type="warning"
      variant="tonal"
      class="mt-4"
    >
      <template #prepend>
        <VIcon icon="tabler-alert-triangle" />
      </template>
      Preencha todos os campos obrigatórios corretamente
    </VAlert>
  </div>
</template>
