<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { watchDebounced } from '@vueuse/core'
import { apiPost, apiFetch } from '@/config/api'

definePage({
  meta: {
    layoutWrapperClasses: 'layout-content-height-fixed',
  },
})

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
  cancelado: boolean
  cod_rota_create_sp: string | null
  responsavel_compra: string | null
  s_parar_rot_id: number | null
}

interface RotaSemParar {
  id: number
  nome: string
}

interface Transportador {
  codtrn: number
  nomtrn: string
}

// ============================================================================
// ESTADO
// ============================================================================
const router = useRouter()
const viagens = ref<Viagem[]>([])
const loading = ref(false)

// Paginação (padrão Vuexy)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 1,
  to: 15,
  has_more_pages: false
})

// Filtros
const dataInicio = ref('')
const dataFim = ref('')
const rotaSelecionada = ref<number | null>(null)
const placaFiltro = ref('')
const transportadorFiltro = ref<number | null>(null)
const pacoteFiltro = ref<number | null>(null)

// Autocompletes
const rotas = ref<RotaSemParar[]>([])
const rotasLoading = ref(false)
const rotaSearch = ref('')

const transportadores = ref<Transportador[]>([])
const transportadoresLoading = ref(false)
const transportadorSearch = ref('')

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
  { title: 'COD. VIAGEM', key: 'cod_viagem', sortable: false, width: '120px' },
  { title: 'PACOTE', key: 'cod_pac', sortable: false, width: '90px' },
  { title: 'CANCEL', key: 'cancelado', sortable: false, width: '80px' },
  { title: 'PLACA', key: 'placa', sortable: false, width: '90px' },
  { title: 'VALOR', key: 'valor', sortable: false, width: '100px' },
  { title: 'ROTA', key: 'rota_nome', sortable: false, width: '280px' },
  { title: 'TRSP', key: 'transportador', sortable: false, width: '150px', class: 'd-none d-lg-table-cell' },
  { title: 'DATA COMPRA', key: 'data_compra', sortable: true, width: '110px' },
  { title: 'COD ROT SP', key: 'cod_rota_create_sp', sortable: false, width: '100px', class: 'd-none d-xl-table-cell' },
  { title: 'RESP', key: 'responsavel_compra', sortable: false, width: '90px', class: 'd-none d-xl-table-cell' },
  { title: 'AÇÕES', key: 'actions', sortable: false, width: '200px' }
]

// ============================================================================
// COMPUTED
// ============================================================================
const filtrosAtivos = computed(() => {
  let count = 0
  if (rotaSelecionada.value) count++
  if (placaFiltro.value) count++
  if (transportadorFiltro.value) count++
  if (pacoteFiltro.value) count++
  return count
})

const rotaAtualNome = computed(() => {
  if (!rotaSelecionada.value) return ''
  const rota = rotas.value.find(r => r.id === rotaSelecionada.value)
  return rota ? rota.nome : ''
})

const transportadorAtualNome = computed(() => {
  if (!transportadorFiltro.value) return ''
  const transp = transportadores.value.find(t => t.codtrn === transportadorFiltro.value)
  return transp ? transp.nomtrn : ''
})

// ============================================================================
// MÉTODOS - AUTOCOMPLETE
// ============================================================================

/**
 * Busca rotas SemParar para autocomplete
 */
const buscarRotas = async (search: string) => {
  if (!search || search.length < 2) {
    rotas.value = []
    return
  }

  rotasLoading.value = true
  try {
    const response = await apiFetch(
      `/semparar-rotas/municipios?search=${encodeURIComponent(search)}`
    )
    const data = await response.json()

    // Mapeia para formato RotaSemParar
    rotas.value = data.data?.map((item: any) => ({
      id: item.id,
      nome: item.nome
    })) || []
  } catch (error) {
    console.error('Erro ao buscar rotas:', error)
    rotas.value = []
  } finally {
    rotasLoading.value = false
  }
}

/**
 * Busca transportadores para autocomplete
 */
const buscarTransportadores = async (search: string) => {
  if (!search || search.length < 2) {
    transportadores.value = []
    return
  }

  transportadoresLoading.value = true
  try {
    const response = await apiFetch(
      `/transportes?search=${encodeURIComponent(search)}&per_page=20`
    )
    const data = await response.json()

    transportadores.value = data.data?.map((item: any) => ({
      codtrn: item.codtrn,
      nomtrn: item.nomtrn
    })) || []
  } catch (error) {
    console.error('Erro ao buscar transportadores:', error)
    transportadores.value = []
  } finally {
    transportadoresLoading.value = false
  }
}

// ============================================================================
// MÉTODOS - BUSCA E FILTROS
// ============================================================================

/**
 * Busca viagens com filtros (Progress database - PUB.sPararViagem)
 */
const fetchViagens = async () => {
  if (!dataInicio.value || !dataFim.value) {
    showToast('Selecione um período de busca', 'warning')
    return
  }

  loading.value = true
  try {
    const payload: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      data_inicio: dataInicio.value,
      data_fim: dataFim.value,
    }

    // Adiciona filtros opcionais
    if (rotaSelecionada.value) {
      payload.s_parar_rot_id = rotaSelecionada.value
    }
    if (placaFiltro.value) {
      payload.placa = placaFiltro.value.toUpperCase()
    }
    if (transportadorFiltro.value) {
      payload.cod_trn = transportadorFiltro.value
    }
    if (pacoteFiltro.value) {
      payload.cod_pac = pacoteFiltro.value
    }

    console.log('🔍 Buscando viagens com filtros:', payload)

    const response = await apiPost(`/compra-viagem/viagens`, payload)
    const data = await response.json()

    if (!data.success) {
      showToast(data.message || 'Erro ao buscar viagens', 'error')
      return
    }

    viagens.value = data.data || []

    // Atualizar paginação
    if (data.pagination) {
      pagination.value = data.pagination
    }

    showToast(`${pagination.value.total} viagens encontradas`, 'success')
  } catch (error: any) {
    console.error('💥 ERRO ao buscar viagens:', error)
    showToast(error.message || 'Erro ao buscar viagens', 'error')
  } finally {
    loading.value = false
  }
}

/**
 * Handler para mudança de página (padrão Vuexy)
 */
const handlePageChange = (page: number) => {
  pagination.value.current_page = page
  fetchViagens()
}

/**
 * Handler para mudança de items per page
 */
const handleItemsPerPageChange = (itemsPerPage: number) => {
  pagination.value.per_page = itemsPerPage
  pagination.value.current_page = 1  // Reset para primeira página
  fetchViagens()
}

/**
 * Limpa todos os filtros
 */
const limparFiltros = () => {
  rotaSelecionada.value = null
  placaFiltro.value = ''
  transportadorFiltro.value = null
  pacoteFiltro.value = null
  rotaSearch.value = ''
  transportadorSearch.value = ''
  viagens.value = []

  // Reset paginação
  pagination.value.current_page = 1

  // Mantém o período de 1 ano
  const hoje = new Date()
  const umAnoAtras = new Date()
  umAnoAtras.setFullYear(umAnoAtras.getFullYear() - 1)
  dataInicio.value = umAnoAtras.toISOString().split('T')[0]
  dataFim.value = hoje.toISOString().split('T')[0]
}

// ============================================================================
// WATCHERS - AUTO-BUSCA QUANDO FILTROS MUDAM
// ============================================================================

/**
 * Watcher para datas - dispara busca quando ambas as datas estão preenchidas
 */
watch([dataInicio, dataFim], ([novoInicio, novoFim]) => {
  if (novoInicio && novoFim) {
    console.log('📅 Datas mudaram, disparando busca automática')
    pagination.value.current_page = 1
    fetchViagens()
  }
})

/**
 * Watchers para filtros opcionais com debounce (300ms)
 * Dispara busca automática apenas se houver período válido
 */
watchDebounced(
  [rotaSelecionada, placaFiltro, transportadorFiltro, pacoteFiltro],
  ([novaRota, novaPlaca, novoTransp, novoPacote], [rotaAnt, placaAnt, transpAnt, pacoteAnt]) => {
    if (
      novaRota !== rotaAnt ||
      novaPlaca !== placaAnt ||
      novoTransp !== transpAnt ||
      novoPacote !== pacoteAnt
    ) {
      console.log('🔧 Filtros opcionais mudaram:', {
        rota: `${rotaAnt} → ${novaRota}`,
        placa: `${placaAnt} → ${novaPlaca}`,
        transportador: `${transpAnt} → ${novoTransp}`,
        pacote: `${pacoteAnt} → ${novoPacote}`
      })

      // Só dispara busca se tiver período válido
      if (dataInicio.value && dataFim.value) {
        pagination.value.current_page = 1
        fetchViagens()
      }
    }
  },
  { debounce: 300 }
)

// ============================================================================
// MÉTODOS - NAVEGAÇÃO E AÇÕES
// ============================================================================

/**
 * Navega para página de nova compra
 */
const irParaNovaCompra = () => {
  router.push({ name: 'compra-viagem-nova' })
}

/**
 * Navega para detalhes da viagem
 */
const verDetalhes = (codViagem: string) => {
  router.push({ name: 'compra-viagem-id', params: { id: codViagem } })
}

/**
 * Baixar recibo PDF via WhatsApp
 */
const baixarRecibo = async (codViagem: string) => {
  try {
    const telefone = prompt('Digite o número com DDD (ex: 31988887777)')
    if (!telefone) return

    const response = await apiPost(`/semparar/gerar-recibo`, {
      cod_viagem: codViagem,
      telefone: `55${telefone}`,
      flg_imprime: false,
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
    const response = await apiPost(`/semparar/cancelar-viagem`, {
      cod_viagem: codViagem,
    })
    const data = await response.json()

    if (data.success) {
      showToast('Viagem cancelada com sucesso', 'success')
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
    const response = await apiPost(`/semparar/reemitir-viagem`, {
      cod_viagem: codViagem,
      placa: novaPlaca.toUpperCase(),
    })
    const data = await response.json()

    if (data.success) {
      showToast('Viagem reemitida com sucesso', 'success')
      await fetchViagens()
    } else {
      showToast(data.message || 'Erro ao reemitir viagem', 'error')
    }
  } catch (error: any) {
    console.error('Erro ao reemitir viagem:', error)
    showToast(error.message || 'Erro ao reemitir viagem', 'error')
  }
}

// ============================================================================
// MÉTODOS - FORMATAÇÃO
// ============================================================================

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
  if (data.includes('/')) return data
  const [ano, mes, dia] = data.split('-')
  return `${dia}/${mes}/${ano}`
}

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
onMounted(() => {
  console.log('🚀 Componente compra-viagem montado!')

  // Define período padrão: 2 ANOS (1 ano atrás até 1 ano à frente)
  // Isso captura dados de teste que podem estar com datas futuras
  const hoje = new Date()
  const umAnoAtras = new Date()
  umAnoAtras.setFullYear(umAnoAtras.getFullYear() - 1)
  const umAnoFrente = new Date()
  umAnoFrente.setFullYear(umAnoFrente.getFullYear() + 1)

  dataInicio.value = umAnoAtras.toISOString().split('T')[0]
  dataFim.value = umAnoFrente.toISOString().split('T')[0]

  console.log('📅 Período padrão definido (2 anos):', {
    inicio: dataInicio.value,
    fim: dataFim.value
  })

  // REMOVIDO: Auto-fetch para resolver problema de render
  // Agora o usuário precisa clicar em "Buscar"
  // Isso evita race conditions no carregamento inicial
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Consulta de Viagens SemParar
        </h4>
        <div class="d-flex align-center flex-wrap gap-3">
          <span class="text-body-1">Sistema de gestão de vale-pedágio</span>
          <VChip
            v-if="pagination.total > 0"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ pagination.total }} Total
          </VChip>
        </div>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="irParaNovaCompra"
      >
        Nova Compra
      </VBtn>
    </div>

    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <!-- Período -->
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="dataInicio"
              label="Data Início"
              type="date"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-calendar"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="dataFim"
              label="Data Fim"
              type="date"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-calendar"
            />
          </VCol>

          <!-- Rota -->
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VAutocomplete
              v-model="rotaSelecionada"
              v-model:search="rotaSearch"
              :items="rotas"
              :loading="rotasLoading"
              label="Rota SemParar (opcional)"
              placeholder="Digite para buscar..."
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-route"
              item-title="nome"
              item-value="id"
              clearable
              no-data-text="Digite para buscar rotas"
              @update:search="buscarRotas"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :title="`#${item.raw.id} - ${item.raw.nome}`"
                />
              </template>
            </VAutocomplete>
          </VCol>

          <!-- Placa -->
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="placaFiltro"
              label="Placa (opcional)"
              placeholder="ABC1234"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-car"
              maxlength="7"
              clearable
              @update:model-value="placaFiltro = placaFiltro.toUpperCase()"
            />
          </VCol>

          <!-- Transportador -->
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <VAutocomplete
              v-model="transportadorFiltro"
              v-model:search="transportadorSearch"
              :items="transportadores"
              :loading="transportadoresLoading"
              label="Transportador (opcional)"
              placeholder="Digite para buscar..."
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-truck"
              item-title="nomtrn"
              item-value="codtrn"
              clearable
              no-data-text="Digite para buscar transportadores"
              @update:search="buscarTransportadores"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :title="`#${item.raw.codtrn} - ${item.raw.nomtrn}`"
                />
              </template>
            </VAutocomplete>
          </VCol>

          <!-- Pacote -->
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model.number="pacoteFiltro"
              label="Pacote (opcional)"
              type="number"
              placeholder="Ex: 3043824"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-package"
              clearable
            />
          </VCol>

          <!-- Botões -->
          <VCol
            cols="12"
            sm="6"
            md="3"
            class="d-flex gap-2"
          >
            <VBtn
              color="primary"
              :loading="loading"
              @click="fetchViagens"
            >
              <VIcon
                icon="tabler-search"
                start
              />
              Buscar
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              @click="limparFiltros"
            >
              <VIcon
                icon="tabler-x"
                start
              />
              Limpar
            </VBtn>
          </VCol>
        </VRow>

        <!-- Filtros ativos -->
        <VRow v-if="filtrosAtivos > 0">
          <VCol cols="12">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="text-sm text-medium-emphasis">Filtros ativos:</span>

              <VChip
                v-if="rotaSelecionada"
                color="primary"
                variant="tonal"
                size="small"
                closable
                @click:close="rotaSelecionada = null"
              >
                Rota: {{ rotaAtualNome }}
              </VChip>

              <VChip
                v-if="placaFiltro"
                color="secondary"
                variant="tonal"
                size="small"
                closable
                @click:close="placaFiltro = ''"
              >
                Placa: {{ placaFiltro }}
              </VChip>

              <VChip
                v-if="transportadorFiltro"
                color="info"
                variant="tonal"
                size="small"
                closable
                @click:close="transportadorFiltro = null"
              >
                Transportador: {{ transportadorAtualNome }}
              </VChip>

              <VChip
                v-if="pacoteFiltro"
                color="warning"
                variant="tonal"
                size="small"
                closable
                @click:close="pacoteFiltro = null"
              >
                Pacote: {{ pacoteFiltro }}
              </VChip>

              <VBtn
                variant="text"
                size="small"
                @click="limparFiltros"
              >
                Limpar todos
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabela -->
    <VCard>
      <VDivider />

      <!-- Tabela -->
          <VDataTableServer
            v-model:items-per-page="pagination.per_page"
            :headers="headers"
            :items="viagens"
            :items-length="pagination.total"
            :loading="loading"
            :page="pagination.current_page"
            @update:page="handlePageChange"
            @update:items-per-page="handleItemsPerPageChange"
            class="text-no-wrap"
            hover
            loading-text="Carregando viagens..."
            no-data-text="Nenhuma viagem encontrada"
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

          <!-- Cancelado -->
          <template #item.cancelado="{ item }">
            <VChip
              :color="item.cancelado ? 'error' : 'success'"
              variant="tonal"
              size="small"
            >
              {{ item.cancelado ? 'SIM' : 'NÃO' }}
            </VChip>
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

          <!-- Valor -->
          <template #item.valor="{ item }">
            <div class="text-body-1 font-weight-semibold text-success">
              {{ formatarValor(item.valor) }}
            </div>
          </template>

          <!-- Rota -->
          <template #item.rota_nome="{ item }">
            <div class="text-body-2">
              {{ item.rota_nome }}
            </div>
          </template>

          <!-- Transportador -->
          <template #item.transportador="{ item }">
            <div class="text-body-2">
              {{ item.transportador || '-' }}
            </div>
          </template>

          <!-- Data -->
          <template #item.data_compra="{ item }">
            <div class="text-body-2">
              {{ formatarData(item.data_compra) }}
            </div>
          </template>

          <!-- Código Rota SP -->
          <template #item.cod_rota_create_sp="{ item }">
            <div class="text-body-2 text-disabled">
              {{ item.cod_rota_create_sp || '-' }}
            </div>
          </template>

          <!-- Responsável -->
          <template #item.responsavel_compra="{ item }">
            <div class="text-body-2">
              {{ item.responsavel_compra || '-' }}
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
                :disabled="item.cancelado"
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
                :disabled="item.cancelado"
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
                :disabled="item.cancelado"
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
                icon="tabler-search"
                size="64"
                color="primary"
                class="mb-4"
              />
              <p class="text-h6 text-high-emphasis mb-2">
                {{ viagens.length === 0 && !loading ? 'Pronto para buscar viagens!' : 'Nenhuma viagem encontrada' }}
              </p>
              <p class="text-body-1 text-medium-emphasis mb-4">
                {{ filtrosAtivos > 0 ? 'Tente ajustar os filtros e buscar novamente' : 'Clique no botão "Buscar" para carregar as viagens' }}
              </p>
              <VBtn
                v-if="viagens.length === 0 && !loading"
                color="primary"
                size="large"
                @click="fetchViagens"
              >
                <VIcon
                  icon="tabler-search"
                  start
                />
                Buscar Viagens
              </VBtn>
            </div>
          </template>

          <!-- Loading -->
          <template #loading>
            <VProgressLinear
              indeterminate
              color="primary"
            />
          </template>
        </VDataTableServer>
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
