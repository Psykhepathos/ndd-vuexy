<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { API_BASE_URL } from '@/config/api'

// ============================================================================
// TIPOS E INTERFACES
// ============================================================================
interface Viagem {
  cod_viagem: string
  data_compra: string
  placa: string
  rota_nome: string
  valor: number
  status: string | null
  cod_pac: number | null
  transportador: string | null
}

interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

// ============================================================================
// ESTADO
// ============================================================================
const router = useRouter()
const viagens = ref<Viagem[]>([])
const loading = ref(false)
const pagination = ref<Pagination>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

// Filtros
const dataInicio = ref('')
const dataFim = ref('')

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
// CONFIGURAÇÃO DA TABELA
// ============================================================================
const headers = [
  { title: 'CÓDIGO VIAGEM', key: 'cod_viagem', sortable: false, width: '140px' },
  { title: 'DATA COMPRA', key: 'data_compra', sortable: true, width: '120px' },
  { title: 'PLACA', key: 'placa', sortable: false, width: '100px' },
  { title: 'ROTA', key: 'rota_nome', sortable: false, width: '250px' },
  { title: 'PACOTE', key: 'cod_pac', sortable: false, width: '100px', class: 'd-none d-lg-table-cell' },
  { title: 'VALOR', key: 'valor', sortable: false, width: '120px' },
  { title: 'AÇÕES', key: 'actions', sortable: false, width: '180px' }
]

// ============================================================================
// COMPUTED
// ============================================================================
const totalPagesComputed = computed(() => pagination.value.last_page)

// ============================================================================
// MÉTODOS
// ============================================================================

/**
 * Busca viagens do período (Progress database - PUB.sPararViagem)
 */
const fetchViagens = async () => {
  if (!dataInicio.value || !dataFim.value) {
    showToast('Selecione um período de busca', 'warning')
    return
  }

  loading.value = true
  try {
    const response = await fetch(`${API_BASE_URL}/api/compra-viagem/viagens`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        data_inicio: dataInicio.value,
        data_fim: dataFim.value,
      }),
    })

    const data = await response.json()

    if (!data.success) {
      showToast(data.message || 'Erro ao buscar viagens', 'error')
      return
    }

    // Viagens já vêm formatadas do backend
    viagens.value = data.data || []

    pagination.value.total = viagens.value.length
    pagination.value.last_page = Math.ceil(viagens.value.length / pagination.value.per_page)

    showToast(`${viagens.value.length} viagens encontradas`, 'success')
  } catch (error: any) {
    console.error('Erro ao buscar viagens:', error)
    showToast(error.message || 'Erro ao buscar viagens', 'error')
  } finally {
    loading.value = false
  }
}

/**
 * Navega para página de nova compra
 */
const irParaNovaCompra = () => {
  router.push('/compra-viagem/nova')
}

/**
 * Navega para detalhes da viagem
 */
const verDetalhes = (codViagem: string) => {
  router.push(`/compra-viagem/${codViagem}`)
}

/**
 * Baixar recibo PDF via WhatsApp
 */
const baixarRecibo = async (codViagem: string) => {
  try {
    // TODO: Implementar modal para pedir telefone/email
    const telefone = prompt('Digite o número com DDD (ex: 31988887777)')
    if (!telefone) return

    const response = await fetch(`${API_BASE_URL}/api/semparar/gerar-recibo`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        cod_viagem: codViagem,
        telefone: `55${telefone}`,
        flg_imprime: false,
      }),
    })

    const data = await response.json()

    if (data.success) {
      showToast('Recibo enviado com sucesso!', 'success')
    } else {
      showToast(data.message || 'Erro ao gerar recibo', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao gerar recibo:', error)
    showToast(error.message || 'Erro ao gerar recibo', 'error')
  }
}

/**
 * Cancelar viagem
 */
const cancelarViagem = async (codViagem: string) => {
  if (!confirm(`Deseja realmente cancelar a viagem ${codViagem}?\n\n⚠️ Esta operação é IRREVERSÍVEL!`)) {
    return
  }

  try {
    const response = await fetch(`${API_BASE_URL}/api/semparar/cancelar-viagem`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        cod_viagem: codViagem,
      }),
    })

    const data = await response.json()

    if (data.success) {
      showToast('Viagem cancelada com sucesso', 'success')
      // Recarrega lista
      await fetchViagens()
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
const reemitirViagem = async (codViagem: string) => {
  const novaPlaca = prompt('Digite a nova placa (7 caracteres):')
  if (!novaPlaca || novaPlaca.length !== 7) {
    showToast('Placa inválida (deve ter 7 caracteres)', 'warning')
    return
  }

  try {
    const response = await fetch(`${API_BASE_URL}/api/semparar/reemitir-viagem`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        cod_viagem: codViagem,
        placa: novaPlaca.toUpperCase(),
      }),
    })

    const data = await response.json()

    if (data.success) {
      showToast('Viagem reemitida com sucesso', 'success')
      // Recarrega lista
      await fetchViagens()
    } else {
      showToast(data.message || 'Erro ao reemitir viagem', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao reemitir viagem:', error)
    showToast(error.message || 'Erro ao reemitir viagem', 'error')
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
const formatarData = (data: string) => {
  if (!data || data === '-') return '-'

  // Se já está em formato DD/MM/YYYY, retorna
  if (data.includes('/')) return data

  // Se está em formato ISO (YYYY-MM-DD), converte
  const [ano, mes, dia] = data.split('-')
  return `${dia}/${mes}/${ano}`
}

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
onMounted(() => {
  // Define período padrão (hoje)
  const hoje = new Date()
  dataInicio.value = hoje.toISOString().split('T')[0]
  dataFim.value = hoje.toISOString().split('T')[0]
})
</script>

<template>
  <div>
    <!-- Header com título e ações -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              Viagens SemParar
            </h4>
            <p class="text-body-1 mb-0">
              Consulte e gerencie suas viagens de pedágio
            </p>
          </div>

          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="irParaNovaCompra"
          >
            Nova Compra
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="dataInicio"
              label="Data Início"
              type="date"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="dataFim"
              label="Data Fim"
              type="date"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              prepend-icon="tabler-search"
              :loading="loading"
              @click="fetchViagens"
              block
            >
              Buscar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabela -->
    <VCard>
      <VCardText class="pa-0">
        <VDataTable
          :headers="headers"
          :items="viagens"
          :loading="loading"
          :items-per-page="pagination.per_page"
          hide-default-footer
          class="text-no-wrap"
        >
          <!-- Código da viagem -->
          <template #item.cod_viagem="{ item }">
            <div class="d-flex align-center gap-2">
              <VAvatar
                color="primary"
                variant="tonal"
                size="32"
              >
                <VIcon
                  icon="tabler-ticket"
                  size="18"
                />
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.cod_viagem }}
                </div>
              </div>
            </div>
          </template>

          <!-- Data -->
          <template #item.data_compra="{ item }">
            <div class="text-body-2">
              {{ formatarData(item.data_compra) }}
            </div>
          </template>

          <!-- Placa -->
          <template #item.placa="{ item }">
            <VChip
              color="secondary"
              variant="tonal"
              size="small"
            >
              {{ item.placa }}
            </VChip>
          </template>

          <!-- Rota -->
          <template #item.rota_nome="{ item }">
            <div class="text-body-2">
              {{ item.rota_nome }}
            </div>
          </template>

          <!-- Pacote -->
          <template #item.cod_pac="{ item }">
            <VChip
              v-if="item.cod_pac"
              color="info"
              variant="tonal"
              size="small"
            >
              #{{ item.cod_pac }}
            </VChip>
            <span
              v-else
              class="text-disabled"
            >
              -
            </span>
          </template>

          <!-- Valor -->
          <template #item.valor="{ item }">
            <div class="text-body-1 font-weight-semibold text-success">
              {{ formatarValor(item.valor) }}
            </div>
          </template>

          <!-- Ações -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <!-- Ver detalhes -->
              <VBtn
                icon
                variant="text"
                color="default"
                size="small"
                @click="verDetalhes(item.cod_viagem)"
              >
                <VIcon icon="tabler-eye" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Ver Detalhes
                </VTooltip>
              </VBtn>

              <!-- Baixar recibo -->
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="baixarRecibo(item.cod_viagem)"
              >
                <VIcon icon="tabler-download" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Baixar Recibo
                </VTooltip>
              </VBtn>

              <!-- Reemitir -->
              <VBtn
                icon
                variant="text"
                color="warning"
                size="small"
                @click="reemitirViagem(item.cod_viagem)"
              >
                <VIcon icon="tabler-refresh" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Reemitir
                </VTooltip>
              </VBtn>

              <!-- Cancelar -->
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="cancelarViagem(item.cod_viagem)"
              >
                <VIcon icon="tabler-x" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Cancelar
                </VTooltip>
              </VBtn>
            </div>
          </template>

          <!-- Empty state -->
          <template #no-data>
            <div class="text-center py-10">
              <VIcon
                icon="tabler-package-off"
                size="48"
                color="disabled"
                class="mb-4"
              />
              <p class="text-h6 text-disabled">
                Nenhuma viagem encontrada
              </p>
              <p class="text-body-2 text-disabled mb-4">
                Selecione um período e clique em "Buscar"
              </p>
            </div>
          </template>

          <!-- Loading -->
          <template #loading>
            <VProgressLinear
              indeterminate
              color="primary"
            />
          </template>
        </VDataTable>
      </VCardText>

      <!-- Footer com info -->
      <VDivider />
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3 pa-4">
        <p class="text-body-2 mb-0">
          <span class="font-weight-medium">{{ viagens.length }}</span>
          {{ viagens.length === 1 ? 'viagem encontrada' : 'viagens encontradas' }}
        </p>
      </VCardText>
    </VCard>

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
