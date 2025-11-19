<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { CompraViagemFormData, PracaPedagio } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: CompraViagemFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const loadingRoteirizacao = ref(false)
const loadingCusto = ref(false)
const error = ref<string | null>(null)
const etapaAtual = ref<'inicial' | 'roteirizando' | 'cadastrando' | 'calculando' | 'concluido'>('inicial')

// Computed
const pedagios = computed(() => props.formData.pedagios)

const isStepValid = computed(() => {
  return (
    pedagios.value.custoCalculado &&
    pedagios.value.valorTotal > 0 &&
    pedagios.value.pracas.length > 0
  )
})

const headers = [
  { title: '#', key: 'index', sortable: false, width: '60px' },
  { title: 'Praça', key: 'nome', sortable: true },
  { title: 'Cidade', key: 'cidade', sortable: true },
  { title: 'UF', key: 'uf', sortable: false, width: '80px' },
  { title: 'Valor', key: 'valor', sortable: true, align: 'end' as const }
]

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const calcularPedagios = async () => {
  error.value = null
  loadingRoteirizacao.value = true

  try {
    // ETAPA 1: Roteirizar praças de pedágio
    etapaAtual.value = 'roteirizando'
    console.log('🚧 Iniciando roteirização de praças de pedágio...')

    const pontos = preparar PontosRoteirizacao()

    const roteirizacaoResponse = await fetch('http://localhost:8002/api/semparar/roteirizar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pontos,
        alternativas: false
      })
    })

    const roteirizacaoData = await roteirizacaoResponse.json()

    if (!roteirizacaoData.success || !roteirizacaoData.data?.pracas) {
      throw new Error(roteirizacaoData.message || 'Erro ao roteirizar praças')
    }

    const pracasIds = roteirizacaoData.data.pracas.map((p: any) => p.id)
    console.log(`✅ ${pracasIds.length} praças encontradas`)

    // ETAPA 2: Cadastrar rota temporária
    etapaAtual.value = 'cadastrando'
    const nomeRotaTemp = `TEMP_${Date.now()}`

    const rotaResponse = await fetch('http://localhost:8002/api/semparar/rota-temporaria', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        praca_ids: pracasIds,
        nome_rota: nomeRotaTemp
      })
    })

    const rotaData = await rotaResponse.json()

    if (!rotaData.success) {
      throw new Error(rotaData.message || 'Erro ao cadastrar rota temporária')
    }

    console.log(`✅ Rota temporária cadastrada: ${nomeRotaTemp}`)

    // ETAPA 3: Obter custo da rota
    etapaAtual.value = 'calculando'
    const custoResponse = await fetch('http://localhost:8002/api/semparar/custo-rota', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        nome_rota: nomeRotaTemp,
        placa: props.formData.configuracao.placa,
        eixos: props.formData.configuracao.eixos,
        data_inicio: props.formData.configuracao.dataInicio,
        data_fim: props.formData.configuracao.dataFim
      })
    })

    const custoData = await custoResponse.json()

    if (!custoData.success || !custoData.data) {
      throw new Error(custoData.message || 'Erro ao calcular custo')
    }

    const valorTotal = parseFloat(custoData.data.total || '0')
    console.log(`✅ Custo calculado: R$ ${valorTotal.toFixed(2)}`)

    // Atualizar form data
    const updated: CompraViagemFormData = {
      ...props.formData,
      pedagios: {
        pracas: roteirizacaoData.data.pracas,
        valorTotal,
        nomeRotaTemporaria: nomeRotaTemp,
        rotaCadastrada: true,
        custoCalculado: true
      },
      step4Completo: true
    }

    emit('update:formData', updated)
    etapaAtual.value = 'concluido'

  } catch (err: any) {
    console.error('❌ Erro no cálculo de pedágios:', err)
    error.value = err.message || 'Erro desconhecido ao calcular pedágios'
    etapaAtual.value = 'inicial'
  } finally {
    loadingRoteirizacao.value = false
  }
}

const prepararPontosRoteirizacao = () => {
  const pontos: any[] = []

  // Adicionar municípios da rota padrão
  props.formData.rotaPadrao.municipios.forEach((mun, index) => {
    if (mun.lat && mun.lon) {
      pontos.push({
        cod_ibge: parseInt(mun.cdibge),
        desc: mun.desMun,
        latitude: mun.lat,
        longitude: mun.lon,
        ordem: index + 1
      })
    }
  })

  // Adicionar entregas do pacote (se houver)
  props.formData.pacote.entregas_com_gps.forEach((entrega, index) => {
    if (entrega.lat && entrega.lon) {
      pontos.push({
        cod_ibge: 0, // Entrega não tem IBGE
        desc: entrega.razcli,
        latitude: entrega.lat,
        longitude: entrega.lon,
        ordem: props.formData.rotaPadrao.municipios.length + index + 1
      })
    }
  })

  return pontos
}

const recalcular = () => {
  // Limpar dados anteriores
  const updated: CompraViagemFormData = {
    ...props.formData,
    pedagios: {
      pracas: [],
      valorTotal: 0,
      nomeRotaTemporaria: '',
      rotaCadastrada: false,
      custoCalculado: false
    },
    step4Completo: false
  }

  emit('update:formData', updated)
  calcularPedagios()
}

// Lifecycle - Calcular automaticamente ao entrar no step
onMounted(() => {
  if (!pedagios.value.custoCalculado && props.formData.step3Completo) {
    calcularPedagios()
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Cálculo de Pedágios
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Aguarde enquanto calculamos as praças de pedágio e o custo da viagem
    </p>

    <!-- Loading State -->
    <div v-if="loadingRoteirizacao">
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
                {{ etapaAtual === 'roteirizando' ? 'Roteirizando praças...' :
                   etapaAtual === 'cadastrando' ? 'Cadastrando rota temporária...' :
                   'Calculando custo total...' }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Este processo pode levar até 30 segundos
              </div>
            </div>

            <VStepper
              :model-value="etapaAtual === 'roteirizando' ? 1 : etapaAtual === 'cadastrando' ? 2 : 3"
              class="w-100"
              flat
            >
              <VStepperHeader>
                <VStepperItem
                  :complete="etapaAtual !== 'roteirizando'"
                  value="1"
                  title="Roteirizar"
                />
                <VDivider />
                <VStepperItem
                  :complete="etapaAtual === 'calculando' || etapaAtual === 'concluido'"
                  value="2"
                  title="Cadastrar"
                />
                <VDivider />
                <VStepperItem
                  :complete="etapaAtual === 'concluido'"
                  value="3"
                  title="Calcular"
                />
              </VStepperHeader>
            </VStepper>
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
    <div v-else-if="pedagios.custoCalculado">
      <!-- Card de Resumo -->
      <VCard variant="tonal" color="success" class="mb-6">
        <VCardText>
          <VRow align="center">
            <VCol cols="auto">
              <VIcon
                icon="tabler-check-circle"
                color="success"
                size="48"
              />
            </VCol>

            <VCol>
              <div class="text-h6 mb-1">
                Cálculo Concluído
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ pedagios.pracas.length }} praça(s) de pedágio identificada(s)
              </div>
            </VCol>

            <VCol cols="auto">
              <div class="text-center">
                <div class="text-caption text-medium-emphasis mb-1">
                  Valor Total
                </div>
                <div class="text-h4 text-success">
                  R$ {{ pedagios.valorTotal.toFixed(2) }}
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Tabela de Praças -->
      <VCard>
        <VCardItem>
          <VCardTitle>Praças de Pedágio</VCardTitle>

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

        <VDataTable
          :headers="headers"
          :items="pedagios.pracas"
          density="compact"
          :items-per-page="-1"
          hide-default-footer
        >
          <template #item.index="{ index }">
            <VChip size="small" color="primary" variant="tonal">
              {{ index + 1 }}
            </VChip>
          </template>

          <template #item.valor="{ item }">
            <span class="font-weight-medium">
              R$ {{ item.valor?.toFixed(2) || '0.00' }}
            </span>
          </template>

          <template #bottom>
            <VDivider />
            <div class="d-flex justify-end align-center pa-4">
              <span class="text-body-2 text-medium-emphasis me-4">
                Total:
              </span>
              <span class="text-h6 text-success">
                R$ {{ pedagios.valorTotal.toFixed(2) }}
              </span>
            </div>
          </template>
        </VDataTable>
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
        @click="calcularPedagios"
      >
        Calcular Agora
      </VBtn>
    </VAlert>
  </div>
</template>
