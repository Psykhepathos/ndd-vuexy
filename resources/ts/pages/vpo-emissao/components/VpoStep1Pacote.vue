<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { VpoEmissaoFormData, PacoteVpo, TransportadorVpo } from '../types'
import { isEmpresa } from '../types'

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
const codpacInput = ref('')
const errorMessage = ref<string | null>(null)

// Computed
const pacoteSelecionado = computed(() => props.formData.pacote.pacote)
const transportador = computed(() => props.formData.pacote.transportador)

const isStepValid = computed(() => {
  return pacoteSelecionado.value !== null && transportador.value !== null
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods
const buscarPacote = async () => {
  const codpac = codpacInput.value.trim()

  if (!codpac) {
    errorMessage.value = 'Digite o código do pacote'
    return
  }

  // Aceitar apenas números
  const codpacNumerico = codpac.replace(/\D/g, '')
  if (!codpacNumerico) {
    errorMessage.value = 'Digite um código válido (apenas números)'
    return
  }

  loading.value = true
  errorMessage.value = null

  try {
    // Buscar pacote pelo código
    const response = await fetch(
      `http://localhost:8002/api/pacotes/${codpacNumerico}`
    )
    const data = await response.json()

    if (!data.success || !data.data) {
      errorMessage.value = data.message || `Pacote #${codpacNumerico} não encontrado`
      return
    }

    const pacote = data.data as PacoteVpo

    // Verificar se tem transportador
    if (!pacote.codtrn || pacote.codtrn === 0) {
      errorMessage.value = `Pacote #${codpacNumerico} não possui transportador vinculado`
      return
    }

    // Sincronizar transportador
    const syncResponse = await fetch('http://localhost:8002/api/vpo/sync/transportador', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codtrn: pacote.codtrn,
        placa: pacote.placa || null,
      }),
    })
    const syncData = await syncResponse.json()

    if (!syncData.success) {
      throw new Error(syncData.message || 'Erro ao sincronizar transportador')
    }

    const transportadorData = syncData.data as TransportadorVpo
    const transportadorIsEmpresa = isEmpresa(transportadorData.cpf_cnpj || '')

    // Atualizar form data
    const updated: VpoEmissaoFormData = {
      ...props.formData,
      pacote: {
        pacote: {
          codpac: pacote.codpac,
          descpac: pacote.descpac || '',
          codtrn: pacote.codtrn,
          nomtrn: pacote.nomtrn,
          sitpac: pacote.sitpac,
          datforpac: pacote.datforpac,
          placa: pacote.placa,
        },
        transportador: transportadorData,
      },
      motorista: {
        ...props.formData.motorista,
        isEmpresa: transportadorIsEmpresa,
        requerSelecaoMotorista: transportadorIsEmpresa,
      },
      veiculo: {
        ...props.formData.veiculo,
        veiculo: transportadorData.placa
          ? {
              placa: transportadorData.placa,
              descricao: '',
              tipo: transportadorData.veiculo_tipo || '',
              modelo: transportadorData.veiculo_modelo || '',
              eixos: 2,
              proprietario: transportadorData.condutor_nome || '',
              tag: null,
              status_semparar: null,
            }
          : null,
      },
      step1Completo: true,
      step2Completo: !transportadorIsEmpresa,
    }

    emit('update:formData', updated)
    codpacInput.value = ''

  } catch (error: any) {
    console.error('Erro ao buscar pacote:', error)
    errorMessage.value = error.message || 'Erro ao buscar pacote'
  } finally {
    loading.value = false
  }
}

const limparPacote = () => {
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    pacote: {
      pacote: null,
      transportador: null,
    },
    motorista: {
      isEmpresa: false,
      requerSelecaoMotorista: false,
      motoristas: [],
      motoristaSelecionado: null,
    },
    veiculo: {
      veiculo: null,
      veiculosDisponiveis: [],
    },
    step1Completo: false,
    step2Completo: false,
  }

  emit('update:formData', updated)
  codpacInput.value = ''
  errorMessage.value = null
}

const getScoreColor = (score: number): string => {
  if (score >= 90) return 'success'
  if (score >= 70) return 'info'
  if (score >= 50) return 'warning'
  return 'error'
}
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Pacote
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Digite o código do pacote para emissão do Vale Pedágio
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

    <!-- Input de Busca (quando não tem pacote selecionado) -->
    <div v-if="!pacoteSelecionado" class="mb-4">
      <VTextField
        v-model="codpacInput"
        label="Código do Pacote"
        placeholder="Ex: 3043368"
        prepend-inner-icon="tabler-package"
        :loading="loading"
        :disabled="loading"
        @keyup.enter="buscarPacote"
      >
        <template #append-inner>
          <VBtn
            color="primary"
            variant="flat"
            size="small"
            :loading="loading"
            :disabled="!codpacInput || loading"
            @click="buscarPacote"
          >
            Buscar
          </VBtn>
        </template>
      </VTextField>
    </div>

    <!-- Loading -->
    <VSkeletonLoader
      v-if="loading"
      type="card"
      class="mt-4"
    />

    <!-- Card com Pacote Selecionado -->
    <VCard
      v-else-if="pacoteSelecionado && transportador"
      variant="tonal"
      color="success"
    >
      <VCardItem>
        <template #prepend>
          <VIcon icon="tabler-package" color="success" size="32" />
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
        <!-- Info do Transportador -->
        <div class="d-flex align-center gap-2 mb-3">
          <VIcon
            :icon="formData.motorista.isEmpresa ? 'tabler-building' : 'tabler-user'"
            size="small"
            color="primary"
          />
          <span class="text-body-2 font-weight-medium">
            {{ formData.motorista.isEmpresa ? 'Empresa:' : 'Autônomo:' }}
          </span>
          <span class="text-body-2">
            {{ transportador.cpf_cnpj }}
          </span>
          <VChip
            :color="formData.motorista.isEmpresa ? 'info' : 'success'"
            size="x-small"
          >
            {{ formData.motorista.isEmpresa ? 'CNPJ' : 'CPF' }}
          </VChip>
        </div>

        <!-- Score de Qualidade -->
        <div class="d-flex align-center gap-2 mb-3">
          <VIcon icon="tabler-chart-bar" size="small" color="primary" />
          <span class="text-body-2 font-weight-medium">Score:</span>
          <VChip :color="getScoreColor(transportador.score_qualidade)" size="small">
            {{ transportador.score_qualidade }}%
          </VChip>
          <span class="text-caption text-medium-emphasis">
            {{ transportador.score_qualidade >= 70 ? 'Apto para VPO' : 'Dados incompletos' }}
          </span>
        </div>

        <!-- RNTRC -->
        <div v-if="transportador.antt_rntrc" class="d-flex align-center gap-2 mb-3">
          <VIcon icon="tabler-id" size="small" color="primary" />
          <span class="text-body-2 font-weight-medium">RNTRC:</span>
          <span class="text-body-2">{{ transportador.antt_rntrc }}</span>
        </div>

        <!-- Placa -->
        <div v-if="transportador.placa" class="d-flex align-center gap-2">
          <VIcon icon="tabler-car" size="small" color="primary" />
          <span class="text-body-2 font-weight-medium">Placa:</span>
          <VChip size="small" color="secondary">
            {{ transportador.placa }}
          </VChip>
        </div>
      </VCardText>

      <!-- Alerta de Campos Faltantes -->
      <VCardText v-if="transportador.campos_faltantes?.length > 0" class="pt-0">
        <VAlert type="warning" variant="tonal" density="compact">
          <template #prepend>
            <VIcon icon="tabler-alert-triangle" />
          </template>
          <div class="text-caption">
            <strong>Campos faltantes:</strong>
            {{ transportador.campos_faltantes.join(', ') }}
          </div>
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Alert quando nada selecionado -->
    <VAlert
      v-else-if="!loading"
      type="info"
      variant="tonal"
      class="mt-4"
    >
      <template #prepend>
        <VIcon icon="tabler-info-circle" />
      </template>
      <div>
        <div class="font-weight-medium mb-1">
          Pacote Obrigatório
        </div>
        <div class="text-caption">
          Digite o código do pacote e clique em "Buscar" ou pressione Enter.
        </div>
      </div>
    </VAlert>
  </div>
</template>
