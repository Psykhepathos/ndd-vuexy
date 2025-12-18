<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { VpoEmissaoFormData, RotaVpo, MunicipioRota } from '../types'
import { getApiUrl, apiFetch } from '@/config/api'

// Props & Emits
const props = defineProps<{
  formData: VpoEmissaoFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: VpoEmissaoFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const loading = ref(false)
const loadingMunicipios = ref(false)
const loadingPracas = ref(false)
const rotas = ref<RotaVpo[]>([])
const searchRota = ref('')
const selectedRotaId = ref<number | null>(null)
const errorMessage = ref<string | null>(null)
const pracasCalculadas = ref(false)

// Filters
const filtroCD = ref<boolean | null>(null)
const filtroRetorno = ref<boolean | null>(null)

// Computed
const rotaSelecionada = computed(() => props.formData.rota.rota)
const municipios = computed(() => props.formData.rota.municipios)

// Headers da tabela de praças (sem rodovia, mais compacto)
const pracasHeaders = [
  { title: '#', key: 'idx', width: '40px', sortable: false },
  { title: 'Praça', key: 'nome', sortable: false },
  { title: 'Valor', key: 'valor', align: 'end' as const, width: '100px', sortable: false },
]

// Praças com índice para exibição na tabela
const pracasComIndice = computed(() => {
  return (props.formData.rota.pracas || []).map((praca, index) => ({
    ...praca,
    idx: index + 1,
  }))
})

const rotasFiltradas = computed(() => {
  let filtered = rotas.value

  if (searchRota.value) {
    const search = searchRota.value.toLowerCase()
    filtered = filtered.filter(r =>
      r.desSPararRot.toLowerCase().includes(search)
    )
  }

  if (filtroCD.value !== null) {
    filtered = filtered.filter(r => r.flgCD === filtroCD.value)
  }

  if (filtroRetorno.value !== null) {
    filtered = filtered.filter(r => r.flgRetorno === filtroRetorno.value)
  }

  return filtered
})

// Step só é válido se rota está selecionada E praças foram calculadas
const isStepValid = computed(() => {
  // Precisa ter rota selecionada
  if (!rotaSelecionada.value) return false

  // Precisa ter calculado as praças (mesmo que não tenha pedágios)
  // Verificamos pelo formData.custo.calculado que é setado após o cálculo
  return props.formData.custo.calculado === true
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const carregarRotas = async () => {
  console.log('=== carregarRotas INICIADO ===')
  loading.value = true
  errorMessage.value = null

  try {
    const url = getApiUrl('/semparar-rotas?per_page=100')
    console.log('Buscando rotas:', url)
    const response = await fetch(url)
    console.log('Response status:', response.status)
    const data = await response.json()

    console.log('API Response rotas:', data)

    if (data.success) {
      rotas.value = data.data || []
      console.log('Rotas carregadas:', rotas.value.length)
      if (rotas.value.length > 0) {
        console.log('Primeira rota:', rotas.value[0])
        console.log('Última rota:', rotas.value[rotas.value.length - 1])
      }
    } else {
      errorMessage.value = data.message || 'Erro ao carregar rotas'
      console.error('API retornou erro:', data.message)
    }
  } catch (error: any) {
    console.error('Erro ao carregar rotas:', error)
    errorMessage.value = error.message || 'Erro ao carregar rotas'
  } finally {
    loading.value = false
    console.log('loading set to false')
  }
}

// Guard para evitar chamadas duplicadas
let isSelectingRota = false

const selecionarRota = async (rota: RotaVpo) => {
  // Evitar chamadas duplicadas
  if (isSelectingRota) {
    console.log('selecionarRota: Ignorando chamada duplicada')
    return
  }

  // Evitar re-seleção da mesma rota
  if (selectedRotaId.value === rota.sPararRotID) {
    console.log('selecionarRota: Rota já selecionada, ignorando')
    return
  }

  isSelectingRota = true
  console.log('selecionarRota chamado:', rota)
  selectedRotaId.value = rota.sPararRotID
  loadingMunicipios.value = true
  errorMessage.value = null

  try {
    // Buscar municípios da rota
    const url = getApiUrl(`/semparar-rotas/${rota.sPararRotID}/municipios`)
    console.log('Buscando municípios:', url)
    const response = await fetch(url)
    const data = await response.json()
    console.log('Resposta municípios:', data)

    if (data.success) {
      const municipiosData: MunicipioRota[] = data.data?.municipios || []
      console.log('Municípios carregados:', municipiosData.length, municipiosData)

      // Atualizar formData - PRESERVAR entregas do Step 1!
      const updated: VpoEmissaoFormData = {
        ...props.formData,
        rota: {
          rota: rota,
          municipios: municipiosData,
          pracas: [],
          entregas: props.formData.rota.entregas,  // Preservar entregas do pacote
          rotaSugerida: null,
        },
        step4Completo: true,
      }
      console.log('Emitindo update:formData com rota:', updated.rota)
      emit('update:formData', updated)
    } else {
      errorMessage.value = data.message || 'Erro ao carregar municípios da rota'
      console.error('Erro ao carregar municípios:', data.message)
    }
  } catch (error: any) {
    console.error('Erro ao carregar municípios:', error)
    errorMessage.value = error.message || 'Erro ao carregar municípios'
  } finally {
    loadingMunicipios.value = false
    isSelectingRota = false
    console.log('loadingMunicipios set to false')
  }
}

const limparRota = () => {
  selectedRotaId.value = null
  pracasCalculadas.value = false

  const updated: VpoEmissaoFormData = {
    ...props.formData,
    rota: {
      rota: null,
      municipios: [],
      pracas: [],
      entregas: props.formData.rota.entregas,  // Preservar entregas do pacote
      rotaSugerida: null,
    },
    custo: {
      custo: null,
      calculado: false,
      calculando: false,
    },
    step4Completo: false,
  }
  emit('update:formData', updated)
}

const recalcularPracas = () => {
  // Resetar estado de cálculo para permitir recalcular
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    rota: {
      ...props.formData.rota,
      pracas: [],
    },
    custo: {
      custo: null,
      calculado: false,
      calculando: false,
    },
  }
  emit('update:formData', updated)
  pracasCalculadas.value = false
}

const calcularPracas = async () => {
  console.log('=== calcularPracas INICIADO ===')
  console.log('formData.rota.rota:', props.formData.rota.rota)
  console.log('formData.rota.municipios:', props.formData.rota.municipios)

  if (!props.formData.rota.rota || props.formData.rota.municipios.length === 0) {
    errorMessage.value = 'Selecione uma rota primeiro'
    console.error('Rota ou municípios não selecionados')
    return
  }

  loadingPracas.value = true
  errorMessage.value = null

  try {
    console.log('Calculando praças para rota:', props.formData.rota.rota.desSPararRot)
    console.log('Municípios:', props.formData.rota.municipios)

    // Preparar municípios para enviar ao backend
    const municipiosParaCalculo = props.formData.rota.municipios.map(m => ({
      desMun: m.desMun,
      desEst: m.desEst,
    }))

    console.log('Enviando municípios para cálculo:', municipiosParaCalculo)

    // Determinar categoria de pedágio baseada no número de eixos
    // 1=Moto, 2=Passeio, 5=Caminhão leve (2 eixos), 6=Caminhão médio (3-5 eixos), 7=Caminhão pesado (6+ eixos)
    const eixos = props.formData.veiculo.veiculo?.eixos ?? 2
    let categoriaPedagio = 7 // Default: caminhão pesado
    if (eixos <= 2)
      categoriaPedagio = 5 // Caminhão leve (2 eixos)
    else if (eixos <= 5)
      categoriaPedagio = 6 // Caminhão médio (3-5 eixos)
    else
      categoriaPedagio = 7 // Caminhão pesado (6+ eixos)

    // Chamar endpoint de cálculo de praças (IBGE → CEP → NDD Cargo)
    const response = await apiFetch(getApiUrl('/vpo/calcular-pracas'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        municipios: municipiosParaCalculo,
        categoria_pedagio: categoriaPedagio,
        tipo_veiculo: 2, // 2 = Caminhão (valores: 1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator)
      }),
    })

    const data = await response.json()
    console.log('Resposta cálculo praças:', data)

    if (data.success) {
      pracasCalculadas.value = true

      // Atualizar formData com praças reais
      const updated: VpoEmissaoFormData = {
        ...props.formData,
        rota: {
          ...props.formData.rota,
          pracas: data.data?.pracas || [],
        },
      }
      emit('update:formData', updated)

      if (data.data?.ceps_nao_encontrados?.length > 0) {
        errorMessage.value = `Aviso: CEP não encontrado para: ${data.data.ceps_nao_encontrados.join(', ')}`
      }
    } else if (response.status === 202 && data.guid) {
      // Processamento assíncrono - fazer polling para obter resultado
      console.log('GUID para consulta assíncrona:', data.guid)
      errorMessage.value = 'Calculando praças de pedágio... Aguarde.'

      // Polling para obter resultado (máximo 60 segundos, intervalo de 3 segundos)
      const maxAttempts = 20
      let attempt = 0
      let resultadoObtido = false

      while (attempt < maxAttempts && !resultadoObtido) {
        attempt++
        const tempoRestante = (maxAttempts - attempt) * 3
        console.log(`Polling tentativa ${attempt}/${maxAttempts}... (${tempoRestante}s restantes)`)
        errorMessage.value = `Calculando praças de pedágio... Tentativa ${attempt}/${maxAttempts}`

        // Aguardar 3 segundos antes de consultar
        await new Promise(resolve => setTimeout(resolve, 3000))

        try {
          const resultResponse = await apiFetch(getApiUrl(`/ndd-cargo/resultado/${data.guid}`), {
            headers: { 'Accept': 'application/json' }
          })
          const resultData = await resultResponse.json()
          console.log(`Resultado polling (tentativa ${attempt}):`, resultData)

          if (resultData.success && resultData.data) {
            // Resultado obtido com sucesso!
            resultadoObtido = true
            pracasCalculadas.value = true

            // Extrair praças do resultado
            const pracasResult = resultData.data.pracas_pedagio || []

            // Atualizar formData com praças reais
            const updated: VpoEmissaoFormData = {
              ...props.formData,
              rota: {
                ...props.formData.rota,
                pracas: pracasResult,
              },
              custo: {
                ...props.formData.custo,
                custo: {
                  valor_total: resultData.data.valor_total_pedagogios || 0,
                  pedagios: pracasResult,
                  rota_nome: props.formData.rota.rota?.desSPararRot || '',
                  km_total: resultData.data.distancia_km || 0,
                  tempo_estimado: `${resultData.data.tempo_minutos || 0} min`,
                },
                calculado: true,
                calculando: false,
              },
            }
            emit('update:formData', updated)

            errorMessage.value = null
            console.log('Praças calculadas com sucesso:', pracasResult.length, 'praças')

            if (pracasResult.length === 0) {
              errorMessage.value = 'Rota calculada com sucesso! (Não há praças de pedágio neste trecho)'
            }
          } else if (
            resultData.status === -2 ||
            resultData.status === 404 ||
            resultData.status === 202 ||
            resultResponse.status === 202 ||
            resultResponse.status === 400 ||
            (resultData.message && resultData.message.includes('processada'))
          ) {
            // Resultado ainda não disponível, continuar polling
            // Status 202 = ainda processando, 400 = GUID não pronto ainda
            console.log('Resultado ainda não disponível, aguardando...', resultData.status || resultResponse.status)
          } else {
            // Erro definitivo no resultado
            console.error('Erro ao obter resultado:', resultData)
            errorMessage.value = resultData.message || 'Erro ao obter resultado do cálculo'
            break
          }
        } catch (pollError) {
          console.error('Erro no polling:', pollError)
        }
      }

      if (!resultadoObtido) {
        errorMessage.value = 'Tempo esgotado aguardando resultado. Tente novamente.'
      }
    } else {
      errorMessage.value = data.message || 'Erro ao calcular praças de pedágio'
    }

  } catch (error: any) {
    console.error('Erro ao calcular praças:', error)
    errorMessage.value = error.message || 'Erro ao calcular praças de pedágio'
  } finally {
    loadingPracas.value = false
  }
}

// Lifecycle
onMounted(() => {
  console.log('VpoStep4Rota mounted')
  carregarRotas()
})

// Debug watchers
watch(rotaSelecionada, (val) => {
  console.log('rotaSelecionada changed:', val)
})

watch(municipios, (val) => {
  console.log('municipios changed:', val?.length, 'items')
})

watch([loading, loadingMunicipios, loadingPracas], ([l, lm, lp]) => {
  console.log('Loading states - main:', l, 'municipios:', lm, 'pracas:', lp)
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Rota
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Selecione a rota para cálculo dos pedágios
    </p>

    <!-- Error Message -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="errorMessage = null"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Loading -->
    <VSkeletonLoader v-if="loading" type="card" />

    <template v-else>
      <!-- Rota Selecionada -->
      <VCard
        v-if="rotaSelecionada"
        class="mb-6"
        variant="tonal"
        color="success"
      >
        <VCardItem>
          <template #prepend>
            <VIcon icon="tabler-route" color="success" size="32" />
          </template>

          <VCardTitle>{{ rotaSelecionada.desSPararRot }}</VCardTitle>

          <VCardSubtitle>
            <VChip v-if="rotaSelecionada.flgCD" size="x-small" color="info" class="me-1">
              CD
            </VChip>
            <VChip v-if="rotaSelecionada.flgRetorno" size="x-small" color="warning" class="me-1">
              Retorno
            </VChip>
            {{ rotaSelecionada.tempoViagem }}h estimado
          </VCardSubtitle>

          <template #append>
            <VBtn
              icon="tabler-x"
              size="small"
              variant="text"
              @click="limparRota"
            />
          </template>
        </VCardItem>

        <!-- Municípios -->
        <VCardText v-if="loadingMunicipios">
          <VSkeletonLoader type="text" />
        </VCardText>

        <VCardText v-else-if="municipios.length > 0">
          <div class="text-caption text-medium-emphasis mb-2">
            {{ municipios.length }} municípios na rota:
          </div>

          <div class="d-flex flex-wrap gap-1 mb-4">
            <VChip
              v-for="(mun, index) in municipios"
              :key="mun.codMun"
              size="x-small"
              color="primary"
              variant="outlined"
            >
              {{ index + 1 }}. {{ mun.desMun }} - {{ mun.desEst }}
            </VChip>
          </div>

          <!-- Botão Calcular Praças -->
          <VBtn
            v-if="!formData.custo.calculado"
            color="warning"
            variant="tonal"
            :loading="loadingPracas"
            :disabled="loadingPracas"
            block
            @click="calcularPracas"
          >
            <VIcon icon="tabler-calculator" start />
            Calcular Praças de Pedágio (NDD Cargo)
          </VBtn>

          <!-- Resultado do Cálculo de Praças -->
          <template v-if="formData.custo.calculado">
            <!-- Card de Sucesso com Resumo -->
            <VCard
              variant="tonal"
              :color="(formData.rota.pracas?.length || 0) > 0 ? 'success' : 'info'"
              class="mb-3"
            >
              <VCardItem>
                <template #prepend>
                  <VAvatar
                    :color="(formData.rota.pracas?.length || 0) > 0 ? 'success' : 'info'"
                    size="48"
                    variant="tonal"
                  >
                    <VIcon
                      :icon="(formData.rota.pracas?.length || 0) > 0 ? 'tabler-toll' : 'tabler-check'"
                      size="24"
                    />
                  </VAvatar>
                </template>

                <VCardTitle class="text-h6">
                  <template v-if="(formData.rota.pracas?.length || 0) > 0">
                    {{ formData.rota.pracas?.length }} Praças de Pedágio
                  </template>
                  <template v-else>
                    Rota Calculada ✓
                  </template>
                </VCardTitle>

                <VCardSubtitle>
                  <template v-if="(formData.rota.pracas?.length || 0) > 0">
                    Total: <strong class="text-success">R$ {{ (formData.custo.custo?.valor_total || 0).toFixed(2) }}</strong>
                    <span v-if="formData.custo.custo?.km_total"> • {{ formData.custo.custo.km_total }} km</span>
                  </template>
                  <template v-else>
                    Nenhuma praça de pedágio neste trecho
                  </template>
                </VCardSubtitle>

                <template #append>
                  <VBtn
                    icon="tabler-refresh"
                    size="small"
                    variant="text"
                    @click="recalcularPracas"
                  >
                    <VTooltip activator="parent" location="top">Recalcular</VTooltip>
                  </VBtn>
                </template>
              </VCardItem>

              <!-- Lista de Praças (se houver) -->
              <VCardText v-if="(formData.rota.pracas?.length || 0) > 0" class="pa-2">
                <VDataTable
                  :headers="pracasHeaders"
                  :items="pracasComIndice"
                  :items-per-page="5"
                  density="compact"
                  class="text-caption pracas-table"
                >
                  <template #item.nome="{ item }">
                    <span class="text-truncate d-inline-block" style="max-width: 180px;">
                      {{ item.nome || item.nomePraca || `Praça ${item.codigo}` }}
                    </span>
                  </template>
                  <template #item.valor="{ item }">
                    <VChip size="x-small" color="success" variant="flat">
                      R$ {{ (item.valor || item.valorPedagio || 0).toFixed(2) }}
                    </VChip>
                  </template>
                  <template #bottom>
                    <VDataTableFooter
                      :items-per-page-options="[5, 10]"
                      items-per-page-text=""
                      show-current-page
                    />
                  </template>
                </VDataTable>
              </VCardText>
            </VCard>

            <!-- Indicador Visual de Passo Completo -->
            <VAlert
              type="success"
              variant="tonal"
              density="compact"
            >
              <template #prepend>
                <VIcon icon="tabler-circle-check" />
              </template>
              <span class="text-caption">
                <strong>Passo completo!</strong> Clique em "Próximo" para revisar e emitir o VPO.
              </span>
            </VAlert>
          </template>
        </VCardText>
      </VCard>

      <!-- Busca e Filtros -->
      <template v-if="!rotaSelecionada">
        <VRow class="mb-4" align="end">
          <VCol cols="12" sm="6" md="5">
            <VTextField
              v-model="searchRota"
              label="Buscar Rota"
              placeholder="Digite o nome da rota"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="6" sm="3" md="3">
            <VSelect
              v-model="filtroCD"
              :items="[
                { title: 'Todos', value: null },
                { title: 'Sim (CD)', value: true },
                { title: 'Não (CD)', value: false },
              ]"
              label="Tipo CD"
              hide-details
            />
          </VCol>

          <VCol cols="6" sm="3" md="4">
            <VSelect
              v-model="filtroRetorno"
              :items="[
                { title: 'Todos', value: null },
                { title: 'Com Retorno', value: true },
                { title: 'Sem Retorno', value: false },
              ]"
              label="Retorno"
              hide-details
            />
          </VCol>
        </VRow>

        <!-- Instrução para o usuário -->
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <template #prepend>
            <VIcon icon="tabler-info-circle" />
          </template>
          <span class="text-caption">
            <strong>Passo 1:</strong> Clique em uma rota abaixo para selecioná-la.
            Depois aparecerá o botão "Calcular Praças de Pedágio (NDD Cargo)".
          </span>
        </VAlert>

        <!-- Debug info -->
        <div class="text-caption text-medium-emphasis mb-2">
          {{ rotasFiltradas.length }} rotas encontradas
        </div>

        <!-- Lista de Rotas -->
        <div
          v-if="rotasFiltradas.length > 0"
          class="rotas-container"
        >
          <div
            v-for="rota in rotasFiltradas"
            :key="rota.sPararRotID"
            class="rota-item d-flex align-center pa-3 gap-3"
            @click="selecionarRota(rota)"
          >
            <!-- Icon -->
            <VAvatar color="primary" variant="tonal" size="40">
              <VIcon icon="tabler-route" />
            </VAvatar>

            <!-- Content -->
            <div class="flex-grow-1">
              <div class="text-body-1 font-weight-medium">
                {{ rota.desSPararRot || `Rota #${rota.sPararRotID}` }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ rota.tempoViagem || 0 }}h estimado
              </div>
            </div>

            <!-- Tags -->
            <div class="d-flex align-center gap-1">
              <VChip v-if="rota.flgCD" size="x-small" color="info">
                CD
              </VChip>
              <VChip v-if="rota.flgRetorno" size="x-small" color="warning">
                Retorno
              </VChip>
              <VIcon icon="tabler-chevron-right" color="primary" size="20" />
            </div>
          </div>
        </div>

        <!-- Nenhuma rota encontrada -->
        <VAlert
          v-else
          type="info"
          variant="tonal"
        >
          <template #prepend>
            <VIcon icon="tabler-info-circle" />
          </template>
          <div>
            <div class="font-weight-medium mb-1">
              Nenhuma Rota Encontrada
            </div>
            <div class="text-caption">
              Tente ajustar os filtros ou a busca.
            </div>
          </div>
        </VAlert>
      </template>
    </template>
  </div>
</template>

<style scoped>
.rotas-container {
  max-height: 350px;
  overflow-y: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
}

.rota-item {
  cursor: pointer;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: background-color 0.2s;
}

.rota-item:last-child {
  border-bottom: none;
}

.rota-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

/* Tabela de praças compacta */
.pracas-table {
  font-size: 12px !important;
}

.pracas-table :deep(th) {
  font-size: 11px !important;
  padding: 4px 8px !important;
}

.pracas-table :deep(td) {
  padding: 4px 8px !important;
}

.pracas-table :deep(.v-data-table-footer) {
  padding: 4px 8px !important;
  font-size: 11px !important;
}
</style>
