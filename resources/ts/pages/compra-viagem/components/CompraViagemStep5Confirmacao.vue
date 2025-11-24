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

    const response = await fetch(`${window.location.origin}/api/compra-viagem/comprar`, {
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
      <p class="text-body-2 text-medium-emphasis mb-4">
        Revise os dados antes de confirmar
      </p>

      <!-- ========== PARTE 1: RESUMO COMPACTO + BOTÃO (Sempre visível) ========== -->
      <VCard class="mb-4">
        <VCardText class="pa-4">
          <!-- Resumo em Tabela Compacta (2 colunas) -->
          <VRow dense>
            <!-- Coluna 1 -->
            <VCol cols="12" md="6">
              <VList density="compact" class="py-0">
                <!-- Pacote -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-package" color="success" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    Pacote #{{ resumo.pacote?.codigo }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ resumo.pacote?.entregas }} entregas
                  </VListItemSubtitle>
                </VListItem>

                <!-- Placa -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-car" color="info" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ resumo.veiculo.placa }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ resumo.veiculo.eixos }} eixos • {{ resumo.veiculo.proprietario }}
                  </VListItemSubtitle>
                </VListItem>

                <!-- Rota -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-route" color="primary" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ resumo.rota?.nome }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ resumo.rota?.municipios }} municípios • {{ resumo.rota?.tempoViagem }} dias
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>

            <!-- Coluna 2 -->
            <VCol cols="12" md="6">
              <VList density="compact" class="py-0">
                <!-- Período -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-calendar" color="warning" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    Período
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ resumo.periodo.dataInicio }} - {{ resumo.periodo.dataFim }}
                  </VListItemSubtitle>
                </VListItem>

                <!-- Pedágios -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-road" color="warning" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    Pedágios
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ resumo.preco.pracas }} praça(s)
                  </VListItemSubtitle>
                </VListItem>

                <!-- Valor Total (Destacado) -->
                <VListItem class="px-0" min-height="32">
                  <template #prepend>
                    <VIcon icon="tabler-cash" color="success" size="small" class="me-3" />
                  </template>
                  <VListItemTitle class="text-h6 text-success font-weight-bold">
                    R$ {{ resumo.preco.valor.toFixed(2) }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    Valor Total
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Botão de Confirmação (Sempre visível, sem scroll) -->
          <div class="d-flex justify-center">
            <VBtn
              color="success"
              size="x-large"
              :loading="loading"
              :disabled="!isStepValid"
              prepend-icon="tabler-check"
              class="px-8"
              @click="confirmarCompra"
            >
              Confirmar e Comprar Viagem
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- ========== PARTE 2: DETALHES COMPLETOS (Colapsável, opcional) ========== -->
      <VExpansionPanels
        variant="accordion"
        class="mb-4"
      >
        <VExpansionPanel>
          <VExpansionPanelTitle class="text-body-2">
            <VIcon icon="tabler-list-details" size="small" class="me-2" />
            <span class="font-weight-medium">Ver Detalhes Completos</span>
          </VExpansionPanelTitle>

          <VExpansionPanelText>
            <VRow>
              <!-- Pacote -->
              <VCol cols="12" md="6">
                <VCard variant="tonal" color="success">
                  <VCardItem class="pb-2">
                    <template #prepend>
                      <VIcon icon="tabler-package" size="small" color="success" />
                    </template>
                    <VCardTitle class="text-body-1">Pacote</VCardTitle>
                  </VCardItem>

                  <VDivider />

                  <VCardText class="pa-3">
                    <VList density="compact" class="py-0">
                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Código</VListItemTitle>
                        <VListItemSubtitle class="text-caption font-weight-bold">#{{ resumo.pacote?.codigo }}</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Transportador</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.pacote?.transportador }}</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Entregas</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.pacote?.entregas }} ({{ resumo.pacote?.entregasComGps }} GPS)</VListItemSubtitle>
                      </VListItem>
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Veículo -->
              <VCol cols="12" md="6">
                <VCard>
                  <VCardItem class="pb-2">
                    <template #prepend>
                      <VIcon icon="tabler-car" size="small" color="info" />
                    </template>
                    <VCardTitle class="text-body-1">Veículo</VCardTitle>
                  </VCardItem>

                  <VDivider />

                  <VCardText class="pa-3">
                    <VList density="compact" class="py-0">
                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Placa</VListItemTitle>
                        <VListItemSubtitle class="text-caption font-weight-bold">{{ resumo.veiculo.placa }}</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Descrição</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.veiculo.descricao }}</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Eixos / Proprietário</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.veiculo.eixos }} eixos • {{ resumo.veiculo.proprietario }}</VListItemSubtitle>
                      </VListItem>
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>

              <!-- Rota -->
              <VCol cols="12" md="6">
                <VCard>
                  <VCardItem class="pb-2">
                    <template #prepend>
                      <VIcon icon="tabler-route" size="small" color="primary" />
                    </template>
                    <VCardTitle class="text-body-1">Rota SemParar</VCardTitle>
                  </VCardItem>

                  <VDivider />

                  <VCardText class="pa-3">
                    <VList density="compact" class="py-0">
                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Nome</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.rota?.nome }}</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Municípios / Tempo</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.rota?.municipios }} municípios • {{ resumo.rota?.tempoViagem }} dias</VListItemSubtitle>
                      </VListItem>

                      <VListItem v-if="resumo.rota?.modoCD || resumo.rota?.modoRetorno" class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Modos</VListItemTitle>
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
                  <VCardItem class="pb-2">
                    <template #prepend>
                      <VIcon icon="tabler-cash" size="small" color="warning" />
                    </template>
                    <VCardTitle class="text-body-1">Valor</VCardTitle>
                  </VCardItem>

                  <VDivider />

                  <VCardText class="pa-3">
                    <div class="text-center mb-3">
                      <div class="text-caption text-medium-emphasis mb-1">Valor Total</div>
                      <div class="text-h5 text-warning font-weight-bold">R$ {{ resumo.preco.valor.toFixed(2) }}</div>
                    </div>

                    <VDivider class="my-2" />

                    <VList density="compact" class="py-0">
                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Praças de Pedágio</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.preco.pracas }} praça(s)</VListItemSubtitle>
                      </VListItem>

                      <VListItem class="px-0" min-height="28">
                        <VListItemTitle class="text-caption text-medium-emphasis">Período</VListItemTitle>
                        <VListItemSubtitle class="text-caption">{{ resumo.periodo.dataInicio }} - {{ resumo.periodo.dataFim }}</VListItemSubtitle>
                      </VListItem>
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>

      <!-- Error -->
      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mt-4"
      >
        <template #prepend>
          <VIcon icon="tabler-alert-circle" />
        </template>

        <VAlertTitle>Erro na Compra</VAlertTitle>
        <div class="text-caption">{{ error }}</div>
      </VAlert>
    </div>
  </div>
</template>
