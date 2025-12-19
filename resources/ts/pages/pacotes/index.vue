<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { apiFetch, getApiUrl } from '@/config/api'
import { showError } from '@/utils/api'

// Interface para tipagem
interface Pacote {
  codpac: number
  datforpac: string
  horforpac: string
  codtrn: number
  codmot: number
  numpla: string
  valpac: number
  volpac: number
  pespac: number
  sitpac: string
  codrot: string
  nroped: number
  nomtrn: string
  flg_tcd: number
}

interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
  has_more_pages: boolean
}

// Estados reativos
const pacotes = ref<Pacote[]>([])
const loading = ref(false)
const loadingTransportadores = ref(false)
const loadingRotas = ref(false)
const transportadoresOptions = ref<Array<{title: string, value: number}>>([])
const rotasOptions = ref<Array<{title: string, value: string}>>([])
const pagination = ref<Pagination>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 1,
  to: 15,
  has_more_pages: false
})

// Filtros
const search = ref('')
const searchCodigo = ref('')
const searchTransportador = ref('')
const selectedTransportador = ref<number | null>(null)
const searchRota = ref('')
const selectedRota = ref<string | null>(null)
const selectedSituacao = ref('')
const apenasRecentes = ref(true)
const dataInicio = ref('')
const dataFim = ref('')

// Router
const router = useRouter()

// Opções de situação
const situacaoOptions = [
  { title: 'Todas', value: '' },
  { title: 'Urgente (U)', value: 'U' },
  { title: 'Marcada (M)', value: 'M' },
  { title: 'Em Separação (S)', value: 'S' },
  { title: 'Aguardando (A)', value: 'A' },
  { title: 'Finalizada (F)', value: 'F' },
  { title: 'Vazia', value: ' ' }
]

// Headers da tabela
const headers = [
  { title: 'CÓDIGO', key: 'codpac', sortable: true, width: '120px' },
  { title: 'DATA', key: 'datforpac', sortable: true, width: '100px' },
  { title: 'TRANSPORTADOR', key: 'nomtrn', sortable: false, width: '250px' },
  { title: 'PLACA', key: 'numpla', sortable: false, width: '90px', class: 'd-none d-md-table-cell' },
  { title: 'ROTA', key: 'codrot', sortable: false, width: '80px', class: 'd-none d-lg-table-cell' },
  { title: 'TCD', key: 'flg_tcd', sortable: false, width: '60px', class: 'd-none d-lg-table-cell' },
  { title: 'VALOR', key: 'valpac', sortable: true, width: '120px' },
  { title: 'PED', key: 'nroped', sortable: true, width: '60px', class: 'd-none d-sm-table-cell' },
  { title: 'SITUAÇÃO', key: 'sitpac', sortable: false, width: '100px', class: 'd-none d-md-table-cell' },
  { title: 'AÇÕES', key: 'actions', sortable: false, width: '100px' }
]

// Buscar transportadores para o autocomplete
const fetchTransportadores = async (searchTerm: string = '') => {
  if (searchTerm.length < 2 && searchTerm !== '') return
  
  loadingTransportadores.value = true
  
  try {
    const params = new URLSearchParams({
      search: searchTerm,
      per_page: '20'
    })

    const response = await apiFetch(getApiUrl(`/transportes?${params}`), {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    const data = await response.json()

    if (data.success && data.data.results) {
      transportadoresOptions.value = data.data.results.map((t: any) => ({
        title: `${t.codtrn} - ${t.nomtrn.toUpperCase()}`,
        value: t.codtrn
      }))
    }
  } catch (error) {
    console.error('Erro ao buscar transportadores:', error)
    showError('Erro ao carregar transportadores')
    transportadoresOptions.value = []
  } finally {
    loadingTransportadores.value = false
  }
}

// Buscar rotas para o autocomplete
const fetchRotas = async (searchTerm: string = '') => {
  if (searchTerm.length < 2 && searchTerm !== '') return
  
  loadingRotas.value = true
  
  try {
    const params = new URLSearchParams({
      search: searchTerm
    })
    const response = await apiFetch(getApiUrl(`/rotas?${params}`), {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    const data = await response.json()
    if (data.success && data.data) {
      rotasOptions.value = data.data.map((r: any) => ({
        title: `${r.codrot} - ${r.desrot.toUpperCase()}`,
        value: r.codrot
      }))
    }
  } catch (error) {
    console.error('Erro ao buscar rotas:', error)
    showError('Erro ao carregar rotas')
    rotasOptions.value = []
  } finally {
    loadingRotas.value = false
  }
}

// Carregar dados
const fetchPacotes = async () => {
  loading.value = true
  
  try {
    const params = new URLSearchParams({
      page: pagination.value.current_page.toString(),
      per_page: pagination.value.per_page.toString(),
      search: search.value || '',
      codigo: searchCodigo.value || '',
      transportador: searchTransportador.value || '',
      codigo_transportador: selectedTransportador.value?.toString() || '',
      rota: selectedRota.value || searchRota.value || '',
      situacao: selectedSituacao.value || '',
      apenas_recentes: apenasRecentes.value ? '1' : '',
      data_inicio: dataInicio.value || '',
      data_fim: dataFim.value || ''
    })

    const response = await apiFetch(getApiUrl(`/pacotes?${params}`), {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    const data = await response.json()

    if (data.success) {
      pacotes.value = data.data.results || []
      pagination.value = data.pagination || pagination.value
    } else {
      console.error('Erro na API:', data.message)
      showError(data.message || 'Erro ao carregar pacotes')
    }
  } catch (error) {
    console.error('Erro ao carregar pacotes:', error)
    showError('Erro ao conectar com o servidor')
  } finally {
    loading.value = false
  }
}

// Formatar valor monetário
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(value)
}

// Formatar número com separadores
const formatNumber = (value: number) => {
  return new Intl.NumberFormat('pt-BR').format(value)
}

// Formatar data
const formatDate = (date: string) => {
  if (!date) return 'N/D'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('pt-BR')
}

// Formatar hora
const formatTime = (time: string) => {
  if (!time || time.length < 3) return 'N/D'
  const hours = time.substring(0, time.length - 2)
  const minutes = time.substring(time.length - 2)
  return `${hours}:${minutes}`
}

// Formatar situação
const formatSituacao = (situacao: string) => {
  const situacoes: { [key: string]: { text: string, color: string } } = {
    'U': { text: 'URGENTE', color: 'error' },
    'M': { text: 'MARCADA', color: 'warning' },
    'S': { text: 'EM SEPARAÇÃO', color: 'info' },
    'A': { text: 'AGUARDANDO', color: 'primary' },
    'F': { text: 'FINALIZADA', color: 'success' },
    '': { text: 'NORMAL', color: 'primary' },
    ' ': { text: 'VAZIA', color: 'secondary' }
  }
  
  return situacoes[situacao] || { text: 'INDEFINIDA', color: 'secondary' }
}

// Formatar placa brasileira
const formatPlaca = (placa: string) => {
  if (!placa) return null
  const placaLimpa = placa.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (placaLimpa.length === 7) {
    return `${placaLimpa.substring(0, 3)}-${placaLimpa.substring(3)}`
  }
  return placaLimpa
}

// Navegação para detalhes
const viewDetails = (item: Pacote) => {
  router.push({ name: 'pacotes-id', params: { id: item.codpac } })
}

// Limpar filtros
const clearFilters = () => {
  search.value = ''
  searchCodigo.value = ''
  searchTransportador.value = ''
  selectedTransportador.value = null
  searchRota.value = ''
  selectedRota.value = null
  selectedSituacao.value = ''
  apenasRecentes.value = false
  dataInicio.value = ''
  dataFim.value = ''
  pagination.value.current_page = 1
  fetchPacotes()
}

// Aplicar filtros
const applyFilters = () => {
  pagination.value.current_page = 1
  fetchPacotes()
}

// Mudança de página
const handlePageChange = (page: number) => {
  pagination.value.current_page = page
  fetchPacotes()
}

// Computed para exibição de paginação
const paginationDisplay = computed(() => {
  return `${pagination.value.from}-${pagination.value.to} de ${pagination.value.total} registros`
})

// Lifecycle
onMounted(() => {
  fetchPacotes()
  fetchTransportadores() // Carregar alguns transportadores inicialmente
  fetchRotas() // Carregar algumas rotas inicialmente
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-3 mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-0">
          Pacotes
        </h4>
        <p class="text-body-1 mb-0">
          Gestão de pacotes de transporte
        </p>
      </div>
    </div>

    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <VTextField
              v-model="search"
              label="Busca Geral"
              placeholder="Código ou transportador"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VTextField
              v-model="searchCodigo"
              label="Código do Pacote"
              placeholder="Ex: 3048790"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VAutocomplete
              v-model="selectedTransportador"
              :items="transportadoresOptions"
              label="Transportador"
              placeholder="Busque pelo código ou nome"
              :loading="loadingTransportadores"
              clearable
              item-title="title"
              item-value="value"
              @update:search="fetchTransportadores"
              @update:model-value="applyFilters"
              no-data-text="Nenhum transportador encontrado"
              loading-text="Buscando transportadores..."
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VAutocomplete
              v-model="selectedRota"
              :items="rotasOptions"
              :loading="loadingRotas"
              label="Rota"
              placeholder="Ex: AC, BAR, CBE"
              clearable
              item-title="title"
              item-value="value"
              @update:search="fetchRotas"
              @update:model-value="applyFilters"
              no-data-text="Nenhuma rota encontrada"
              loading-text="Buscando rotas..."
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="selectedSituacao"
              :items="situacaoOptions"
              label="Situação"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VCheckbox
              v-model="apenasRecentes"
              label="Apenas Recentes (Cód > 800.000)"
              color="primary"
              @change="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VTextField
              v-model="dataInicio"
              label="Data Início"
              type="date"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VTextField
              v-model="dataFim"
              label="Data Fim"
              type="date"
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="3" class="d-flex gap-2">
            <VBtn
              color="primary"
              @click="applyFilters"
            >
              <VIcon icon="tabler-search" start />
              Filtrar
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              @click="clearFilters"
            >
              <VIcon icon="tabler-x" start />
              Limpar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabela -->
    <VCard>
      <VCardText class="pa-0">
        <div class="table-responsive">
          <VDataTableServer
            v-model:items-per-page="pagination.per_page"
            :headers="headers"
            :items="pacotes"
            :items-length="pagination.total"
            :loading="loading"
            :page="pagination.current_page"
            @update:page="handlePageChange"
            @update:items-per-page="(value) => { pagination.per_page = value; pagination.current_page = 1; fetchPacotes() }"
            hover
            loading-text="Carregando pacotes..."
            no-data-text="Nenhum pacote encontrado"
          >
          <!-- Código do Pacote -->
          <template #item.codpac="{ item }">
            <div class="d-flex align-center">
              <VChip
                :color="formatSituacao(item.sitpac).color"
                size="small"
                class="me-2"
              >
                #{{ item.codpac }}
              </VChip>
            </div>
          </template>

          <!-- Data de Formação -->
          <template #item.datforpac="{ item }">
            <div>
              <p class="text-body-2 font-weight-medium mb-0">
                {{ formatDate(item.datforpac) }}
              </p>
              <small class="text-disabled">
                {{ formatTime(item.horforpac) }}
              </small>
            </div>
          </template>

          <!-- Transportador -->
          <template #item.nomtrn="{ item }">
            <div class="d-flex align-start">
              <VAvatar
                size="24"
                :color="item.codmot > 0 ? 'primary' : 'success'"
                variant="tonal"
                class="me-2 flex-shrink-0"
              >
                <VIcon
                  :icon="item.codmot > 0 ? 'tabler-users' : 'tabler-user'"
                  size="14"
                />
              </VAvatar>
              <div class="text-truncate" style="min-width: 0;">
                <p class="text-body-2 font-weight-medium mb-0 text-truncate" :title="item.nomtrn.toUpperCase()">
                  {{ item.nomtrn.toUpperCase() }}
                </p>
                <small class="text-disabled">
                  {{ item.codtrn }}{{ item.codmot > 0 ? ` | ${item.codmot}` : '' }}
                </small>
              </div>
            </div>
          </template>

          <!-- Placa -->
          <template #item.numpla="{ item }">
            <VChip
              v-if="item.numpla"
              color="info"
              size="small"
              variant="tonal"
            >
              {{ formatPlaca(item.numpla) }}
            </VChip>
            <span v-else class="text-disabled">N/D</span>
          </template>

          <!-- Rota -->
          <template #item.codrot="{ item }">
            <VChip
              color="secondary"
              size="small"
              variant="outlined"
            >
              {{ item.codrot }}
            </VChip>
          </template>
          <!-- TCD -->
          <template #item.flg_tcd="{ item }">
            <VChip
              v-if="item.flg_tcd === 1"
              color="warning"
              size="small"
              variant="flat"
            >
              TCD
            </VChip>
            <span v-else class="text-disabled">-</span>
          </template>

          <!-- Valor -->
          <template #item.valpac="{ item }">
            <span class="font-weight-medium">
              {{ formatCurrency(item.valpac) }}
            </span>
          </template>

          <!-- Número de Pedidos -->
          <template #item.nroped="{ item }">
            <VChip
              color="primary"
              size="small"
              variant="tonal"
            >
              {{ item.nroped }}
            </VChip>
          </template>

          <!-- Situação -->
          <template #item.sitpac="{ item }">
            <VChip
              :color="formatSituacao(item.sitpac).color"
              size="small"
            >
              {{ formatSituacao(item.sitpac).text }}
            </VChip>
          </template>

          <!-- Ações -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                color="primary"
                variant="tonal"
                @click="viewDetails(item)"
              >
                <VIcon icon="tabler-eye" size="16" />
              </VBtn>
              
              <VBtn
                size="small"
                color="success"
                variant="tonal"
                :to="`/itinerario/${item.codpac}`"
              >
                <VIcon icon="tabler-map" size="16" />
              </VBtn>
            </div>
          </template>

          <!-- Bottom (paginação customizada) -->
          <template #bottom>
            <div class="d-flex flex-wrap justify-space-between align-center gap-3 pa-4">
              <p class="text-body-2 mb-0">
                {{ paginationDisplay }}
              </p>
              
              <div class="d-flex align-center gap-2">
                <VBtn
                  size="small"
                  variant="tonal"
                  :disabled="pagination.current_page === 1"
                  @click="handlePageChange(1)"
                >
                  <VIcon icon="tabler-chevrons-left" />
                </VBtn>
                
                <VBtn
                  size="small"
                  variant="tonal"
                  :disabled="pagination.current_page === 1"
                  @click="handlePageChange(pagination.current_page - 1)"
                >
                  <VIcon icon="tabler-chevron-left" />
                </VBtn>
                
                <VChip color="primary">
                  {{ pagination.current_page }} de {{ pagination.last_page }}
                </VChip>
                
                <VBtn
                  size="small"
                  variant="tonal"
                  :disabled="!pagination.has_more_pages"
                  @click="handlePageChange(pagination.current_page + 1)"
                >
                  <VIcon icon="tabler-chevron-right" />
                </VBtn>
                
                <VBtn
                  size="small"
                  variant="tonal"
                  :disabled="!pagination.has_more_pages"
                  @click="handlePageChange(pagination.last_page)"
                >
                  <VIcon icon="tabler-chevrons-right" />
                </VBtn>
              </div>
            </div>
          </template>
          </VDataTableServer>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.table-responsive .v-data-table {
  min-width: 800px;
}

/* Responsividade para colunas */
@media (max-width: 960px) {
  .d-lg-table-cell {
    display: none !important;
  }
}

@media (max-width: 768px) {
  .d-md-table-cell {
    display: none !important;
  }

  .table-responsive .v-data-table {
    min-width: 600px;
  }
}

@media (max-width: 600px) {
  .d-sm-table-cell {
    display: none !important;
  }

  .table-responsive .v-data-table {
    min-width: 500px;
  }
}

/* Melhor controle de largura das colunas */
.v-data-table :deep(.v-data-table__td) {
  padding: 8px 12px !important;
}

.v-data-table :deep(.v-data-table__th) {
  padding: 8px 12px !important;
  white-space: nowrap;
}

/* Otimização da coluna de transportador */
.v-data-table :deep(td[data-key="nomtrn"]) {
  max-width: 250px;
}
</style>