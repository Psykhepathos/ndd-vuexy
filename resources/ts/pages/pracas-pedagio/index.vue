<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { apiFetch, apiPost } from '@/config/api'

const router = useRouter()

// ============================================================================
// INTERFACES & TYPES
// ============================================================================

interface PracaPedagio {
  id: number
  concessionaria: string
  praca: string
  rodovia: string
  uf: string
  km: string
  municipio: string
  situacao: string
  latitude: string
  longitude: string
}

interface Statistics {
  total: number
  ativas: number
  inativas: number
  por_uf: Array<{ uf: string; total: number }>
  por_concessionaria: Array<{ concessionaria: string; total: number }>
}

// ============================================================================
// STATE
// ============================================================================

const loading = ref(false)
const importing = ref(false)
const loadingStats = ref(false)
const search = ref('')
const totalItems = ref(0)
const pracas = ref<PracaPedagio[]>([])
const statistics = ref<Statistics | null>(null)

// Upload state
const selectedFile = ref<File | null>(null)
const importResult = ref<any>(null)
const showImportDialog = ref(false)

// Filtros
const filtroUF = ref('')
const filtroRodovia = ref('')
const filtroSituacao = ref('Ativo')

// Pagination options
const options = ref({
  page: 1,
  itemsPerPage: 15,
  sortBy: ['rodovia'],
  sortOrder: ['asc']
})

// Lock para prevenir dupla chamada de loadPracas
let isLoadingLocked = false

// ============================================================================
// TABLE HEADERS
// ============================================================================

const headers = [
  { title: 'RODOVIA', key: 'rodovia', sortable: true, width: '100px' },
  { title: 'PRAÇA', key: 'praca', sortable: false },
  { title: 'MUNICÍPIO', key: 'municipio', sortable: false, width: '150px' },
  { title: 'UF', key: 'uf', sortable: true, width: '60px' },
  { title: 'KM', key: 'km', sortable: false, width: '80px' },
  { title: 'CONCESSIONÁRIA', key: 'concessionaria', sortable: false },
  { title: 'SITUAÇÃO', key: 'situacao', sortable: false, width: '100px' },
  { title: 'AÇÕES', key: 'actions', sortable: false, width: '100px' }
]

// ============================================================================
// COMPUTED
// ============================================================================

const UFsDisponiveis = computed(() => {
  if (!statistics.value) return []
  return statistics.value.por_uf.map(item => item.uf)
})

const canImport = computed(() => {
  return selectedFile.value !== null && !importing.value
})

// ============================================================================
// METHODS
// ============================================================================

const loadStatistics = async () => {
  loadingStats.value = true
  try {
    const response = await apiFetch(`${window.location.origin}/api/pracas-pedagio/estatisticas`)
    const data = await response.json()
    if (data.success) {
      statistics.value = data.data
    }
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error)
  } finally {
    loadingStats.value = false
  }
}

const loadPracas = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: options.value.page.toString(),
      per_page: options.value.itemsPerPage.toString(),
      sort_by: options.value.sortBy[0] || 'rodovia',
      sort_order: options.value.sortOrder[0] || 'asc'
    })

    if (search.value) params.append('search', search.value)
    if (filtroUF.value) params.append('uf', filtroUF.value)
    if (filtroRodovia.value) params.append('rodovia', filtroRodovia.value)
    if (filtroSituacao.value) params.append('situacao', filtroSituacao.value)

    const response = await apiFetch(`${window.location.origin}/api/pracas-pedagio?${params.toString()}`)
    const data = await response.json()

    if (data.success) {
      pracas.value = data.data
      totalItems.value = data.pagination.total
    }
  } catch (error) {
    console.error('Erro ao carregar praças:', error)
  } finally {
    loading.value = false
  }
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0]
    importResult.value = null
  }
}

const importCSV = async () => {
  if (!selectedFile.value) return

  importing.value = true
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    // Para FormData, precisamos definir headers manualmente:
    // - Authorization: Bearer token (para auth:sanctum)
    // - Accept: application/json
    // - NÃO incluir Content-Type (browser define automaticamente com boundary)
    const accessToken = useCookie('accessToken').value
    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    }
    if (accessToken) {
      headers['Authorization'] = `Bearer ${accessToken}`
    }

    const response = await fetch(`${window.location.origin}/api/pracas-pedagio/importar`, {
      method: 'POST',
      body: formData,
      headers,
    })
    const data = await response.json()
    importResult.value = data

    if (data.success) {
      // Recarregar dados
      await Promise.all([loadStatistics(), loadPracas()])

      // Limpar seleção de arquivo
      selectedFile.value = null

      // Fechar dialog após 2 segundos se não houver erros
      if (data.data.errors === 0) {
        setTimeout(() => {
          showImportDialog.value = false
          importResult.value = null
        }, 2000)
      }
    }
  } catch (error) {
    console.error('Erro ao importar CSV:', error)
    importResult.value = {
      success: false,
      error: 'Erro ao enviar arquivo para o servidor'
    }
  } finally {
    importing.value = false
  }
}

const viewOnMap = (praca: PracaPedagio) => {
  // Navegar para página interna de visualização com OSM
  router.push({ name: 'pracas-pedagio-mapa-id', params: { id: praca.id } })
}

const formatKm = (km: string) => {
  return parseFloat(km).toFixed(1) + ' km'
}

// ============================================================================
// WATCHERS
// ============================================================================

watchDebounced(search, () => {
  if (isLoadingLocked) return
  isLoadingLocked = true
  options.value.page = 1
  loadPracas().finally(() => {
    isLoadingLocked = false
  })
}, { debounce: 500 })

watchDebounced([filtroUF, filtroRodovia, filtroSituacao], () => {
  if (isLoadingLocked) return
  isLoadingLocked = true
  options.value.page = 1
  loadPracas().finally(() => {
    isLoadingLocked = false
  })
}, { debounce: 300 })

watchDebounced(() => options.value.itemsPerPage, () => {
  if (isLoadingLocked) return
  isLoadingLocked = true
  options.value.page = 1
  loadPracas().finally(() => {
    isLoadingLocked = false
  })
}, { debounce: 300 })

watchDebounced(() => options.value.page, () => {
  if (isLoadingLocked) return
  isLoadingLocked = true
  loadPracas().finally(() => {
    isLoadingLocked = false
  })
}, { debounce: 300 })

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(async () => {
  await Promise.all([loadStatistics(), loadPracas()])
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-y-4 mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Praças de Pedágio (ANTT)
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Gestão e importação de praças de pedágio cadastradas na ANTT
        </div>
      </div>

      <div class="d-flex gap-4">
        <VBtn
          color="primary"
          prepend-icon="tabler-upload"
          @click="showImportDialog = true"
        >
          Importar CSV
        </VBtn>
      </div>
    </div>

    <!-- Statistics Cards -->
    <VRow v-if="statistics" class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                rounded
                color="primary"
                variant="tonal"
                size="44"
                class="me-4"
              >
                <VIcon icon="tabler-coin" size="28" />
              </VAvatar>
              <div>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Total de Praças
                </p>
                <h4 class="text-h4 font-weight-medium">
                  {{ statistics.total }}
                </h4>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                rounded
                color="success"
                variant="tonal"
                size="44"
                class="me-4"
              >
                <VIcon icon="tabler-check" size="28" />
              </VAvatar>
              <div>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Praças Ativas
                </p>
                <h4 class="text-h4 font-weight-medium">
                  {{ statistics.ativas }}
                </h4>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                rounded
                color="error"
                variant="tonal"
                size="44"
                class="me-4"
              >
                <VIcon icon="tabler-x" size="28" />
              </VAvatar>
              <div>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Praças Inativas
                </p>
                <h4 class="text-h4 font-weight-medium">
                  {{ statistics.inativas }}
                </h4>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                rounded
                color="info"
                variant="tonal"
                size="44"
                class="me-4"
              >
                <VIcon icon="tabler-map-pin" size="28" />
              </VAvatar>
              <div>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Estados Cobertos
                </p>
                <h4 class="text-h4 font-weight-medium">
                  {{ statistics.por_uf.length }}
                </h4>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters and Table -->
    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap gap-4 mb-6">
          <!-- Search -->
          <div style="inline-size: 250px;">
            <AppTextField
              v-model="search"
              placeholder="Buscar praça ou município..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </div>

          <!-- UF Filter -->
          <AppSelect
            v-model="filtroUF"
            :items="UFsDisponiveis"
            placeholder="Todos os estados"
            clearable
            style="inline-size: 150px;"
          />

          <!-- Rodovia Filter -->
          <AppTextField
            v-model="filtroRodovia"
            placeholder="Rodovia (ex: BR-381)"
            clearable
            style="inline-size: 180px;"
          />

          <!-- Situação Filter -->
          <AppSelect
            v-model="filtroSituacao"
            :items="['Ativo', 'Inativo']"
            placeholder="Situação"
            clearable
            style="inline-size: 150px;"
          />
        </div>

        <!-- Data Table -->
        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :headers="headers"
          :items="pracas"
          :items-length="totalItems"
          :loading="loading"
          item-value="id"
          class="text-no-wrap"
        >
          <!-- Situação Badge -->
          <template #item.situacao="{ item }">
            <VChip
              :color="item.situacao === 'Ativo' ? 'success' : 'error'"
              size="small"
            >
              {{ item.situacao }}
            </VChip>
          </template>

          <!-- KM formatted -->
          <template #item.km="{ item }">
            {{ formatKm(item.km) }}
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <VBtn
              icon
              size="small"
              variant="text"
              color="default"
              @click="viewOnMap(item)"
            >
              <VIcon icon="tabler-map-pin" />
              <VTooltip activator="parent" location="top">
                Ver no mapa (OpenStreetMap)
              </VTooltip>
            </VBtn>
          </template>

          <!-- Loading -->
          <template #loading>
            <VSkeletonLoader type="table-row@10" />
          </template>

          <!-- No data -->
          <template #no-data>
            <div class="text-center pa-4">
              <VIcon icon="tabler-database-off" size="48" class="mb-2" />
              <p class="text-body-1 text-medium-emphasis">
                Nenhuma praça encontrada
              </p>
              <p class="text-body-2 text-medium-emphasis">
                Importe o CSV da ANTT para começar
              </p>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Import Dialog -->
    <VDialog
      v-model="showImportDialog"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-upload" />
          Importar Praças de Pedágio
        </VCardTitle>

        <VCardText>
          <div class="mb-4">
            <p class="text-body-2 text-medium-emphasis mb-2">
              Selecione o arquivo CSV exportado da ANTT contendo os dados das praças de pedágio.
            </p>
            <p class="text-body-2 text-medium-emphasis">
              O arquivo deve conter as colunas: concessionaria, praca_de_pedagio, rodovia, uf, km_m, municipio, latitude, longitude, etc.
            </p>
          </div>

          <!-- File Input -->
          <VFileInput
            v-model="selectedFile"
            label="Arquivo CSV"
            accept=".csv"
            prepend-icon="tabler-file-text"
            show-size
            :disabled="importing"
            @change="handleFileSelect"
          />

          <!-- Import Result -->
          <VAlert
            v-if="importResult"
            :type="importResult.success ? 'success' : 'error'"
            class="mt-4"
          >
            <template v-if="importResult.success">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-check" />
                <span class="font-weight-medium">{{ importResult.message }}</span>
              </div>
              <div class="text-body-2">
                • Importadas: {{ importResult.data.imported }} praças<br>
                • Erros: {{ importResult.data.errors }}<br>
                • Duração: {{ importResult.data.duration }}
              </div>

              <!-- Show errors if any -->
              <div v-if="importResult.data.errors > 0" class="mt-3">
                <p class="text-body-2 font-weight-medium mb-2">Erros encontrados:</p>
                <div class="error-list" style="max-block-size: 200px; overflow-y: auto;">
                  <div
                    v-for="(error, idx) in importResult.data.error_details"
                    :key="idx"
                    class="text-caption mb-1"
                  >
                    Linha {{ error.line }}: {{ error.error }}
                  </div>
                </div>
              </div>
            </template>
            <template v-else>
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-alert-circle" />
                <span>{{ importResult.error || 'Erro ao importar arquivo' }}</span>
              </div>
            </template>
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="importing"
            @click="showImportDialog = false; importResult = null; selectedFile = null"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="importing"
            :disabled="!canImport"
            @click="importCSV"
          >
            Importar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.error-list {
  background-color: rgba(var(--v-theme-error), 0.08);
  padding: 12px;
  border-radius: 4px;
}
</style>
