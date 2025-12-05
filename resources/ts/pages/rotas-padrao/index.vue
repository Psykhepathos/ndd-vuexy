<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { API_ENDPOINTS, apiFetch } from '@/config/api'
import { useToast } from '@/composables/useToast'

// Interface para tipagem
interface RotaSemParar {
  spararrotid: number
  desspararrot: string
  tempoviagem: number
  flgcd: boolean
  flgretorno: boolean
  totalmunicipios: number
  datatu: string | null
  resatu: string | null
}

// Composables
const { showError, showSuccess, showWarning, toast } = useToast()

// Estados reativos
const loading = ref(false)
const deleting = ref(false)
const searchQuery = ref('')
const itemsPerPage = ref(10)
const page = ref(1)
const totalRotas = ref(0)
const serverItems = ref<RotaSemParar[]>([])

// Filtros
const selectedTipo = ref<'all' | 'cd' | 'rota'>('all') // 3 estados: all, cd, rota
const selectedRetorno = ref<'all' | 'com' | 'sem'>('all') // 3 estados: all, com, sem

// Função para toggle do tipo (3 estados)
const toggleTipo = () => {
  console.log('🔄 toggleTipo ANTES:', selectedTipo.value)
  if (selectedTipo.value === 'all') selectedTipo.value = 'cd'
  else if (selectedTipo.value === 'cd') selectedTipo.value = 'rota'
  else selectedTipo.value = 'all'
  console.log('🔄 toggleTipo DEPOIS:', selectedTipo.value)
}

// Função para toggle do retorno (3 estados)
const toggleRetorno = () => {
  if (selectedRetorno.value === 'all') selectedRetorno.value = 'com'
  else if (selectedRetorno.value === 'com') selectedRetorno.value = 'sem'
  else selectedRetorno.value = 'all'
}

// Computed para label e cor do botão TIPO
const tipoButtonLabel = computed(() => {
  const tipo = selectedTipo.value
  console.log('🔍 tipoButtonLabel computed - selectedTipo.value:', tipo)
  if (tipo === 'cd') {
    console.log('✅ Retornando: CD')
    return 'CD'
  }
  if (tipo === 'rota') {
    console.log('✅ Retornando: Rotas')
    return 'Rotas'
  }
  console.log('✅ Retornando: Todas')
  return 'Todas'
})

const tipoButtonColor = computed(() => {
  const tipo = selectedTipo.value
  console.log('🎨 tipoButtonColor computed - selectedTipo.value:', tipo)
  if (tipo === 'cd') {
    console.log('🎨 Retornando cor: info (ciano)')
    return 'info'
  }
  if (tipo === 'rota') {
    console.log('🎨 Retornando cor: primary (azul)')
    return 'primary'
  }
  console.log('🎨 Retornando cor: default (cinza)')
  return 'default'
})

const tipoButtonIcon = computed(() => {
  const tipo = selectedTipo.value
  if (tipo === 'cd') return 'tabler-building-warehouse'
  if (tipo === 'rota') return 'tabler-route'
  return 'tabler-list'
})

// Computed para label e cor do botão RETORNO
const retornoButtonLabel = computed(() => {
  if (selectedRetorno.value === 'com') return 'Retorno'
  if (selectedRetorno.value === 'sem') return 'Ida'
  return 'Todos Retornos'
})

const retornoButtonColor = computed(() => {
  if (selectedRetorno.value === 'com') return 'success'
  if (selectedRetorno.value === 'sem') return 'error'
  return 'default'
})

const retornoButtonIcon = computed(() => {
  if (selectedRetorno.value === 'com') return 'tabler-arrow-back-up'
  if (selectedRetorno.value === 'sem') return 'tabler-arrow-right'
  return 'tabler-arrows-left-right'
})

// Router
const router = useRouter()

// Headers da tabela
const headers = [
  { title: 'CÓDIGO', key: 'spararrotid', sortable: true },
  { title: 'DESCRIÇÃO', key: 'desspararrot', sortable: true },
  { title: 'TEMPO (DIAS)', key: 'tempoviagem', sortable: true },
  { title: 'MUNICÍPIOS', key: 'totalmunicipios', sortable: false },
  { title: 'TIPO', key: 'flgcd', sortable: false },
  { title: 'RETORNO', key: 'flgretorno', sortable: false },
  { title: 'AÇÕES', key: 'actions', sortable: false }
]

// Função helper para normalizar dados da API
const normalizeRotaData = (item: any): RotaSemParar => ({
  spararrotid: Number(item.spararrotid ?? item.sPararRotID ?? 0),
  desspararrot: String(item.desspararrot ?? item.desSPararRot ?? ''),
  tempoviagem: Number(item.tempoviagem ?? item.tempoViagem ?? 0),
  flgcd: Boolean(item.flgcd ?? item.flgCD),
  flgretorno: Boolean(item.flgretorno ?? item.flgRetorno),
  totalmunicipios: Number(item.totalmunicipios ?? 0),
  datatu: item.datatu || null,
  resatu: item.resatu || null
})

// Computed para estatísticas
const statistics = computed(() => {
  const stats = {
    total: totalRotas.value,
    comCD: 0,
    semCD: 0,
    comRetorno: 0,
    semRetorno: 0
  }

  serverItems.value.forEach(item => {
    if (item.flgcd) stats.comCD++
    else stats.semCD++
    if (item.flgretorno) stats.comRetorno++
    else stats.semRetorno++
  })

  return stats
})

// Função para buscar rotas
const fetchRotas = async () => {
  loading.value = true

  try {
    const params = new URLSearchParams({
      page: page.value.toString(),
      per_page: itemsPerPage.value.toString()
    })

    // Filtro de busca
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    // Filtro de tipo (all / cd / rota)
    if (selectedTipo.value === 'cd') {
      params.append('flg_cd', 'true')
    } else if (selectedTipo.value === 'rota') {
      params.append('flg_cd', 'false')
    }

    // Filtro de retorno
    if (selectedRetorno.value === 'com') {
      params.append('flg_retorno', 'true')
    } else if (selectedRetorno.value === 'sem') {
      params.append('flg_retorno', 'false')
    }

    const response = await apiFetch(`${API_ENDPOINTS.semPararRotas}?${params}`)

    const data = await response.json()

    if (data.success && data.data) {
      // Usar função de normalização
      serverItems.value = data.data.map(normalizeRotaData)

      if (data.pagination) {
        totalRotas.value = data.pagination.total || 0
        page.value = parseInt(data.pagination.current_page) || 1
      } else {
        totalRotas.value = serverItems.value.length
      }
    } else {
      showError('Erro ao carregar rotas: ' + (data.message || 'Erro desconhecido'))
      serverItems.value = []
      totalRotas.value = 0
    }
  } catch (error) {
    console.error('Erro ao buscar rotas:', error)
    showError('Erro ao carregar rotas. Verifique sua conexão e tente novamente.')
    serverItems.value = []
    totalRotas.value = 0
  } finally {
    loading.value = false
  }
}

// Update options
const updateOptions = () => {
  fetchRotas()
}

// Debounce timer
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Watchers
watch(searchQuery, () => {
  // Debounce de 500ms para searchQuery para evitar chamadas excessivas à API
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    page.value = 1
    fetchRotas()
  }, 500)
})

// Filtros trigger direto (sem debounce)
watch([selectedTipo, selectedRetorno], () => {
  page.value = 1
  fetchRotas()
})

// Watcher separado para itemsPerPage (reseta página)
watch(itemsPerPage, () => {
  page.value = 1
  fetchRotas()
})

// Watcher para page apenas
watch(page, () => {
  fetchRotas()
})

// Cleanup de timers ao desmontar
onBeforeUnmount(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
    searchDebounceTimer = null
  }
})

// Funções
const viewMap = (item: RotaSemParar) => {
  router.push(`/rotas-padrao/mapa/${item.spararrotid}`)
}

// Estado do dialog de confirmação
const deleteDialog = ref(false)
const rotaToDelete = ref<RotaSemParar | null>(null)

const confirmDelete = (item: RotaSemParar) => {
  rotaToDelete.value = item
  deleteDialog.value = true
}

const deleteRoute = async () => {
  if (!rotaToDelete.value || deleting.value) return

  try {
    deleting.value = true

    const response = await apiFetch(API_ENDPOINTS.semPararRota(rotaToDelete.value.spararrotid), {
      method: 'DELETE'
    })

    const data = await response.json()

    if (data.success) {
      showSuccess(`Rota "${rotaToDelete.value.desspararrot}" excluída com sucesso!`)
      await fetchRotas()
    } else {
      showError('Erro ao excluir rota: ' + (data.message || 'Erro desconhecido'))
    }
  } catch (error) {
    console.error('Erro ao excluir rota:', error)
    showError('Erro ao excluir rota. Verifique sua conexão e tente novamente.')
  } finally {
    deleting.value = false
    deleteDialog.value = false
    rotaToDelete.value = null
  }
}

const createNewRoute = () => {
  router.push('/rotas-padrao/nova')
}

// Formatar data
const formatDate = (date: string | null) => {
  if (!date) return 'N/D'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('pt-BR')
}

// Lifecycle
onMounted(() => {
  fetchRotas()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Rotas Padrão
        </h4>
        <div class="d-flex align-center flex-wrap gap-3">
          <span class="text-body-1">Sistema de rotas pré-programadas</span>
          <VChip size="small" color="primary" variant="tonal">
            {{ statistics.total }} Total
          </VChip>
          <VChip size="small" color="info" variant="tonal" v-if="!selectedTipo">
            {{ statistics.comCD }} Retornos
          </VChip>
        </div>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="createNewRoute"
      >
        Nova Rota
      </VBtn>
    </div>

    <!-- Card Principal -->
    <VCard>
      <!-- Controles superiores -->
      <VCardText class="d-flex flex-wrap gap-4">
        <!-- Items per page -->
        <AppSelect
          :model-value="itemsPerPage"
          :items="[
            { value: 10, title: '10' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
            { value: 100, title: '100' }
          ]"
          style="inline-size: 5rem;"
          @update:model-value="itemsPerPage = parseInt($event, 10)"
        />

        <VSpacer />

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- Busca -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar rota"
            prepend-inner-icon="tabler-search"
            style="inline-size: 15rem;"
          />

          <!-- Toggle Tipo (3 estados) -->
          <VBtn
            :variant="selectedTipo === 'all' ? 'tonal' : 'flat'"
            :color="tipoButtonColor"
            :prepend-icon="tipoButtonIcon"
            @click="toggleTipo"
          >
            {{ tipoButtonLabel }}
          </VBtn>

          <!-- Toggle Retorno (3 estados) -->
          <VBtn
            :variant="selectedRetorno !== 'all' ? 'flat' : 'tonal'"
            :color="retornoButtonColor"
            :prepend-icon="retornoButtonIcon"
            @click="toggleRetorno"
          >
            {{ retornoButtonLabel }}
          </VBtn>

          <!-- Botão Atualizar -->
          <VBtn
            prepend-icon="tabler-refresh"
            @click="fetchRotas"
            :loading="loading"
          >
            Atualizar
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Tabela -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="serverItems"
        :items-length="totalRotas"
        :headers="headers"
        :loading="loading"
        loading-text="Carregando rotas..."
        no-data-text="Nenhuma rota encontrada"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Código -->
        <template #item.spararrotid="{ item }">
          <div class="text-body-1 font-weight-medium text-high-emphasis">
            <span class="text-primary">#{{ item.spararrotid }}</span>
          </div>
        </template>

        <!-- Descrição -->
        <template #item.desspararrot="{ item }">
          <div class="d-flex align-center">
            <VAvatar
              size="32"
              :color="item.flgcd ? 'primary' : 'secondary'"
              variant="tonal"
              class="me-3"
            >
              <VIcon
                :icon="item.flgcd ? 'tabler-building-warehouse' : 'tabler-route'"
                size="18"
              />
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-medium text-high-emphasis">
                {{ item.desspararrot.toUpperCase() }}
              </div>
              <div class="text-sm text-medium-emphasis">
                {{ item.totalmunicipios }} município(s) {{ item.flgretorno ? '• RETORNO' : '' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Tempo -->
        <template #item.tempoviagem="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ item.tempoviagem }} {{ item.tempoviagem === 1 ? 'dia' : 'dias' }}
          </VChip>
        </template>

        <!-- Municípios -->
        <template #item.totalmunicipios="{ item }">
          <div class="text-center">
            <VChip
              size="small"
              color="secondary"
              variant="tonal"
            >
              <VIcon start size="14">tabler-map-pin</VIcon>
              {{ item.totalmunicipios }}
            </VChip>
          </div>
        </template>

        <!-- Tipo -->
        <template #item.flgcd="{ item }">
          <VChip
            v-if="item.flgcd"
            color="info"
            size="small"
            variant="tonal"
          >
            CD
          </VChip>
          <VChip
            v-else
            color="default"
            size="small"
            variant="tonal"
          >
            ROTA
          </VChip>
        </template>

        <!-- Retorno -->
        <template #item.flgretorno="{ item }">
          <VIcon
            :icon="item.flgretorno ? 'tabler-check' : 'tabler-x'"
            :color="item.flgretorno ? 'success' : 'error'"
            size="20"
          />
        </template>

        <!-- Ações -->
        <template #item.actions="{ item }">
          <IconBtn @click="viewMap(item)">
            <VIcon icon="tabler-map-2" />
            <VTooltip activator="parent">Ver no Mapa</VTooltip>
          </IconBtn>

          <IconBtn @click="confirmDelete(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Excluir</VTooltip>
          </IconBtn>
        </template>

        <!-- Paginação -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalRotas"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Dialog de Confirmação de Exclusão -->
    <VDialog
      v-model="deleteDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 bg-error">
          <VIcon icon="tabler-alert-triangle" />
          Confirmar Exclusão
        </VCardTitle>

        <VCardText class="pt-5">
          <p class="text-body-1 mb-4">
            Tem certeza que deseja excluir a rota:
          </p>
          <p class="text-h6 font-weight-medium text-error mb-2">
            {{ rotaToDelete?.desspararrot }}
          </p>
          <p class="text-body-2 text-medium-emphasis">
            Esta ação não pode ser desfeita.
          </p>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="deleteDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            :disabled="deleting"
            :loading="deleting"
            @click="deleteRoute"
          >
            Excluir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Toast de Notificações -->
    <VSnackbar
      v-model="toast.show"
      :color="toast.color"
      :timeout="toast.timeout"
      location="top right"
    >
      {{ toast.message }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.invoice-list-filter {
  inline-size: 12.5rem;
}
</style>
