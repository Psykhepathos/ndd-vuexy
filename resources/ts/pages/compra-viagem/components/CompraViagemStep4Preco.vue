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

    <!-- Success State -->
    <div v-else-if="props.formData.preco.calculado">
      <!-- Card de Valor em Destaque -->
      <VCard variant="tonal" color="success" class="pa-6 text-center mb-6">
        <div class="text-body-2 text-medium-emphasis mb-2">
          Valor Total da Viagem
        </div>
        <h1 class="text-h1 font-weight-bold text-success">
          R$ {{ props.formData.preco.valor.toFixed(2) }}
        </h1>
      </VCard>

      <!-- Detalhes da Rota -->
      <VCard>
        <VCardItem>
          <VCardTitle>Detalhes da Viagem</VCardTitle>

          <template #append>
            <VBtn
              icon="tabler-refresh"
              size="small"
              variant="text"
              @click="recalcular"
            />
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <VList density="compact">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-route"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Rota SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                {{ props.formData.preco.nomeRotaSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-barcode"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Código da Rota
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                {{ props.formData.preco.codRotaSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem v-if="props.formData.preco.numeroViagem">
              <template #prepend>
                <VIcon
                  icon="tabler-receipt"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Número da Viagem
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                {{ props.formData.preco.numeroViagem }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-package"
                  class="me-2"
                  color="success"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Pacote
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                #{{ props.formData.pacote.pacote?.codpac }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-car"
                  class="me-2"
                  color="info"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Placa
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                {{ props.formData.placa.placa }} • {{ props.formData.placa.eixos }} eixos
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-calendar"
                  class="me-2"
                  color="warning"
                />
              </template>
              <VListItemTitle class="text-caption text-medium-emphasis">
                Período
              </VListItemTitle>
              <VListItemSubtitle class="text-body-2 mt-1">
                {{ new Date(props.formData.configuracao.dataInicio).toLocaleDateString('pt-BR') }}
                até
                {{ new Date(props.formData.configuracao.dataFim).toLocaleDateString('pt-BR') }}
              </VListItemSubtitle>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>

      <!-- Praças de Pedágio (se houver) -->
      <VCard v-if="props.formData.preco.pracas && props.formData.preco.pracas.length > 0" class="mt-4">
        <VCardItem>
          <VCardTitle>Praças de Pedágio</VCardTitle>
          <template #append>
            <VChip size="small" color="warning">
              {{ props.formData.preco.pracas.length }}
            </VChip>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <div class="d-flex flex-column gap-2">
            <div
              v-for="(praca, index) in props.formData.preco.pracas"
              :key="index"
              class="d-flex justify-space-between align-center pa-2 bg-surface-variant rounded"
            >
              <div class="d-flex align-center gap-2">
                <VChip size="x-small" color="warning">{{ index + 1 }}</VChip>
                <span class="text-body-2">{{ praca.nome }}</span>
              </div>
              <span class="text-body-2 font-weight-medium">R$ {{ praca.valor?.toFixed(2) || '0.00' }}</span>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>

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
