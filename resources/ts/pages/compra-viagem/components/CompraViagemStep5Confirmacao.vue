<script setup lang="ts">
import { ref, computed } from 'vue'
import type { CompraViagemFormData } from '../types'

// Props & Emits
const props = defineProps<{
  formData: CompraViagemFormData
}>()

const emit = defineEmits<{
  'comprar': []
  'stepComplete': [complete: boolean]
}>()

// State
const loading = ref(false)
const success = ref(false)
const error = ref<string | null>(null)
const codViagem = ref<string | null>(null)

// Computed
const resumo = computed(() => {
  const pacote = props.formData.pacote.pacote
  const placa = props.formData.placa
  const rota = props.formData.rota.rota
  const preco = props.formData.preco
  const config = props.formData.configuracao

  return {
    pacote: pacote ? {
      codigo: pacote.codpac,
      entregas: props.formData.pacote.entregas.length,
      entregasComGps: props.formData.pacote.entregas_com_gps.length,
      transportador: pacote.nomtrn
    } : null,
    veiculo: {
      placa: placa.placa,
      descricao: placa.descricao,
      eixos: placa.eixos,
      proprietario: placa.proprietario,
      tag: placa.tag
    },
    rota: rota ? {
      id: rota.sPararRotID,
      nome: rota.desSPararRot,
      municipios: props.formData.rota.municipios.length,
      tempoViagem: rota.tempoViagem,
      modoCD: props.formData.rota.modoCD,
      modoRetorno: props.formData.rota.modoRetorno
    } : null,
    preco: {
      valor: preco.valor,
      numeroViagem: preco.numeroViagem,
      nomeRota: preco.nomeRotaSemParar,
      codRota: preco.codRotaSemParar,
      pracas: preco.pracas.length
    },
    periodo: {
      dataInicio: new Date(config.dataInicio).toLocaleDateString('pt-BR'),
      dataFim: new Date(config.dataFim).toLocaleDateString('pt-BR')
    }
  }
})

const isStepValid = computed(() => {
  return (
    props.formData.step1Completo &&
    props.formData.step2Completo &&
    props.formData.step3Completo &&
    props.formData.step4Completo
  )
})

// Methods
const confirmarCompra = async () => {
  loading.value = true
  error.value = null
  success.value = false

  try {
    // Preparar dados da compra
    const payload = {
      // Dados obrigatórios
      codpac: props.formData.pacote.pacote?.codpac,
      cod_rota: props.formData.rota.rota?.sPararRotID,
      placa: props.formData.placa.placa,
      qtd_eixos: props.formData.placa.eixos,
      data_inicio: props.formData.configuracao.dataInicio,
      data_fim: props.formData.configuracao.dataFim,

      // Dados do preço calculado
      nome_rota_semparar: props.formData.preco.nomeRotaSemParar,
      cod_rota_semparar: props.formData.preco.codRotaSemParar,
      valor_viagem: props.formData.preco.valor,

      // Modos da rota
      flgcd: props.formData.rota.modoCD,
      flgretorno: props.formData.rota.modoRetorno
    }

    console.log('🛒 Enviando compra:', payload)

    const response = await fetch('http://localhost:8002/api/compra-viagem/comprar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (!data.success) {
      throw new Error(data.message || data.error || 'Erro ao comprar viagem')
    }

    codViagem.value = data.data?.numero_viagem || data.data?.cod_viagem || 'N/A'
    success.value = true

    console.log(`✅ Viagem comprada com sucesso: ${codViagem.value}`)

    // Emitir evento de sucesso
    emit('comprar')

  } catch (err: any) {
    console.error('❌ Erro ao comprar viagem:', err)
    error.value = err.message || 'Erro desconhecido ao processar compra'
  } finally {
    loading.value = false
  }
}

const voltarParaInicio = () => {
  window.location.reload() // Recarregar página para limpar formulário
}

const irParaListagem = () => {
  window.location.href = '/compra-viagem'
}
</script>

<template>
  <div>
    <!-- Success State -->
    <div v-if="success">
      <VCard variant="tonal" color="success">
        <VCardText>
          <div class="d-flex flex-column align-center gap-4 py-8">
            <VIcon
              icon="tabler-circle-check"
              color="success"
              size="80"
            />

            <div class="text-center">
              <div class="text-h5 mb-2">
                Compra Realizada com Sucesso!
              </div>
              <div class="text-body-1 text-medium-emphasis mb-4">
                Sua viagem foi comprada e registrada no sistema
              </div>

              <VCard variant="outlined">
                <VCardText class="d-flex flex-column align-center gap-2">
                  <div class="text-caption text-medium-emphasis">
                    Código da Viagem
                  </div>
                  <div class="text-h4 text-success font-weight-bold">
                    {{ codViagem }}
                  </div>
                </VCardText>
              </VCard>
            </div>

            <div class="d-flex gap-4">
              <VBtn
                color="success"
                variant="tonal"
                prepend-icon="tabler-refresh"
                @click="voltarParaInicio"
              >
                Nova Compra
              </VBtn>

              <VBtn
                color="primary"
                prepend-icon="tabler-list"
                @click="irParaListagem"
              >
                Ver Viagens
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Normal State (Review) -->
    <div v-else>
      <!-- Header -->
      <h6 class="text-h6 font-weight-medium mb-2">
        Confirmação da Compra
      </h6>
      <p class="text-body-2 text-medium-emphasis mb-6">
        Revise todos os dados antes de confirmar a compra
      </p>

      <!-- Aviso Importante -->
      <VAlert
        type="warning"
        variant="tonal"
        prominent
        class="mb-6"
      >
        <template #prepend>
          <VIcon icon="tabler-alert-triangle" size="32" />
        </template>

        <VAlertTitle>Atenção: Operação Irreversível</VAlertTitle>
        <div class="text-caption">
          Ao confirmar, a viagem será efetivamente comprada no sistema SemParar.
          Esta operação NÃO pode ser desfeita. Verifique todos os dados cuidadosamente.
        </div>
      </VAlert>

      <!-- Resumo Completo -->
      <VRow>
        <!-- Pacote -->
        <VCol cols="12" md="6">
          <VCard variant="tonal" color="success">
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-package" color="success" />
              </template>

              <VCardTitle>Pacote</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <VList density="compact">
                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Código
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2 font-weight-bold">
                    #{{ resumo.pacote?.codigo }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Transportador
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.pacote?.transportador }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Entregas
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.pacote?.entregas }} total ({{ resumo.pacote?.entregasComGps }} com GPS)
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Veículo -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-car" color="info" />
              </template>

              <VCardTitle>Veículo</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <VList density="compact">
                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Placa
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2 font-weight-bold">
                    {{ resumo.veiculo.placa }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Descrição
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.descricao }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Eixos
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.eixos }} eixos
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Proprietário
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.proprietario }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Rota -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-route" color="primary" />
              </template>

              <VCardTitle>Rota SemParar</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <VList density="compact">
                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Nome
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.rota?.nome }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Municípios
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.rota?.municipios }} município(s)
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Tempo de Viagem
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.rota?.tempoViagem }} dia(s)
                  </VListItemSubtitle>
                </VListItem>

                <VListItem v-if="resumo.rota?.modoCD || resumo.rota?.modoRetorno">
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Modos
                  </VListItemTitle>
                  <VListItemSubtitle class="d-flex gap-2 mt-1">
                    <VChip v-if="resumo.rota?.modoCD" size="x-small" color="primary">CD</VChip>
                    <VChip v-if="resumo.rota?.modoRetorno" size="x-small" color="warning">Retorno</VChip>
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Preço -->
        <VCol cols="12" md="6">
          <VCard variant="tonal" color="warning">
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-cash" color="warning" />
              </template>

              <VCardTitle>Valor da Viagem</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <div class="text-center mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Valor Total
                </div>
                <div class="text-h3 text-warning font-weight-bold">
                  R$ {{ resumo.preco.valor.toFixed(2) }}
                </div>
              </div>

              <VDivider class="my-4" />

              <VList density="compact">
                <VListItem v-if="resumo.preco.numeroViagem">
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Número da Viagem
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.preco.numeroViagem }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Praças de Pedágio
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.preco.pracas }} praça(s)
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Período -->
        <VCol cols="12">
          <VCard variant="outlined">
            <VCardText>
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-calendar" color="info" />
                  <span class="text-body-2 font-weight-medium">Período da Viagem:</span>
                </div>
                <span class="text-body-2">
                  {{ resumo.periodo.dataInicio }} até {{ resumo.periodo.dataFim }}
                </span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Error -->
      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mt-6"
      >
        <template #prepend>
          <VIcon icon="tabler-alert-circle" />
        </template>

        <VAlertTitle>Erro na Compra</VAlertTitle>
        <div class="text-caption">{{ error }}</div>
      </VAlert>

      <!-- Botão de Confirmação -->
      <div class="mt-6 d-flex justify-center">
        <VBtn
          color="success"
          size="x-large"
          :loading="loading"
          :disabled="!isStepValid"
          prepend-icon="tabler-check"
          @click="confirmarCompra"
        >
          Confirmar e Comprar Viagem
        </VBtn>
      </div>
    </div>
  </div>
</template>
