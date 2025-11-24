<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { CompraViagemFormData, RotaCompraViagem } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: CompraViagemFormData]
  'stepComplete': [complete: boolean]
  'rotaValidada': [] // Emitir quando rota for validada (para auto-calcular preço)
}>()

// State
const loadingRotas = ref(false)
const rotasOptions = ref<any[]>([])
const selectedRota = ref<number | null>(null)
const modoCD = ref(false)
const modoRetorno = ref(false)
const loadingRotaMunicipios = ref(false)

// Computed
const isStepValid = computed(() => {
  return props.formData.rota.rota !== null && props.formData.step3Completo
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Recarregar rotas quando modo CD muda
watch(modoCD, async () => {
  selectedRota.value = null
  const updated: CompraViagemFormData = {
    ...props.formData,
    rota: {
      ...props.formData.rota,
      rota: null,
      municipios: [],
      modoCD: modoCD.value
    },
    step3Completo: false
  }
  emit('update:formData', updated)
  await carregarTodasRotas()
})

watch(modoRetorno, () => {
  const updated: CompraViagemFormData = {
    ...props.formData,
    rota: {
      ...props.formData.rota,
      modoRetorno: modoRetorno.value
    }
  }
  emit('update:formData', updated)
})

// Methods
const carregarTodasRotas = async () => {
  loadingRotas.value = true
  try {
    const params = new URLSearchParams({
      search: '', // Vazio = busca TODAS
      flg_cd: modoCD.value ? '1' : '0'
    })

    const response = await fetch(`${window.location.origin}/api/compra-viagem/rotas?${params}`)
    const data = await response.json()

    if (!data.success) {
      throw new Error(data.message || 'Erro ao buscar rotas')
    }

    // data.data já está no formato correto do backend (value, title, subtitle, flgcd, flgretorno)
    rotasOptions.value = data.data || []

  } catch (error) {
    console.error('Erro ao carregar rotas:', error)
    rotasOptions.value = []
  } finally {
    loadingRotas.value = false
  }
}

const selecionarRota = async (rotaIdValue: number | null) => {
  if (!rotaIdValue) {
    limparRota()
    return
  }

  try {
    // VALIDAR ROTA PRIMEIRO
    const response = await fetch(`${window.location.origin}/api/compra-viagem/validar-rota`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codpac: props.formData.pacote.pacote?.codpac,
        cod_rota: rotaIdValue,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value
      })
    })

    const data = await response.json()

    if (!data.success) {
      console.error('Erro ao validar rota:', data.error)
      selectedRota.value = null
      return
    }

    console.log('✅ Rota validada com sucesso')

    // Buscar dados da rota selecionada nas options
    const rotaSelecionada = rotasOptions.value.find(r => r.value === rotaIdValue)
    if (!rotaSelecionada) {
      console.error('Rota não encontrada nas opções')
      return
    }

    // Carregar municípios da rota
    await carregarMunicipiosRota(rotaIdValue)

    // Atualizar formData
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
        municipios: props.formData.rota.municipios, // Será preenchido em carregarMunicipiosRota
        modoCD: modoCD.value,
        modoRetorno: modoRetorno.value
      },
      step3Completo: true
    }

    emit('update:formData', updated)

    // Emitir evento para auto-calcular preço (Step 4)
    emit('rotaValidada')

  } catch (error) {
    console.error('Erro ao selecionar rota:', error)
    selectedRota.value = null
  }
}

const carregarMunicipiosRota = async (rotaIdValue: number) => {
  if (!rotaIdValue) {
    return
  }

  loadingRotaMunicipios.value = true
  try {
    const response = await fetch(`${window.location.origin}/api/semparar-rotas/${rotaIdValue}/municipios`)
    const data = await response.json()

    if (!data.success) {
      throw new Error(data.message || 'Erro ao carregar municípios')
    }

    const municipios = data.data.municipios || []

    // Atualizar formData com municípios
    const updated: CompraViagemFormData = {
      ...props.formData,
      rota: {
        ...props.formData.rota,
        municipios
      }
    }

    emit('update:formData', updated)

    console.log(`🗺️ ${municipios.length} municípios carregados`)

  } catch (error) {
    console.error('Erro ao carregar municípios:', error)
  } finally {
    loadingRotaMunicipios.value = false
  }
}

const limparRota = () => {
  selectedRota.value = null

  const updated: CompraViagemFormData = {
    ...props.formData,
    rota: {
      rota: null,
      municipios: [],
      modoCD: modoCD.value,
      modoRetorno: modoRetorno.value
    },
    step3Completo: false
  }

  emit('update:formData', updated)
}

// Lifecycle - Carregar rotas ao montar + inicializar com dados existentes
onMounted(async () => {
  await carregarTodasRotas()

  // Inicializar com dados existentes se houver
  if (props.formData.rota.rota) {
    modoCD.value = props.formData.rota.modoCD
    modoRetorno.value = props.formData.rota.modoRetorno
    selectedRota.value = props.formData.rota.rota.sPararRotID
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

    <!-- Switches de Modo -->
    <VRow class="mb-4">
      <VCol cols="6">
        <VSwitch
          v-model="modoCD"
          color="primary"
          label="Modo CD (TCD)"
          hide-details
          density="compact"
          :disabled="props.formData.step3Completo"
        />
      </VCol>

      <VCol cols="6">
        <VSwitch
          v-model="modoRetorno"
          color="warning"
          label="Retorno"
          hide-details
          density="compact"
          :disabled="props.formData.step3Completo"
        />
      </VCol>
    </VRow>

    <!-- Autocomplete de Rotas -->
    <VAutocomplete
      :key="`rota-autocomplete-${modoCD}`"
      v-model="selectedRota"
      :items="rotasOptions"
      :loading="loadingRotas"
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
