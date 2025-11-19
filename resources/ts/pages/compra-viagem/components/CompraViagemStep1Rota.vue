<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { CompraViagemFormData, SemPararRota, Municipio } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: CompraViagemFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const loadingRotas = ref(false)
const loadingMunicipios = ref(false)
const rotasDisponiveis = ref<SemPararRota[]>([])
const searchRota = ref<string | null>(null)

// Computed
const rotaSelecionada = computed(() => props.formData.rotaPadrao.rota)
const municipios = computed(() => props.formData.rotaPadrao.municipios)

const isStepValid = computed(() => {
  return (
    rotaSelecionada.value !== null &&
    municipios.value.length >= 2
  )
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const buscarRotas = async (search: string) => {
  if (!search || search.length < 2) {
    rotasDisponiveis.value = []
    return
  }

  loadingRotas.value = true
  try {
    const response = await fetch(
      `http://localhost:8002/api/semparar-rotas?search=${encodeURIComponent(search)}&per_page=10`
    )
    const data = await response.json()

    if (data.success && data.data) {
      rotasDisponiveis.value = data.data
    }
  } catch (error) {
    console.error('Erro ao buscar rotas:', error)
  } finally {
    loadingRotas.value = false
  }
}

const selecionarRota = async (rota: SemPararRota) => {
  loadingMunicipios.value = true

  try {
    // Buscar municípios da rota
    const response = await fetch(
      `http://localhost:8002/api/semparar-rotas/${rota.sPararRotID}/municipios`
    )
    const data = await response.json()

    if (data.success && data.data) {
      const municipiosData: Municipio[] = data.data.municipios || []

      // Atualizar form data
      const updated: CompraViagemFormData = {
        ...props.formData,
        rotaPadrao: {
          rota,
          municipios: municipiosData
        },
        step1Completo: true
      }

      emit('update:formData', updated)
    }
  } catch (error) {
    console.error('Erro ao carregar municípios:', error)
  } finally {
    loadingMunicipios.value = false
  }
}

const limparRota = () => {
  const updated: CompraViagemFormData = {
    ...props.formData,
    rotaPadrao: {
      rota: null,
      municipios: []
    },
    step1Completo: false
  }

  emit('update:formData', updated)
  searchRota.value = null
}

// Lifecycle
watch(searchRota, (newSearch) => {
  if (newSearch && newSearch.length >= 2) {
    buscarRotas(newSearch)
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Rota Padrão
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Escolha a rota SemParar que será usada como base para a viagem
    </p>

    <!-- Autocomplete de Rotas -->
    <AppAutocomplete
      v-model="searchRota"
      :items="rotasDisponiveis"
      :loading="loadingRotas"
      item-title="desSPararRot"
      item-value="sPararRotID"
      label="Buscar Rota"
      placeholder="Digite o nome da rota (ex: ROTA 204)"
      prepend-inner-icon="tabler-route"
      clearable
      :return-object="true"
      @update:model-value="selecionarRota"
    >
      <template #item="{ props: itemProps, item }">
        <VListItem v-bind="itemProps">
          <template #prepend>
            <VIcon
              :icon="item.raw.flgCD ? 'tabler-building-warehouse' : 'tabler-route'"
              :color="item.raw.flgCD ? 'success' : 'primary'"
            />
          </template>

          <VListItemTitle>{{ item.raw.desSPararRot }}</VListItemTitle>

          <VListItemSubtitle>
            <VChip
              v-if="item.raw.flgCD"
              size="x-small"
              color="success"
              class="me-2"
            >
              CD
            </VChip>
            <VChip
              v-if="item.raw.flgRetorno"
              size="x-small"
              color="info"
            >
              Retorno
            </VChip>
            <span class="ms-2 text-caption">
              {{ item.raw.tempoViagem }} dias
            </span>
          </VListItemSubtitle>
        </VListItem>
      </template>
    </AppAutocomplete>

    <!-- Loading Municípios -->
    <VSkeletonLoader
      v-if="loadingMunicipios"
      type="card"
      class="mt-6"
    />

    <!-- Card com Rota Selecionada -->
    <VCard
      v-else-if="rotaSelecionada"
      class="mt-6"
      variant="tonal"
      color="primary"
    >
      <VCardItem>
        <template #prepend>
          <VIcon
            :icon="rotaSelecionada.flgCD ? 'tabler-building-warehouse' : 'tabler-route'"
            color="primary"
            size="32"
          />
        </template>

        <VCardTitle>{{ rotaSelecionada.desSPararRot }}</VCardTitle>

        <VCardSubtitle>
          ID: {{ rotaSelecionada.sPararRotID }} •
          {{ municipios.length }} municípios •
          {{ rotaSelecionada.tempoViagem }} dias
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
        <div class="d-flex flex-column gap-2">
          <div class="text-caption text-medium-emphasis mb-2">
            Sequência de Municípios:
          </div>

          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="(mun, index) in municipios"
              :key="mun.cdibge"
              size="small"
              color="primary"
              variant="tonal"
            >
              <template #prepend>
                <VAvatar
                  size="20"
                  color="primary"
                  class="text-caption"
                >
                  {{ index + 1 }}
                </VAvatar>
              </template>
              {{ mun.desMun }} - {{ mun.desEst }}
            </VChip>
          </div>
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
      Busque e selecione uma rota padrão SemParar para continuar
    </VAlert>

    <!-- Validação -->
    <VAlert
      v-if="rotaSelecionada && !isStepValid"
      type="warning"
      variant="tonal"
      class="mt-4"
    >
      <template #prepend>
        <VIcon icon="tabler-alert-triangle" />
      </template>
      A rota deve conter pelo menos 2 municípios
    </VAlert>
  </div>
</template>
