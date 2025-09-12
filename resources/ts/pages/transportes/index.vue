<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'

// Interface para tipagem
interface Transporte {
  codtrn: number
  nomtrn: string
}

// Estado reativo seguindo padrão Vuexy
const loading = ref(false)
const search = ref('')
const totalItems = ref(0)
const serverItems = ref<Transporte[]>([])

// Opções de paginação (padrão Vuexy)
const options = ref({ 
  page: 1, 
  itemsPerPage: 10, 
  sortBy: ['codtrn'], 
  sortDesc: [false] 
})

// Headers da tabela com tradução
const headers = [
  { 
    title: 'CÓDIGO', 
    key: 'codtrn', 
    sortable: true
  },
  { 
    title: 'NOME DO TRANSPORTADOR', 
    key: 'nomtrn', 
    sortable: true
  },
  { 
    title: 'AÇÕES', 
    key: 'actions', 
    sortable: false,
    width: '100px'
  },
]

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

// Função para ver detalhes
const viewDetails = (item: Transporte) => {
  // Placeholder para futura funcionalidade
  console.log('Ver detalhes:', item)
}

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
        
        <div class="text-body-2 text-medium-emphasis">
          {{ totalItems.toLocaleString() }} registros
        </div>
      </div>
    </VCol>

    <!-- Filtros e Tabela -->
    <VCol cols="12">
      <VCard>
        <VCardText class="d-flex flex-wrap gap-4">
          <div class="me-3 d-flex gap-3">
            <AppSelect
              :model-value="options.itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: -1, title: 'Todos' },
              ]"
              style="inline-size: 6.25rem;"
              @update:model-value="options.itemsPerPage = parseInt($event, 10)"
            />
          </div>
          <VSpacer />

          <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
            <!-- 👉 Search  -->
            <div style="inline-size: 15.625rem;">
              <AppTextField
                v-model="search"
                placeholder="Buscar Transportador"
              />
            </div>

            <!-- 👉 Export button -->
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-upload"
            >
              Exportar
            </VBtn>

            <!-- 👉 Atualizar button -->
            <VBtn
              prepend-icon="tabler-reload"
              @click="fetchTransportes"
              :loading="loading"
            >
              Atualizar
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
          <!-- Slot para código seguindo padrão Vuexy -->
          <template #item.codtrn="{ item }">
            <VChip
              color="primary"
              class="font-weight-medium"
              size="small"
            >
              {{ item.codtrn }}
            </VChip>
          </template>

          <!-- Slot para nome seguindo padrão Vuexy -->
          <template #item.nomtrn="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                size="32"
                :color="item.codtrn % 2 === 0 ? 'primary' : 'success'"
                :variant="'tonal'"
              >
                <span>{{ item.nomtrn.substring(0, 2).toUpperCase() }}</span>
              </VAvatar>
              <div class="d-flex flex-column ms-3">
                <span class="d-block font-weight-medium text-high-emphasis text-truncate">{{ item.nomtrn }}</span>
                <small>Código: {{ item.codtrn }}</small>
              </div>
            </div>
          </template>

          <!-- Slot para ações seguindo padrão Vuexy -->
          <template #item.actions="{ item }">
            <VTooltip text="Visualizar">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="viewDetails(item)"
                />
              </template>
            </VTooltip>
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