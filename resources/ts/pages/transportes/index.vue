<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'

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

// Filtros avançados
const filtroTipo = ref('todos')
const filtroNatureza = ref('')
const filtroStatus = ref('ativo') // Padrão: mostrar apenas ativos
const mostrarInativos = ref(false)
const showFilters = ref(false)

// Opções de paginação (padrão Vuexy)
const options = ref({ 
  page: 1, 
  itemsPerPage: 10, 
  sortBy: ['codtrn'], 
  sortDesc: [false] 
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

// Computed para estatísticas
const statistics = computed(() => {
  const stats = {
    total: totalItems.value,
    autonomos: 0,
    empresas: 0,
    ativos: 0,
    inativos: 0,
    naturezaT: 0,
    naturezaA: 0
  }
  
  serverItems.value.forEach(item => {
    if (item.flgautonomo) stats.autonomos++
    else stats.empresas++
    
    if (item.flgati) stats.ativos++
    else stats.inativos++
    
    if (item.natcam === 'T') stats.naturezaT++
    else if (item.natcam === 'A') stats.naturezaA++
  })
  
  return stats
})

// Função para buscar transportes com paginação real (padrão Vuexy)
const fetchTransportes = async () => {
  try {
    loading.value = true
    
    // Construir parâmetros da query usando options object
    const params = new URLSearchParams({
      page: options.value.page.toString(),
      per_page: options.value.itemsPerPage.toString()
    })
    
    // Adicionar filtro de busca se houver
    if (search.value && search.value.trim() !== '') {
      params.append('search', search.value.trim())
    }
    
    // Filtros avançados
    if (filtroTipo.value !== 'todos') {
      params.append('tipo', filtroTipo.value)
    }
    
    if (filtroNatureza.value !== '') {
      params.append('natureza', filtroNatureza.value)
    }
    
    if (filtroStatus.value !== 'todos') {
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
      
      console.log(`✅ Dados carregados - Página ${pagination.current_page} de ${pagination.last_page}`, {
        total: pagination.total,
        from: pagination.from,
        to: pagination.to,
        items: transportesList.length,
        search: search.value
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
  options.value.page = newOptions.page
  options.value.itemsPerPage = newOptions.itemsPerPage
  options.value.sortBy = newOptions.sortBy || ['codtrn']
  options.value.sortDesc = newOptions.sortDesc || [false]
}

// Watchers para recarregar dados quando necessário (padrão Vuexy)
watch(options, (newOptions, oldOptions) => {
  console.log(`📄 Mudança de paginação:`, {
    page: `${oldOptions.page} → ${newOptions.page}`,
    itemsPerPage: `${oldOptions.itemsPerPage} → ${newOptions.itemsPerPage}`
  })
  fetchTransportes()
}, { deep: true })

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
      fetchTransportes()
    }, 500)
  }
})

// Watchers para filtros avançados
watch([filtroTipo, filtroNatureza, filtroStatus], ([novoTipo, novaNatureza, novoStatus], [tipoAnt, naturezaAnt, statusAnt]) => {
  if (novoTipo !== tipoAnt || novaNatureza !== naturezaAnt || novoStatus !== statusAnt) {
    console.log(`🔧 Filtros mudaram:`, {
      tipo: `${tipoAnt} → ${novoTipo}`,
      natureza: `${naturezaAnt} → ${novaNatureza}`,
      status: `${statusAnt} → ${novoStatus}`
    })
    options.value.page = 1
    fetchTransportes()
  }
})

// Funções de interação
const viewDetails = (item: Transporte) => {
  console.log('Ver detalhes:', item)
  // TODO: Implementar modal de detalhes
}

const getTipoTransportador = (item: Transporte) => {
  return item.flgautonomo ? 'Autônomo' : 'Empresa'
}

const getNaturezaLabel = (natcam: string) => {
  switch(natcam) {
    case 'T': return 'Transporte'
    case 'A': return 'Agregado'
    default: return natcam || 'N/D'
  }
}

const formatTelefone = (ddd: number, telefone: number) => {
  if (!ddd || !telefone) return 'N/D'
  return `(${ddd}) ${telefone.toString().replace(/(\d{4,5})(\d{4})/, '$1-$2')}`
}

const clearFilters = () => {
  filtroTipo.value = 'todos'
  filtroNatureza.value = ''
  filtroStatus.value = 'ativo'
  mostrarInativos.value = false
  search.value = ''
}

// Watcher para mostrar inativos
watch(mostrarInativos, (mostrar) => {
  filtroStatus.value = mostrar ? 'todos' : 'ativo'
  console.log(`👁️ Mostrar inativos: ${mostrar}`, { filtroStatus: filtroStatus.value })
})

// Carregar dados ao montar o componente
onMounted(() => {
  fetchTransportes()
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
          <VChip color="primary" size="small">
            {{ statistics.total.toLocaleString() }} Total
          </VChip>
          <VChip color="success" size="small">
            {{ statistics.autonomos }} Autônomos
          </VChip>
          <VChip color="info" size="small">
            {{ statistics.empresas }} Empresas
          </VChip>
          <VChip color="secondary" size="small">
            {{ statistics.ativos }} Ativos
          </VChip>
          <VChip v-if="mostrarInativos" color="error" size="small">
            {{ statistics.inativos }} Inativos
          </VChip>
        </div>
      </div>
    </VCol>

    <!-- Filtros Avançados -->
    <VCol cols="12">
      <VCard>
        <VCardText>
          <!-- Linha superior: Controles básicos -->
          <div class="d-flex align-center flex-wrap gap-4 mb-4">
            <div class="d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: '100' }
                ]"
                style="inline-size: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
              
              <VBtn
                :prepend-icon="showFilters ? 'tabler-filter-off' : 'tabler-filter'"
                variant="tonal"
                @click="showFilters = !showFilters"
              >
                {{ showFilters ? 'Ocultar' : 'Filtros' }}
              </VBtn>
            </div>
            
            <VSpacer />
            
            <div class="d-flex align-center flex-wrap gap-4">
              <div style="inline-size: 15.625rem;">
                <AppTextField
                  v-model="search"
                  placeholder="Buscar por nome ou código"
                  prepend-inner-icon="tabler-search"
                />
              </div>
              
              <!-- Toggle para mostrar inativos -->
              <VTooltip text="Incluir transportadores inativos">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    :variant="mostrarInativos ? 'flat' : 'tonal'"
                    :color="mostrarInativos ? 'warning' : 'default'"
                    :prepend-icon="mostrarInativos ? 'tabler-eye' : 'tabler-eye-off'"
                    @click="mostrarInativos = !mostrarInativos"
                  >
                    {{ mostrarInativos ? 'Todos' : 'Apenas Ativos' }}
                  </VBtn>
                </template>
              </VTooltip>
              
              <VBtn
                prepend-icon="tabler-reload"
                @click="fetchTransportes"
                :loading="loading"
              >
                Atualizar
              </VBtn>
              
              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-upload"
              >
                Exportar
              </VBtn>
            </div>
          </div>
          
          <!-- Filtros Avançados (Expansível) -->
          <VExpandTransition>
            <div v-show="showFilters" class="border rounded p-4">
              <VRow>
                <VCol cols="12" md="3">
                  <AppSelect
                    v-model="filtroTipo"
                    label="Tipo de Transportador"
                    :items="[
                      { value: 'todos', title: 'Todos' },
                      { value: 'autonomo', title: 'Autônomo' },
                      { value: 'empresa', title: 'Empresa' }
                    ]"
                  />
                </VCol>
                
                <VCol cols="12" md="3">
                  <AppSelect
                    v-model="filtroNatureza"
                    label="Natureza do Transporte"
                    :items="[
                      { value: '', title: 'Todas' },
                      { value: 'T', title: 'Transporte (T)' },
                      { value: 'A', title: 'Agregado (A)' }
                    ]"
                  />
                </VCol>
                
                <VCol cols="12" md="3">
                  <div class="d-flex flex-column gap-2">
                    <label class="text-body-2 text-medium-emphasis">Visualização</label>
                    <VCheckbox
                      v-model="mostrarInativos"
                      label="Incluir transportadores inativos"
                      color="warning"
                      density="comfortable"
                    />
                  </div>
                </VCol>
                
                <VCol cols="12" md="3" class="d-flex align-center">
                  <VBtn
                    variant="outlined"
                    color="secondary"
                    @click="clearFilters"
                    prepend-icon="tabler-x"
                  >
                    Limpar Filtros
                  </VBtn>
                </VCol>
              </VRow>
            </div>
          </VExpandTransition>
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
            <VChip
              color="primary"
              class="font-weight-medium"
              size="small"
            >
              {{ item.codtrn }}
            </VChip>
          </template>
          
          <!-- Tipo de Transportador -->
          <template #item.tipo="{ item }">
            <VChip
              :color="item.flgautonomo ? 'success' : 'info'"
              :variant="'tonal'"
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
                :color="item.flgautonomo ? 'success' : 'info'"
                :variant="'tonal'"
              >
                <VIcon 
                  :icon="item.flgautonomo ? 'tabler-user' : 'tabler-building'"
                  size="18"
                />
              </VAvatar>
              <div class="d-flex flex-column ms-3">
                <span class="d-block font-weight-medium text-high-emphasis text-truncate">{{ item.nomtrn }}</span>
                <small class="text-medium-emphasis">
                  {{ item.codcnpjcpf ? `CNPJ/CPF: ${item.codcnpjcpf}` : 'Sem documento' }}
                </small>
              </div>
            </div>
          </template>
          
          <!-- Natureza do Transporte -->
          <template #item.natcam="{ item }">
            <VChip
              :color="item.natcam === 'T' ? 'primary' : 'warning'"
              size="small"
              :variant="'tonal'"
            >
              {{ getNaturezaLabel(item.natcam) }}
            </VChip>
          </template>
          
          <!-- Placa do Veículo -->
          <template #item.numpla="{ item }">
            <span v-if="item.numpla" class="font-weight-medium">{{ item.numpla }}</span>
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
            >
              {{ item.flgati ? 'Ativo' : 'Inativo' }}
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