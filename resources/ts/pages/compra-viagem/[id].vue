<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { API_BASE_URL, apiPost } from '@/config/api'

// ============================================================================
// TIPOS
// ============================================================================
interface ViagemDetalhes {
  cod_viagem: string
  data_compra: string
  placa: string
  rota_nome: string
  valor: number
  eixos?: number
  data_inicio?: string
  data_fim?: string
  cod_pac?: number
  transportador?: string
  status?: string
  cancelado?: boolean
}

// ============================================================================
// COMPOSABLES
// ============================================================================
const route = useRoute()
const router = useRouter()

// ============================================================================
// ESTADO
// ============================================================================
const codViagem = ref(route.params.id as string)
const viagem = ref<ViagemDetalhes | null>(null)
const loading = ref(false)

// Dialogs
const showCancelarDialog = ref(false)
const showReemitirDialog = ref(false)
const showReciboDialog = ref(false)

// Reemissão
const novaPlaca = ref('')

// Recibo
const telefone = ref('')
const email = ref('')

// Snackbar
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error' | 'warning' | 'info'>('error')

const showToast = (message: string, color: 'success' | 'error' | 'warning' | 'info' = 'error') => {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// ============================================================================
// MÉTODOS
// ============================================================================

/**
 * Busca detalhes da viagem do Progress database
 */
const fetchViagem = async () => {
  loading.value = true
  try {
    // Busca últimos 3 meses para garantir que encontraremos a viagem
    const hoje = new Date()
    const tresMesesAtras = new Date()
    tresMesesAtras.setMonth(tresMesesAtras.getMonth() - 3)

    const response = await apiPost(`${API_BASE_URL}/api/compra-viagem/viagens`, {
      data_inicio: tresMesesAtras.toISOString().split('T')[0],
      data_fim: hoje.toISOString().split('T')[0],
    })
    const data = await response.json()

    if (!data.success) {
      showToast('Erro ao buscar viagem', 'error')
      return
    }

    // Procura viagem específica pelo código
    const viagemEncontrada = (data.data || []).find(
      (v: any) => v.cod_viagem === codViagem.value
    )

    if (!viagemEncontrada) {
      showToast('Viagem não encontrada', 'warning')
      setTimeout(() => router.push('/compra-viagem'), 2000)
      return
    }

    viagem.value = {
      cod_viagem: viagemEncontrada.cod_viagem,
      data_compra: viagemEncontrada.data_compra,
      placa: viagemEncontrada.placa,
      rota_nome: viagemEncontrada.rota_nome,
      valor: viagemEncontrada.valor,
      cod_pac: viagemEncontrada.cod_pac,
      transportador: viagemEncontrada.transportador,
      cancelado: viagemEncontrada.cancelado,
    }
  } catch (error: any) {
    console.error('Erro ao buscar viagem:', error)
    showToast(error.message || 'Erro ao buscar viagem', 'error')
  } finally {
    loading.value = false
  }
}

/**
 * Cancelar viagem
 */
const cancelarViagem = async () => {
  try {
    const response = await apiPost(`${API_BASE_URL}/api/semparar/cancelar-viagem`, {
      cod_viagem: codViagem.value,
    })
    const data = await response.json()

    if (data.success) {
      showToast('Viagem cancelada com sucesso', 'success')
      showCancelarDialog.value = false
      // Atualiza dados
      await fetchViagem()
    } else {
      showToast(data.message || 'Erro ao cancelar viagem', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao cancelar viagem:', error)
    showToast(error.message || 'Erro ao cancelar viagem', 'error')
  }
}

/**
 * Reemitir viagem
 */
const reemitirViagem = async () => {
  if (!novaPlaca.value || novaPlaca.value.length !== 7) {
    showToast('Placa inválida (deve ter 7 caracteres)', 'warning')
    return
  }

  try {
    const response = await apiPost(`${API_BASE_URL}/api/semparar/reemitir-viagem`, {
      cod_viagem: codViagem.value,
      placa: novaPlaca.value.toUpperCase(),
    })
    const data = await response.json()

    if (data.success) {
      showToast('Viagem reemitida com sucesso', 'success')
      showReemitirDialog.value = false
      novaPlaca.value = ''
      // Atualiza dados
      await fetchViagem()
    } else {
      showToast(data.message || 'Erro ao reemitir viagem', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao reemitir viagem:', error)
    showToast(error.message || 'Erro ao reemitir viagem', 'error')
  }
}

/**
 * Gerar recibo
 */
const gerarRecibo = async () => {
  if (!telefone.value) {
    showToast('Digite o telefone', 'warning')
    return
  }

  try {
    const response = await apiPost(`${API_BASE_URL}/api/semparar/gerar-recibo`, {
      cod_viagem: codViagem.value,
      telefone: `55${telefone.value.replace(/\D/g, '')}`,
      email: email.value || undefined,
      flg_imprime: false,
    })
    const data = await response.json()

    if (data.success) {
      showToast('Recibo enviado com sucesso!', 'success')
      showReciboDialog.value = false
      telefone.value = ''
      email.value = ''
    } else {
      showToast(data.message || 'Erro ao gerar recibo', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao gerar recibo:', error)
    showToast(error.message || 'Erro ao gerar recibo', 'error')
  }
}

/**
 * Formata valor para BRL
 */
const formatarValor = (valor: number) => {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(valor)
}

/**
 * Formata data DD/MM/YYYY
 */
const formatarData = (data?: string) => {
  if (!data || data === '-') return '-'

  // Se já está em formato DD/MM/YYYY, retorna
  if (data.includes('/')) return data

  // Se está em formato ISO (YYYY-MM-DD), converte
  const [ano, mes, dia] = data.split('-')
  return `${dia}/${mes}/${ano}`
}

/**
 * Voltar para listagem
 */
const voltar = () => {
  router.push('/compra-viagem')
}

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
onMounted(() => {
  fetchViagem()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <VBtn
          variant="text"
          color="default"
          prepend-icon="tabler-arrow-left"
          class="mb-2"
          @click="voltar"
        >
          Voltar
        </VBtn>
        <h4 class="text-h4">
          Viagem {{ codViagem }}
        </h4>
        <p class="text-body-1 mb-0">
          Detalhes e ações da viagem
        </p>
      </div>

      <div class="d-flex gap-2">
        <VBtn
          color="primary"
          prepend-icon="tabler-download"
          variant="tonal"
          @click="showReciboDialog = true"
        >
          Baixar Recibo
        </VBtn>
        <VBtn
          color="warning"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="showReemitirDialog = true"
        >
          Reemitir
        </VBtn>
        <VBtn
          color="error"
          prepend-icon="tabler-x"
          variant="tonal"
          @click="showCancelarDialog = true"
        >
          Cancelar
        </VBtn>
      </div>
    </div>

    <!-- Loading state -->
    <VCard v-if="loading">
      <VCardText class="text-center py-16">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <p class="text-body-1 mt-4">
          Carregando detalhes...
        </p>
      </VCardText>
    </VCard>

    <!-- Detalhes da viagem -->
    <VRow v-else-if="viagem">
      <!-- Coluna principal -->
      <VCol
        cols="12"
        md="8"
      >
        <!-- Informações principais -->
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="tabler-ticket"
                size="28"
              />
            </VAvatar>
            <div>
              <div class="text-h6">
                Informações da Viagem
              </div>
              <div class="text-caption text-medium-emphasis">
                Dados gerais
              </div>
            </div>
          </VCardTitle>

          <VDivider />

          <VCardText>
            <VList>
              <VListItem>
                <VListItemTitle class="text-body-2 text-disabled">
                  Código da Viagem
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  {{ viagem.cod_viagem }}
                </VListItemSubtitle>
              </VListItem>

              <VListItem>
                <VListItemTitle class="text-body-2 text-disabled">
                  Data da Compra
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  {{ formatarData(viagem.data_compra) }}
                </VListItemSubtitle>
              </VListItem>

              <VListItem>
                <VListItemTitle class="text-body-2 text-disabled">
                  Placa
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  <VChip
                    color="secondary"
                    variant="tonal"
                  >
                    {{ viagem.placa }}
                  </VChip>
                </VListItemSubtitle>
              </VListItem>

              <VListItem v-if="viagem.eixos">
                <VListItemTitle class="text-body-2 text-disabled">
                  Quantidade de Eixos
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  {{ viagem.eixos }} eixos
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <!-- Rota -->
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center gap-3">
            <VAvatar
              color="info"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="tabler-route"
                size="28"
              />
            </VAvatar>
            <div>
              <div class="text-h6">
                Rota
              </div>
              <div class="text-caption text-medium-emphasis">
                Informações da rota
              </div>
            </div>
          </VCardTitle>

          <VDivider />

          <VCardText>
            <VList>
              <VListItem>
                <VListItemTitle class="text-body-2 text-disabled">
                  Nome da Rota
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  {{ viagem.rota_nome }}
                </VListItemSubtitle>
              </VListItem>

              <VListItem v-if="viagem.data_inicio">
                <VListItemTitle class="text-body-2 text-disabled">
                  Período de Vigência
                </VListItemTitle>
                <VListItemSubtitle class="text-body-1 mt-1">
                  {{ formatarData(viagem.data_inicio) }} até {{ formatarData(viagem.data_fim) }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>

        <!-- Pacote associado -->
        <VCard v-if="viagem.cod_pac">
          <VCardTitle class="d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="tabler-package"
                size="28"
              />
            </VAvatar>
            <div>
              <div class="text-h6">
                Pacote Associado
              </div>
              <div class="text-caption text-medium-emphasis">
                Vinculado à viagem
              </div>
            </div>
          </VCardTitle>

          <VDivider />

          <VCardText>
            <VList>
              <VListItem>
                <VListItemTitle class="text-body-2 text-disabled">
                  Código do Pacote
                </VListItemTitle>
                <VListItemSubtitle class="text-h6 mt-1">
                  <VChip
                    color="info"
                    variant="tonal"
                  >
                    #{{ viagem.cod_pac }}
                  </VChip>
                </VListItemSubtitle>
              </VListItem>

              <VListItem v-if="viagem.transportador">
                <VListItemTitle class="text-body-2 text-disabled">
                  Transportador
                </VListItemTitle>
                <VListItemSubtitle class="text-body-1 mt-1">
                  {{ viagem.transportador }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Coluna lateral -->
      <VCol
        cols="12"
        md="4"
      >
        <!-- Valor -->
        <VCard class="mb-6">
          <VCardText class="text-center pa-8">
            <div class="text-body-2 text-disabled mb-2">
              Valor Total
            </div>
            <h1 class="text-h1 text-success font-weight-bold">
              {{ formatarValor(viagem.valor) }}
            </h1>
          </VCardText>
        </VCard>

        <!-- Status -->
        <VCard>
          <VCardTitle>Status</VCardTitle>
          <VCardText>
            <VChip
              :color="viagem.cancelado ? 'error' : 'success'"
              variant="tonal"
              block
              size="large"
            >
              <VIcon
                :icon="viagem.cancelado ? 'tabler-x' : 'tabler-check'"
                start
              />
              {{ viagem.cancelado ? 'CANCELADA' : 'ATIVA' }}
            </VChip>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Dialog: Cancelar Viagem -->
    <VDialog
      v-model="showCancelarDialog"
      max-width="500"
    >
      <VCard>
        <VCardText class="pa-6">
          <div class="text-center mb-6">
            <VAvatar
              color="error"
              variant="tonal"
              size="56"
              class="mb-4"
            >
              <VIcon
                icon="tabler-alert-triangle"
                size="32"
              />
            </VAvatar>
            <h5 class="text-h5 mb-2">
              Cancelar Viagem
            </h5>
            <p class="text-body-2 text-medium-emphasis">
              Tem certeza que deseja cancelar a viagem <strong>{{ codViagem }}</strong>?
            </p>
          </div>

          <VAlert
            type="warning"
            variant="tonal"
            prominent
          >
            <VAlertTitle>⚠️ Atenção</VAlertTitle>
            Esta ação é irreversível! A viagem será cancelada permanentemente no sistema SemParar.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showCancelarDialog = false"
          >
            Voltar
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            @click="cancelarViagem"
          >
            <VIcon
              icon="tabler-x"
              start
            />
            Confirmar Cancelamento
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Reemitir Viagem -->
    <VDialog
      v-model="showReemitirDialog"
      max-width="500"
    >
      <VCard>
        <VCardText class="pa-6">
          <div class="d-flex align-center gap-3 mb-6">
            <VAvatar
              color="warning"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="tabler-refresh"
                size="28"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Reemitir Viagem
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Altere a placa do veículo
              </p>
            </div>
          </div>

          <VTextField
            v-model="novaPlaca"
            label="Nova Placa"
            placeholder="ABC1234"
            maxlength="7"
            variant="outlined"
            autofocus
          >
            <template #prepend-inner>
              <VIcon icon="tabler-car" />
            </template>
          </VTextField>

          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            A viagem será reemitida com a nova placa informada.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showReemitirDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            @click="reemitirViagem"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Reemitir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Gerar Recibo -->
    <VDialog
      v-model="showReciboDialog"
      max-width="500"
    >
      <VCard>
        <VCardText class="pa-6">
          <div class="d-flex align-center gap-3 mb-6">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
            >
              <VIcon
                icon="tabler-download"
                size="28"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Baixar Recibo
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Envie o recibo por WhatsApp e Email
              </p>
            </div>
          </div>

          <VTextField
            v-model="telefone"
            label="Telefone (com DDD)"
            placeholder="31988887777"
            variant="outlined"
            class="mb-4"
            autofocus
          >
            <template #prepend-inner>
              <VIcon icon="tabler-phone" />
            </template>
          </VTextField>

          <VTextField
            v-model="email"
            label="Email (opcional)"
            placeholder="usuario@exemplo.com"
            type="email"
            variant="outlined"
          >
            <template #prepend-inner>
              <VIcon icon="tabler-mail" />
            </template>
          </VTextField>

          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            O recibo será enviado por WhatsApp (obrigatório) e Email (se informado).
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showReciboDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="gerarRecibo"
          >
            <VIcon
              icon="tabler-send"
              start
            />
            Enviar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      location="top end"
      variant="flat"
      :timeout="4000"
    >
      {{ snackbarText }}
    </VSnackbar>
  </div>
</template>
