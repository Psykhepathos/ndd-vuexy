<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { $api, getErrorMessage } from '@/utils/api'
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
const showPracasExpanded = ref(false)

// Computed
const isStepValid = computed(() => {
  return props.formData.preco.calculado && props.formData.preco.valor > 0
})

// Praças processadas para exibição
const pracasProcessadas = computed(() => {
  return (props.formData.preco.pracas || []).map((praca: any, index: number) => ({
    ...praca,
    idx: index + 1,
    nome: praca.praca || praca.nome || `Praça ${index + 1}`,
    rodoviaFormatada: praca.rodovia || '-',
    kmFormatado: praca.km ? `km ${praca.km}` : '',
    concessionaria: praca.concessionaria || praca.concessionaria_antt || '-',
    matchIncerto: praca.match_incerto || false,
    temCoordenadas: !!(praca.lat && praca.lon)
  }))
})

// Estatísticas
const stats = computed(() => {
  const pracas = props.formData.preco.pracas || []
  const total = pracas.length
  const comCoordenadas = pracas.filter((p: any) => p.lat && p.lon).length
  const incertas = pracas.filter((p: any) => p.match_incerto).length

  return { total, comCoordenadas, incertas }
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Watch para quando step3 for alterado (rota mudou)
watch(() => props.formData.step3Completo, (completo, anteriorCompleto) => {
  console.log('📊 Step4: step3Completo mudou:', { completo, anteriorCompleto })

  // Se step3 foi marcado como completo E não temos preço calculado, calcular
  if (completo && !props.formData.preco.calculado) {
    console.log('📊 Step4: Iniciando cálculo de preço automaticamente')
    verificarPreco()
  }
})

// Methods
const verificarPreco = async () => {
  // Validar dados necessários
  if (!props.formData.pacote.pacote) {
    error.value = 'Pacote não selecionado (Passo 1)'
    console.error('❌ Step4: Pacote não selecionado')
    return
  }

  if (!props.formData.rota.rota) {
    error.value = 'Rota não selecionada (Passo 3)'
    console.error('❌ Step4: Rota não selecionada')
    return
  }

  if (!props.formData.placa.placa) {
    error.value = 'Placa não informada (Passo 2)'
    console.error('❌ Step4: Placa não informada')
    return
  }

  if (!props.formData.configuracao.dataInicio || !props.formData.configuracao.dataFim) {
    error.value = 'Datas não configuradas'
    console.error('❌ Step4: Datas não configuradas')
    return
  }

  loadingPreco.value = true
  error.value = null

  const payload = {
    codpac: props.formData.pacote.pacote.codpac,
    cod_rota: props.formData.rota.rota.sPararRotID,
    qtd_eixos: props.formData.placa.eixos,
    placa: props.formData.placa.placa,
    data_inicio: props.formData.configuracao.dataInicio,
    data_fim: props.formData.configuracao.dataFim
  }

  console.log('💰 Step4: Verificando preço...', payload)

  try {
    const data = await $api('/compra-viagem/verificar-preco', {
      method: 'POST',
      body: payload
    })

    console.log('💰 Step4: Resposta verificar-preco:', data)

    if (!data.success) {
      throw new Error(data.message || data.error || 'Erro ao calcular preço')
    }

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

    console.log('✅ Step4: Preço calculado com sucesso:', {
      valor: data.data.valor,
      pracas: data.data.pracas?.length || 0
    })

    emit('update:formData', updated)

  } catch (err: any) {
    console.error('❌ Step4: Erro ao verificar preço:', err)

    // Extrair mensagem de erro do backend
    const errorData = err?.data || err?.response?._data
    if (errorData?.error) {
      error.value = errorData.error
    } else {
      error.value = getErrorMessage(err)
    }

  } finally {
    loadingPreco.value = false
  }
}

const recalcular = () => {
  console.log('🔄 Step4: Recalculando preço...')

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
  verificarPreco()
}

// Lifecycle
onMounted(() => {
  console.log('🚀 Step4 montado', {
    step3Completo: props.formData.step3Completo,
    precoCalculado: props.formData.preco.calculado,
    rota: props.formData.rota.rota?.sPararRotID
  })

  // Calcular automaticamente se step3 está completo e não tem preço
  if (props.formData.step3Completo && !props.formData.preco.calculado) {
    verificarPreco()
  }
})
</script>

<template>
  <div class="step4-container">
    <!-- Header compacto -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h6 class="text-h6 font-weight-medium">Cálculo do Preço</h6>
        <p class="text-caption text-medium-emphasis mb-0">
          Valor total dos pedágios na rota
        </p>
      </div>
      <VBtn
        v-if="props.formData.preco.calculado"
        icon
        size="small"
        variant="text"
        color="default"
        @click="recalcular"
      >
        <VIcon icon="tabler-refresh" size="18" />
        <VTooltip activator="parent" location="top">Recalcular</VTooltip>
      </VBtn>
    </div>

    <!-- Loading State -->
    <div v-if="loadingPreco" class="text-center py-8">
      <VProgressCircular
        :size="48"
        :width="4"
        color="primary"
        indeterminate
        class="mb-4"
      />
      <div class="text-body-2 text-medium-emphasis">
        Calculando preço...
      </div>
    </div>

    <!-- Error State -->
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      <div class="d-flex flex-column gap-2">
        <span class="text-body-2">{{ error }}</span>
        <VBtn size="small" variant="tonal" color="error" @click="recalcular">
          <VIcon icon="tabler-refresh" size="16" class="me-1" />
          Tentar novamente
        </VBtn>
      </div>
    </VAlert>

    <!-- Success State -->
    <template v-else-if="props.formData.preco.calculado">
      <!-- Card de Valor Principal -->
      <VCard
        color="success"
        variant="flat"
        class="mb-4"
      >
        <VCardText class="pa-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="white" size="48">
              <VIcon icon="tabler-coin" color="success" size="24" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="text-h4 font-weight-bold text-white">
                R$ {{ props.formData.preco.valor.toFixed(2) }}
              </div>
              <div class="text-caption text-white-50">
                {{ stats.total }} praça{{ stats.total !== 1 ? 's' : '' }} de pedágio
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Resumo Compacto em Grid -->
      <div class="summary-grid mb-4">
        <div class="summary-item">
          <VIcon icon="tabler-route" size="16" color="primary" />
          <span class="text-caption text-truncate">
            {{ props.formData.rota.rota?.desSPararRot || 'Rota' }}
          </span>
        </div>
        <div class="summary-item">
          <VIcon icon="tabler-car" size="16" color="info" />
          <span class="text-caption">
            {{ props.formData.placa.placa }} ({{ props.formData.placa.eixos }}e)
          </span>
        </div>
        <div class="summary-item">
          <VIcon icon="tabler-calendar" size="16" color="warning" />
          <span class="text-caption">
            {{ new Date(props.formData.configuracao.dataInicio).toLocaleDateString('pt-BR') }}
          </span>
        </div>
        <div class="summary-item">
          <VIcon icon="tabler-map-pin" size="16" color="success" />
          <span class="text-caption">
            {{ stats.comCoordenadas }}/{{ stats.total }} no mapa
          </span>
        </div>
      </div>

      <!-- Lista de Praças Expansível -->
      <VCard v-if="stats.total > 0" variant="outlined" density="compact">
        <VCardItem
          class="py-2 cursor-pointer"
          @click="showPracasExpanded = !showPracasExpanded"
        >
          <template #prepend>
            <VIcon icon="tabler-toll" size="18" color="warning" />
          </template>
          <VCardTitle class="text-body-2">
            Praças de Pedágio
          </VCardTitle>
          <template #append>
            <div class="d-flex align-center gap-2">
              <VChip size="x-small" color="warning" variant="tonal">
                {{ stats.total }}
              </VChip>
              <VIcon
                :icon="showPracasExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                size="18"
              />
            </div>
          </template>
        </VCardItem>

        <VExpandTransition>
          <div v-show="showPracasExpanded">
            <VDivider />
            <VList density="compact" class="pracas-list">
              <VListItem
                v-for="praca in pracasProcessadas"
                :key="praca.idx"
                density="compact"
                class="px-3 py-1"
              >
                <template #prepend>
                  <VAvatar
                    :color="praca.matchIncerto ? 'warning' : 'success'"
                    size="24"
                    variant="tonal"
                  >
                    <span class="text-caption font-weight-medium">{{ praca.idx }}</span>
                  </VAvatar>
                </template>

                <VListItemTitle class="text-caption praca-nome">
                  {{ praca.nome }}
                </VListItemTitle>

                <VListItemSubtitle class="text-caption">
                  {{ praca.rodoviaFormatada }} {{ praca.kmFormatado }}
                  <span v-if="praca.concessionaria !== '-'" class="text-disabled">
                    - {{ praca.concessionaria }}
                  </span>
                </VListItemSubtitle>

                <template #append>
                  <VIcon
                    v-if="praca.matchIncerto"
                    icon="tabler-alert-triangle"
                    size="14"
                    color="warning"
                  >
                    <VTooltip activator="parent" location="left">
                      Localização aproximada
                    </VTooltip>
                  </VIcon>
                  <VIcon
                    v-else-if="praca.temCoordenadas"
                    icon="tabler-map-pin-check"
                    size="14"
                    color="success"
                  />
                  <VIcon
                    v-else
                    icon="tabler-map-pin-off"
                    size="14"
                    color="disabled"
                  />
                </template>
              </VListItem>
            </VList>
          </div>
        </VExpandTransition>
      </VCard>

      <!-- Indicador de Sucesso -->
      <VAlert
        type="success"
        variant="tonal"
        density="compact"
        class="mt-4"
      >
        <template #prepend>
          <VIcon icon="tabler-check" size="18" />
        </template>
        <span class="text-caption">
          Passo completo! Clique em "Próximo" para revisar.
        </span>
      </VAlert>
    </template>

    <!-- Initial State -->
    <VCard
      v-else
      variant="tonal"
      color="info"
      class="text-center pa-6"
    >
      <VIcon icon="tabler-calculator" size="48" color="info" class="mb-3" />
      <div class="text-body-2 mb-4">
        Pronto para calcular o preço da viagem
      </div>
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-calculator"
        @click="verificarPreco"
      >
        Calcular Preço
      </VBtn>
    </VCard>
  </div>
</template>

<style scoped>
.step4-container {
  max-height: 100%;
}

.summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: rgba(var(--v-theme-surface-variant), 0.3);
  border-radius: 6px;
  overflow: hidden;
}

.summary-item .text-caption {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pracas-list {
  max-height: 180px;
  overflow-y: auto;
}

.pracas-list::-webkit-scrollbar {
  width: 4px;
}

.pracas-list::-webkit-scrollbar-thumb {
  background: rgba(var(--v-border-color), 0.5);
  border-radius: 2px;
}

.praca-nome {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 180px;
}

.cursor-pointer {
  cursor: pointer;
}

.cursor-pointer:hover {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

/* Dark mode */
.v-theme--dark .summary-item {
  background: rgba(var(--v-theme-surface-variant), 0.15);
}

/* Texto branco com opacidade */
.text-white-50 {
  color: rgba(255, 255, 255, 0.7) !important;
}
</style>
