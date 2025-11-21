<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
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
const loadingPreco = ref(false)
const error = ref<string | null>(null)
const etapaAtual = ref<'inicial' | 'calculando' | 'concluido'>('inicial')

// Computed
const isStepValid = computed(() => {
  return props.formData.preco.calculado && props.formData.preco.valor > 0
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const verificarPreco = async () => {
  if (!props.formData.pacote.pacote ||
      !props.formData.rota.rota ||
      !props.formData.placa.placa ||
      !props.formData.configuracao.dataInicio ||
      !props.formData.configuracao.dataFim) {
    error.value = 'Dados incompletos para calcular preço'
    return
  }

  loadingPreco.value = true
  etapaAtual.value = 'calculando'
  error.value = null

  try {
    console.log('💰 Calculando preço da viagem...')

    const response = await fetch('http://localhost:8002/api/compra-viagem/verificar-preco', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codpac: props.formData.pacote.pacote.codpac,
        cod_rota: props.formData.rota.rota.sPararRotID,
        qtd_eixos: props.formData.placa.eixos,
        placa: props.formData.placa.placa,
        data_inicio: props.formData.configuracao.dataInicio,
        data_fim: props.formData.configuracao.dataFim
      })
    })

    const data = await response.json()

    if (!data.success) {
      throw new Error(data.message || data.error || 'Erro ao calcular preço')
    }

    console.log(`✅ Preço calculado: R$ ${data.data.valor}`)

    // Atualizar formData
    const updated: CompraViagemFormData = {
      ...props.formData,
      preco: {
        valor: data.data.valor || 0,
        numeroViagem: data.data.numero_viagem || '',
        nomeRotaSemParar: data.data.nome_rota || '',
        codRotaSemParar: data.data.cod_rota || '',
        pracas: data.data.pracas || [],
        calculado: true
      },
      step4Completo: true
    }

    emit('update:formData', updated)
    etapaAtual.value = 'concluido'

  } catch (err: any) {
    console.error('❌ Erro ao calcular preço:', err)
    error.value = err.message || 'Erro desconhecido ao calcular preço'
    etapaAtual.value = 'inicial'
  } finally {
    loadingPreco.value = false
  }
}

const recalcular = () => {
  const updated: CompraViagemFormData = {
    ...props.formData,
    preco: {
      valor: 0,
      numeroViagem: '',
      nomeRotaSemParar: '',
      codRotaSemParar: '',
      pracas: [],
      calculado: false
    },
    step4Completo: false
  }

  emit('update:formData', updated)
  etapaAtual.value = 'inicial'
  verificarPreco()
}

// Lifecycle - Calcular automaticamente ao entrar no step
onMounted(() => {
  if (!props.formData.preco.calculado && props.formData.step3Completo) {
    verificarPreco()
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Cálculo do Preço
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Aguarde enquanto calculamos o valor da viagem
    </p>

    <!-- Loading State -->
    <div v-if="loadingPreco">
      <VCard variant="tonal" color="primary">
        <VCardText>
          <div class="d-flex flex-column align-center gap-4 py-8">
            <VProgressCircular
              :size="64"
              :width="6"
              color="primary"
              indeterminate
            />

            <div class="text-center">
              <div class="text-h6 mb-2">
                Calculando preço da viagem...
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Este processo pode levar alguns segundos
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Error State -->
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      <template #prepend>
        <VIcon icon="tabler-alert-circle" />
      </template>

      <VAlertTitle>Erro no Cálculo</VAlertTitle>
      <div class="text-caption">{{ error }}</div>

      <template #append>
        <VBtn
          size="small"
          variant="tonal"
          @click="recalcular"
        >
          Tentar Novamente
        </VBtn>
      </template>
    </VAlert>

    <!-- Success State - Layout Compacto -->
    <VCard v-else-if="props.formData.preco.calculado" class="mt-4">
      <VCardText class="pa-3">
        <VRow dense>
          <!-- Coluna Esquerda: Preço -->
          <VCol cols="12" md="5">
            <div class="d-flex flex-column align-center justify-center h-100 pa-2">
              <div class="text-caption text-medium-emphasis mb-1">
                Valor Total
              </div>
              <div class="text-h4 text-success font-weight-bold mb-2">
                R$ {{ props.formData.preco.valor.toFixed(2) }}
              </div>
              <VChip
                v-if="props.formData.preco.pracas?.length > 0"
                size="small"
                color="warning"
                prepend-icon="tabler-road"
              >
                {{ props.formData.preco.pracas.length }} pedágios
              </VChip>
              <VBtn
                icon="tabler-refresh"
                size="x-small"
                variant="text"
                class="mt-2"
                @click="recalcular"
              />
            </div>
          </VCol>

          <VDivider vertical />

          <!-- Coluna Direita: Detalhes -->
          <VCol cols="12" md="7">
            <VList density="compact" class="py-0">
              <VListItem class="px-2" min-height="32">
                <template #prepend>
                  <VIcon icon="tabler-route" size="small" color="primary" />
                </template>
                <VListItemTitle class="text-caption font-weight-medium">
                  Rota SemParar
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ props.formData.preco.nomeRotaSemParar }}
                </VListItemSubtitle>
              </VListItem>

              <VListItem class="px-2" min-height="32">
                <template #prepend>
                  <VIcon icon="tabler-package" size="small" color="success" />
                </template>
                <VListItemTitle class="text-caption font-weight-medium">
                  Pacote #{{ props.formData.pacote.pacote?.codpac }}
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ props.formData.pacote.entregas.length }} entregas
                </VListItemSubtitle>
              </VListItem>

              <VListItem class="px-2" min-height="32">
                <template #prepend>
                  <VIcon icon="tabler-car" size="small" color="info" />
                </template>
                <VListItemTitle class="text-caption font-weight-medium">
                  {{ props.formData.placa.placa }}
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ props.formData.placa.eixos }} eixos
                </VListItemSubtitle>
              </VListItem>

              <VListItem class="px-2" min-height="32">
                <template #prepend>
                  <VIcon icon="tabler-calendar" size="small" color="warning" />
                </template>
                <VListItemTitle class="text-caption font-weight-medium">
                  Período
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ new Date(props.formData.configuracao.dataInicio).toLocaleDateString('pt-BR') }} -
                  {{ new Date(props.formData.configuracao.dataFim).toLocaleDateString('pt-BR') }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Praças de Pedágio - Expandible -->
    <VExpansionPanels
      v-if="props.formData.preco.calculado && props.formData.preco.pracas && props.formData.preco.pracas.length > 0"
      class="mt-3"
    >
      <VExpansionPanel>
        <VExpansionPanelTitle class="text-body-2 py-2">
          <VIcon icon="tabler-road" color="warning" size="small" class="me-2" />
          Ver Detalhes dos Pedágios ({{ props.formData.preco.pracas.length }})
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <VList density="compact" class="py-0">
            <VListItem
              v-for="(praca, index) in props.formData.preco.pracas"
              :key="index"
              class="px-2"
              min-height="28"
            >
              <template #prepend>
                <VChip size="x-small" color="warning" class="me-2">{{ index + 1 }}</VChip>
              </template>
              <VListItemTitle class="text-caption">
                {{ praca.praca || 'Praça não identificada' }}
              </VListItemTitle>
              <VListItemSubtitle class="text-caption">
                {{ praca.rodovia }} - KM {{ praca.km }} ({{ praca.concessionaria }})
              </VListItemSubtitle>
            </VListItem>
          </VList>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>

    <!-- Initial State -->
    <VAlert
      v-else
      type="info"
      variant="tonal"
    >
      <template #prepend>
        <VIcon icon="tabler-info-circle" />
      </template>

      <VAlertTitle>Pronto para Calcular</VAlertTitle>
      <div class="text-caption mb-4">
        Clique em "Próximo" ou aguarde o cálculo automático
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-calculator"
        @click="verificarPreco"
      >
        Calcular Agora
      </VBtn>
    </VAlert>
  </div>
</template>
