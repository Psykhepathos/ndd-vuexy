<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { VpoEmissaoFormData, PacoteVpo, TransportadorVpo } from '../types'
import { isEmpresa } from '../types'
import { apiFetch, getApiUrl } from '@/config/api'

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
    const response = await apiFetch(
      getApiUrl(`/pacotes/${codpacNumerico}`)
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
    const syncResponse = await apiFetch(getApiUrl(`/vpo/sync/transportador`), {
      method: 'POST',
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

    // Carregar itinerário do pacote (entregas com GPS)
    let entregas: Array<{
      numseqped: number
      razcli: string
      cidcli: string
      sigufs: string
      lat: number | null
      lon: number | null
    }> = []

    try {
      const itinerarioResponse = await apiFetch(getApiUrl(`/pacotes/itinerario`), {
        method: 'POST',
        body: JSON.stringify({ codPac: pacote.codpac }),
      })
      const itinerarioData = await itinerarioResponse.json()

      if (itinerarioData.success && itinerarioData.data?.pedidos) {
        // Processar GPS - API já retorna em formato decimal
        const processGps = (gpsValue: number | string | null): number | null => {
          if (gpsValue === null || gpsValue === undefined) return null
          const value = typeof gpsValue === 'string' ? parseFloat(gpsValue) : gpsValue
          if (isNaN(value) || value === 0) return null
          return value
        }

        // Filtrar pedidos com GPS válido
        // Campos da API: seqent, razcli, desmun, uf, gps_lat, gps_lon
        const pedidosComGps = itinerarioData.data.pedidos
          .map((p: any) => ({
            numseqped: p.seqent || p.numseqped || 0,
            razcli: (p.razcli || 'Cliente').trim(),
            cidcli: (p.desmun || p.cidcli || '').trim(),
            sigufs: p.uf || p.sigufs || '',
            lat: processGps(p.gps_lat),
            lon: processGps(p.gps_lon),
          }))
          .filter((p: any) => p.lat !== null && p.lon !== null)

        // Pegar apenas primeira e última entrega
        if (pedidosComGps.length > 0) {
          const primeira = pedidosComGps[0]
          const ultima = pedidosComGps[pedidosComGps.length - 1]
          entregas = primeira.numseqped === ultima.numseqped
            ? [primeira]
            : [primeira, ultima]

          console.log(`✅ Entregas carregadas: ${entregas.length} (de ${pedidosComGps.length} com GPS)`)
        }
      }
    } catch (error) {
      console.warn('Não foi possível carregar entregas do pacote:', error)
    }

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
      rota: {
        ...props.formData.rota,
        entregas: entregas,  // Primeira e última entrega do pacote
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
