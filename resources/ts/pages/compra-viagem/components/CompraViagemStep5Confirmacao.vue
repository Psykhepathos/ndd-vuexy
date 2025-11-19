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
  const rota = props.formData.rotaPadrao.rota
  const pacote = props.formData.pacote.pacote
  const config = props.formData.configuracao
  const pedagios = props.formData.pedagios

  return {
    rota: rota ? {
      id: rota.sPararRotID,
      nome: rota.desSPararRot,
      municipios: props.formData.rotaPadrao.municipios.length,
      tempoViagem: rota.tempoViagem
    } : null,
    pacote: pacote ? {
      codigo: pacote.codpac,
      entregas: props.formData.pacote.entregas.length,
      entregasComGps: props.formData.pacote.entregas_com_gps.length
    } : null,
    veiculo: {
      placa: config.placa,
      eixos: config.eixos,
      dataInicio: new Date(config.dataInicio).toLocaleDateString('pt-BR'),
      dataFim: new Date(config.dataFim).toLocaleDateString('pt-BR'),
      itemFin1: config.itemFin1 || 'N/A'
    },
    pedagios: {
      quantidade: pedagios.pracas.length,
      valor: pedagios.valorTotal
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
      nome_rota: props.formData.pedagios.nomeRotaTemporaria,
      placa: props.formData.configuracao.placa,
      eixos: props.formData.configuracao.eixos,
      data_inicio: props.formData.configuracao.dataInicio,
      data_fim: props.formData.configuracao.dataFim,

      // Dados opcionais
      item_fin1: props.formData.configuracao.itemFin1 || '',

      // Dados para Progress (se houver pacote)
      ...(props.formData.pacote.pacote && {
        cod_pac: props.formData.pacote.pacote.codpac,
        s_parar_rot_id: props.formData.rotaPadrao.rota?.sPararRotID,
        valor_viagem: props.formData.pedagios.valorTotal,
        res_compra: 'sistema'
      })
    }

    console.log('🛒 Enviando compra:', payload)

    const response = await fetch('http://localhost:8002/api/semparar/comprar-viagem', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (!data.success || !data.data) {
      throw new Error(data.message || data.error || 'Erro ao comprar viagem')
    }

    codViagem.value = data.data.cod_viagem
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
        <!-- Rota Padrão -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-route" color="primary" />
              </template>

              <VCardTitle>Rota Padrão</VCardTitle>
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
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Pacote (se houver) -->
        <VCol cols="12" md="6">
          <VCard :color="resumo.pacote ? 'success' : 'default'" :variant="resumo.pacote ? 'tonal' : 'outlined'">
            <VCardItem>
              <template #prepend>
                <VIcon :icon="resumo.pacote ? 'tabler-package' : 'tabler-package-off'" :color="resumo.pacote ? 'success' : 'default'" />
              </template>

              <VCardTitle>Pacote</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <div v-if="resumo.pacote">
                <VList density="compact">
                  <VListItem>
                    <VListItemTitle class="text-caption text-medium-emphasis">
                      Código
                    </VListItemTitle>
                    <VListItemSubtitle class="text-body-2">
                      #{{ resumo.pacote.codigo }}
                    </VListItemSubtitle>
                  </VListItem>

                  <VListItem>
                    <VListItemTitle class="text-caption text-medium-emphasis">
                      Entregas
                    </VListItemTitle>
                    <VListItemSubtitle class="text-body-2">
                      {{ resumo.pacote.entregas }} total ({{ resumo.pacote.entregasComGps }} com GPS)
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </div>

              <div v-else class="text-center py-4 text-medium-emphasis">
                Nenhum pacote selecionado
              </div>
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
                    Eixos
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.eixos }} eixos
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Período
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.dataInicio }} até {{ resumo.veiculo.dataFim }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem v-if="resumo.veiculo.itemFin1 !== 'N/A'">
                  <VListItemTitle class="text-caption text-medium-emphasis">
                    Item Financeiro
                  </VListItemTitle>
                  <VListItemSubtitle class="text-body-2">
                    {{ resumo.veiculo.itemFin1 }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Pedágios -->
        <VCol cols="12" md="6">
          <VCard variant="tonal" color="warning">
            <VCardItem>
              <template #prepend>
                <VIcon icon="tabler-road" color="warning" />
              </template>

              <VCardTitle>Pedágios</VCardTitle>
            </VCardItem>

            <VDivider />

            <VCardText>
              <div class="d-flex justify-space-between align-center mb-4">
                <span class="text-body-2">Praças Identificadas:</span>
                <VChip color="warning" size="small">
                  {{ resumo.pedagios.quantidade }}
                </VChip>
              </div>

              <VDivider class="my-4" />

              <div class="d-flex justify-space-between align-center">
                <span class="text-h6">Valor Total:</span>
                <span class="text-h5 text-warning font-weight-bold">
                  R$ {{ resumo.pedagios.valor.toFixed(2) }}
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
