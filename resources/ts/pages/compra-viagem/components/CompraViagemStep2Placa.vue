<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { apiPost } from '@/config/api'
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
const loadingPlaca = ref(false)
const showPlacaDialog = ref(false)
const placa = ref<string>('')
const descricaoVei = ref<string>('')
const eixos = ref<number>(2)
const proprietario = ref<string>('')
const tag = ref<string>('')

// Computed
const isStepValid = computed(() => {
  return props.formData.placa.placa !== '' && props.formData.step2Completo
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const validarPlaca = async () => {
  if (!placa.value || placa.value.length < 7) {
    return
  }

  loadingPlaca.value = true
  try {
    const response = await apiPost('/api/compra-viagem/validar-placa', { placa: placa.value })

    const data = await response.json()

    if (!data.success) {
      console.error('Erro ao validar placa:', data.error)
      return
    }

    // Preencher dados retornados pela API
    descricaoVei.value = data.data.descricao || ''
    eixos.value = data.data.eixos || 2
    proprietario.value = data.data.proprietario || ''
    tag.value = data.data.tag || ''

    // Mostrar dialog de confirmação
    showPlacaDialog.value = true

  } catch (error) {
    console.error('Erro ao validar placa:', error)
  } finally {
    loadingPlaca.value = false
  }
}

const confirmarPlaca = () => {
  if (eixos.value < 2 || eixos.value > 10) {
    console.warn('Eixos inválidos (mín: 2, máx: 10)')
    return
  }

  // Atualizar formData
  const updated: CompraViagemFormData = {
    ...props.formData,
    placa: {
      placa: placa.value,
      descricao: descricaoVei.value,
      eixos: eixos.value,
      proprietario: proprietario.value,
      tag: tag.value
    },
    step2Completo: true
  }

  emit('update:formData', updated)
  showPlacaDialog.value = false
}

const cancelarPlaca = () => {
  showPlacaDialog.value = false
  placa.value = ''
}

const limparPlaca = () => {
  placa.value = ''
  descricaoVei.value = ''
  eixos.value = 2
  proprietario.value = ''
  tag.value = ''

  const updated: CompraViagemFormData = {
    ...props.formData,
    placa: {
      placa: '',
      descricao: '',
      eixos: 2,
      proprietario: '',
      tag: ''
    },
    step2Completo: false
  }

  emit('update:formData', updated)
}

// Inicializar com dados existentes
if (props.formData.placa.placa) {
  placa.value = props.formData.placa.placa
  descricaoVei.value = props.formData.placa.descricao
  eixos.value = props.formData.placa.eixos
  proprietario.value = props.formData.placa.proprietario
  tag.value = props.formData.placa.tag
}
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Validação do Veículo
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Informe a placa do veículo para validação no sistema SemParar
    </p>

    <!-- Input de Placa -->
    <VTextField
      v-model="placa"
      label="Placa do Veículo *"
      placeholder="ABC1234"
      prepend-inner-icon="tabler-car"
      :loading="loadingPlaca"
      :disabled="props.formData.step2Completo"
      maxlength="7"
      @blur="validarPlaca"
      @keydown.enter="validarPlaca"
    >
      <template #append-inner>
        <VIcon
          v-if="props.formData.step2Completo"
          icon="tabler-check"
          color="success"
        />
      </template>
    </VTextField>

    <!-- Botão Validar -->
    <div v-if="!props.formData.step2Completo" class="mt-4">
      <VBtn
        color="primary"
        :loading="loadingPlaca"
        :disabled="!placa || placa.length < 7"
        prepend-icon="tabler-check"
        @click="validarPlaca"
      >
        Validar Placa
      </VBtn>
    </div>

    <!-- Card com Placa Validada -->
    <VCard
      v-if="props.formData.step2Completo"
      class="mt-6"
      variant="tonal"
      color="success"
    >
      <VCardItem>
        <template #prepend>
          <VIcon
            icon="tabler-car"
            color="success"
            size="32"
          />
        </template>

        <VCardTitle>Placa Validada</VCardTitle>

        <VCardSubtitle>
          {{ props.formData.placa.placa }} • {{ props.formData.placa.eixos }} eixos
        </VCardSubtitle>

        <template #append>
          <VBtn
            icon="tabler-x"
            size="small"
            variant="text"
            @click="limparPlaca"
          />
        </template>
      </VCardItem>

      <VDivider />

      <VCardText>
        <VList density="compact">
          <VListItem>
            <template #prepend>
              <VIcon
                icon="tabler-truck"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-caption text-medium-emphasis">
              Descrição
            </VListItemTitle>
            <VListItemSubtitle class="text-body-2 mt-1">
              {{ props.formData.placa.descricao }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem>
            <template #prepend>
              <VIcon
                icon="tabler-user"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-caption text-medium-emphasis">
              Proprietário
            </VListItemTitle>
            <VListItemSubtitle class="text-body-2 mt-1">
              {{ props.formData.placa.proprietario }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem>
            <template #prepend>
              <VIcon
                icon="tabler-id-badge"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-caption text-medium-emphasis">
              Tag SemParar
            </VListItemTitle>
            <VListItemSubtitle class="text-body-2 mt-1">
              {{ props.formData.placa.tag }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem>
            <template #prepend>
              <VIcon
                icon="tabler-settings"
                class="me-2"
              />
            </template>
            <VListItemTitle class="text-caption text-medium-emphasis">
              Eixos
            </VListItemTitle>
            <VListItemSubtitle class="text-body-2 mt-1">
              {{ props.formData.placa.eixos }} eixos
            </VListItemSubtitle>
          </VListItem>
        </VList>
      </VCardText>
    </VCard>

    <!-- Dialog de Confirmação -->
    <VDialog
      v-model="showPlacaDialog"
      max-width="600"
    >
      <VCard>
        <VCardText class="pa-6">
          <!-- Header com ícone -->
          <div class="d-flex align-center mb-6">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
              class="me-4"
            >
              <VIcon
                icon="tabler-truck"
                size="28"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Dados do Veículo
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Confirme as informações retornadas pelo SemParar
              </p>
            </div>
          </div>

          <VDivider class="mb-6" />

          <!-- Informações do veículo -->
          <VList class="mb-4">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-car"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Descrição
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ descricaoVei }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-user"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Proprietário
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ proprietario }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-id-badge"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Tag SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ tag }}
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <!-- Eixos editável -->
          <VCard
            variant="tonal"
            color="primary"
            class="pa-4"
          >
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-settings"
                  class="me-3"
                  size="24"
                />
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    Quantidade de Eixos
                  </div>
                  <div class="text-caption">
                    Ajuste se necessário
                  </div>
                </div>
              </div>
              <VTextField
                v-model.number="eixos"
                type="number"
                min="2"
                max="10"
                density="compact"
                style="max-width: 100px"
                variant="outlined"
              />
            </div>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="cancelarPlaca"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="confirmarPlaca"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
