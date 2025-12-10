<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { VpoEmissaoFormData, VeiculoVpo } from '../types'
import { formatPlaca } from '../types'

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
const validatingPlaca = ref(false)
const placaInput = ref('')
const eixosInput = ref(2)
const errorMessage = ref<string | null>(null)
const veiculosDisponiveis = ref<VeiculoVpo[]>([])

// Computed
const transportador = computed(() => props.formData.pacote.transportador)
const veiculoSelecionado = computed(() => props.formData.veiculo.veiculo)

const isStepValid = computed(() => {
  return veiculoSelecionado.value !== null
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const carregarVeiculos = async () => {
  if (!transportador.value) return

  loading.value = true
  errorMessage.value = null

  try {
    // Para empresas, buscar veículos do transportador
    if (props.formData.motorista.isEmpresa) {
      const response = await fetch(
        `http://localhost:8002/api/transportes/${transportador.value.codtrn}`
      )
      const data = await response.json()

      if (data.success && data.data?.veiculos) {
        veiculosDisponiveis.value = data.data.veiculos.map((v: any) => ({
          placa: v.numpla,
          descricao: v.descricao || '',
          tipo: v.tipcam || '',
          modelo: v.modvei || '',
          eixos: 2,
          proprietario: transportador.value?.condutor_nome || '',
          tag: null,
          status_semparar: null,
        }))

        // Atualizar formData
        const updated: VpoEmissaoFormData = {
          ...props.formData,
          veiculo: {
            ...props.formData.veiculo,
            veiculosDisponiveis: veiculosDisponiveis.value,
          },
        }
        emit('update:formData', updated)
      }
    } else {
      // Para autônomos, usar placa do transportador
      if (transportador.value.placa) {
        placaInput.value = transportador.value.placa
      }
    }
  } catch (error: any) {
    console.error('Erro ao carregar veículos:', error)
    errorMessage.value = error.message || 'Erro ao carregar veículos'
  } finally {
    loading.value = false
  }
}

const validarPlaca = async () => {
  const placa = placaInput.value.replace(/[^A-Z0-9]/gi, '').toUpperCase()

  if (!placa || placa.length !== 7) {
    errorMessage.value = 'Placa inválida. Use formato AAA1234 ou AAA1A23'
    return
  }

  validatingPlaca.value = true
  errorMessage.value = null

  try {
    // Validar placa no SemParar (status do veículo)
    const response = await fetch('http://localhost:8002/api/semparar/status-veiculo', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ placa }),
    })

    const data = await response.json()

    // Criar veículo mesmo se SemParar não encontrar (pode ser veículo novo)
    const veiculo: VeiculoVpo = {
      placa: formatPlaca(placa),
      descricao: data.data?.descricao || '',
      tipo: transportador.value?.veiculo_tipo || '',
      modelo: transportador.value?.veiculo_modelo || '',
      eixos: eixosInput.value,
      proprietario: transportador.value?.condutor_nome || '',
      tag: data.data?.tag || null,
      status_semparar: data.success ? 'ativo' : 'pendente',
    }

    // Atualizar formData
    const updated: VpoEmissaoFormData = {
      ...props.formData,
      veiculo: {
        ...props.formData.veiculo,
        veiculo: veiculo,
      },
      periodo: {
        ...props.formData.periodo,
        eixos: eixosInput.value,
      },
      step3Completo: true,
    }
    emit('update:formData', updated)

    if (!data.success) {
      // Avisar que placa não está cadastrada no SemParar, mas permitir continuar
      errorMessage.value = 'Aviso: Placa não encontrada no SemParar. Verifique se está cadastrada.'
    }
  } catch (error: any) {
    console.error('Erro ao validar placa:', error)
    errorMessage.value = error.message || 'Erro ao validar placa'
  } finally {
    validatingPlaca.value = false
  }
}

const selecionarVeiculo = (veiculo: VeiculoVpo) => {
  placaInput.value = veiculo.placa
  eixosInput.value = veiculo.eixos || 2
  validarPlaca()
}

const limparVeiculo = () => {
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    veiculo: {
      ...props.formData.veiculo,
      veiculo: null,
    },
    step3Completo: false,
  }
  emit('update:formData', updated)
  placaInput.value = ''
  errorMessage.value = null
}

// Lifecycle
onMounted(async () => {
  await carregarVeiculos()

  // Se já tem placa no formData (auto-preenchido), usar
  if (props.formData.veiculo.veiculo?.placa) {
    placaInput.value = props.formData.veiculo.veiculo.placa
    eixosInput.value = props.formData.veiculo.veiculo.eixos || 2
  } else if (transportador.value?.placa) {
    placaInput.value = transportador.value.placa
    // Auto-validar placa se já está preenchida
    if (placaInput.value && placaInput.value.length >= 7) {
      await validarPlaca()
    }
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Veículo
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Informe a placa e quantidade de eixos do veículo
    </p>

    <!-- Error/Warning Message -->
    <VAlert
      v-if="errorMessage"
      :type="errorMessage.startsWith('Aviso') ? 'warning' : 'error'"
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
      <!-- Veículos Disponíveis (para empresas) -->
      <div v-if="veiculosDisponiveis.length > 0" class="mb-6">
        <p class="text-body-2 font-weight-medium mb-3">
          Veículos cadastrados:
        </p>

        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="v in veiculosDisponiveis"
            :key="v.placa"
            :color="veiculoSelecionado?.placa === v.placa ? 'primary' : 'default'"
            :variant="veiculoSelecionado?.placa === v.placa ? 'elevated' : 'outlined'"
            class="cursor-pointer"
            @click="selecionarVeiculo(v)"
          >
            <VIcon icon="tabler-car" start />
            {{ v.placa }}
          </VChip>
        </div>
      </div>

      <!-- Input de Placa -->
      <VRow>
        <VCol cols="12" md="6">
          <VTextField
            v-model="placaInput"
            label="Placa do Veículo *"
            placeholder="AAA1234 ou AAA1A23"
            prepend-inner-icon="tabler-car"
            :disabled="validatingPlaca"
            @keyup.enter="validarPlaca"
          />
        </VCol>

        <VCol cols="12" md="6">
          <VSelect
            v-model="eixosInput"
            :items="[2, 3, 4, 5, 6, 7, 8, 9]"
            label="Quantidade de Eixos *"
            prepend-inner-icon="tabler-circles"
          />
        </VCol>
      </VRow>

      <!-- Botão Validar -->
      <VBtn
        color="primary"
        variant="tonal"
        class="mt-4"
        :loading="validatingPlaca"
        :disabled="!placaInput || validatingPlaca"
        @click="validarPlaca"
      >
        <VIcon icon="tabler-check" start />
        Validar Placa
      </VBtn>

      <!-- Veículo Selecionado -->
      <VCard
        v-if="veiculoSelecionado"
        class="mt-6"
        variant="tonal"
        color="success"
      >
        <VCardItem>
          <template #prepend>
            <VIcon icon="tabler-car" color="success" size="32" />
          </template>

          <VCardTitle>{{ veiculoSelecionado.placa }}</VCardTitle>

          <VCardSubtitle>
            {{ veiculoSelecionado.modelo || veiculoSelecionado.tipo || 'Veículo' }}
            • {{ veiculoSelecionado.eixos }} eixos
          </VCardSubtitle>

          <template #append>
            <VBtn
              icon="tabler-x"
              size="small"
              variant="text"
              @click="limparVeiculo"
            />
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <div class="d-flex flex-wrap gap-4">
            <!-- Status SemParar -->
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-badge" size="small" color="primary" />
              <span class="text-body-2 font-weight-medium">Status:</span>
              <VChip
                :color="veiculoSelecionado.status_semparar === 'ativo' ? 'success' : 'warning'"
                size="small"
              >
                {{ veiculoSelecionado.status_semparar || 'Verificando' }}
              </VChip>
            </div>

            <!-- Tag -->
            <div v-if="veiculoSelecionado.tag" class="d-flex align-center gap-2">
              <VIcon icon="tabler-tag" size="small" color="primary" />
              <span class="text-body-2 font-weight-medium">Tag:</span>
              <span class="text-body-2">{{ veiculoSelecionado.tag }}</span>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Info quando nada selecionado -->
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
            Veículo Obrigatório
          </div>
          <div class="text-caption">
            Informe a placa do veículo e clique em "Validar Placa".
            O sistema verificará se o veículo está cadastrado no SemParar.
          </div>
        </div>
      </VAlert>
    </template>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
