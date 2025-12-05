<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { watchDebounced } from '@vueuse/core'

const router = useRouter()

// Interface para tipagem expandida
interface Transporte {
  codtrn: number
  nomtrn: string
  flgautonomo: boolean
  natcam: string
  tipcam: number
  codcnpjcpf: string
  numpla: string
  numtel: number
  dddtel: number
  flgati: boolean
  indcd: string
}

// Estado reativo seguindo padrão Vuexy
const loading = ref(false)
const search = ref('')
const totalItems = ref(0)
const serverItems = ref<Transporte[]>([])

// Filtros - valor padrão: mostrar apenas ativos
const filtroTipo = ref<string | undefined>()
const filtroNatureza = ref<string | undefined>()
const filtroStatus = ref<string | undefined>('ativo')

// Opções de paginação (padrão Vuexy)
const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: ['codtrn'],
  sortDesc: [false]
})

// Keyset pagination cursors
const cursors = ref({
  next: null as number | null,
  prev: null as number | null,
  hasNext: false,
  hasPrev: false
})

// Headers da tabela expandidos
const headers = [
  { 
    title: 'CÓDIGO', 
    key: 'codtrn', 
    sortable: true,
    width: '80px'
  },
  { 
    title: 'TIPO', 
    key: 'tipo', 
    sortable: false,
    width: '120px'
  },
  { 
    title: 'TRANSPORTADOR/MOTORISTA', 
    key: 'nomtrn', 
    sortable: true
  },
  { 
    title: 'NATUREZA', 
    key: 'natcam', 
    sortable: false,
    width: '100px'
  },
  { 
    title: 'PLACA', 
    key: 'numpla', 
    sortable: false,
    width: '120px'
  },
  { 
    title: 'TELEFONE', 
    key: 'telefone', 
    sortable: false,
    width: '150px'
  },
  { 
    title: 'STATUS', 
    key: 'status', 
    sortable: false,
    width: '100px'
  },
  { 
    title: 'AÇÕES', 
    key: 'actions', 
    sortable: false,
    width: '100px'
  },
]

// Statistics state (fetched from backend API)
const statistics = ref({
  total: 0,
  autonomos: 0,
  empresas: 0,
  ativos: 0,
  inativos: 0,
  natureza_T: 0,
  natureza_A: 0
})
const loadingStats = ref(false)

// Fetch statistics from backend (global counts, not filtered)
const fetchStatistics = async () => {
  try {
    loadingStats.value = true

    const response = await fetch('http://localhost:8002/api/transportes/statistics', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    })

    const result = await response.json()

    if (result.success && result.data) {
      statistics.value = result.data
      console.log('✅ Estatísticas globais carregadas:', result.data)
    }
  } catch (error) {
    console.error('Erro ao buscar estatísticas:', error)
  } finally {
    loadingStats.value = false
  }
}

// Função para buscar transportes com paginação real (padrão Vuexy)
// Suporta KEYSET PAGINATION (cursor-based) para melhor performance
const fetchTransportes = async (direction: 'next' | 'prev' | null = null) => {
  try {
    loading.value = true

    // Construir parâmetros da query
    const params = new URLSearchParams({
      per_page: options.value.itemsPerPage.toString()
    })

    // KEYSET PAGINATION: Use cursor se disponível
    if (direction === 'next' && cursors.value.next) {
      params.append('last_id', cursors.value.next.toString())
      params.append('direction', 'next')
    } else if (direction === 'prev' && cursors.value.prev) {
      params.append('last_id', cursors.value.prev.toString())
      params.append('direction', 'prev')
    } else if (options.value.page > 1) {
      // LEGACY MODE: Use page-based pagination (less efficient)
      params.append('page', options.value.page.toString())
    }

    // Adicionar filtro de busca se houver
    if (search.value && search.value.trim() !== '') {
      params.append('search', search.value.trim())
    }

    // Filtros
    if (filtroTipo.value) {
      params.append('tipo', filtroTipo.value)
    }

    if (filtroNatureza.value) {
      params.append('natureza', filtroNatureza.value)
    }

    if (filtroStatus.value && filtroStatus.value !== 'todos') {
      params.append('status_ativo', filtroStatus.value === 'ativo' ? 'true' : 'false')
    }

    const response = await fetch(`http://localhost:8002/api/transportes?${params}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    })

    const result = await response.json()

    if (result.success) {
      const transportesList = result.data.results || []
      const pagination = result.pagination || {}

      // Atualizar dados com resposta do servidor
      serverItems.value = transportesList
      totalItems.value = pagination.total || 0

      // Atualizar cursors para keyset pagination
      cursors.value = {
        next: pagination.next_cursor || null,
        prev: pagination.prev_cursor || null,
        hasNext: pagination.has_next || false,
        hasPrev: pagination.has_prev || false
      }

      console.log(`✅ Dados carregados - Página ${pagination.current_page}`, {
        total: pagination.total,
        count: pagination.count,
        items: transportesList.length,
        hasNext: cursors.value.hasNext,
        hasPrev: cursors.value.hasPrev,
        mode: direction ? 'keyset' : (options.value.page > 1 ? 'legacy' : 'first')
      })
    } else {
      console.error('Erro ao buscar transportes:', result.message)
      serverItems.value = []
      totalItems.value = 0
    }
  } catch (error) {
    console.error('Erro na requisição:', error)
    serverItems.value = []
    totalItems.value = 0
  } finally {
    loading.value = false
  }
}

// Update data table options
const updateOptions = (newOptions: any) => {
  console.log('🔄 updateOptions chamado:', newOptions)

  const oldPage = options.value.page
  const newPage = newOptions.page

  options.value.page = newPage
  options.value.itemsPerPage = newOptions.itemsPerPage
  options.value.sortBy = newOptions.sortBy || ['codtrn']
  options.value.sortDesc = newOptions.sortDesc || [false]

  // Detect navigation direction and use keyset pagination
  if (newPage > oldPage && cursors.value.hasNext) {
    // Navigate to NEXT page using keyset
    fetchTransportes('next')
  } else if (newPage < oldPage && cursors.value.hasPrev) {
    // Navigate to PREV page using keyset
    fetchTransportes('prev')
  } else if (newPage === 1) {
    // Reset to first page
    cursors.value = { next: null, prev: null, hasNext: false, hasPrev: false }
    fetchTransportes(null)
  } else {
    // Fallback to legacy pagination (inefficient, for edge cases)
    fetchTransportes(null)
  }
}

// Watchers removido - agora a paginação é controlada pelo updateOptions

// Debounce para busca (aguardar parada de digitação)
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, (newSearch, oldSearch) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  // Só executar se o valor realmente mudou
  if (newSearch !== oldSearch) {
    console.log(`🔍 Busca mudou: "${oldSearch}" → "${newSearch}"`)
    searchTimeout = setTimeout(() => {
      // Reset para primeira página ao buscar
      options.value.page = 1
      cursors.value = { next: null, prev: null, hasNext: false, hasPrev: false }
      fetchTransportes(null)
    }, 500)
  }
})

// Watchers para filtros avançados com debounce (300ms)
watchDebounced(
  [filtroTipo, filtroNatureza, filtroStatus],
  ([novoTipo, novaNatureza, novoStatus], [tipoAnt, naturezaAnt, statusAnt]) => {
    if (novoTipo !== tipoAnt || novaNatureza !== naturezaAnt || novoStatus !== statusAnt) {
      console.log(`🔧 Filtros mudaram:`, {
        tipo: `${tipoAnt} → ${novoTipo}`,
        natureza: `${naturezaAnt} → ${novaNatureza}`,
        status: `${statusAnt} → ${novoStatus}`
      })
      options.value.page = 1
      cursors.value = { next: null, prev: null, hasNext: false, hasPrev: false }
      fetchTransportes(null)
      fetchStatistics()
    }
  },
  { debounce: 300 }
)

// Funções de interação
const viewDetails = (item: Transporte) => {
  router.push(`/transportes/${item.codtrn}`)
}

const handleItemsPerPageChange = (value: string | number) => {
  const newValue = Number(value)
  if (isNaN(newValue) || newValue <= 0) {
    console.error('Invalid itemsPerPage value:', value)
    return
  }

  options.value.itemsPerPage = newValue
  options.value.page = 1
  cursors.value = { next: null, prev: null, hasNext: false, hasPrev: false }
  fetchTransportes(null)
}

const getTipoTransportador = (item: Transporte) => {
  return item.flgautonomo ? 'AUTÔNOMO' : 'EMPRESA'
}

const formatPlacaBrasileira = (placa: string) => {
  if (!placa) return null
  
  const placaClean = placa.toUpperCase().replace(/[^A-Z0-9]/g, '')
  
  // Formato Mercosul (ABC1D23)
  if (/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(placaClean)) {
    return placaClean.substring(0, 3) + '-' + placaClean.substring(3, 4) + placaClean.substring(4, 5) + placaClean.substring(5, 7)
  }
  // Formato antigo (ABC1234)
  else if (/^[A-Z]{3}[0-9]{4}$/.test(placaClean)) {
    return placaClean.substring(0, 3) + '-' + placaClean.substring(3)
  }
  
  return placaClean
}

const getNaturezaLabel = (natcam: string) => {
  switch(natcam) {
    case 'T': return 'TRANSPORTE'
    case 'A': return 'AGREGADO'
    default: return natcam?.toUpperCase() || 'N/D'
  }
}

const formatTelefone = (ddd: number, telefone: number) => {
  if (!ddd || !telefone) return 'N/D'
  return `(${ddd}) ${telefone.toString().replace(/(\d{4,5})(\d{4})/, '$1-$2')}`
}

const clearFilters = () => {
  filtroTipo.value = undefined
  filtroNatureza.value = undefined
  filtroStatus.value = undefined
  search.value = ''
}

// Carregar dados ao montar o componente
onMounted(() => {
  fetchTransportes()
  fetchStatistics()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <div class="d-flex align-center justify-space-between mb-4">
        <div class="d-flex align-center">
          <VIcon 
            icon="tabler-truck" 
            class="me-3" 
            color="primary" 
            size="28"
          />
          <div>
            <h4 class="text-h4 font-weight-medium mb-0">Transportadores</h4>
            <p class="text-body-2 mb-0 text-medium-emphasis">Sistema Progress</p>
          </div>
        </div>
        
        <div class="d-flex align-center gap-4">
          <VChip color="primary" size="small" variant="tonal">
            {{ statistics.total.toLocaleString() }} Total
          </VChip>
          <VChip color="success" size="small" variant="tonal">
            {{ statistics.autonomos }} Autônomos
          </VChip>
          <VChip color="primary" size="small" variant="tonal">
            {{ statistics.empresas }} Empresas
          </VChip>
        </div>
      </div>
    </VCol>

    <!-- Card Único com Filtros e Tabela -->
    <VCol cols="12">
      <VCard>
        <VCardText class="d-flex flex-wrap gap-4">
          <!-- Items per page -->
          <AppSelect
            :model-value="options.itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' }
            ]"
            style="inline-size: 5rem;"
            @update:model-value="handleItemsPerPageChange"
          />

          <!-- Filtro Tipo -->
          <AppSelect
            v-model="filtroTipo"
            placeholder="Tipo"
            :items="[
              { value: 'autonomo', title: 'Autônomo' },
              { value: 'empresa', title: 'Empresa' }
            ]"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />

          <!-- Filtro Natureza -->
          <AppSelect
            v-model="filtroNatureza"
            placeholder="Natureza"
            :items="[
              { value: 'T', title: 'Transporte' },
              { value: 'A', title: 'Agregado' }
            ]"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />

          <VSpacer />

          <div class="d-flex align-center flex-wrap gap-4">
            <!-- Busca -->
            <AppTextField
              v-model="search"
              placeholder="Buscar transportador"
              prepend-inner-icon="tabler-search"
              style="inline-size: 15rem;"
            />

            <!-- Toggle Apenas Ativos -->
            <VBtn
              :variant="filtroStatus === 'ativo' ? 'flat' : 'tonal'"
              :color="filtroStatus === 'ativo' ? 'success' : 'default'"
              :prepend-icon="filtroStatus === 'ativo' ? 'tabler-check' : 'tabler-users'"
              @click="filtroStatus = filtroStatus === 'ativo' ? undefined : 'ativo'"
            >
              {{ filtroStatus === 'ativo' ? 'Apenas Ativos' : 'Todos' }}
            </VBtn>

            <!-- Botão Atualizar -->
            <VBtn
              prepend-icon="tabler-refresh"
              @click="fetchTransportes"
              :loading="loading"
            >
              Atualizar
            </VBtn>

            <!-- Botão Exportar -->
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-download"
            >
              Exportar
            </VBtn>
          </div>
        </VCardText>

        <VDivider />

        <!-- SECTION datatable -->
        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :items="serverItems"
          item-value="codtrn"
          :items-length="totalItems"
          :headers="headers"
          :loading="loading"
          class="text-no-wrap"
          loading-text="Carregando transportadores..."
          no-data-text="Nenhum transportador encontrado"
          @update:options="updateOptions"
        >
          <!-- Código -->
          <template #item.codtrn="{ item }">
            <div class="text-body-1 font-weight-medium">
              <span class="text-primary">{{ item.codtrn }}</span>
            </div>
          </template>

          <!-- Tipo de Transportador -->
          <template #item.tipo="{ item }">
            <VChip
              :color="item.flgautonomo ? 'success' : 'primary'"
              variant="tonal"
              size="small"
            >
              <VIcon
                :icon="item.flgautonomo ? 'tabler-user' : 'tabler-building'"
                size="16"
                class="me-1"
              />
              {{ getTipoTransportador(item) }}
            </VChip>
          </template>

          <!-- Nome com avatar e informações -->
          <template #item.nomtrn="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                size="32"
                :color="item.flgautonomo ? 'success' : 'primary'"
                variant="tonal"
              >
                <VIcon
                  :icon="item.flgautonomo ? 'tabler-user' : 'tabler-building'"
                  size="18"
                />
              </VAvatar>
              <div class="d-flex flex-column ms-3">
                <span class="d-block font-weight-medium text-high-emphasis text-truncate">{{ item.nomtrn?.toUpperCase() }}</span>
                <small class="text-medium-emphasis">
                  {{ item.codcnpjcpf ? `CNPJ/CPF: ${item.codcnpjcpf}` : 'SEM DOCUMENTO' }}
                </small>
              </div>
            </div>
          </template>

          <!-- Natureza do Transporte -->
          <template #item.natcam="{ item }">
            <VChip
              :color="item.natcam === 'T' ? 'info' : 'secondary'"
              size="small"
              variant="tonal"
            >
              {{ getNaturezaLabel(item.natcam) }}
            </VChip>
          </template>

          <!-- Placa do Veículo -->
          <template #item.numpla="{ item }">
            <div v-if="item.numpla" class="d-flex align-center">
              <VChip
                color="info"
                size="small"
                variant="flat"
                class="font-mono font-weight-medium"
              >
                {{ formatPlacaBrasileira(item.numpla) }}
              </VChip>
            </div>
            <span v-else class="text-disabled">N/D</span>
          </template>

          <!-- Telefone -->
          <template #item.telefone="{ item }">
            <span class="font-mono">{{ formatTelefone(item.dddtel, item.numtel) }}</span>
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <VChip
              :color="item.flgati ? 'success' : 'error'"
              size="small"
              variant="tonal"
            >
              {{ item.flgati ? 'ATIVO' : 'INATIVO' }}
            </VChip>
          </template>

          <!-- Ações -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VTooltip text="Visualizar detalhes">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-eye"
                    size="small"
                    variant="text"
                    color="primary"
                    @click="viewDetails(item)"
                  />
                </template>
              </VTooltip>
              
              <VTooltip v-if="!item.flgautonomo" text="Ver motoristas">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-users"
                    size="small"
                    variant="text"
                    color="info"
                    @click="viewDetails(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>

          <!-- pagination -->
          <template #bottom>
            <TablePagination
              v-model:page="options.page"
              :items-per-page="options.itemsPerPage"
              :total-items="totalItems"
            />
          </template>
        </VDataTableServer>
        <!-- SECTION -->
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.v-data-table {
  border-radius: 8px;
}

.v-data-table__wrapper {
  border-radius: 8px;
}
</style>