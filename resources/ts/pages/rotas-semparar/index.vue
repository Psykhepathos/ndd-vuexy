<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

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

// Estados reativos
const rotas = ref<RotaSemParar[]>([])
const loading = ref(false)
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
  if (selectedTipo.value === 'all') selectedTipo.value = 'cd'
  else if (selectedTipo.value === 'cd') selectedTipo.value = 'rota'
  else selectedTipo.value = 'all'
}

// Função para toggle do retorno (3 estados)
const toggleRetorno = () => {
  if (selectedRetorno.value === 'all') selectedRetorno.value = 'com'
  else if (selectedRetorno.value === 'com') selectedRetorno.value = 'sem'
  else selectedRetorno.value = 'all'
}

// Computed para label e cor do botão TIPO
const tipoButtonLabel = computed(() => {
  if (selectedTipo.value === 'cd') return 'Apenas CDs'
  if (selectedTipo.value === 'rota') return 'Apenas Rotas'
  return 'Todas'
})

const tipoButtonColor = computed(() => {
  if (selectedTipo.value === 'cd') return 'info'
  if (selectedTipo.value === 'rota') return 'primary'
  return 'default'
})

const tipoButtonIcon = computed(() => {
  if (selectedTipo.value === 'cd') return 'tabler-building-warehouse'
  if (selectedTipo.value === 'rota') return 'tabler-route'
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

    const response = await fetch(`http://localhost:8002/api/semparar-rotas?${params}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success && data.data) {
      serverItems.value = data.data.map((item: any) => ({
        spararrotid: item.spararrotid || item.sPararRotID,
        desspararrot: item.desspararrot || item.desSPararRot || '',
        tempoviagem: item.tempoviagem || item.tempoViagem || 0,
        flgcd: item.flgcd === true || item.flgcd === 1 || item.flgCD === true || item.flgCD === 1,
        flgretorno: item.flgretorno === true || item.flgretorno === 1 || item.flgRetorno === true || item.flgRetorno === 1,
        totalmunicipios: item.totalmunicipios || 0,
        datatu: item.datatu || null,
        resatu: item.resatu || null
      }))

      if (data.pagination) {
        totalRotas.value = data.pagination.total || 0
        page.value = parseInt(data.pagination.current_page) || 1
      } else {
        totalRotas.value = serverItems.value.length
      }
    }
  } catch (error) {
    console.error('Erro ao buscar rotas:', error)
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

// Watchers
watch([searchQuery, selectedTipo, selectedRetorno], () => {
  page.value = 1
  fetchRotas()
})

watch([page, itemsPerPage], () => {
  fetchRotas()
})

// Funções
const viewMap = (item: RotaSemParar) => {
  router.push(`/rotas-semparar/mapa/${item.spararrotid}`)
}

const deleteRoute = async (item: RotaSemParar) => {
  if (!confirm(`Tem certeza que deseja excluir a rota "${item.desspararrot}"?`)) {
    return
  }

  try {
    const response = await fetch(`http://localhost:8002/api/semparar-rotas/${item.spararrotid}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success) {
      await fetchRotas()
    }
  } catch (error) {
    console.error('Erro ao excluir rota:', error)
  }
}

const createNewRoute = () => {
  router.push('/rotas-semparar/nova')
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
          Rotas SemParar
        </h4>
        <div class="d-flex align-center flex-wrap gap-3">
          <span class="text-body-1">Sistema de rotas pré-programadas</span>
          <VChip size="small" color="primary" variant="tonal">
            {{ statistics.total }} Total
          </VChip>
          <VChip size="small" color="info" variant="tonal" v-if="!selectedTipo">
            {{ statistics.comCD }} c/ Retorno
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
            :variant="selectedTipo !== 'all' ? 'flat' : 'tonal'"
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

          <IconBtn @click="deleteRoute(item)">
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
  </div>
</template>

<style scoped>
.invoice-list-filter {
  inline-size: 12.5rem;
}
</style>
