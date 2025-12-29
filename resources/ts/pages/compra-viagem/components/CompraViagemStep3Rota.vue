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
  'rotaValidada': []
}>()

// State
const loadingRotas = ref(false)
const loadingValidacao = ref(false)
const rotasOptions = ref<any[]>([])
const selectedRota = ref<number | null>(null)
const modoCD = ref(false)
const modoRetorno = ref(false)
const loadingRotaMunicipios = ref(false)
const erroValidacao = ref<string | null>(null)
const isAutoPreenchendo = ref(false)  // Flag para ignorar watchers durante auto-preenchimento

// Computed
const isStepValid = computed(() => {
  return props.formData.rota.rota !== null && props.formData.step3Completo
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Recarregar rotas quando modo CD muda (ignorar durante auto-preenchimento)
watch(modoCD, async () => {
  if (isAutoPreenchendo.value) {
    console.log('🔒 Ignorando watch modoCD durante auto-preenchimento')
    return
  }
  // Limpar tudo ao mudar modo
  limparRota()
  await carregarTodasRotas()
})

watch(modoRetorno, () => {
  if (isAutoPreenchendo.value) {
    console.log('🔒 Ignorando watch modoRetorno durante auto-preenchimento')
    return
  }
  // Se já tem rota selecionada e mudou retorno, precisa revalidar
  if (props.formData.rota.rota) {
    limparRota()
  }
})

// Methods
const carregarTodasRotas = async () => {
  loadingRotas.value = true
  erroValidacao.value = null

  try {
    console.log('📋 Carregando rotas...', { modoCD: modoCD.value })

    const data = await $api('/compra-viagem/rotas', {
      query: {
        search: '',
        flg_cd: modoCD.value ? '1' : '0'
      }
    })

    if (!data.success) {
      throw new Error(data.message || 'Erro ao buscar rotas')
    }

    rotasOptions.value = data.data || []
    console.log(`✅ ${rotasOptions.value.length} rotas carregadas`)

  } catch (error: any) {
    console.error('❌ Erro ao carregar rotas:', error)
    rotasOptions.value = []
    erroValidacao.value = getErrorMessage(error)
  } finally {
    loadingRotas.value = false
  }
}

const selecionarRota = async (rotaIdValue: number | null) => {
  // Limpar erro anterior
  erroValidacao.value = null

  if (!rotaIdValue) {
    limparRota()
    return
  }

  // Verificar se tem pacote selecionado
  if (!props.formData.pacote.pacote?.codpac) {
    erroValidacao.value = 'Selecione um pacote primeiro (Passo 1)'
    selectedRota.value = null
    return
  }

  loadingValidacao.value = true

  try {
    console.log('🔍 Validando rota...', {
      codpac: props.formData.pacote.pacote.codpac,
      cod_rota: rotaIdValue,
      flgcd: modoCD.value,
      flgretorno: modoRetorno.value
    })

    // VALIDAR ROTA NO BACKEND
    const data = await $api('/compra-viagem/validar-rota', {
      method: 'POST',
      body: {
        codpac: props.formData.pacote.pacote.codpac,
        cod_rota: rotaIdValue,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value
      }
    })

    // Se chegou aqui sem exceção, validação passou
    console.log('✅ Rota validada com sucesso:', data)

    // Buscar dados da rota selecionada nas options
    const rotaSelecionada = rotasOptions.value.find(r => r.value === rotaIdValue)
    if (!rotaSelecionada) {
      erroValidacao.value = 'Rota não encontrada nas opções'
      selectedRota.value = null
      return
    }

    // Carregar municípios da rota
    await carregarMunicipiosRota(rotaIdValue, rotaSelecionada)

  } catch (error: any) {
    console.error('❌ Erro ao validar rota:', error)

    // Extrair mensagem de erro do backend
    const errorData = error?.data || error?.response?._data
    if (errorData?.error) {
      erroValidacao.value = errorData.error
    } else {
      erroValidacao.value = getErrorMessage(error)
    }

    // Limpar seleção
    selectedRota.value = null

  } finally {
    loadingValidacao.value = false
  }
}

const carregarMunicipiosRota = async (rotaIdValue: number, rotaSelecionada: any) => {
  loadingRotaMunicipios.value = true

  try {
    console.log('🗺️ Carregando municípios da rota', rotaIdValue)

    const data = await $api(`/semparar-rotas/${rotaIdValue}/municipios`)

    if (!data.success) {
      throw new Error(data.message || 'Erro ao carregar municípios')
    }

    const municipios = data.data.municipios || []
    console.log(`✅ ${municipios.length} municípios carregados`)

    // Atualizar formData com rota E municípios
    const updated: CompraViagemFormData = {
      ...props.formData,
      rota: {
        rota: {
          sPararRotID: rotaIdValue,
          desSPararRot: rotaSelecionada.title,
          tempoViagem: rotaSelecionada.tempoviagem,
          flgCD: rotaSelecionada.flgcd,
          flgRetorno: rotaSelecionada.flgretorno
        },
        municipios,
        modoCD: modoCD.value,
        modoRetorno: modoRetorno.value
      },
      // LIMPAR dados do step 4 quando muda rota
      preco: {
        valor: 0,
        numeroViagem: '',
        nomeRotaSemParar: '',
        codRotaSemParar: '',
        pracas: [],
        calculado: false
      },
      step3Completo: true,
      step4Completo: false
    }

    emit('update:formData', updated)
    emit('rotaValidada')

  } catch (error: any) {
    console.error('❌ Erro ao carregar municípios:', error)
    erroValidacao.value = getErrorMessage(error)
    selectedRota.value = null
  } finally {
    loadingRotaMunicipios.value = false
  }
}

const limparRota = () => {
  console.log('🧹 Limpando rota selecionada')

  selectedRota.value = null
  erroValidacao.value = null

  const updated: CompraViagemFormData = {
    ...props.formData,
    rota: {
      rota: null,
      municipios: [],
      modoCD: modoCD.value,
      modoRetorno: modoRetorno.value
    },
    // LIMPAR também dados do step 4
    preco: {
      valor: 0,
      numeroViagem: '',
      nomeRotaSemParar: '',
      codRotaSemParar: '',
      pracas: [],
      calculado: false
    },
    step3Completo: false,
    step4Completo: false
  }

  emit('update:formData', updated)
}

// Lifecycle
onMounted(async () => {
  console.log('🚀 Step3 montado')
  console.log('📦 formData.pacote:', props.formData.pacote)
  console.log('🎯 rotaSugerida:', props.formData.pacote?.rotaSugerida)

  // Inicializar com dados existentes se houver
  if (props.formData.rota.rota) {
    modoCD.value = props.formData.rota.modoCD
    modoRetorno.value = props.formData.rota.modoRetorno
    await carregarTodasRotas()
    selectedRota.value = props.formData.rota.rota.sPararRotID
  }
  // AUTO-PREENCHIMENTO: Se há rota sugerida e nenhuma rota selecionada ainda
  // (Progress compraRota.p linhas 432-463: auto-preenche via semPararIntrot)
  else if (props.formData.pacote.rotaSugerida && !props.formData.rota.rota) {
    const rotaSugerida = props.formData.pacote.rotaSugerida
    console.log('🎯 Auto-preenchendo rota sugerida:', rotaSugerida)

    // ATIVAR FLAG para ignorar watchers durante auto-preenchimento
    isAutoPreenchendo.value = true

    try {
      // CORREÇÃO: Ajustar modo CD/Retorno ANTES de carregar as rotas
      // Se a rota sugerida é CD, precisa marcar modoCD = true para que ela apareça na lista
      modoCD.value = rotaSugerida.flgcd || false
      modoRetorno.value = rotaSugerida.flgretorno || false

      console.log('🔧 Flags ajustados para rota sugerida:', {
        modoCD: modoCD.value,
        modoRetorno: modoRetorno.value
      })

      // Carregar rotas COM os flags corretos
      await carregarTodasRotas()

      // Aguardar um tick para garantir que as rotas foram carregadas
      await new Promise(resolve => setTimeout(resolve, 100))

      // Selecionar a rota sugerida automaticamente
      const rotaId = rotaSugerida.spararrotid
      console.log('🔍 Buscando rota sugerida nas opções:', {
        rotaId,
        totalRotas: rotasOptions.value.length
      })

      // Verificar se a rota sugerida está nas opções carregadas
      let rotaEncontrada = rotasOptions.value.some(r => r.value === rotaId)

      // Se não encontrou, adicionar manualmente a rota sugerida às opções
      if (!rotaEncontrada && rotaId) {
        console.log('📌 Adicionando rota sugerida às opções manualmente')
        rotasOptions.value.unshift({
          value: rotaId,
          title: rotaSugerida.desspararrot,
          subtitle: `${rotaSugerida.flgcd ? 'CD' : 'Rota'} | ${rotaSugerida.tempoviagem || 0} dias`,
          flgcd: rotaSugerida.flgcd,
          flgretorno: rotaSugerida.flgretorno,
          tempoviagem: rotaSugerida.tempoviagem
        })
        rotaEncontrada = true
      }

      if (rotaEncontrada) {
        console.log('✅ Selecionando rota sugerida automaticamente:', rotaId)
        selectedRota.value = rotaId
        // Chamar selecionarRota para validar e carregar municípios
        await selecionarRota(rotaId)
      } else {
        console.warn('⚠️ Rota sugerida não encontrada nas opções:', rotaId)
      }
    } finally {
      // DESATIVAR FLAG após auto-preenchimento
      isAutoPreenchendo.value = false
    }
  } else {
    // Sem rota sugerida - carrega rotas normais
    await carregarTodasRotas()
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Rota
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Escolha a rota SemParar para a viagem
    </p>

    <!-- Rota Sugerida (Progress compraRota.p linhas 432-463) -->
    <VAlert
      v-if="props.formData.pacote.rotaSugerida && !props.formData.step3Completo"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      <template #prepend>
        <VIcon icon="tabler-bulb" />
      </template>
      <strong>Rota sugerida:</strong> {{ props.formData.pacote.rotaSugerida.desspararrot }}
      <span v-if="props.formData.pacote.rotaSugerida.flgcd" class="ms-2">
        <VChip size="x-small" color="primary">CD</VChip>
      </span>
      <span v-if="props.formData.pacote.rotaSugerida.flgretorno" class="ms-2">
        <VChip size="x-small" color="warning">Retorno</VChip>
      </span>
    </VAlert>

    <!-- Erro de Validação -->
    <VAlert
      v-if="erroValidacao"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="erroValidacao = null"
    >
      <template #prepend>
        <VIcon icon="tabler-alert-circle" />
      </template>
      {{ erroValidacao }}
    </VAlert>

    <!-- Switches de Modo -->
    <VRow class="mb-4">
      <VCol cols="6">
        <VSwitch
          v-model="modoCD"
          color="primary"
          label="Modo CD (TCD)"
          hide-details
          density="compact"
          :disabled="props.formData.step3Completo || loadingValidacao"
        />
      </VCol>

      <VCol cols="6">
        <VSwitch
          v-model="modoRetorno"
          color="warning"
          label="Retorno"
          hide-details
          density="compact"
          :disabled="props.formData.step3Completo || loadingValidacao"
        />
      </VCol>
    </VRow>

    <!-- Autocomplete de Rotas -->
    <VAutocomplete
      :key="`rota-autocomplete-${modoCD}-${modoRetorno}`"
      v-model="selectedRota"
      :items="rotasOptions"
      :loading="loadingRotas || loadingValidacao"
      :disabled="props.formData.step3Completo"
      item-title="title"
      item-value="value"
      label="Buscar Rota SemParar *"
      placeholder="Digite para buscar..."
      prepend-inner-icon="tabler-route"
      clearable
      :menu-props="{ maxHeight: 400 }"
      @update:model-value="selecionarRota"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem
          v-bind="itemProps"
          :title="item.raw.title"
          :subtitle="item.raw.subtitle"
        >
          <template #prepend>
            <VIcon
              :icon="item.raw.flgcd ? 'tabler-building-warehouse' : 'tabler-route'"
              :color="item.raw.flgcd ? 'info' : 'primary'"
            />
          </template>

          <template #append>
            <VChip
              v-if="item.raw.flgretorno"
              size="x-small"
              color="warning"
            >
              Retorno
            </VChip>
          </template>
        </VListItem>
      </template>
    </VAutocomplete>

    <!-- Loading Municípios -->
    <VSkeletonLoader
      v-if="loadingRotaMunicipios"
      type="card"
      class="mt-6"
    />

    <!-- Card com Rota Selecionada -->
    <VCard
      v-else-if="props.formData.rota.rota"
      class="mt-6"
      variant="tonal"
      color="success"
    >
      <VCardItem>
        <template #prepend>
          <VIcon
            icon="tabler-route"
            color="success"
            size="32"
          />
        </template>

        <VCardTitle>{{ props.formData.rota.rota.desSPararRot }}</VCardTitle>

        <VCardSubtitle>
          ID: {{ props.formData.rota.rota.sPararRotID }} •
          {{ props.formData.rota.rota.tempoViagem }} dias de viagem
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

      <VDivider />

      <VCardText>
        <VRow>
          <VCol cols="6">
            <div class="text-center">
              <div class="text-h5 text-primary">
                {{ props.formData.rota.municipios.length }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Municípios
              </div>
            </div>
          </VCol>

          <VCol cols="6">
            <div class="text-center">
              <div class="text-h5 text-info">
                {{ props.formData.rota.rota.tempoViagem }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Dias
              </div>
            </div>
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <!-- Modos -->
        <div class="d-flex gap-2">
          <VChip
            v-if="props.formData.rota.modoCD"
            size="small"
            color="primary"
            prepend-icon="tabler-building-warehouse"
          >
            Modo CD
          </VChip>

          <VChip
            v-if="props.formData.rota.modoRetorno"
            size="small"
            color="warning"
            prepend-icon="tabler-arrow-back-up"
          >
            Retorno
          </VChip>

          <VChip
            v-if="!props.formData.rota.modoCD && !props.formData.rota.modoRetorno"
            size="small"
            color="default"
            prepend-icon="tabler-route"
          >
            Rota Normal
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- Alert quando nada selecionado -->
    <VAlert
      v-else
      type="info"
      variant="tonal"
      class="mt-6"
    >
      <template #prepend>
        <VIcon icon="tabler-info-circle" />
      </template>
      <div>
        <div class="font-weight-medium mb-1">
          Rota Obrigatória
        </div>
        <div class="text-caption">
          Selecione uma rota SemParar para continuar. Use os modos CD e Retorno conforme necessário.
        </div>
      </div>
    </VAlert>
  </div>
</template>
