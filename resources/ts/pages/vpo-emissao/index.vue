<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { API_BASE_URL, apiFetch } from '@/config/api'

// Types - baseado na estrutura retornada por GET /api/vpo/emissao (VpoEmissao model)
interface VpoEmissao {
  id: number
  uuid: string
  codpac: number
  codtrn: number
  codmot: number | null
  rota_id: number
  rota_nome: string
  waypoints: Array<{
    lat: number
    lon: number
    cdibge: number
    tipo: string
    nome: string
  }>
  total_waypoints: number
  vpo_data: {
    flgautonomo: boolean
    cpf_cnpj: string
    antt_rntrc: string
    antt_nome: string
    antt_validade: string | null
    antt_status: string
    placa: string
    veiculo_tipo: string
    veiculo_modelo: string
    condutor_nome: string
    condutor_rg: string
    condutor_nome_mae: string
    condutor_data_nascimento: string | null
    endereco_rua: string
    endereco_bairro: string
    endereco_cidade: string
    endereco_estado: string
    contato_celular: string
    contato_email: string
  }
  fontes_dados: Record<string, boolean>
  score_qualidade: number
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled'
  ndd_request_xml: string | null
  ndd_response: Record<string, any> | null
  error_message: string | null
  error_code: string | null
  pracas_pedagio: Array<{
    cnp?: string
    codigo?: string
    codigoPraca?: string
    nomePraca?: string
    nome?: string
    valorPraca?: number
    valor?: number
    rodovia?: string
  }> | null
  total_pracas: number
  custo_total: number | null
  distancia_km: number | null
  tempo_minutos: number | null
  tentativas_polling: number
  requested_at: string | null
  polled_at: string | null
  completed_at: string | null
  failed_at: string | null
  usuario_id: number | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  updated_at: string
  usuario: { id: number; name: string } | null
}

interface EmissaoStats {
  total: number
  completed: number
  failed: number
  processing: number
  pending: number
  cancelled: number
  custo_total: number
}

// State
const loading = ref(false)
const loadingStats = ref(false)
const emissoes = ref<VpoEmissao[]>([])
const stats = ref<EmissaoStats | null>(null)
const search = ref('')
const statusFilter = ref<string | null>(null)
const totalEmissoes = ref(0)
const page = ref(1)
const perPage = ref(15)
const lastPage = ref(1)
const errorMessage = ref<string | null>(null)

// Selected emission for detail view
const selectedEmissao = ref<VpoEmissao | null>(null)
const showDetailDialog = ref(false)

// Computed - Headers seguindo padrão Vuexy (sem align)
const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '70px' },
  { title: 'Status', key: 'status', sortable: true, width: '140px' },
  { title: 'Pacote', key: 'codpac', sortable: true, width: '100px' },
  { title: 'Transportador', key: 'vpo_data.antt_nome', sortable: false },
  { title: 'Placa', key: 'vpo_data.placa', sortable: true, width: '110px' },
  { title: 'Rota', key: 'rota_nome', sortable: false },
  { title: 'Praças', key: 'total_pracas', sortable: true, width: '90px' },
  { title: 'Custo', key: 'custo_total', sortable: true, width: '130px' },
  { title: 'Score', key: 'score_qualidade', sortable: true, width: '90px' },
  { title: 'Data', key: 'created_at', sortable: true, width: '160px' },
  { title: 'Ações', key: 'actions', sortable: false, width: '100px' },
]

const statusOptions = [
  { title: 'Todos', value: null },
  { title: 'Pendente', value: 'pending' },
  { title: 'Processando', value: 'processing' },
  { title: 'Concluído', value: 'completed' },
  { title: 'Erro', value: 'failed' },
  { title: 'Cancelado', value: 'cancelled' },
]

// Methods
const carregarEmissoes = async () => {
  loading.value = true
  errorMessage.value = null

  try {
    const params = new URLSearchParams({
      page: page.value.toString(),
      per_page: perPage.value.toString(),
    })

    if (statusFilter.value) {
      params.append('status', statusFilter.value)
    }

    // Usar o endpoint correto do VpoEmissaoController
    const response = await apiFetch(`${API_BASE_URL}/api/vpo/emissao?${params}`)
    const data = await response.json()

    console.log('Emissões carregadas:', data)

    if (data.success) {
      emissoes.value = data.data
      totalEmissoes.value = data.pagination.total
      lastPage.value = data.pagination.last_page

      // Debug: verificar quais emissões estão em processing
      const processingEmissoes = data.data.filter((e: VpoEmissao) => e.status === 'processing')
      console.log('Emissões em processing:', processingEmissoes.length, processingEmissoes.map((e: VpoEmissao) => ({id: e.id, status: e.status})))
    } else {
      errorMessage.value = data.message || 'Erro ao carregar emissões'
    }
  } catch (error) {
    console.error('Erro ao carregar emissões:', error)
    errorMessage.value = 'Erro de conexão com o servidor'
  } finally {
    loading.value = false
  }
}

const carregarStats = async () => {
  loadingStats.value = true

  try {
    // Usar o endpoint de estatísticas do VpoEmissaoController
    const response = await apiFetch(`${API_BASE_URL}/api/vpo/emissao/statistics`)
    const data = await response.json()

    if (data.success) {
      stats.value = data.data
    }
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error)
  } finally {
    loadingStats.value = false
  }
}

const verDetalhes = (emissao: VpoEmissao) => {
  selectedEmissao.value = emissao
  showDetailDialog.value = true
}

// Estado para polling
const pollingId = ref<number | null>(null)
const pollingLoading = ref(false)

// Snackbar state
const snackbar = ref({
  show: false,
  message: '',
  color: 'info',
})

const showSnackbar = (message: string, color: string = 'info') => {
  snackbar.value = { show: true, message, color }
}

/**
 * Consulta o resultado de uma emissão em processamento
 * Faz polling no endpoint GET /api/vpo/emissao/{uuid}
 *
 * Se a emissão falhou com TIMEOUT, força um retry
 */
const consultarResultado = async (emissao: VpoEmissao) => {
  if (pollingLoading.value) return

  pollingLoading.value = true
  pollingId.value = emissao.id

  try {
    // Se falhou com TIMEOUT ou NDD_CARGO_ERROR, forçar retry para tentar novamente
    const forceRetry = emissao.status === 'failed' && ['TIMEOUT', 'NDD_CARGO_ERROR'].includes(emissao.error_code || '')
    const url = `${API_BASE_URL}/api/vpo/emissao/${emissao.uuid}${forceRetry ? '?force_retry=1' : ''}`

    console.log('Consultando resultado:', { uuid: emissao.uuid, forceRetry, url })

    const response = await fetch(url)
    const data = await response.json()

    console.log('Resultado do polling:', data)

    if (data.success && data.data) {
      // Atualizar a emissão na lista com os novos dados
      const index = emissoes.value.findIndex(e => e.id === emissao.id)
      if (index !== -1) {
        // Mesclar dados atualizados
        emissoes.value[index] = {
          ...emissoes.value[index],
          ...data.data,
        }
      }

      // Se está selecionado no dialog, atualizar também
      if (selectedEmissao.value?.id === emissao.id) {
        selectedEmissao.value = emissoes.value[index]
      }

      // Mostrar mensagem de status com snackbar
      const status = data.data.status
      if (status === 'completed') {
        showSnackbar(
          `Emissão concluída! Praças: ${data.data.total_pracas}, Custo: R$ ${data.data.custo_total?.toFixed(2) || '0.00'}`,
          'success'
        )
      } else if (status === 'failed') {
        showSnackbar(`Emissão falhou: ${data.data.error_message || 'Erro desconhecido'}`, 'error')
      } else if (status === 'processing') {
        showSnackbar(
          `Reprocessando... Tentativas: ${data.data.tentativas_polling || 0}. Aguarde a resposta do NDD Cargo.`,
          'info'
        )
      }

      // Recarregar estatísticas
      carregarStats()
    } else {
      showSnackbar(data.message || 'Erro ao consultar resultado', 'error')
    }
  } catch (error) {
    console.error('Erro ao consultar resultado:', error)
    showSnackbar('Erro de conexão ao consultar resultado', 'error')
  } finally {
    pollingLoading.value = false
    pollingId.value = null
  }
}

/**
 * Recarrega uma emissão específica da API
 */
const recarregarEmissao = async (uuid: string) => {
  try {
    const response = await apiFetch(`${API_BASE_URL}/api/vpo/emissao/${uuid}`)
    const data = await response.json()

    if (data.success) {
      // Recarregar a lista completa para garantir dados atualizados
      await carregarEmissoes()
      await carregarStats()
    }
  } catch (error) {
    console.error('Erro ao recarregar emissão:', error)
  }
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: 'warning',
    processing: 'info',
    completed: 'success',
    failed: 'error',
    cancelled: 'secondary',
  }
  return colors[status] || 'default'
}

const getStatusLabel = (status: string): string => {
  const labels: Record<string, string> = {
    pending: 'Pendente',
    processing: 'Processando',
    completed: 'Concluído',
    failed: 'Erro',
    cancelled: 'Cancelado',
  }
  return labels[status] || status
}

const getStatusIcon = (status: string): string => {
  const icons: Record<string, string> = {
    pending: 'ri-time-line',
    processing: 'ri-loader-4-line',
    completed: 'ri-check-line',
    failed: 'ri-error-warning-line',
    cancelled: 'ri-close-circle-line',
  }
  return icons[status] || 'ri-question-line'
}

const formatDate = (dateStr: string | null): string => {
  if (!dateStr) return '-'
  try {
    return new Date(dateStr).toLocaleString('pt-BR')
  } catch {
    return dateStr
  }
}

const formatDateShort = (dateStr: string | null): string => {
  if (!dateStr) return '-'
  try {
    return new Date(dateStr).toLocaleDateString('pt-BR')
  } catch {
    return dateStr
  }
}

const formatTime = (dateStr: string | null): string => {
  if (!dateStr) return ''
  try {
    return new Date(dateStr).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

const formatCurrency = (value: number | string | null): string => {
  if (value === null || value === undefined) return '-'
  const numValue = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(numValue)) return '-'
  return `R$ ${numValue.toFixed(2).replace('.', ',')}`
}

const formatDistance = (value: number | string | null): string => {
  if (value === null || value === undefined) return '-'
  const numValue = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(numValue)) return '-'
  return numValue.toFixed(1)
}

const getScoreColor = (score: number): string => {
  if (score >= 80) return 'success'
  if (score >= 50) return 'warning'
  return 'error'
}

// Watchers
const handleFilterChange = () => {
  page.value = 1
  carregarEmissoes()
}

// Lifecycle
onMounted(() => {
  carregarEmissoes()
  carregarStats()
})
</script>

<template>
  <VContainer fluid>
    <!-- Header -->
    <VRow>
      <VCol cols="12">
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <h4 class="text-h4 text-high-emphasis mb-1">
              Histórico de Emissões VPO
            </h4>
            <p class="text-body-1 text-medium-emphasis mb-0">
              Acompanhe todas as emissões de Vale Pedágio via NDD Cargo
            </p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="ri-add-line"
            :to="{ path: '/vpo-emissao/nova' }"
          >
            Nova Emissão
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Statistics Cards -->
    <VRow v-if="stats" class="mb-6">
      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-file-invoice" size="24" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-semibold text-high-emphasis">{{ stats.total }}</div>
              <div class="text-body-2 text-medium-emphasis">Total</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-circle-check" size="24" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-semibold text-success">{{ stats.completed }}</div>
              <div class="text-body-2 text-medium-emphasis">Concluídos</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="info"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-loader" size="24" class="animate-spin" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-semibold text-info">{{ stats.processing }}</div>
              <div class="text-body-2 text-medium-emphasis">Processando</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="warning"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-clock" size="24" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-semibold text-warning">{{ stats.pending }}</div>
              <div class="text-body-2 text-medium-emphasis">Pendentes</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="error"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-alert-triangle" size="24" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-semibold text-error">{{ stats.failed }}</div>
              <div class="text-body-2 text-medium-emphasis">Com Erro</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="6" sm="4" lg="2">
        <VCard class="stats-card">
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-cash" size="24" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-semibold text-success">{{ formatCurrency(stats.custo_total) }}</div>
              <div class="text-body-2 text-medium-emphasis">Custo Total</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Error Alert -->
    <VAlert
      v-if="errorMessage"
      type="error"
      closable
      class="mb-4"
      @click:close="errorMessage = null"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText class="py-4">
        <VRow align="center">
          <VCol cols="12" sm="6" md="4" lg="3">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              label="Filtrar por Status"
              placeholder="Selecione um status"
              clearable
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-filter"
              hide-details
              @update:model-value="handleFilterChange"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4" lg="3">
            <VTextField
              v-model="search"
              label="Buscar"
              placeholder="Pacote, placa, transportador..."
              clearable
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              hide-details
              @keyup.enter="carregarEmissoes"
            />
          </VCol>

          <VCol cols="auto" class="ms-auto">
            <div class="d-flex gap-3">
              <VBtn
                variant="tonal"
                color="secondary"
                :loading="loading"
                @click="carregarEmissoes"
              >
                <VIcon icon="tabler-refresh" start />
                Atualizar
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="perPage"
        v-model:page="page"
        :headers="headers"
        :items="emissoes"
        :items-length="totalEmissoes"
        :loading="loading"
        class="text-no-wrap"
        @update:options="carregarEmissoes"
      >
        <!-- ID Column -->
        <template #item.id="{ item }">
          <span class="text-body-1 font-weight-medium text-primary">#{{ item.id }}</span>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            size="small"
            label
          >
            {{ getStatusLabel(item.status) }}
          </VChip>
        </template>

        <!-- Pacote Column -->
        <template #item.codpac="{ item }">
          <span class="text-body-1 font-weight-medium">{{ item.codpac }}</span>
        </template>

        <!-- Transportador Column -->
        <template #item.vpo_data.antt_nome="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-1 text-high-emphasis">
              {{ item.vpo_data?.antt_nome || '-' }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ item.vpo_data?.cpf_cnpj || '-' }}
            </span>
          </div>
        </template>

        <!-- Placa Column -->
        <template #item.vpo_data.placa="{ item }">
          <VChip size="small" variant="outlined">
            {{ item.vpo_data?.placa || '-' }}
          </VChip>
        </template>

        <!-- Custo Column -->
        <template #item.custo_total="{ item }">
          <VChip
            v-if="item.custo_total !== null && item.custo_total !== undefined"
            color="success"
            size="small"
            variant="tonal"
          >
            {{ formatCurrency(item.custo_total) }}
          </VChip>
          <span v-else class="text-medium-emphasis">--</span>
        </template>

        <!-- Score Column -->
        <template #item.score_qualidade="{ item }">
          <VChip
            :color="getScoreColor(item.score_qualidade)"
            size="small"
            variant="tonal"
          >
            {{ item.score_qualidade }}%
          </VChip>
        </template>

        <!-- Total Praças Column -->
        <template #item.total_pracas="{ item }">
          <VChip
            v-if="item.total_pracas !== null && item.total_pracas !== undefined && item.total_pracas > 0"
            color="primary"
            size="small"
            variant="tonal"
          >
            {{ item.total_pracas }}
          </VChip>
          <span v-else class="text-medium-emphasis">--</span>
        </template>

        <!-- Date Column -->
        <template #item.created_at="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-medium">{{ formatDateShort(item.created_at) }}</span>
            <span class="text-caption text-medium-emphasis">{{ formatTime(item.created_at) }}</span>
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <!-- Botão Consultar Resultado (processando OU falhou com TIMEOUT/NDD_CARGO_ERROR) -->
            <VBtn
              v-if="item.status === 'processing' || (item.status === 'failed' && ['TIMEOUT', 'NDD_CARGO_ERROR'].includes(item.error_code || ''))"
              icon
              size="small"
              variant="text"
              :color="item.status === 'failed' ? 'warning' : 'info'"
              :loading="pollingLoading && pollingId === item.id"
              :disabled="pollingLoading && pollingId !== item.id"
              @click="consultarResultado(item)"
            >
              <VIcon :icon="item.status === 'failed' ? 'tabler-refresh-alert' : 'tabler-refresh'" />
              <VTooltip activator="parent" location="top">
                {{ item.status === 'failed' ? 'Reconsultar NDD Cargo' : 'Consultar Resultado' }}
              </VTooltip>
            </VBtn>

            <!-- Botão Ver Detalhes -->
            <VBtn
              icon
              size="small"
              variant="text"
              color="default"
              @click="verDetalhes(item)"
            >
              <VIcon icon="tabler-eye" />
              <VTooltip activator="parent" location="top">Ver detalhes</VTooltip>
            </VBtn>
          </div>
        </template>

        <!-- No data slot -->
        <template #no-data>
          <div class="text-center pa-4">
            <VIcon size="48" color="medium-emphasis" class="mb-2">
              ri-file-list-3-line
            </VIcon>
            <p class="text-body-1 text-medium-emphasis">
              Nenhuma emissão encontrada
            </p>
          </div>
        </template>

        <!-- Pagination (padrão Vuexy) -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="perPage"
            :total-items="totalEmissoes"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Detail Dialog -->
    <VDialog
      v-model="showDetailDialog"
      max-width="900"
      scrollable
    >
      <VCard v-if="selectedEmissao">
        <VCardTitle class="d-flex justify-space-between align-center pa-4">
          <span>Detalhes da Emissão VPO</span>
          <VChip
            :color="getStatusColor(selectedEmissao.status)"
            size="small"
            label
          >
            {{ getStatusLabel(selectedEmissao.status) }}
          </VChip>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4" style="max-height: 70vh; overflow-y: auto;">
          <!-- UUID e Identificação -->
          <VRow class="mb-4">
            <VCol cols="12">
              <div class="text-overline text-medium-emphasis mb-2">IDENTIFICAÇÃO</div>
              <VChip variant="outlined" class="mr-2">
                <VIcon start size="14">ri-fingerprint-line</VIcon>
                UUID: {{ selectedEmissao.uuid }}
              </VChip>
              <VChip variant="outlined" class="mr-2">
                <VIcon start size="14">ri-hashtag</VIcon>
                ID: {{ selectedEmissao.id }}
              </VChip>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Dados do Pacote/Transportador -->
          <VRow>
            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">PACOTE</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Código</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis font-weight-medium">{{ selectedEmissao.codpac }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Cod. Transportador</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.codtrn }}</span>
                  </template>
                </VListItem>
                <VListItem v-if="selectedEmissao.codmot">
                  <VListItemTitle>Cod. Motorista</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.codmot }}</span>
                  </template>
                </VListItem>
              </VList>
            </VCol>

            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">TRANSPORTADOR</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Nome</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.vpo_data?.antt_nome || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>CPF/CNPJ</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.vpo_data?.cpf_cnpj || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>RNTRC</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.vpo_data?.antt_rntrc || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Autônomo</VListItemTitle>
                  <template #append>
                    <VChip :color="selectedEmissao.vpo_data?.flgautonomo ? 'success' : 'info'" size="x-small">
                      {{ selectedEmissao.vpo_data?.flgautonomo ? 'Sim' : 'Não' }}
                    </VChip>
                  </template>
                </VListItem>
              </VList>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Veículo e Rota -->
          <VRow>
            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">VEÍCULO</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Placa</VListItemTitle>
                  <template #append>
                    <VChip variant="outlined" size="small">{{ selectedEmissao.vpo_data?.placa || '-' }}</VChip>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Modelo</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.vpo_data?.veiculo_modelo || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Tipo</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.vpo_data?.veiculo_tipo || '-' }}</span>
                  </template>
                </VListItem>
              </VList>
            </VCol>

            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">ROTA</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Nome</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.rota_nome }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>ID</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.rota_id }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Waypoints</VListItemTitle>
                  <template #append>
                    <VChip color="primary" size="small">{{ selectedEmissao.total_waypoints }}</VChip>
                  </template>
                </VListItem>
              </VList>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Praças de Pedágio -->
          <VRow>
            <VCol cols="12">
              <div class="text-overline text-medium-emphasis mb-2">PRAÇAS DE PEDÁGIO</div>
              <VRow>
                <VCol cols="12" md="4">
                  <VCard variant="outlined">
                    <VCardText class="text-center">
                      <div class="text-h5 text-primary">{{ selectedEmissao.total_pracas }}</div>
                      <div class="text-caption text-medium-emphasis">Praças</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="outlined">
                    <VCardText class="text-center">
                      <div class="text-h5 text-success">{{ formatCurrency(selectedEmissao.custo_total) }}</div>
                      <div class="text-caption text-medium-emphasis">Custo Total</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard variant="outlined">
                    <VCardText class="text-center">
                      <div class="text-h5">{{ formatDistance(selectedEmissao.distancia_km) }} km</div>
                      <div class="text-caption text-medium-emphasis">Distância</div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Lista de praças -->
              <div v-if="selectedEmissao.pracas_pedagio && selectedEmissao.pracas_pedagio.length > 0" class="mt-4">
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th>CNP</th>
                      <th>Nome da Praça</th>
                      <th>Rodovia</th>
                      <th class="text-right">Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(praca, index) in selectedEmissao.pracas_pedagio" :key="index">
                      <td class="text-caption">{{ praca.cnp || praca.codigo || praca.codigoPraca || '-' }}</td>
                      <td>{{ praca.nomePraca || praca.nome || '-' }}</td>
                      <td class="text-caption">{{ praca.rodovia || '-' }}</td>
                      <td class="text-right text-success">{{ formatCurrency(praca.valorPraca || praca.valor) }}</td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Timestamps e Auditoria -->
          <VRow>
            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">TIMESTAMPS</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Criado em</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ formatDate(selectedEmissao.created_at) }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Requisitado em</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ formatDate(selectedEmissao.requested_at) }}</span>
                  </template>
                </VListItem>
                <VListItem v-if="selectedEmissao.completed_at">
                  <VListItemTitle>Concluído em</VListItemTitle>
                  <template #append>
                    <span class="text-success">{{ formatDate(selectedEmissao.completed_at) }}</span>
                  </template>
                </VListItem>
                <VListItem v-if="selectedEmissao.failed_at">
                  <VListItemTitle>Falhou em</VListItemTitle>
                  <template #append>
                    <span class="text-error">{{ formatDate(selectedEmissao.failed_at) }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Tentativas de Polling</VListItemTitle>
                  <template #append>
                    <VChip size="x-small">{{ selectedEmissao.tentativas_polling }}</VChip>
                  </template>
                </VListItem>
              </VList>
            </VCol>

            <VCol cols="12" md="6">
              <div class="text-overline text-medium-emphasis mb-2">AUDITORIA</div>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <VListItemTitle>Usuário</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.usuario?.name || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>IP</VListItemTitle>
                  <template #append>
                    <span class="text-high-emphasis">{{ selectedEmissao.ip_address || '-' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Score Qualidade</VListItemTitle>
                  <template #append>
                    <VChip :color="getScoreColor(selectedEmissao.score_qualidade)" size="small">
                      {{ selectedEmissao.score_qualidade }}%
                    </VChip>
                  </template>
                </VListItem>
              </VList>
            </VCol>
          </VRow>

          <!-- Erro (se houver) -->
          <VAlert
            v-if="selectedEmissao.error_message"
            type="error"
            variant="tonal"
            class="mt-4"
          >
            <VAlertTitle>Mensagem de Erro</VAlertTitle>
            {{ selectedEmissao.error_message }}
            <div v-if="selectedEmissao.error_code" class="text-caption mt-1">
              Código: {{ selectedEmissao.error_code }}
            </div>
          </VAlert>

          <!-- NDD Response (se houver) -->
          <VExpansionPanels v-if="selectedEmissao.ndd_response" class="mt-4">
            <VExpansionPanel title="Resposta NDD Cargo">
              <VExpansionPanelText>
                <pre class="text-caption" style="white-space: pre-wrap; word-break: break-all;">{{ JSON.stringify(selectedEmissao.ndd_response, null, 2) }}</pre>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>

          <!-- NDD Request XML (expansível) -->
          <VExpansionPanels v-if="selectedEmissao.ndd_request_xml" class="mt-4">
            <VExpansionPanel title="XML Enviado para NDD Cargo">
              <VExpansionPanelText>
                <pre class="text-caption" style="white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto;">{{ selectedEmissao.ndd_request_xml }}</pre>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <!-- Botão Consultar Resultado (processando OU falhou com TIMEOUT/NDD_CARGO_ERROR) -->
          <VBtn
            v-if="selectedEmissao.status === 'processing' || (selectedEmissao.status === 'failed' && ['TIMEOUT', 'NDD_CARGO_ERROR'].includes(selectedEmissao.error_code || ''))"
            :color="selectedEmissao.status === 'failed' ? 'warning' : 'info'"
            variant="tonal"
            :loading="pollingLoading"
            @click="consultarResultado(selectedEmissao)"
          >
            <VIcon :icon="selectedEmissao.status === 'failed' ? 'tabler-refresh-alert' : 'tabler-refresh'" start />
            {{ selectedEmissao.status === 'failed' ? 'Reconsultar NDD Cargo' : 'Consultar Resultado NDD' }}
          </VBtn>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showDetailDialog = false"
          >
            Fechar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar para feedback -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="5000"
      location="top end"
    >
      {{ snackbar.message }}
      <template #actions>
        <VBtn
          variant="text"
          @click="snackbar.show = false"
        >
          Fechar
        </VBtn>
      </template>
    </VSnackbar>
  </VContainer>
</template>

<style scoped>
/* Stats Cards */
.stats-card {
  height: 100%;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.08);
}

/* Animação de spin para o ícone de processando */
.animate-spin {
  animation: spin 2s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
