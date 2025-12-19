<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { usePackageSimulation } from '@/composables/usePackageSimulation'

// ============================================================================
// SISTEMA DE DEBUG E LOGGING
// ============================================================================
interface DebugLog {
  timestamp: string
  level: 'info' | 'warn' | 'error' | 'success'
  category: string
  message: string
  data?: any
}

const debugLogs = ref<DebugLog[]>([])
const showDebugPanel = ref(false)
const debugStats = ref({
  totalRequests: 0,
  successfulRequests: 0,
  failedRequests: 0,
  cacheHits: 0,
  lastUpdate: null as Date | null
})

const addDebugLog = (level: DebugLog['level'], category: string, message: string, data?: any) => {
  const log: DebugLog = {
    timestamp: new Date().toISOString(),
    level,
    category,
    message,
    data
  }
  debugLogs.value.unshift(log)

  // Manter apenas últimos 100 logs
  if (debugLogs.value.length > 100) {
    debugLogs.value = debugLogs.value.slice(0, 100)
  }

  // Log no console também
  const emoji = {
    info: 'ℹ️',
    warn: '⚠️',
    error: '❌',
    success: '✅'
  }[level]

  console.log(`${emoji} [${category}] ${message}`, data || '')
}

const clearDebugLogs = () => {
  debugLogs.value = []
  debugStats.value = {
    totalRequests: 0,
    successfulRequests: 0,
    failedRequests: 0,
    cacheHits: 0,
    lastUpdate: null
  }
  addDebugLog('info', 'SYSTEM', 'Logs limpos')
}

// Helper para fazer requests COM DEBUG
const apiFetch = async (url: string, options: RequestInit = {}) => {
  const startTime = performance.now()
  debugStats.value.totalRequests++
  debugStats.value.lastUpdate = new Date()

  addDebugLog('info', 'API', `Request: ${options.method || 'GET'} ${url}`)

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
    })

    const data = await response.json()
    const duration = ((performance.now() - startTime) / 1000).toFixed(2)

    if (data.success !== false) {
      debugStats.value.successfulRequests++
      addDebugLog('success', 'API', `Response ${response.status} em ${duration}s`, data)
    } else {
      debugStats.value.failedRequests++
      addDebugLog('error', 'API', `Erro ${response.status} em ${duration}s`, data)
    }

    return data
  } catch (error: any) {
    const duration = ((performance.now() - startTime) / 1000).toFixed(2)
    debugStats.value.failedRequests++
    addDebugLog('error', 'API', `Exceção após ${duration}s: ${error.message}`, error)
    throw error
  }
}

// ============================================================================
// ESTADO DO FORMULÁRIO
// ============================================================================

// Dados do pacote
const codpac = ref<number | null>(null)
const descPacote = ref('')
const codtrn = ref<number | null>(null)
const nomeTransporte = ref('')

// Dados da placa
const placa = ref('')
const descricaoVei = ref('')
const eixos = ref<number>(2)
const proprietario = ref('')
const tag = ref('')

// Dados da rota
const rotaId = ref<number | null>(null)
const rotaNome = ref('')
const rotasOptions = ref<any[]>([]) // Rotas atuais (muda com modo CD)
const rotasCache = ref<{cd: any[], normal: any[]}>({ cd: [], normal: [] }) // Cache completo

// Dados da compra
const nomRotSemParar = ref('')
const codRotaSemParar = ref('')
const valorViagem = ref(0)
const numeroViagem = ref('')

// Inicializar datas com valores padrão (hoje e daqui a 7 dias)
const getDataHoje = () => {
  const hoje = new Date()
  return hoje.toISOString().split('T')[0]
}

const getDataFutura = (dias: number) => {
  const futuro = new Date()
  futuro.setDate(futuro.getDate() + dias)
  return futuro.toISOString().split('T')[0]
}

const dataInicio = ref(getDataHoje())
const dataFim = ref(getDataFutura(7))

// Modos de operação (Progress: compraRota.p linha 176-196)
const modoCD = ref(false) // false = Normal, true = CD (TCD)
const modoRetorno = ref(false) // false = Ida, true = Retorno

// Checkboxes de progresso
const verificaPacote = ref(false)
const verificaTransporte = ref(false)
const verificaPlaca = ref(false)
const verificaRota = ref(false)
const verificaValor = ref(false)

// Estados de loading
const loadingPacote = ref(false)
const loadingPlaca = ref(false)
const loadingRotas = ref(false)
const loadingPreco = ref(false)

// Timer para debounce de busca
let searchTimer: ReturnType<typeof setTimeout> | null = null

// Dialogs
const showPlacaDialog = ref(false)
const showPrecoDialog = ref(false)
const showMapDialog = ref(false)

// Snackbar/Toast (Vuexy pattern)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error' | 'warning' | 'info'>('error')

const showToast = (message: string, color: 'success' | 'error' | 'warning' | 'info' = 'error') => {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Controle de campos
const pacoteDisabled = ref(false)
const placaDisabled = ref(true)
const rotaDisabled = ref(true)

// Config
const testMode = ref(false)

// ============================================================================
// ESTADO DO MAPA E COMPOSABLE DE SIMULAÇÃO
// ============================================================================
const mapContainer = ref<HTMLElement | null>(null)
const map = ref<L.Map | null>(null)
const markers = ref<L.Marker[]>([])
const routingControl = ref<any>(null)
const distanciaTotal = ref(0)
const rotaMunicipios = ref<any[]>([])
const loadingRotaMunicipios = ref(false)
const loadingEntregas = ref(false)
const loadingRouting = ref(false)

// Estado de progresso do routing
interface SegmentoRota {
  index: number
  origem: string
  destino: string
  status: 'pending' | 'calculating' | 'complete' | 'error'
  cached: boolean
  distanciaKm: number
  tempoMs: number
}

const routingProgress = ref(0) // 0-100
const routingSegmentos = ref<SegmentoRota[]>([])
const routingTotalSegmentos = ref(0)
const routingSegmentosCompletos = ref(0)
const routingSegmentosCached = ref(0)

// Lock para prevenir múltiplas atualizações simultâneas do mapa
const isUpdatingMap = ref(false)

// Composable de simulação de pacotes
const {
  entregas,
  simulationActive,
  loadPacoteEntregas,
  stopSimulation,
  processGpsCoordinate,
} = usePackageSimulation()

// ============================================================================
// WATCHERS - Recarrega rotas quando modo CD muda
// ============================================================================
watch(modoCD, async () => {
  // Recarrega rotas quando modo CD muda
  if (verificaPacote.value) {
    rotaId.value = null
    await carregarTodasRotas()
  }
})

// ============================================================================
// WATCHERS DO MAPA - Atualizam mapa quando rota ou pacote mudam
// ============================================================================

// Watch para rotaId - carrega municípios da rota
watch(rotaId, async (novoRotaId) => {
  if (!novoRotaId) {
    rotaMunicipios.value = []
    addDebugLog('info', 'WATCH', 'Rota desmarcada, limpando municípios')
    await updateMapMarkers()
    return
  }

  addDebugLog('info', 'WATCH', `Rota mudou para ${novoRotaId}, carregando municípios...`)
  console.log('🔍 [WATCH rotaId] Nova rota:', novoRotaId)
  await carregarMunicipiosRota(novoRotaId)
  console.log('🔍 [WATCH rotaId] Municípios após carregar:', rotaMunicipios.value.length)
})

// Watch para codpac - carrega entregas do pacote
watch(codpac, async (novoCodpac) => {
  if (!novoCodpac) {
    stopSimulation()
    await updateMapMarkers()
    return
  }

  loadingEntregas.value = true
  addDebugLog('info', 'WATCH', `Pacote mudou para ${novoCodpac}, carregando entregas...`)

  try {
    const success = await loadPacoteEntregas(novoCodpac)
    if (success) {
      simulationActive.value = true
      addDebugLog('success', 'PACOTE', `${entregas.value.length} entregas carregadas`)
      await updateMapMarkers()
    } else {
      addDebugLog('warn', 'PACOTE', 'Nenhuma entrega com GPS encontrada')
    }
  } catch (error: any) {
    addDebugLog('error', 'PACOTE', `Erro ao carregar entregas: ${error.message}`)
  } finally {
    loadingEntregas.value = false
  }
})

// Watch para showMapDialog - inicializa mapa quando dialog abre
watch(showMapDialog, async (isOpen) => {
  if (isOpen) {
    console.log('🗺️ [WATCH showMapDialog] Dialog aberto')
    console.log('🗺️ Municípios disponíveis:', rotaMunicipios.value.length)
    console.log('🗺️ Entregas disponíveis:', entregas.value.length)

    // Dialog aberto
    if (!map.value) {
      // Primeira vez: inicializar mapa
      addDebugLog('info', 'WATCH', 'Dialog do mapa aberto, inicializando mapa...')
      await nextTick() // Aguarda o DOM atualizar
      await initMap()
    }

    // Sempre atualizar marcadores quando dialog abre (primeira vez ou não)
    if (rotaMunicipios.value.length > 0 || entregas.value.length > 0) {
      addDebugLog('info', 'WATCH', 'Atualizando marcadores do mapa...')
      await updateMapMarkers()
    } else {
      addDebugLog('warn', 'WATCH', 'Nenhum dado para mostrar no mapa')
    }
  }
})

// ============================================================================
// ETAPA ATUAL (Simplificado: 3 etapas ao invés de 5)
// ============================================================================
const currentStep = computed(() => {
  // Etapa 1: Pacote + Placa
  if (!verificaPacote.value || !verificaPlaca.value) return 1

  // Etapa 2: Rota
  if (!verificaRota.value || !verificaValor.value) return 2

  // Etapa 3: Confirmar compra
  return 3
})

const steps = [
  { number: 1, title: 'Dados', icon: 'tabler-file-text', description: 'Pacote & Veículo' },
  { number: 2, title: 'Rota', icon: 'tabler-route', description: 'Rota & Preço' },
  { number: 3, title: 'Confirmar', icon: 'tabler-check', description: 'Finalizar Compra' },
]

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
const initialize = async () => {
  try {
    const data = await apiFetch('/api/compra-viagem/initialize')
    testMode.value = data.data.test_mode

    const hoje = new Date()
    dataInicio.value = hoje.toISOString().split('T')[0]
    const fim = new Date()
    fim.setDate(fim.getDate() + 5)
    dataFim.value = fim.toISOString().split('T')[0]
  }
  catch (error) {
    console.error('Erro ao inicializar:', error)
  }
}

// ============================================================================
// ETAPA 1: VALIDAR PACOTE
// ============================================================================
const validarPacote = async () => {
  if (!codpac.value) return

  loadingPacote.value = true
  try {
    const data = await apiFetch('/api/compra-viagem/validar-pacote', {
      method: 'POST',
      body: JSON.stringify({ codpac: codpac.value, flgcd: modoCD.value }),
    })

    if (!data.success) {
      showToast(data.error || 'Pacote inválido', 'error')
      return
    }

    const pkg = data.data
    descPacote.value = `${pkg.rota?.desrot || ''} ${pkg.rota?.codrot || ''}`
    codtrn.value = pkg.transporte.codtrn
    nomeTransporte.value = pkg.transporte.nomtrn
    placa.value = pkg.transporte.numpla

    // AUTO-SUGESTÃO DE ROTA (Progress: compraRota.p linha 432-475)
    if (pkg.rota_sugerida) {
      rotaId.value = pkg.rota_sugerida.spararrotid
      showToast(`Rota sugerida: ${pkg.rota_sugerida.desspararrot}`, 'info')
    }

    verificaPacote.value = true
    verificaTransporte.value = true
    pacoteDisabled.value = true
    placaDisabled.value = false

    // Carrega TODAS as rotas para o autocomplete
    await carregarTodasRotas()
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar pacote', 'error')
  }
  finally {
    loadingPacote.value = false
  }
}

// ============================================================================
// ETAPA 2: VALIDAR PLACA
// ============================================================================
const validarPlaca = async () => {
  if (!placa.value) return

  loadingPlaca.value = true
  try {
    const data = await apiFetch('/api/compra-viagem/validar-placa', {
      method: 'POST',
      body: JSON.stringify({ placa: placa.value }),
    })

    if (!data.success) {
      showToast(data.error || 'Placa inválida', 'error')
      return
    }

    descricaoVei.value = data.data.descricao
    eixos.value = data.data.eixos
    proprietario.value = data.data.proprietario
    tag.value = data.data.tag

    showPlacaDialog.value = true
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar placa', 'error')
  }
  finally {
    loadingPlaca.value = false
  }
}

const confirmarPlaca = () => {
  if (eixos.value < 2 || eixos.value > 10) {
    showToast('Eixos inválidos (mín: 2, máx: 10)', 'warning')
    return
  }

  showPlacaDialog.value = false
  verificaPlaca.value = true
  placaDisabled.value = true
  rotaDisabled.value = false
}

// ============================================================================
// ETAPA 3: BUSCAR E SELECIONAR ROTA
// Progress: compraRota.p linha 479-485 (F2 help)
// ============================================================================
const carregarTodasRotas = async () => {
  loadingRotas.value = true
  try {
    const tipoAtual = modoCD.value ? 'cd' : 'normal'

    // Se já tem cache, usa direto
    if (rotasCache.value[tipoAtual].length > 0) {
      rotasOptions.value = rotasCache.value[tipoAtual]
      loadingRotas.value = false
      return
    }

    // Busca TODAS as rotas do tipo atual
    const params = new URLSearchParams({
      search: '', // Vazio = busca TODAS
      flg_cd: modoCD.value ? '1' : '0',
    })

    const data = await apiFetch(`/api/compra-viagem/rotas?${params}`)
    const rotas = data.data || []

    // Guarda no cache
    rotasCache.value[tipoAtual] = rotas
    rotasOptions.value = rotas
  }
  catch (error) {
    console.error('Erro ao carregar rotas:', error)
    rotasOptions.value = []
  }
  finally {
    loadingRotas.value = false
  }
}

const selecionarRota = async () => {
  if (!rotaId.value) return

  // VALIDAR ROTA PRIMEIRO (Progress: compraRota.p linha 492-696)
  try {
    const data = await apiFetch('/api/compra-viagem/validar-rota', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value,
      }),
    })

    if (!data.success) {
      showToast(data.error || 'Rota inválida', 'error')
      rotaId.value = null
      return
    }

    // Rota validada! Usa datas retornadas pelo backend
    showToast('Rota validada com sucesso', 'success')

    // Auto-verificar preço após validação
    await verificarPreco()
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar rota', 'error')
    rotaId.value = null
  }
}

// ============================================================================
// ETAPA 4: VERIFICAR PREÇO
// ============================================================================
const verificarPreco = async () => {
  if (!codpac.value || !rotaId.value || !placa.value) {
    showToast('Preencha todos os campos antes de verificar o preço', 'warning')
    return
  }

  loadingPreco.value = true
  addDebugLog('info', 'PRECO', 'Verificando preço da viagem...')

  try {
    const data = await apiFetch('/api/compra-viagem/verificar-preco', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        qtd_eixos: eixos.value,
        placa: placa.value,
        data_inicio: dataInicio.value,
        data_fim: dataFim.value,
      }),
    })

    if (!data.success) {
      const errorMsg = data.message || data.error || 'Erro ao calcular preço'
      addDebugLog('error', 'PRECO', `Erro: ${errorMsg}`)

      // Se houver detalhes do erro, mostrar
      if (data.errors) {
        console.error('Detalhes do erro:', data.errors)
        addDebugLog('error', 'PRECO', `Detalhes: ${JSON.stringify(data.errors)}`)
      }

      showToast(errorMsg, 'error')
      return
    }

    addDebugLog('success', 'PRECO', `Preço calculado: R$ ${data.data.valor}`)

    valorViagem.value = data.data.valor
    numeroViagem.value = data.data.numero_viagem || ''
    nomRotSemParar.value = data.data.nome_rota || ''
    codRotaSemParar.value = data.data.cod_rota || ''

    verificaRota.value = true
    verificaValor.value = true
    rotaDisabled.value = true

    showPrecoDialog.value = true
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao verificar preço', 'error')
  }
  finally {
    loadingPreco.value = false
  }
}

// ============================================================================
// ETAPA 5: COMPRAR VIAGEM
// Progress: compraRota.p linha 827-995
// ============================================================================
const comprar = async () => {
  if (!codpac.value || !rotaId.value || !placa.value || !eixos.value) {
    showToast('Dados incompletos para compra', 'error')
    return
  }

  try {
    const data = await apiFetch('/api/compra-viagem/comprar', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        placa: placa.value,
        qtd_eixos: eixos.value,
        data_inicio: dataInicio.value,
        data_fim: dataFim.value,
        nome_rota_semparar: nomRotSemParar.value,
        cod_rota_semparar: codRotaSemParar.value,
        valor_viagem: valorViagem.value,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value,
      }),
    })

    if (!data.success) {
      showToast(data.error || 'Erro ao comprar viagem', 'error')
      return
    }

    // Compra concluída com sucesso!
    numeroViagem.value = data.data.numero_viagem
    showPrecoDialog.value = false

    // Mostra mensagem de sucesso
    showToast(
      `Viagem comprada! Número: ${numeroViagem.value}`,
      'success',
    )

    // RESET COMPLETO (Progress: compraRota.p linha 919-956)
    setTimeout(() => resetarCompleto(), 2000)
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao comprar viagem', 'error')
  }
}

// ============================================================================
// RESET COMPLETO PÓS-COMPRA
// Progress: compraRota.p linha 925-956
// ============================================================================
const resetarCompleto = () => {
  // Zera TODAS as flags de verificação
  verificaPacote.value = false
  verificaTransporte.value = false
  verificaPlaca.value = false
  verificaRota.value = false
  verificaValor.value = false

  // Reseta modos
  modoCD.value = false
  modoRetorno.value = false

  // Limpa TODAS as variáveis (Progress: linha 932-951)
  codpac.value = null
  descPacote.value = ''
  placa.value = ''
  nomeTransporte.value = ''
  descricaoVei.value = ''
  proprietario.value = ''
  tag.value = ''
  eixos.value = null
  rotaId.value = null
  rotaNome.value = ''
  nomRotSemParar.value = ''
  codRotaSemParar.value = ''
  valorViagem.value = 0
  numeroViagem.value = ''
  dataInicio.value = ''
  dataFim.value = ''
  codtrn.value = null

  // Reseta estados disabled
  pacoteDisabled.value = false
  placaDisabled.value = true
  rotaDisabled.value = true

  // Mostra mensagem e foca no campo pacote
  showToast('Sistema resetado. Pronto para nova compra!', 'info')

  // Limpar mapa também
  if (map.value) {
    markers.value.forEach(m => map.value?.removeLayer(m))
    markers.value = []
    if (routingControl.value) {
      map.value.removeLayer(routingControl.value)
      routingControl.value = null
    }
  }
  rotaMunicipios.value = []
  stopSimulation()
}

// ============================================================================
// FUNÇÕES DO MAPA INTERATIVO
// ============================================================================

/**
 * Inicializar mapa Leaflet + OpenStreetMap
 */
const initMap = async () => {
  await nextTick()

  if (!mapContainer.value) {
    addDebugLog('error', 'MAP', 'Container do mapa não encontrado')
    return
  }

  if (map.value) {
    addDebugLog('warn', 'MAP', 'Mapa já existe, pulando inicialização')
    return
  }

  addDebugLog('info', 'MAP', 'Inicializando mapa Leaflet + OpenStreetMap...')

  // Criar mapa centrado no Brasil
  map.value = L.map(mapContainer.value).setView([-14.2350, -51.9253], 4)

  // Adicionar tiles OpenStreetMap (100% GRATUITO!)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map.value)

  addDebugLog('success', 'MAP', 'Mapa inicializado com sucesso')
}

/**
 * Geocoding de município via API (IBGE code → lat/lon)
 */
const geocodeByIBGE = async (municipios: any[]): Promise<Record<number, { lat: number; lon: number }>> => {
  if (municipios.length === 0) {
    addDebugLog('warn', 'GEOCODING', 'Nenhum município para geocodificar')
    return {}
  }

  try {
    addDebugLog('info', 'GEOCODING', `Geocodificando ${municipios.length} municípios...`)
    console.log('📍 Municípios para geocodificar:', municipios)

    const requestBody = { municipios }
    console.log('📍 Request body stringified:', JSON.stringify(requestBody, null, 2))

    const response = await fetch(`/api/geocoding/lote`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody),
    })

    console.log('📍 Status response:', response.status)
    const data = await response.json()
    console.log('📍 Data recebida:', data)

    if (!data.success) {
      console.error('❌ Geocoding falhou:', data)
      if (data.errors) {
        console.error('❌ Erros de validação:', JSON.stringify(data.errors, null, 2))
      }
      throw new Error(data.message || 'Erro ao geocodificar')
    }

    console.log('📍 data.data tipo:', typeof data.data, data.data)

    const coordsMap: Record<number, { lat: number; lon: number }> = {}

    if (Array.isArray(data.data)) {
      data.data.forEach((item: any) => {
        console.log(`📍 Processando município:`, item)
        const ibge = item.codigo_ibge
        const coords = item.coordenadas

        // Backend retorna {lat, lon}, NÃO {latitude, longitude}
        if (coords && coords.lat && coords.lon) {
          coordsMap[parseInt(ibge)] = {
            lat: parseFloat(coords.lat),
            lon: parseFloat(coords.lon),
          }
          console.log(`✅ IBGE ${ibge} adicionado: ${coords.lat}, ${coords.lon}`)
        } else {
          console.warn(`⚠️ IBGE ${ibge} sem coordenadas válidas:`, item)
        }
      })
    } else {
      console.error('❌ data.data não é um array:', data.data)
    }

    console.log('📍 coordsMap final:', coordsMap)
    addDebugLog('success', 'GEOCODING', `${Object.keys(coordsMap).length}/${municipios.length} municípios geocodificados`)

    return coordsMap
  } catch (error: any) {
    console.error('❌ Erro no geocoding:', error)
    addDebugLog('error', 'GEOCODING', `Erro ao geocodificar: ${error.message}`)
    return {}
  }
}

/**
 * Calcular rota usando MapService unificado (OSRM com chunking automático)
 * @param waypoints Array de waypoints no formato [lat, lon]
 * @returns Coordenadas da rota, distância e informações de cache
 */
async function calculateRouteWithMapService(waypoints: Array<[number, number]>): Promise<{
  coordinates: Array<[number, number]>
  distance_km: number
  cached: boolean
  segments?: Array<{waypoints: number, distance_km: number}>
  total_segments?: number
} | null> {
  if (waypoints.length < 2) return null

  try {
    const payload = {
      waypoints: waypoints,
      options: {
        use_cache: true,
        fallback_to_straight: true
      }
    }

    addDebugLog('info', 'MAPSERVICE', `Calculando rota com MapService para ${waypoints.length} waypoints`)

    const response = await fetch(`/api/map/route`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error('❌ MapService retornou erro:', response.status, errorText)
      addDebugLog('error', 'MAPSERVICE', `MapService retornou erro ${response.status}`)
      return null
    }

    const result = await response.json()

    if (result.success && result.data?.coordinates) {
      addDebugLog('success', 'MAPSERVICE', `Rota calculada via ${result.data.provider}`, {
        distanciaKm: result.data.distance_km,
        pontosRota: result.data.coordinates.length,
        cached: result.data.cached ? 'HIT' : 'MISS',
        segments: result.data.total_segments || 1
      })

      return {
        coordinates: result.data.coordinates,
        distance_km: result.data.distance_km,
        cached: result.data.cached || false,
        segments: result.data.segments,
        total_segments: result.data.total_segments
      }
    } else {
      console.error('❌ MapService resposta inválida:', result)
      addDebugLog('error', 'MAPSERVICE', 'Resposta inválida do MapService')
      return null
    }
  } catch (error: any) {
    console.error('❌ Erro ao chamar MapService:', error)
    addDebugLog('error', 'MAPSERVICE', `Erro ao chamar MapService: ${error.message}`)
    return null
  }
}

/**
 * Carregar municípios da rota SemParar
 */
const carregarMunicipiosRota = async (rotaIdValue: number) => {
  if (!rotaIdValue) {
    rotaMunicipios.value = []
    return
  }

  loadingRotaMunicipios.value = true
  try {
    addDebugLog('info', 'ROTA', `Carregando municípios da rota ${rotaIdValue}...`)

    const response = await fetch(`/api/semparar-rotas/${rotaIdValue}/municipios`)
    const data = await response.json()

    console.log('🗺️ Response municípios:', data)

    if (!data.success) {
      throw new Error(data.message || 'Erro ao carregar municípios')
    }

    rotaMunicipios.value = data.data.municipios || []
    console.log('🗺️ Municípios carregados:', rotaMunicipios.value)

    // DEBUG: Mostrar estrutura de cada município
    if (rotaMunicipios.value.length > 0) {
      console.log('🔍 Estrutura do primeiro município:', rotaMunicipios.value[0])
      console.log('🔍 Campos disponíveis:', Object.keys(rotaMunicipios.value[0]))
    }

    addDebugLog('success', 'ROTA', `${rotaMunicipios.value.length} municípios carregados`)

    // Atualizar mapa
    await updateMapMarkers()
  } catch (error: any) {
    console.error('❌ Erro ao carregar municípios:', error)
    addDebugLog('error', 'ROTA', `Erro ao carregar municípios: ${error.message}`)
    rotaMunicipios.value = []
  } finally {
    loadingRotaMunicipios.value = false
  }
}

/**
 * Agrupar entregas por proximidade geográfica (mesma cidade/região)
 * @param entregas Array de entregas com lat/lon
 * @param raioKm Raio em km para considerar mesma localidade (padrão: 5km)
 * @returns Array de grupos {lat, lon, entregas[], cidade, count}
 */
const agruparEntregasPorProximidade = (entregas: any[], raioKm = 5) => {
  if (entregas.length === 0) return []

  const grupos: any[] = []
  const entregasRestantes = [...entregas]

  while (entregasRestantes.length > 0) {
    const entregaBase = entregasRestantes.shift()!
    const grupo = {
      lat: entregaBase.lat,
      lon: entregaBase.lon,
      entregas: [entregaBase],
      cidade: entregaBase.razcli || entregaBase.cidade || 'Localidade',
      count: 1,
    }

    // Encontrar entregas próximas (dentro do raio)
    for (let i = entregasRestantes.length - 1; i >= 0; i--) {
      const entrega = entregasRestantes[i]
      const distancia = calcularDistancia(
        grupo.lat,
        grupo.lon,
        entrega.lat,
        entrega.lon
      )

      if (distancia <= raioKm) {
        grupo.entregas.push(entrega)
        grupo.count++
        // Recalcular centro do grupo (média das coordenadas)
        grupo.lat = grupo.entregas.reduce((sum, e) => sum + e.lat, 0) / grupo.entregas.length
        grupo.lon = grupo.entregas.reduce((sum, e) => sum + e.lon, 0) / grupo.entregas.length
        entregasRestantes.splice(i, 1)
      }
    }

    grupos.push(grupo)
  }

  addDebugLog('info', 'AGRUPAMENTO', `${entregas.length} entregas agrupadas em ${grupos.length} localidades`)

  return grupos
}

/**
 * Calcular distância entre dois pontos (Haversine formula)
 * @returns Distância em km
 */
const calcularDistancia = (lat1: number, lon1: number, lat2: number, lon2: number) => {
  const R = 6371 // Raio da Terra em km
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLon = (lon2 - lon1) * Math.PI / 180
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return R * c
}

/**
 * Atualizar marcadores e rota no mapa
 */
const updateMapMarkers = async () => {
  if (!map.value) {
    addDebugLog('info', 'MAP', 'Mapa ainda não inicializado (será atualizado quando dialog abrir)')
    return
  }

  // Verificar lock: prevenir múltiplas execuções simultâneas
  if (isUpdatingMap.value) {
    addDebugLog('warn', 'MAP', 'Atualização já em andamento, pulando...')
    return
  }

  // Ativar lock
  isUpdatingMap.value = true

  try {
    addDebugLog('info', 'MAP', 'Atualizando marcadores...')

    // Limpar marcadores anteriores
    markers.value.forEach(m => {
      if (map.value && map.value.hasLayer(m)) {
        map.value.removeLayer(m)
      }
    })
    markers.value = []

    if (routingControl.value) {
      if (map.value && map.value.hasLayer(routingControl.value)) {
        map.value.removeLayer(routingControl.value)
      }
      routingControl.value = null
    }

  // === PARTE 1: Marcadores de municípios da rota ===
  const waypoints: L.LatLng[] = []

  if (rotaMunicipios.value.length > 0) {
    console.log('🗺️ Iniciando geocoding de', rotaMunicipios.value.length, 'municípios')

    // Preparar municípios para geocoding (garantir formato correto)
    // IMPORTANTE: Progress retorna CodMun, DesMun (case sensitive!)
    const municipiosFormatados = rotaMunicipios.value.map(m => ({
      cdibge: String(m.cdibge), // ✅ Converter para string
      desmun: String(m.DesMun || m.desmun).trim(), // Progress usa DesMun (D e M maiúsculos)
      desest: String(m.desest).trim(), // ✅ OK (alias na query)
      cod_mun: m.CodMun || m.codmun || m.cod_mun, // Progress usa CodMun (C e M maiúsculos)
      cod_est: m.CodEst || m.codest || m.cod_est  // Progress usa CodEst (C e E maiúsculos)
    }))

    console.log('🗺️ Municípios formatados:', municipiosFormatados)

    // DEBUG: Verificar se municípios têm cdibge válido
    municipiosFormatados.forEach((m, i) => {
      console.log(`🔍 Município ${i + 1}:`, {
        cdibge: m.cdibge,
        cdibge_type: typeof m.cdibge,
        desmun: m.desmun,
        desest: m.desest
      })
    })

    // Geocodificar municípios
    const coords = await geocodeByIBGE(municipiosFormatados)
    console.log('🗺️ Coordenadas retornadas:', coords)
    console.log('🗺️ Quantidade de coordenadas:', Object.keys(coords).length)

    let municipiosRenderizados = 0

    rotaMunicipios.value.forEach((municipio, index) => {
      console.log(`🗺️ Tentando renderizar município ${index + 1}:`, municipio)
      const coord = coords[municipio.cdibge]

      if (!coord) {
        console.warn(`⚠️ Município ${municipio.DesMun || municipio.desmun} (IBGE: ${municipio.cdibge}) sem coordenadas`)
        return
      }

      console.log(`✅ Município ${municipio.DesMun || municipio.desmun} tem coordenadas:`, coord)
      municipiosRenderizados++

      const latLng = L.latLng(coord.lat, coord.lon)
      waypoints.push(latLng)

      // Marcador azul numerado para municípios
      const icon = L.divIcon({
        html: `<div style="
          background: #2196F3;
          color: white;
          border-radius: 50%;
          width: 32px;
          height: 32px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: bold;
          border: 2px solid white;
          box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        ">${index + 1}</div>`,
        className: '',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
      })

      const marker = L.marker(latLng, { icon })
        .bindPopup(`<b>${municipio.DesMun || municipio.desmun}</b><br>${municipio.desest}`)
        .addTo(map.value!)

      markers.value.push(marker)
    })

    console.log(`🗺️ Total de municípios renderizados: ${municipiosRenderizados}/${rotaMunicipios.value.length}`)
    addDebugLog('success', 'MAP', `${municipiosRenderizados} municípios renderizados`)
  }

  // === PARTE 2: Marcadores de entregas do pacote (AGRUPADOS) ===
  if (simulationActive.value && entregas.value.length > 0) {
    const gruposEntregas = agruparEntregasPorProximidade(entregas.value, 5)

    gruposEntregas.forEach((grupo, index) => {
      const latLng = L.latLng(grupo.lat, grupo.lon)
      waypoints.push(latLng)

      // Cor baseada na posição do grupo
      let color = '#FF9800' // Laranja (intermediário)
      if (index === 0) color = '#4CAF50' // Verde (primeiro grupo)
      else if (index === gruposEntregas.length - 1) color = '#F44336' // Vermelho (último grupo)

      // Badge com contagem se houver múltiplas entregas
      const badge = grupo.count > 1
        ? `<div style="
            position: absolute;
            top: -8px;
            right: -8px;
            background: #FF5252;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
          ">${grupo.count}</div>`
        : ''

      const icon = L.divIcon({
        html: `<div style="position: relative;">
          <div style="
            background: ${color};
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            border: 3px solid white;
            box-shadow: 0 3px 6px rgba(0,0,0,0.4);
          ">📦</div>
          ${badge}
        </div>`,
        className: '',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
      })

      // Popup detalhado com lista de entregas
      let popupContent = `<div style="min-width: 200px;">
        <b style="font-size: 14px;">📍 ${grupo.cidade || 'Localidade'}</b>
        <br><small style="color: #666;">${grupo.count} entrega${grupo.count > 1 ? 's' : ''}</small>
        <hr style="margin: 8px 0; border: none; border-top: 1px solid #ddd;">
      `

      grupo.entregas.forEach((entrega: any, i: number) => {
        popupContent += `
          <div style="margin: 4px 0; padding: 4px 0; ${i > 0 ? 'border-top: 1px solid #eee;' : ''}">
            <b style="color: ${color};">Entrega ${i + 1}</b>
            <br><small>${entrega.razcli || 'Cliente não identificado'}</small>
          </div>
        `
      })

      popupContent += `</div>`

      const marker = L.marker(latLng, { icon })
        .bindPopup(popupContent, { maxWidth: 300 })
        .addTo(map.value!)

      markers.value.push(marker)
    })

    addDebugLog('success', 'MAP', `${gruposEntregas.length} grupos de entregas renderizados (${entregas.value.length} entregas totais)`)
  }

  // === DEBUG: Mostrar composição final dos waypoints ===
  console.log('═══════════════════════════════════════════════════')
  console.log('📊 WAYPOINTS FINAIS PARA ROUTING:')
  console.log(`   Total: ${waypoints.length} waypoints`)
  console.log(`   Municípios na rota: ${rotaMunicipios.value.length}`)
  console.log(`   Entregas ativas: ${simulationActive.value ? entregas.value.length : 0}`)
  console.log('═══════════════════════════════════════════════════')

  // === PARTE 3: Desenhar rota via MapService (OSRM com chunking automático) ===
  if (waypoints.length > 1) {
    addDebugLog('info', 'MAPSERVICE', `Calculando rota com MapService para ${waypoints.length} waypoints`)
    loadingRouting.value = true

    // Converter waypoints L.LatLng para formato MapService [lat, lon]
    const mapServiceWaypoints = waypoints.map(w => [w.lat, w.lng] as [number, number])

    // Calcular rota com MapService
    const routeResult = await calculateRouteWithMapService(mapServiceWaypoints)

    loadingRouting.value = false

    if (routeResult && routeResult.coordinates.length > 0) {
      // Atualizar distância total (garantir que é número)
      distanciaTotal.value = Number(routeResult.distance_km)

      // Converter coordenadas para Leaflet LatLng
      const routeLatLngs = routeResult.coordinates.map(coord => L.latLng(coord[0], coord[1]))

      // Desenhar polyline rosa/magenta (#E91E63 = compra-viagem)
      if (routingControl.value) {
        map.value?.removeLayer(routingControl.value)
      }

      routingControl.value = L.polyline(routeLatLngs, {
        color: '#E91E63',  // Rosa/magenta para compra-viagem
        weight: 4,
        opacity: 0.7,
      }).addTo(map.value!)

      addDebugLog('success', 'MAPSERVICE', 'Rota calculada via OSRM', {
        distanciaKm: routeResult.distance_km,
        pontosRota: routeResult.coordinates.length,
        cached: routeResult.cached ? 'HIT' : 'MISS',
        segments: routeResult.total_segments || 1
      })

      // Ajustar zoom para mostrar tudo
      const bounds = L.latLngBounds(routeLatLngs)
      map.value!.fitBounds(bounds, { padding: [50, 50], animate: false })
    } else {
      addDebugLog('error', 'MAPSERVICE', 'Falha ao calcular rota via MapService')
    }

    isUpdatingMap.value = false // Liberar lock
  } else if (waypoints.length === 1) {
    // Apenas 1 ponto: centralizar
    map.value.setView(waypoints[0], 12, { animate: false })
    isUpdatingMap.value = false // Liberar lock
  } else {
    // Nenhum ponto: voltar para Brasil
    map.value.setView([-14.2350, -51.9253], 4, { animate: false })
    isUpdatingMap.value = false // Liberar lock
  }
  } catch (error: any) {
    addDebugLog('error', 'MAP', `Erro ao atualizar marcadores: ${error.message}`)
    isUpdatingMap.value = false // Liberar lock em caso de erro
  }
}

initialize()
addDebugLog('info', 'SYSTEM', 'Sistema de Compra de Viagem SemParar inicializado')

// Mapa será inicializado quando o dialog abrir (watch showMapDialog)
</script>

<template>
  <div>
    <!-- Header com título e stepper -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
          <div>
            <h4 class="text-h4 mb-1">
              Compra de Viagem SemParar
            </h4>
            <p class="text-body-1 mb-0">
              Siga as etapas para comprar uma viagem
            </p>
          </div>
          <div class="d-flex gap-3 align-center">
            <!-- Switches de Modo (Progress: compraRota.p linha 176-196) -->
            <VSwitch
              v-model="modoCD"
              color="primary"
              label="Modo CD (TCD)"
              hide-details
              density="compact"
              :disabled="verificaPacote"
            />

            <VSwitch
              v-model="modoRetorno"
              color="warning"
              label="Retorno"
              hide-details
              density="compact"
              :disabled="verificaPacote"
            />

            <VDivider vertical />

            <VChip
              v-if="testMode"
              color="warning"
              variant="tonal"
            >
              Modo Seguro
            </VChip>

            <!-- Botão Debug -->
            <VBtn
              :color="showDebugPanel ? 'warning' : 'info'"
              :variant="showDebugPanel ? 'flat' : 'tonal'"
              prepend-icon="tabler-bug"
              size="small"
              @click="showDebugPanel = !showDebugPanel"
              title="Abrir painel de debug"
            >
              Debug
              <VBadge
                v-if="debugLogs.length > 0"
                :content="debugLogs.length"
                color="error"
                inline
                class="ms-2"
              />
            </VBtn>
          </div>
        </div>

        <!-- Stepper visual (3 etapas simplificadas) -->
        <div class="d-flex align-center justify-space-between">
          <div
            v-for="(step, index) in steps"
            :key="step.number"
            class="d-flex align-center"
            :style="{ flex: index < steps.length - 1 ? 1 : 0 }"
          >
            <div class="d-flex flex-column align-center" style="min-width: 120px;">
              <VAvatar
                :color="currentStep >= step.number ? 'primary' : 'default'"
                :variant="currentStep >= step.number ? 'tonal' : 'outlined'"
                size="56"
                class="mb-2"
              >
                <VIcon :icon="step.icon" size="28" />
              </VAvatar>
              <span class="text-body-2 font-weight-medium">{{ step.title }}</span>
              <span class="text-caption text-medium-emphasis">{{ step.description }}</span>
            </div>
            <VDivider
              v-if="index < steps.length - 1"
              class="mx-4"
              :thickness="3"
              :color="currentStep > step.number ? 'primary' : 'default'"
            />
          </div>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Coluna principal -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardText>
            <!-- ETAPA 1: Pacote -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                1. Dados do Pacote
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model.number="codpac"
                    label="Código do Pacote"
                    type="number"
                    :disabled="pacoteDisabled"
                    :loading="loadingPacote"
                    @keydown.enter="validarPacote"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaPacote"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="descPacote"
                    label="Descrição"
                    readonly
                    variant="outlined"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="2"
                  class="d-flex align-center"
                >
                  <VBtn
                    block
                    color="primary"
                    :disabled="!codpac || pacoteDisabled"
                    :loading="loadingPacote"
                    @click="validarPacote"
                  >
                    <VIcon icon="tabler-search" start />
                    Buscar
                  </VBtn>
                </VCol>
              </VRow>

              <VRow v-if="verificaPacote">
                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model="codtrn"
                    label="Cód. Transporte"
                    readonly
                    variant="plain"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="8"
                >
                  <VTextField
                    v-model="nomeTransporte"
                    label="Transportadora"
                    readonly
                    variant="plain"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaTransporte"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 2: Placa -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                2. Veículo
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="placa"
                    label="Placa"
                    :disabled="placaDisabled"
                    :loading="loadingPlaca"
                    @keydown.enter="validarPlaca"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaPlaca"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
                <VCol
                  cols="12"
                  sm="3"
                  class="d-flex align-center"
                >
                  <VBtn
                    block
                    color="primary"
                    :disabled="!placa || placaDisabled || verificaPlaca"
                    :loading="loadingPlaca"
                    @click="validarPlaca"
                  >
                    <VIcon icon="tabler-check" start />
                    Validar
                  </VBtn>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 3: Rota -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                3. Rota SemParar
              </h5>
              <VRow>
                <VCol cols="12">
                  <VAutocomplete
                    :key="`rota-autocomplete-${modoCD}`"
                    v-model="rotaId"
                    label="Buscar Rota SemParar"
                    placeholder="Digite para buscar..."
                    :items="rotasOptions"
                    :loading="loadingRotas"
                    :disabled="rotaDisabled"
                    item-title="title"
                    item-value="value"
                    variant="outlined"
                    clearable
                    :menu-props="{ maxHeight: 400 }"
                    @update:model-value="selecionarRota"
                  >
                    <template #prepend-inner>
                      <VIcon
                        icon="tabler-search"
                        class="me-1"
                      />
                    </template>

                    <template #item="{ props, item }">
                      <VListItem
                        v-bind="props"
                        :title="item.raw.title"
                        :subtitle="item.raw.subtitle"
                      >
                        <template #prepend>
                          <VAvatar
                            :color="item.raw.flgcd ? 'info' : 'primary'"
                            variant="tonal"
                            size="40"
                          >
                            <VIcon :icon="item.raw.flgcd ? 'tabler-building' : 'tabler-route'" />
                          </VAvatar>
                        </template>

                        <template #append>
                          <div class="d-flex gap-2">
                            <VChip
                              v-if="item.raw.flgretorno"
                              size="small"
                              color="warning"
                              variant="tonal"
                            >
                              <VIcon
                                icon="tabler-arrow-back"
                                start
                                size="14"
                              />
                              Retorno
                            </VChip>
                            <VChip
                              size="small"
                              color="secondary"
                              variant="tonal"
                            >
                              {{ item.raw.tempoviagem }} dias
                            </VChip>
                          </div>
                        </template>
                      </VListItem>
                    </template>

                    <template #append-inner>
                      <VIcon
                        v-if="verificaRota"
                        icon="tabler-circle-check"
                        color="success"
                      />
                    </template>

                    <template #no-data>
                      <VListItem>
                        <VListItemTitle class="text-center text-medium-emphasis">
                          <VIcon
                            icon="tabler-search-off"
                            class="me-2"
                          />
                          Digite pelo menos 2 caracteres para buscar
                        </VListItemTitle>
                      </VListItem>
                    </template>
                  </VAutocomplete>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 4: Datas -->
            <div>
              <h5 class="text-h5 mb-4">
                4. Período de Vigência
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="dataInicio"
                    label="Data Início"
                    type="date"
                    :disabled="!verificaRota && !rotaId"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="dataFim"
                    label="Data Fim"
                    type="date"
                    :disabled="!verificaRota && !rotaId"
                  />
                </VCol>
              </VRow>

              <!-- Botão Calcular Preço (GRANDE e DESTACADO) -->
              <VRow class="mt-4">
                <VCol cols="12">
                  <VBtn
                    block
                    color="success"
                    size="x-large"
                    :disabled="!codpac || !rotaId || !placa || verificaValor"
                    :loading="loadingPreco"
                    @click="verificarPreco"
                  >
                    <VIcon icon="tabler-calculator" start size="24" />
                    Calcular Preço da Viagem
                  </VBtn>
                  <div v-if="!codpac || !rotaId || !placa" class="text-caption text-center text-medium-emphasis mt-2">
                    <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                    Complete todos os campos acima para calcular o preço
                  </div>
                </VCol>
              </VRow>
            </div>
          </VCardText>

          <VCardActions class="justify-end">
            <VBtn
              variant="outlined"
              @click="resetarCompleto"
            >
              Limpar
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>

      <!-- Coluna lateral - Resumo -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardTitle>Resumo</VCardTitle>
          <VCardText>
            <div class="d-flex flex-column gap-4">
              <div>
                <p class="text-caption text-disabled mb-1">
                  Pacote
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ codpac || '-' }}
                </p>
              </div>

              <div>
                <p class="text-caption text-disabled mb-1">
                  Placa
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ placa || '-' }}
                </p>
              </div>

              <div>
                <p class="text-caption text-disabled mb-1">
                  Eixos
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ eixos }}
                </p>
              </div>

              <div v-if="nomRotSemParar">
                <p class="text-caption text-disabled mb-1">
                  Rota
                </p>
                <p class="text-body-2 font-weight-medium">
                  {{ nomRotSemParar }}
                </p>
              </div>

              <div v-if="valorViagem > 0">
                <p class="text-caption text-disabled mb-1">
                  Valor da Viagem
                </p>
                <p class="text-h5 text-success font-weight-bold">
                  R$ {{ valorViagem.toFixed(2) }}
                </p>
              </div>
            </div>
          </VCardText>

          <!-- Botão Ver Mapa (só aparece quando há dados) -->
          <VCardActions v-if="rotaMunicipios.length > 0 || entregas.length > 0">
            <VBtn
              block
              color="primary"
              variant="tonal"
              size="large"
              prepend-icon="tabler-map"
              @click="showMapDialog = true"
            >
              Ver Mapa da Rota
              <VChip
                v-if="rotaMunicipios.length > 0"
                color="primary"
                variant="flat"
                size="small"
                class="ml-2"
              >
                {{ rotaMunicipios.length }} pontos
              </VChip>
            </VBtn>
          </VCardActions>
        </VCard>

        <!-- Checklist de progresso -->
        <VCard class="mt-4">
          <VCardTitle>Progresso</VCardTitle>
          <VCardText>
            <VList>
              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaPacote ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaPacote ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Pacote Validado</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaTransporte ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaTransporte ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Transporte Carregado</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaPlaca ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaPlaca ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Placa Validada</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaRota ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaRota ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Rota Selecionada</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaValor ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaValor ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Preço Calculado</VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Dialog: Mapa Interativo (substituindo o mapa fixo gigante) -->
    <VDialog
      v-model="showMapDialog"
      fullscreen
      transition="dialog-bottom-transition"
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between bg-primary">
          <div class="d-flex align-center gap-3">
            <VIcon icon="tabler-map" size="32" />
            <div>
              <div class="text-h5">Visualização da Rota</div>
              <div class="text-body-2 text-medium-emphasis">
                Municípios e entregas no mapa interativo
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 align-center">
            <VChip
              v-if="rotaMunicipios.length > 0"
              color="white"
              variant="tonal"
              size="small"
            >
              <VIcon
                icon="tabler-map-pin"
                start
                size="14"
              />
              {{ rotaMunicipios.length }} municípios
            </VChip>
            <VChip
              v-if="entregas.length > 0"
              color="success"
              variant="tonal"
              size="small"
            >
              <VIcon
                icon="tabler-package"
                start
                size="14"
              />
              {{ entregas.length }} entregas
            </VChip>
            <VChip
              v-if="distanciaTotal > 0"
              color="warning"
              variant="tonal"
              size="small"
            >
              <VIcon
                icon="tabler-route"
                start
                size="14"
              />
              {{ distanciaTotal.toFixed(1) }} km
            </VChip>
            <VBtn
              icon="tabler-x"
              variant="text"
              color="white"
              @click="showMapDialog = false"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-0" style="position: relative;">
          <!-- Container do mapa (altura completa da janela) -->
          <div
            ref="mapContainer"
            style="height: calc(100vh - 200px); width: 100%;"
          />

          <!-- Loading overlay para o mapa com progress detalhado -->
          <VOverlay
            :model-value="loadingRotaMunicipios || loadingEntregas || loadingRouting"
            contained
            class="align-center justify-center"
          >
            <VCard
              v-if="loadingRouting && routingSegmentos.length > 0"
              class="pa-6"
              style="min-width: 500px; max-width: 600px;"
            >
              <!-- Header -->
              <div class="text-center mb-4">
                <VIcon icon="tabler-route" size="48" color="primary" class="mb-2" />
                <div class="text-h6">Calculando Rota</div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ routingSegmentosCompletos }} de {{ routingTotalSegmentos }} segmentos
                  <VChip
                    v-if="routingSegmentosCached > 0"
                    color="success"
                    variant="tonal"
                    size="small"
                    class="ml-2"
                  >
                    <VIcon icon="tabler-database" start size="14" />
                    {{ routingSegmentosCached }} em cache
                  </VChip>
                </div>
              </div>

              <!-- Progress Bar -->
              <VProgressLinear
                :model-value="routingProgress"
                color="primary"
                height="8"
                rounded
                class="mb-4"
              />

              <!-- Lista de Segmentos (scroll se muitos) -->
              <div style="max-height: 300px; overflow-y: auto;" class="pr-2">
                <VList density="compact" class="py-0">
                  <VListItem
                    v-for="segmento in routingSegmentos"
                    :key="segmento.index"
                    class="px-2 py-1"
                  >
                    <template #prepend>
                      <!-- Ícone de status -->
                      <VIcon
                        v-if="segmento.status === 'complete'"
                        icon="tabler-circle-check-filled"
                        color="success"
                        size="20"
                      />
                      <VProgressCircular
                        v-else-if="segmento.status === 'calculating'"
                        indeterminate
                        size="20"
                        width="2"
                        color="primary"
                      />
                      <VIcon
                        v-else-if="segmento.status === 'error'"
                        icon="tabler-alert-circle"
                        color="error"
                        size="20"
                      />
                      <VIcon
                        v-else
                        icon="tabler-circle"
                        color="grey"
                        size="20"
                      />
                    </template>

                    <VListItemTitle class="text-body-2">
                      {{ segmento.origem }} → {{ segmento.destino }}
                    </VListItemTitle>

                    <VListItemSubtitle v-if="segmento.status === 'complete'" class="text-caption">
                      {{ segmento.distanciaKm.toFixed(1) }} km
                      <VChip
                        v-if="segmento.cached"
                        color="success"
                        variant="text"
                        size="x-small"
                        class="ml-1"
                      >
                        💾 cache {{ segmento.tempoMs }}ms
                      </VChip>
                      <VChip
                        v-else
                        color="primary"
                        variant="text"
                        size="x-small"
                        class="ml-1"
                      >
                        🌐 API {{ segmento.tempoMs }}ms
                      </VChip>
                    </VListItemSubtitle>
                    <VListItemSubtitle v-else-if="segmento.status === 'calculating'" class="text-caption text-primary">
                      Calculando...
                    </VListItemSubtitle>
                    <VListItemSubtitle v-else-if="segmento.status === 'error'" class="text-caption text-error">
                      Erro - usando linha reta
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </div>

              <!-- Estatísticas -->
              <VDivider class="my-3" />
              <div class="text-center text-caption text-medium-emphasis">
                <VIcon icon="tabler-clock" size="14" class="mr-1" />
                Tempo total: {{ routingSegmentos.reduce((sum, s) => sum + s.tempoMs, 0) }}ms
                <span class="mx-2">•</span>
                <VIcon icon="tabler-zap" size="14" class="mr-1" />
                {{ Math.round((routingSegmentosCached / Math.max(routingSegmentosCompletos, 1)) * 100) }}% cache hit
              </div>
            </VCard>

            <!-- Loading simples para municípios e entregas -->
            <VCard v-else class="pa-6 text-center">
              <VProgressCircular
                indeterminate
                size="64"
                color="primary"
                class="mb-4"
              />
              <div class="text-h6">
                {{ loadingRotaMunicipios ? 'Carregando municípios...' : 'Carregando entregas...' }}
              </div>
            </VCard>
          </VOverlay>
        </VCardText>

        <VDivider />

        <VCardText class="py-4">
          <!-- Legenda compacta -->
          <div class="d-flex align-center justify-center flex-wrap gap-4">
            <div class="d-flex align-center gap-2">
              <div
                style="
                  background: #2196F3;
                  width: 24px;
                  height: 24px;
                  border-radius: 50%;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  color: white;
                  font-size: 11px;
                  font-weight: bold;
                  border: 2px solid white;
                  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "
              >
                1
              </div>
              <span class="text-caption">Municípios da Rota</span>
            </div>

            <VDivider vertical />

            <div class="d-flex align-center gap-2">
              <div
                style="
                  background: #4CAF50;
                  width: 24px;
                  height: 24px;
                  border-radius: 50%;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 14px;
                  border: 2px solid white;
                  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "
              >
                📦
              </div>
              <span class="text-caption">Primeira Entrega</span>
            </div>

            <div class="d-flex align-center gap-2">
              <div
                style="
                  background: #FF9800;
                  width: 24px;
                  height: 24px;
                  border-radius: 50%;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 14px;
                  border: 2px solid white;
                  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "
              >
                📦
              </div>
              <span class="text-caption">Entregas Intermediárias</span>
            </div>

            <div class="d-flex align-center gap-2">
              <div
                style="
                  background: #F44336;
                  width: 24px;
                  height: 24px;
                  border-radius: 50%;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-size: 14px;
                  border: 2px solid white;
                  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "
              >
                📦
              </div>
              <span class="text-caption">Última Entrega</span>
            </div>

            <VDivider vertical />

            <div class="d-flex align-center gap-2">
              <div
                style="
                  width: 32px;
                  height: 3px;
                  background: #E91E63;
                  border-radius: 2px;
                "
              />
              <span class="text-caption">Rota (OSRM)</span>
            </div>
          </div>

          <!-- Nota explicativa compacta -->
          <div class="text-center mt-2">
            <small class="text-disabled text-caption">
              <VIcon icon="tabler-info-circle" size="12" class="me-1" />
              Entregas na mesma região (5km) são agrupadas
            </small>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Dialog: Confirmar Placa -->
    <VDialog
      v-model="showPlacaDialog"
      max-width="600"
    >
      <VCard>
        <VCardText class="pa-6">
          <!-- Header com ícone -->
          <div class="d-flex align-center mb-6">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
              class="me-4"
            >
              <VIcon
                icon="tabler-truck"
                size="28"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Dados do Veículo
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Confirme as informações retornadas pelo SemParar
              </p>
            </div>
          </div>

          <VDivider class="mb-6" />

          <!-- Informações do veículo em formato lista elegante -->
          <VList class="mb-4">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-car"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Descrição
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ descricaoVei }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-user"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Proprietário
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ proprietario }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-id-badge"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Tag SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ tag }}
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <!-- Eixos editável destacado -->
          <VCard
            variant="tonal"
            color="primary"
            class="pa-4"
          >
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-settings"
                  class="me-3"
                  size="24"
                />
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    Quantidade de Eixos
                  </div>
                  <div class="text-caption">
                    Ajuste se necessário
                  </div>
                </div>
              </div>
              <VTextField
                v-model.number="eixos"
                type="number"
                min="2"
                max="10"
                density="compact"
                style="max-width: 100px"
                variant="outlined"
              />
            </div>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showPlacaDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="confirmarPlaca"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Confirmar Preço -->
    <VDialog
      v-model="showPrecoDialog"
      max-width="650"
    >
      <VCard>
        <VCardText class="pa-6">
          <!-- Header com ícone -->
          <div class="d-flex align-center mb-6">
            <VAvatar
              color="success"
              variant="tonal"
              size="56"
              class="me-4"
            >
              <VIcon
                icon="tabler-cash"
                size="32"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Confirmar Compra de Viagem
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Revise os dados antes de finalizar
              </p>
            </div>
          </div>

          <VDivider class="mb-6" />

          <!-- Valor em destaque -->
          <VCard
            variant="tonal"
            color="success"
            class="pa-6 text-center mb-6"
          >
            <div class="text-body-2 text-medium-emphasis mb-2">
              Valor Total da Viagem
            </div>
            <h1 class="text-h1 font-weight-bold">
              R$ {{ valorViagem.toFixed(2) }}
            </h1>
          </VCard>

          <!-- Informações da rota -->
          <VList class="mb-4">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-route"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Rota SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ nomRotSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-barcode"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Código da Rota
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ codRotaSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem v-if="numeroViagem">
              <template #prepend>
                <VIcon
                  icon="tabler-receipt"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Número da Viagem
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ numeroViagem }}
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <!-- Alerta importante -->
          <VAlert
            type="warning"
            variant="tonal"
            prominent
          >
            <VAlertTitle class="mb-2">
              <VIcon
                icon="tabler-alert-triangle"
                class="me-2"
              />
              Atenção
            </VAlertTitle>
            Se cancelar após esta etapa, será necessário excluir manualmente a rota temporária no sistema SemParar.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="error"
            @click="showPrecoDialog = false"
          >
            <VIcon
              icon="tabler-x"
              start
            />
            Cancelar
          </VBtn>
          <VBtn
            color="success"
            variant="elevated"
            size="large"
            @click="comprar"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Confirmar Compra
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Painel de Debug Funcional (Modal) -->
    <VDialog
      v-model="showDebugPanel"
      max-width="1200"
      scrollable
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center bg-warning">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-bug" />
            <span>Painel de Debug - Compra de Viagem SemParar</span>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="showDebugPanel = false"
          />
        </VCardTitle>

        <VCardText class="pa-4">
          <!-- Estatísticas de Debug -->
          <div class="mb-4">
            <h6 class="text-h6 mb-3">📊 Estatísticas</h6>
            <VRow dense>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-blue-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-primary">{{ debugStats.totalRequests }}</div>
                  <div class="text-caption">Total de Requests</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-green-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-success">{{ debugStats.successfulRequests }}</div>
                  <div class="text-caption">Requests Bem-Sucedidos</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-red-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-error">{{ debugStats.failedRequests }}</div>
                  <div class="text-caption">Requests Falhados</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-purple-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-purple">{{ debugStats.cacheHits }}</div>
                  <div class="text-caption">Cache Hits</div>
                </div>
              </VCol>
            </VRow>
            <div class="mt-2 text-caption text-medium-emphasis">
              Última atualização: {{ debugStats.lastUpdate ? debugStats.lastUpdate.toLocaleTimeString('pt-BR') : 'N/A' }}
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Estado Atual do Sistema -->
          <div class="mb-4">
            <h6 class="text-h6 mb-3">🎯 Estado do Sistema</h6>
            <VRow dense>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardText>
                    <div class="text-caption text-medium-emphasis mb-2">Dados do Pacote</div>
                    <div><strong>Código:</strong> {{ codpac || 'N/A' }}</div>
                    <div><strong>Descrição:</strong> {{ descPacote || 'N/A' }}</div>
                    <div><strong>Transportador:</strong> {{ nomeTransporte || 'N/A' }}</div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardText>
                    <div class="text-caption text-medium-emphasis mb-2">Rota & Placa</div>
                    <div><strong>Placa:</strong> {{ placa || 'N/A' }}</div>
                    <div><strong>Rota ID:</strong> {{ rotaId || 'N/A' }}</div>
                    <div><strong>Valor:</strong> {{ valorViagem > 0 ? `R$ ${valorViagem.toFixed(2)}` : 'N/A' }}</div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-4" />

          <!-- Logs -->
          <div>
            <div class="d-flex justify-space-between align-center mb-3">
              <h6 class="text-h6">📋 Logs do Sistema ({{ debugLogs.length }})</h6>
              <VBtn
                size="small"
                variant="tonal"
                color="error"
                prepend-icon="tabler-trash"
                @click="clearDebugLogs"
              >
                Limpar Logs
              </VBtn>
            </div>

            <div class="debug-logs-container">
              <VCard
                v-for="(log, index) in debugLogs"
                :key="index"
                :color="log.level === 'error' ? 'error' : log.level === 'warn' ? 'warning' : log.level === 'success' ? 'success' : 'default'"
                variant="tonal"
                class="mb-2"
              >
                <VCardText class="py-2">
                  <div class="d-flex align-center gap-2 mb-1">
                    <VChip :color="log.level === 'error' ? 'error' : log.level === 'warn' ? 'warning' : log.level === 'success' ? 'success' : 'info'" size="x-small">
                      {{ log.level }}
                    </VChip>
                    <VChip color="default" size="x-small">{{ log.category }}</VChip>
                    <span class="text-caption text-medium-emphasis">{{ new Date(log.timestamp).toLocaleTimeString('pt-BR') }}</span>
                  </div>
                  <div class="text-body-2">{{ log.message }}</div>
                  <div v-if="log.data" class="mt-2">
                    <VExpansionPanels>
                      <VExpansionPanel>
                        <VExpansionPanelTitle class="text-caption py-1">Ver Dados</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption" style="white-space: pre-wrap; max-height: 200px; overflow: auto">{{ JSON.stringify(log.data, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>
                    </VExpansionPanels>
                  </div>
                </VCardText>
              </VCard>

              <div v-if="debugLogs.length === 0" class="text-center text-medium-emphasis py-8">
                Nenhum log ainda. As ações serão registradas aqui.
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Snackbar (Vuexy style toast) -->
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

<style scoped>
.debug-logs-container {
  max-height: 500px;
  overflow-y: auto;
}

.debug-logs-container::-webkit-scrollbar {
  width: 8px;
}

.debug-logs-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.debug-logs-container::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 4px;
}

.debug-logs-container::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
