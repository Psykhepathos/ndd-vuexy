<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { CompraViagemFormData, PacoteCompraViagem, EntregaPacote } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: CompraViagemFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const loadingPacotes = ref(false)
const loadingEntregas = ref(false)
const pacotesDisponiveis = ref<any[]>([])
const searchPacote = ref('')
const selectedPacote = ref<PacoteCompraViagem | null>(null)

// Computed
const pacoteSelecionado = computed(() => props.formData.pacote.pacote)
const entregas = computed(() => props.formData.pacote.entregas)
const entregasComGps = computed(() => props.formData.pacote.entregas_com_gps)

const isStepValid = computed(() => {
  return pacoteSelecionado.value !== null
})

const estatisticasEntregas = computed(() => {
  if (!pacoteSelecionado.value) return null

  return {
    total: entregas.value.length,
    comGps: entregasComGps.value.length,
    semGps: entregas.value.length - entregasComGps.value.length,
    percentualGps: entregas.value.length > 0
      ? Math.round((entregasComGps.value.length / entregas.value.length) * 100)
      : 0
  }
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const buscarPacotes = async (search: string | null) => {
  if (!search || search.length < 2) {
    pacotesDisponiveis.value = []
    return
  }

  loadingPacotes.value = true
  try {
    const response = await fetch(
      `http://localhost:8002/api/pacotes/autocomplete?search=${encodeURIComponent(search)}`
    )
    const data = await response.json()

    if (data.success && data.data) {
      // Formatar para o autocomplete
      pacotesDisponiveis.value = data.data.map((pacote: any) => ({
        label: `#${pacote.codpac} - ${pacote.nomtrn} (${pacote.sitpac})`,
        codpac: pacote.codpac,
        raw: pacote
      }))
    }
  } catch (error) {
    console.error('Erro ao buscar pacotes:', error)
  } finally {
    loadingPacotes.value = false
  }
}

const selecionarPacote = async (pacoteItem: any) => {
  if (!pacoteItem || !pacoteItem.raw) return

  const pacote = pacoteItem.raw
  loadingEntregas.value = true

  try {
    // Buscar itinerário do pacote
    const response = await fetch('http://localhost:8002/api/pacotes/itinerario', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codPac: pacote.codpac })
    })

    const data = await response.json()

    if (data.success && data.data) {
      const todasEntregas: any[] = data.data.pedidos || []

      // Processar coordenadas GPS
      const entregasProcessadas: EntregaPacote[] = todasEntregas.map((entrega: any) => ({
        numseqped: entrega.numseqped,
        razcli: entrega.razcli,
        endcli: entrega.endcli,
        baicli: entrega.baicli,
        cidcli: entrega.cidcli,
        sigufs: entrega.sigufs,
        cepcli: entrega.cepcli,
        gps_lat: entrega.gps_lat,
        gps_lon: entrega.gps_lon,
        lat: processGpsCoordinate(entrega.gps_lat),
        lon: processGpsCoordinate(entrega.gps_lon),
        tipo: 'entrega' as const
      }))

      // Filtrar apenas entregas com GPS válido
      const entregasComGpsValido = entregasProcessadas.filter(
        e => e.lat !== null && e.lon !== null && !isNaN(e.lat!) && !isNaN(e.lon!)
      )

      // Atualizar form data
      const updated: CompraViagemFormData = {
        ...props.formData,
        pacote: {
          pacote,
          entregas: entregasProcessadas,
          entregas_com_gps: entregasComGpsValido
        },
        step1Completo: true
      }

      emit('update:formData', updated)
    }
  } catch (error) {
    console.error('Erro ao carregar entregas:', error)
  } finally {
    loadingEntregas.value = false
  }
}

const limparPacote = () => {
  const updated: CompraViagemFormData = {
    ...props.formData,
    pacote: {
      pacote: null,
      entregas: [],
      entregas_com_gps: []
    },
    step1Completo: false
  }

  emit('update:formData', updated)
  selectedPacote.value = null
  searchPacote.value = ''
  pacotesDisponiveis.value = []
}

// Utility: Processar coordenada GPS do Progress
const processGpsCoordinate = (coord: string | null): number | null => {
  if (!coord) return null

  // Formato 1: "-23,0876543" → -23.0876543
  if (coord.includes(',')) {
    return parseFloat(coord.replace(',', '.'))
  }

  // Formato 2: "230876543" → -23.0876543
  const num = parseInt(coord)
  if (Math.abs(num) > 1000000) {
    return num / 10000000
  }

  return parseFloat(coord)
}
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Pacote
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Busque e selecione o pacote para compra da viagem
    </p>

    <!-- Autocomplete de Pacotes -->
    <VAutocomplete
      v-model="selectedPacote"
      v-model:search="searchPacote"
      :items="pacotesDisponiveis"
      :loading="loadingPacotes"
      item-title="label"
      item-value="codpac"
      label="Buscar Pacote *"
      placeholder="Digite o código do pacote (ex: 3043368)"
      prepend-inner-icon="tabler-package"
      clearable
      return-object
      hide-no-data
      @update:search="buscarPacotes"
      @update:model-value="selecionarPacote"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem v-bind="itemProps">
          <template #prepend>
            <VIcon
              icon="tabler-package"
              :color="item.raw.raw?.sitpac === 'FECHADO' ? 'success' : 'warning'"
            />
          </template>

          <VListItemTitle>Pacote #{{ item.raw.raw?.codpac }}</VListItemTitle>

          <VListItemSubtitle>
            <VChip
              size="x-small"
              :color="item.raw.raw?.sitpac === 'FECHADO' ? 'success' : 'warning'"
              class="me-2"
            >
              {{ item.raw.raw?.sitpac }}
            </VChip>
            <span class="text-caption">
              Transportador: {{ item.raw.raw?.nomtrn }}
            </span>
          </VListItemSubtitle>
        </VListItem>
      </template>
    </VAutocomplete>

    <!-- Loading Entregas -->
    <VSkeletonLoader
      v-if="loadingEntregas"
      type="card"
      class="mt-6"
    />

    <!-- Card com Pacote Selecionado -->
    <VCard
      v-else-if="pacoteSelecionado"
      class="mt-6"
      variant="tonal"
      color="success"
    >
      <VCardItem>
        <template #prepend>
          <VIcon
            icon="tabler-package"
            color="success"
            size="32"
          />
        </template>

        <VCardTitle>Pacote #{{ pacoteSelecionado.codpac }}</VCardTitle>

        <VCardSubtitle>
          {{ pacoteSelecionado.nomtrn }} • {{ pacoteSelecionado.sitpac }}
        </VCardSubtitle>

        <template #append>
          <VBtn
            icon="tabler-x"
            size="small"
            variant="text"
            @click="limparPacote"
          />
        </template>
      </VCardItem>

      <VDivider />

      <VCardText>
        <!-- Estatísticas -->
        <VRow class="mb-4">
          <VCol cols="4">
            <div class="text-center">
              <div class="text-h5 text-primary">
                {{ estatisticasEntregas?.total }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Total
              </div>
            </div>
          </VCol>

          <VCol cols="4">
            <div class="text-center">
              <div class="text-h5 text-success">
                {{ estatisticasEntregas?.comGps }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Com GPS
              </div>
            </div>
          </VCol>

          <VCol cols="4">
            <div class="text-center">
              <div class="text-h5 text-info">
                {{ estatisticasEntregas?.percentualGps }}%
              </div>
              <div class="text-caption text-medium-emphasis">
                Cobertura
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Informações do Transportador -->
        <VDivider class="my-4" />

        <div class="d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-truck" size="small" color="primary" />
          <span class="text-body-2 font-weight-medium">
            Transportador:
          </span>
          <span class="text-body-2">
            {{ pacoteSelecionado.nomtrn }}
          </span>
        </div>

        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-calendar" size="small" color="primary" />
          <span class="text-body-2 font-weight-medium">
            Data Formação:
          </span>
          <span class="text-body-2">
            {{ pacoteSelecionado.datforpac }}
          </span>
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
          Pacote Obrigatório
        </div>
        <div class="text-caption">
          Busque e selecione um pacote para iniciar a compra da viagem.
        </div>
      </div>
    </VAlert>

    <!-- Aviso sobre GPS -->
    <VAlert
      v-if="pacoteSelecionado && estatisticasEntregas && estatisticasEntregas.semGps > 0"
      type="warning"
      variant="tonal"
      class="mt-4"
    >
      <template #prepend>
        <VIcon icon="tabler-alert-triangle" />
      </template>
      {{ estatisticasEntregas.semGps }} entrega(s) sem coordenadas GPS não aparecerão no mapa
    </VAlert>
  </div>
</template>
