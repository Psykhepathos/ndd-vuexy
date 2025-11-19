<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { CompraViagemFormData, PacoteBasico, EntregaPacote } from '../types'

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
const pacotesDisponiveis = ref<PacoteBasico[]>([])
const searchPacote = ref<string | null>(null)

// Computed
const pacoteSelecionado = computed(() => props.formData.pacote.pacote)
const entregas = computed(() => props.formData.pacote.entregas)
const entregasComGps = computed(() => props.formData.pacote.entregas_com_gps)

const isStepValid = computed(() => {
  // Pacote é opcional - step sempre válido
  return true
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
}, { immediate: true })

// Methods
const buscarPacotes = async (search: string) => {
  if (!search || search.length < 2) {
    pacotesDisponiveis.value = []
    return
  }

  loadingPacotes.value = true
  try {
    const response = await fetch(
      `http://localhost:8002/api/pacotes?search=${encodeURIComponent(search)}&per_page=10`
    )
    const data = await response.json()

    if (data.success && data.data) {
      pacotesDisponiveis.value = data.data
    }
  } catch (error) {
    console.error('Erro ao buscar pacotes:', error)
  } finally {
    loadingPacotes.value = false
  }
}

const selecionarPacote = async (pacote: PacoteBasico) => {
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
      const todasEntregas: EntregaPacote[] = data.data.pedidos || []

      // Processar coordenadas GPS
      const entregasProcessadas = todasEntregas.map((entrega: any) => ({
        ...entrega,
        lat: processGpsCoordinate(entrega.gps_lat),
        lon: processGpsCoordinate(entrega.gps_lon),
        tipo: 'entrega' as const
      }))

      // Filtrar apenas entregas com GPS válido
      const entregasComGpsValido = entregasProcessadas.filter(
        e => e.lat !== null && e.lon !== null && !isNaN(e.lat) && !isNaN(e.lon)
      )

      // Atualizar form data
      const updated: CompraViagemFormData = {
        ...props.formData,
        pacote: {
          pacote,
          entregas: entregasProcessadas,
          entregas_com_gps: entregasComGpsValido
        },
        step2Completo: true
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
    step2Completo: true // Ainda válido pois é opcional
  }

  emit('update:formData', updated)
  searchPacote.value = null
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

// Lifecycle
watch(searchPacote, (newSearch) => {
  if (newSearch && newSearch.length >= 2) {
    buscarPacotes(newSearch)
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Pacote (Opcional)
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Adicione um pacote para incluir suas entregas na rota
    </p>

    <!-- Autocomplete de Pacotes -->
    <AppAutocomplete
      v-model="searchPacote"
      :items="pacotesDisponiveis"
      :loading="loadingPacotes"
      item-title="codpac"
      item-value="codpac"
      label="Buscar Pacote"
      placeholder="Digite o código do pacote (ex: 3043368)"
      prepend-inner-icon="tabler-package"
      clearable
      :return-object="true"
      @update:model-value="selecionarPacote"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem v-bind="itemProps">
          <template #prepend>
            <VIcon
              icon="tabler-package"
              :color="item.raw.sitpac === 'FECHADO' ? 'success' : 'warning'"
            />
          </template>

          <VListItemTitle>Pacote #{{ item.raw.codpac }}</VListItemTitle>

          <VListItemSubtitle>
            <VChip
              size="x-small"
              :color="item.raw.sitpac === 'FECHADO' ? 'success' : 'warning'"
              class="me-2"
            >
              {{ item.raw.sitpac }}
            </VChip>
            <span class="text-caption">
              {{ item.raw.datforpac }}
            </span>
          </VListItemSubtitle>
        </VListItem>
      </template>
    </AppAutocomplete>

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
          Status: {{ pacoteSelecionado.sitpac }} •
          {{ estatisticasEntregas?.total }} entregas
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
              <div class="text-h5 text-warning">
                {{ estatisticasEntregas?.semGps }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Sem GPS
              </div>
            </div>
          </VCol>

          <VCol cols="4">
            <div class="text-center">
              <div class="text-h5 text-primary">
                {{ estatisticasEntregas?.percentualGps }}%
              </div>
              <div class="text-caption text-medium-emphasis">
                Cobertura
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Lista de Entregas (primeiras 5) -->
        <div v-if="entregas.length > 0">
          <div class="text-caption text-medium-emphasis mb-2">
            Entregas ({{ entregasComGps.length }} serão visualizadas no mapa):
          </div>

          <VList density="compact">
            <VListItem
              v-for="(entrega, index) in entregas.slice(0, 5)"
              :key="entrega.numseqped"
              :class="{ 'opacity-50': !entrega.lat || !entrega.lon }"
            >
              <template #prepend>
                <VIcon
                  :icon="entrega.lat && entrega.lon ? 'tabler-map-pin' : 'tabler-map-pin-off'"
                  :color="entrega.lat && entrega.lon ? 'success' : 'warning'"
                  size="small"
                />
              </template>

              <VListItemTitle class="text-caption">
                {{ entrega.razcli }}
              </VListItemTitle>

              <VListItemSubtitle class="text-caption">
                {{ entrega.cidcli }} - {{ entrega.sigufs }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem v-if="entregas.length > 5">
              <VListItemTitle class="text-caption text-center text-medium-emphasis">
                + {{ entregas.length - 5 }} entregas...
              </VListItemTitle>
            </VListItem>
          </VList>
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
          Pacote Opcional
        </div>
        <div class="text-caption">
          Você pode pular esta etapa e comprar apenas a rota padrão, ou adicionar um pacote para incluir suas entregas no cálculo de pedágios.
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
