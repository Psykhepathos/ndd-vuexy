<script setup lang="ts">
// @ts-nocheck - Leaflet type incompatibilities (known @types/leaflet issue)
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { usePackageSimulation } from '@/composables/usePackageSimulation'
import { usePracasPedagio } from '@/composables/usePracasPedagio'
import { useToast } from '@/composables/useToast'
import { API_BASE_URL, API_ENDPOINTS, apiFetch } from '@/config/api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Interfaces
interface SemPararRota {
  spararrotid: number
  desspararrot: string
  tempoviagem: number
  flgcd: boolean
  flgretorno: boolean
  datatu: string | null
  resatu: string | null
}

interface RotaMunicipio {
  spararmuseq: number
  codmun: number
  codest: number
  desmun: string
  desest: string
  cdibge: number
  lat?: number
  lon?: number
  geocodingStatus?: 'pending' | 'loading' | 'success' | 'error'
  geocodingError?: string
}


// Sistema de Debug e Logging
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
  totalGeocodes: 0,
  successfulGeocodes: 0,
  failedGeocodes: 0,
  cachedGeocodes: 0,
  mapUpdates: 0,
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
    totalGeocodes: 0,
    successfulGeocodes: 0,
    failedGeocodes: 0,
    cachedGeocodes: 0,
    mapUpdates: 0,
    lastUpdate: null
  }
}

// Composables
const route = useRoute()
const router = useRouter()
const { showError, showSuccess } = useToast()

// Estados reativos
const rota = ref<SemPararRota | null>(null)
const municipios = ref<RotaMunicipio[]>([])
const loading = ref(false)
const saving = ref(false)
const editMode = ref(false)
const map = ref<L.Map | null>(null)
const mapContainer = ref<HTMLElement>()
const markers = ref<L.Marker[]>([])
const routingControl = ref<any>(null)
const distanciaTotal = ref(0)

// Controle de sincronização
const isUpdatingMap = ref(false)
const updateMapDebounceTimer = ref<number | null>(null)
const geocodingQueue = ref<Set<number>>(new Set()) // Track municípios being geocoded

// Sistema de Simulação de Entregas (Composable)
const {
  loadingPacotes,
  pacotesOptions,
  selectedPacote,
  searchPacote,
  entregas,
  simulationActive,
  loadingSimulation,
  hasSimulation,
  totalEntregas,
  entregasComGps,
  fetchPacotesAutocomplete,
  startSimulation,
  stopSimulation,
  createCombinedMarkers,
  createCombinedWaypoints,
} = usePackageSimulation()

// Composable para praças de pedágio
const {
  loading: loadingPracas,
  pracas,
  loadAndDisplayPracas,
  removePracasFromMap
} = usePracasPedagio()

// Estado para controlar exibição de praças
const mostrarPracas = ref(true)

// Validação de coordenadas
const isValidCoordinate = (lat?: number, lon?: number): boolean => {
  if (lat === undefined || lon === undefined) return false
  if (isNaN(lat) || isNaN(lon)) return false
  if (lat < -90 || lat > 90) return false
  if (lon < -180 || lon > 180) return false
  // Brasil aproximadamente: lat -34 a 5, lon -74 a -34
  if (lat < -35 || lat > 6) {
    addDebugLog('warn', 'VALIDATION', `Latitude fora do Brasil: ${lat}`)
  }
  if (lon < -75 || lon > -33) {
    addDebugLog('warn', 'VALIDATION', `Longitude fora do Brasil: ${lon}`)
  }
  return true
}

const sanitizeCoordinate = (coord: any): number | undefined => {
  if (coord === null || coord === undefined || coord === '') return undefined
  const num = Number(coord)
  if (isNaN(num)) return undefined
  return num
}

// Autocomplete
const loadingMunicipios = ref(false)
const municipiosOptions = ref<Array<{title: string, value: any}>>([])
const selectedMunicipio = ref<any>(null)
const searchMunicipio = ref('')

// ID da rota
const rotaId = computed(() => route.params.id as string)

// Carregar dados da rota
const fetchRota = async () => {
  loading.value = true

  try {
    const response = await apiFetch(API_ENDPOINTS.semPararRotaMunicipios(parseInt(rotaId.value)))

    const data = await response.json()

    if (data.success) {
      // Normalizar campos da rota (API retorna camelCase, frontend usa lowercase)
      const rawRota = data.data.rota || {}
      rota.value = {
        spararrotid: rawRota.sPararRotID || rawRota.spararrotid || 0,
        desspararrot: rawRota.desSPararRot || rawRota.desspararrot || '',
        tempoviagem: parseInt(String(rawRota.tempoViagem || rawRota.tempoviagem), 10) || 1,
        flgcd: Boolean(rawRota.flgCD ?? rawRota.flgcd),
        flgretorno: Boolean(rawRota.flgRetorno ?? rawRota.flgretorno),
        datatu: rawRota.datAtu || rawRota.datatu || null,
        resatu: rawRota.resAtu || rawRota.resatu || null
      }

      // Normalizar tipos dos municípios (API retorna camelCase, frontend usa lowercase)
      const rawMunicipios = data.data.municipios || []
      municipios.value = rawMunicipios.map((m: any) => ({
        spararmuseq: parseInt(String(m.sPararMuSeq || m.spararmuseq), 10) || 0,
        codmun: parseInt(String(m.codMun || m.codmun), 10) || 0,
        codest: parseInt(String(m.codEst || m.codest), 10) || 0,
        cdibge: parseInt(String(m.cdibge), 10) || 0,
        desmun: String(m.desMun || m.desmun || '').trim(),
        desest: String(m.desEst || m.desest || '').trim(),
        lat: m.lat !== null && m.lat !== undefined ? Number(m.lat) : undefined,
        lon: m.lon !== null && m.lon !== undefined ? Number(m.lon) : undefined,
        geocodingStatus: m.lat && m.lon ? 'success' : 'pending'
      }))

      console.log('✅ Rota carregada:', rota.value)
      console.log('✅ Municípios carregados (normalizados):', municipios.value.length, municipios.value)

      // Garantir sequência correta
      municipios.value.sort((a, b) => a.spararmuseq - b.spararmuseq)

      // IMPORTANTE: Setar loading = false ANTES de inicializar o mapa
      // para que o v-if renderize o mapContainer
      loading.value = false

      await nextTick()
      console.log('🗺️ Inicializando mapa...')
      initMap()
    } else {
      console.error('❌ Erro na API:', data.message)
      loading.value = false
    }
  } catch (error) {
    console.error('Erro ao carregar rota SemParar:', error)
    loading.value = false
  }
}

/**
 * Carrega e exibe TODAS as praças de pedágio no mapa
 */
async function loadPracasProximasRota() {
  if (!map.value || !mostrarPracas.value) {
    return
  }

  try {
    addDebugLog('info', 'PRACAS_PEDAGIO', 'Carregando TODAS as praças de pedágio...')

    const pracasEncontradas = await loadAndDisplayPracas(
      map.value,
      {
        color: '#F44336', // Vermelho para praças de pedágio
        showPopup: true,
        zIndex: 1000
      }
    )

    addDebugLog('success', 'PRACAS_PEDAGIO', `${pracasEncontradas.length} praças de pedágio exibidas no mapa`)
  } catch (error) {
    addDebugLog('error', 'PRACAS_PEDAGIO', 'Erro ao carregar praças de pedágio', error)
  }
}

/**
 * Toggle para mostrar/ocultar praças de pedágio
 */
async function togglePracas() {
  mostrarPracas.value = !mostrarPracas.value

  if (mostrarPracas.value) {
    // Carregar TODAS as praças
    await loadPracasProximasRota()
  } else {
    // Remover praças do mapa
    removePracasFromMap()
    addDebugLog('info', 'PRACAS_PEDAGIO', 'Praças de pedágio ocultadas')
  }
}

/**
 * Calcula rota usando MapService unificado (OSRM-only)
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

    const response = await apiFetch(`${API_BASE_URL}/api/map/route`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      const errorText = await response.text()
      addDebugLog('error', 'MAPSERVICE', `Erro HTTP ${response.status}`, errorText)
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
        cached: result.data.cached,
        segments: result.data.segments,
        total_segments: result.data.total_segments
      }
    }

    addDebugLog('warn', 'MAPSERVICE', 'Resposta sem coordenadas válidas')
    return null
  } catch (error) {
    addDebugLog('error', 'MAPSERVICE', 'Erro ao calcular rota', error)
    return null
  }
}

// Buscar municípios para autocomplete
const fetchMunicipios = async (search: string = '') => {
  if (search.length < 2 && search !== '') return

  loadingMunicipios.value = true

  try {
    const params = new URLSearchParams({
      search: search
    })

    const response = await apiFetch(`${API_ENDPOINTS.municipios}?${params}`)

    const data = await response.json()

    if (data.success && data.data) {
      console.log('📍 Municípios da API (raw):', data.data[0]) // Debug primeiro item
      municipiosOptions.value = data.data.map((m: any) => ({
        title: `${m.desmun} - ${m.desest}`,
        value: {
          codmun: parseInt(String(m.codmun), 10) || 0,
          codest: parseInt(String(m.codest), 10) || 0,
          desmun: String(m.desmun || '').trim(),
          desest: String(m.desest || '').trim(),
          cdibge: parseInt(String(m.cdibge), 10) || 0,
          lat: m.lat,
          lon: m.lon
        }
      }))
    }
  } catch (error) {
    console.error('Erro ao buscar municípios:', error)
    municipiosOptions.value = []
  } finally {
    loadingMunicipios.value = false
  }
}

// Inicializar mapa Leaflet + OpenStreetMap
const initMap = async () => {
  console.log('🗺️ initMap() chamado')
  console.log('📦 mapContainer.value:', mapContainer.value)

  if (!mapContainer.value) {
    console.error('❌ mapContainer não encontrado!')
    return
  }

  console.log('✅ Criando mapa Leaflet + OpenStreetMap...')

  // Criar mapa centrado no Brasil
  map.value = L.map(mapContainer.value).setView([-14.2350, -51.9253], 4)

  // Adicionar tiles OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map.value)

  console.log('✅ Mapa criado com sucesso!')

  // Plotar pontos no mapa
  updateMapMarkers()
}

// Atualizar marcadores e polyline no mapa (com debounce e controle de sincronização)
const updateMapMarkers = async (forceImmediate = false) => {
  // Debounce: evitar múltiplas atualizações seguidas
  if (!forceImmediate) {
    if (updateMapDebounceTimer.value) {
      clearTimeout(updateMapDebounceTimer.value)
    }

    updateMapDebounceTimer.value = window.setTimeout(() => {
      updateMapMarkersInternal()
    }, 300) // 300ms debounce

    return
  }

  // Executar imediatamente
  await updateMapMarkersInternal()
}

const updateMapMarkersInternal = async () => {
  if (!map.value) {
    addDebugLog('warn', 'MAP_UPDATE', 'Mapa não inicializado')
    return
  }

  if (isUpdatingMap.value) {
    addDebugLog('warn', 'MAP_UPDATE', 'Atualização já em andamento, ignorando...')
    return
  }

  isUpdatingMap.value = true
  debugStats.value.mapUpdates++
  debugStats.value.lastUpdate = new Date()

  addDebugLog('info', 'MAP_UPDATE', `Iniciando atualização do mapa`, {
    totalMunicipios: municipios.value.length,
    editMode: editMode.value
  })

  try {
    // Limpar marcadores antigos
    markers.value.forEach(marker => marker.remove())
    markers.value = []

    // Limpar routing control antigo
    if (routingControl.value) {
      map.value.removeControl(routingControl.value)
      routingControl.value = null
    }

    const path: L.LatLngExpression[] = []
    const bounds: [number, number][] = []

    // Verificar se há municípios
    if (municipios.value.length === 0) {
      addDebugLog('warn', 'MAP_UPDATE', 'Nenhum município na rota')
      isUpdatingMap.value = false
      return
    }

    // IMPORTANTE: Processar geocoding de forma síncrona, um por vez
    // para evitar race conditions
    for (let i = 0; i < municipios.value.length; i++) {
      const municipio = municipios.value[i]

      // Sanitizar coordenadas
      municipio.lat = sanitizeCoordinate(municipio.lat)
      municipio.lon = sanitizeCoordinate(municipio.lon)

      // Verificar se precisa fazer geocoding
      if (!isValidCoordinate(municipio.lat, municipio.lon)) {
        // Evitar geocoding duplicado
        if (geocodingQueue.value.has(municipio.codmun)) {
          addDebugLog('info', 'GEOCODING', `Geocoding já em andamento para ${municipio.desmun}`)
          continue
        }

        geocodingQueue.value.add(municipio.codmun)
        municipio.geocodingStatus = 'loading'

        addDebugLog('info', 'GEOCODING', `Buscando coordenadas: ${municipio.desmun}, ${municipio.desest}`, {
          codmun: municipio.codmun,
          cdibge: municipio.cdibge
        })

        try {
          const coords = await geocodeByIBGE(municipio)
          if (coords && isValidCoordinate(coords.lat, coords.lon)) {
            municipio.lat = coords.lat
            municipio.lon = coords.lon
            municipio.geocodingStatus = 'success'
            debugStats.value.successfulGeocodes++

            addDebugLog('success', 'GEOCODING', `Coordenadas encontradas para ${municipio.desmun}`, coords)
          } else {
            municipio.geocodingStatus = 'error'
            municipio.geocodingError = 'Coordenadas não encontradas'
            debugStats.value.failedGeocodes++

            addDebugLog('error', 'GEOCODING', `Coordenadas NÃO encontradas para ${municipio.desmun}`)
          }
        } catch (error: any) {
          municipio.geocodingStatus = 'error'
          municipio.geocodingError = error.message || 'Erro desconhecido'
          debugStats.value.failedGeocodes++

          addDebugLog('error', 'GEOCODING', `Erro ao buscar coordenadas: ${municipio.desmun}`, error)
        } finally {
          geocodingQueue.value.delete(municipio.codmun)
        }
      } else {
        municipio.geocodingStatus = 'success'
      }
    }

    // Criar waypoints e marcadores para municípios com coordenadas válidas
    let validMunicipios = 0
    const waypoints: L.LatLng[] = []

    municipios.value.forEach((municipio, index) => {
      if (isValidCoordinate(municipio.lat, municipio.lon)) {
        validMunicipios++
        const latLng = L.latLng(Number(municipio.lat), Number(municipio.lon))
        waypoints.push(latLng)
        bounds.push([Number(municipio.lat), Number(municipio.lon)])

        // Cor do marcador baseado no status
        let fillColor = '#1976d2' // Azul padrão
        if (editMode.value) fillColor = '#ff9800' // Laranja em modo edição
        if (municipio.geocodingStatus === 'error') fillColor = '#f44336' // Vermelho se erro

        // Criar marcador customizado com número
        const icon = L.divIcon({
          className: 'custom-marker',
          html: `
            <div style="
              background-color: ${fillColor};
              width: 32px;
              height: 32px;
              border-radius: 50%;
              display: flex;
              align-items: center;
              justify-content: center;
              color: white;
              font-weight: bold;
              font-size: 12px;
              border: 3px solid white;
              box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            ">
              ${municipio.spararmuseq}
            </div>
          `,
          iconSize: [32, 32],
          iconAnchor: [16, 16]
        })

        const marker = L.marker(latLng, {
          icon,
          title: `${municipio.spararmuseq}. ${municipio.desmun}`
        }).addTo(map.value!)

        // Popup com informações
        marker.bindPopup(`
          <div style="padding: 8px; font-family: sans-serif; min-width: 200px;">
            <h6 style="margin: 0 0 8px 0;"><strong>${municipio.desmun}</strong></h6>
            <p style="margin: 2px 0; font-size: 13px;">Estado: <strong>${municipio.desest}</strong></p>
            <p style="margin: 2px 0; font-size: 13px;">Sequência: <strong>${municipio.spararmuseq}</strong></p>
            <p style="margin: 2px 0; font-size: 13px;">IBGE: <strong>${municipio.cdibge}</strong></p>
            <p style="margin: 2px 0; font-size: 12px; color: #666;">Lat: ${municipio.lat?.toFixed(6)}</p>
            <p style="margin: 2px 0; font-size: 12px; color: #666;">Lon: ${municipio.lon?.toFixed(6)}</p>
            ${municipio.geocodingStatus === 'error' ? `<p style="margin: 4px 0; color: #f44336; font-size: 12px;">⚠️ ${municipio.geocodingError}</p>` : ''}
          </div>
        `)

        markers.value.push(marker)
      }
    })

    addDebugLog('info', 'MAP_UPDATE', `Marcadores criados`, {
      total: municipios.value.length,
      validos: validMunicipios,
      invalidos: municipios.value.length - validMunicipios
    })

    // ============================================================================
    // CARREGAMENTO PARALELO: Routing + Praças de Pedágio
    // ============================================================================
    // Iniciar carregamento das praças ANTES do routing para renderizar simultaneamente
    // ⚠️ IMPORTANTE: Não usar await aqui, apenas iniciar a Promise
    const pracasPromise = mostrarPracas.value ? loadPracasProximasRota() : Promise.resolve()

    // ============================================================================
    // ROUTING VIA MAPSERVICE UNIFICADO (OSRM-only com chunking automático)
    // ============================================================================
    //
    // ✅ SOLUÇÃO: MapService unificado com OSRM + cache + chunking automático
    //    - Service: app/Services/Map/MapService.php
    //    - Endpoint: POST /api/map/route
    //    - Features:
    //      * Chunking automático para rotas >10 waypoints
    //      * Cache unificado (30 dias TTL)
    //      * Fallback inteligente
    //      * 100% gratuito (OSRM público)
    //
    // FORMATO DO ENDPOINT:
    //   Request:  { waypoints: [[lat,lon], ...], options: {use_cache, fallback_to_straight} }
    //   Response: { success: true, data: {coordinates, distance_km, cached, segments} }
    // ============================================================================

    // Calcular rota usando MapService
    if (waypoints.length > 1) {
      // Cor da rota baseado no modo
      let routeColor = '#2196F3' // Azul padrão
      if (editMode.value) routeColor = '#FF9800' // Laranja em modo edição

      // Converter waypoints para formato MapService [lat, lon]
      const mapServiceWaypoints = waypoints.map(w => [w.lat, w.lng] as [number, number])

      // Calcular rota com MapService
      const routeResult = await calculateRouteWithMapService(mapServiceWaypoints)

      if (routeResult && routeResult.coordinates.length > 0) {
        // Atualizar distância total (garantir que é número)
        distanciaTotal.value = Number(routeResult.distance_km)

        // Converter coordenadas para Leaflet LatLng
        const routeLatLngs = routeResult.coordinates.map(coord => [coord[0], coord[1]] as L.LatLngExpression)

        // Remover rota anterior
        if (routingControl.value) {
          map.value?.removeControl(routingControl.value)
        }

        // Adicionar rota real
        routingControl.value = L.polyline(routeLatLngs, {
          color: routeColor,
          weight: 4,
          opacity: 0.7
        }).addTo(map.value!)

        // Ajustar bounds para mostrar toda a rota
        if (bounds.length > 0) {
          map.value!.fitBounds(bounds, { padding: [50, 50] })
        }
      } else {
        // Fallback: desenhar linha reta tracejada
        addDebugLog('warn', 'ROUTING', 'Usando linha reta como fallback')

        const polylinePoints = waypoints.map(w => [w.lat, w.lng] as L.LatLngExpression)

        if (routingControl.value) {
          map.value?.removeControl(routingControl.value)
        }

        routingControl.value = L.polyline(polylinePoints, {
          color: routeColor,
          weight: 3,
          opacity: 0.5,
          dashArray: '10, 10'
        }).addTo(map.value!)

        // Calcular distância aproximada
        let totalDist = 0
        for (let i = 0; i < waypoints.length - 1; i++) {
          totalDist += waypoints[i].distanceTo(waypoints[i + 1])
        }
        distanciaTotal.value = totalDist / 1000

        addDebugLog('warn', 'ROUTING', 'Distância calculada por linha reta', {
          distanciaKm: distanciaTotal.value.toFixed(1)
        })

        // Ajustar bounds inicial
        if (bounds.length > 0) {
          map.value.fitBounds(bounds, { padding: [50, 50] })
        }
      }
    } else if (waypoints.length === 1) {
      // Se apenas 1 ponto, centralizar nele
      map.value.setView([waypoints[0].lat, waypoints[0].lng], 10)
      addDebugLog('warn', 'ROUTING', 'Apenas 1 município válido, não é possível calcular rota')
    } else {
      addDebugLog('error', 'ROUTING', 'Nenhum município com coordenadas válidas')
    }

    // ============================================================================
    // AGUARDAR PRAÇAS DE PEDÁGIO CARREGAREM (iniciado em paralelo acima)
    // ============================================================================
    await pracasPromise
    addDebugLog('success', 'RENDER_COMPLETE', 'Rota e praças renderizadas simultaneamente')

  } catch (error: any) {
    addDebugLog('error', 'MAP_UPDATE', 'Erro ao atualizar mapa', error)
  } finally {
    isUpdatingMap.value = false
  }
}

// ===================================
// FUNÇÕES DE GEOCODING
// ===================================

// Função para geocoding usando código IBGE
const geocodeByIBGE = async (municipio: RotaMunicipio): Promise<{lat: number, lon: number} | null> => {
  const startTime = performance.now()
  debugStats.value.totalGeocodes++

  try {
    const nomeMunicipio = municipio.desmun.trim()
    const nomeEstado = municipio.desest.trim()
    const codigoIBGE = String(municipio.cdibge).padStart(7, '0')

    addDebugLog('info', 'GEOCODING_API', `Solicitando coordenadas`, {
      ibge: codigoIBGE,
      municipio: nomeMunicipio,
      estado: nomeEstado
    })

    const response = await apiFetch(API_ENDPOINTS.geocodingLote, {
      method: 'POST',
      body: JSON.stringify({
        municipios: [{
          cdibge: codigoIBGE,
          desmun: nomeMunicipio,
          desest: nomeEstado,
          cod_mun: municipio.codmun,
          cod_est: municipio.codest
        }]
      })
    })

    const data = await response.json()
    const endTime = performance.now()
    const duration = ((endTime - startTime) / 1000).toFixed(2)

    if (data.success && data.data && data.data.length > 0) {
      const resultado = data.data[0]

      if (resultado.coordenadas) {
        const coords = {
          lat: resultado.coordenadas.lat,
          lon: resultado.coordenadas.lon
        }

        const cached = resultado.coordenadas.cached
        if (cached) {
          debugStats.value.cachedGeocodes++
        }

        addDebugLog('success', 'GEOCODING_API', `Coordenadas obtidas em ${duration}s (${cached ? 'cache' : 'Google API'})`, {
          municipio: nomeMunicipio,
          coords,
          cached
        })

        return coords
      } else {
        addDebugLog('warn', 'GEOCODING_API', `Resposta sem coordenadas após ${duration}s`, resultado)
      }
    } else {
      addDebugLog('error', 'GEOCODING_API', `API retornou erro após ${duration}s`, data)
    }
  } catch (error: any) {
    const endTime = performance.now()
    const duration = ((endTime - startTime) / 1000).toFixed(2)

    addDebugLog('error', 'GEOCODING_API', `Erro após ${duration}s`, {
      municipio: municipio.desmun,
      error: error.message
    })
  }

  return null
}

// Adicionar município à rota
const adicionarMunicipio = async () => {
  if (!selectedMunicipio.value) return

  console.log('🔍 selectedMunicipio.value:', selectedMunicipio.value)

  // VAutocomplete com return-object retorna o objeto inteiro do item
  // Precisamos acessar a propriedade 'value' se existir, ou usar o objeto diretamente
  const municipioData = selectedMunicipio.value.value || selectedMunicipio.value

  console.log('🔍 municipioData:', municipioData)
  console.log('🔍 municipioData tipos:', {
    codmun: typeof municipioData.codmun,
    codest: typeof municipioData.codest,
    cdibge: typeof municipioData.cdibge
  })

  // Converter e validar códigos
  const codmun = parseInt(String(municipioData.codmun), 10)
  const codest = parseInt(String(municipioData.codest), 10)
  const cdibge = parseInt(String(municipioData.cdibge), 10)

  if (isNaN(codmun) || isNaN(codest) || isNaN(cdibge)) {
    console.error('❌ Códigos inválidos do município:', { codmun, codest, cdibge, municipioData })
    showError(`Município "${municipioData.desmun}" possui códigos inválidos`)
    return
  }

  addDebugLog('info', 'EDIT', 'Adicionando novo município', {
    municipio: municipioData.desmun,
    posicao: municipios.value.length + 1,
    codigos: { codmun, codest, cdibge }
  })

  const novoMunicipio: RotaMunicipio = {
    spararmuseq: municipios.value.length + 1,
    codmun: codmun,
    codest: codest,
    desmun: String(municipioData.desmun || '').trim(),
    desest: String(municipioData.desest || '').trim(),
    cdibge: cdibge,
    lat: sanitizeCoordinate(municipioData.lat),
    lon: sanitizeCoordinate(municipioData.lon),
    geocodingStatus: 'pending'
  }

  // Validar coordenadas existentes
  if (!isValidCoordinate(novoMunicipio.lat, novoMunicipio.lon)) {
    novoMunicipio.geocodingStatus = 'pending'
    addDebugLog('warn', 'EDIT', 'Município sem coordenadas válidas, será feito geocoding', {
      municipio: novoMunicipio.desmun
    })
  } else {
    novoMunicipio.geocodingStatus = 'success'
    addDebugLog('success', 'EDIT', 'Município adicionado com coordenadas', {
      municipio: novoMunicipio.desmun,
      coords: { lat: novoMunicipio.lat, lon: novoMunicipio.lon }
    })
  }

  municipios.value.push(novoMunicipio)
  selectedMunicipio.value = null
  searchMunicipio.value = ''

  // Atualizar mapa (com debounce)
  updateMapMarkers()
}

// Remover município
const removerMunicipio = (index: number) => {
  const municipioRemovido = municipios.value[index]

  addDebugLog('info', 'EDIT', 'Removendo município', {
    municipio: municipioRemovido.desmun,
    sequenciaAnterior: municipioRemovido.spararmuseq,
    totalAntes: municipios.value.length
  })

  municipios.value.splice(index, 1)

  // Reajustar sequências
  municipios.value.forEach((m, i) => {
    m.spararmuseq = i + 1
  })

  addDebugLog('success', 'EDIT', 'Município removido e sequências reajustadas', {
    totalDepois: municipios.value.length
  })

  // Atualizar mapa (com debounce)
  updateMapMarkers()
}

// Função para quando terminar de arrastar
const onDragEnd = () => {
  addDebugLog('info', 'EDIT', 'Reordenando municípios após drag & drop', {
    total: municipios.value.length
  })

  // Reajustar sequências após drag & drop
  const sequenciasAntes = municipios.value.map(m => ({ seq: m.spararmuseq, nome: m.desmun }))

  municipios.value.forEach((m, i) => {
    m.spararmuseq = i + 1
  })

  const sequenciasDepois = municipios.value.map(m => ({ seq: m.spararmuseq, nome: m.desmun }))

  addDebugLog('success', 'EDIT', 'Sequências reajustadas', {
    antes: sequenciasAntes,
    depois: sequenciasDepois
  })

  // Atualizar mapa com nova ordem (com debounce)
  updateMapMarkers()
}

// Salvar alterações
const salvarAlteracoes = async () => {
  if (!rota.value) {
    showError('Dados da rota não carregados')
    return
  }

  // Validar nome da rota
  const nomeRota = String(rota.value.desspararrot || '').trim()
  if (!nomeRota) {
    showError('Nome da rota é obrigatório')
    return
  }

  saving.value = true

  try {
    // Converter municipios com validação rigorosa
    const municipiosPayload = municipios.value.map((m, index) => {
      const codEst = parseInt(String(m.codest), 10)
      const codMun = parseInt(String(m.codmun), 10)
      const cdibge = parseInt(String(m.cdibge), 10)

      // Validar que são números válidos
      if (isNaN(codEst) || isNaN(codMun) || isNaN(cdibge)) {
        console.error(`Município ${index + 1} com valores inválidos:`, m)
        throw new Error(`Município "${m.desmun}" possui códigos inválidos`)
      }

      return {
        cod_est: codEst,
        cod_mun: codMun,
        des_est: String(m.desest || '').trim(),
        des_mun: String(m.desmun || '').trim(),
        cdibge: cdibge
      }
    })

    const payload = {
      nome: nomeRota,
      tempo_viagem: parseInt(String(rota.value.tempoviagem), 10) || 1,
      flg_cd: Boolean(rota.value.flgcd),
      flg_retorno: Boolean(rota.value.flgretorno),
      municipios: municipiosPayload
    }

    console.log('📤 Payload para salvar:', payload)

    const response = await apiFetch(API_ENDPOINTS.semPararRota(parseInt(rotaId.value)), {
      method: 'PUT',
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (data.success) {
      showSuccess('Rota atualizada com sucesso!')
      editMode.value = false
      await fetchRota()
    } else {
      // Mostrar erros de validação
      if (data.errors) {
        const firstError = Object.values(data.errors)[0]
        const errorMsg = Array.isArray(firstError) ? firstError[0] : firstError
        showError(String(errorMsg))
      } else {
        showError(data.message || 'Erro ao salvar rota')
      }
    }
  } catch (error: any) {
    console.error('Erro ao salvar alterações:', error)
    showError(error.message || 'Erro ao salvar alterações')
  } finally {
    saving.value = false
  }
}

// Cancelar edição
const cancelarEdicao = () => {
  editMode.value = false
  fetchRota() // Recarregar dados originais
}

// Ativar modo edição
const ativarEdicao = () => {
  editMode.value = true
  updateMapMarkers()
}

// Formatar data
const formatDate = (date: string | null) => {
  if (!date) return 'N/D'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('pt-BR')
}

// Voltar para listagem
const goBack = () => {
  router.push('/rotas-padrao')
}

// Nota: Não usamos watch aqui pois updateMapMarkers já é chamado
// explicitamente em todas as operações (adicionar, remover, drag)

// ===================================
// FUNÇÕES DE SIMULAÇÃO
// ===================================

// Função para atualizar mapa com simulação ativa
const updateMapWithSimulation = async () => {
  if (!map.value) return

  addDebugLog('info', 'SIMULATION', 'Atualizando mapa com simulação ativa', {
    totalMunicipios: municipios.value.length,
    totalEntregas: entregas.value.length
  })

  // Limpar marcadores e routing control existentes
  markers.value.forEach(marker => marker.remove())
  markers.value = []
  if (routingControl.value) {
    map.value.removeControl(routingControl.value)
    routingControl.value = null
  }

  const bounds: [number, number][] = []
  const allWaypoints: L.LatLng[] = []

  // 1. Processar municípios da rota SemParar (azul)
  const validMunicipios = municipios.value.filter(m => isValidCoordinate(m.lat, m.lon))

  validMunicipios.forEach((municipio, index) => {
    const latLng = L.latLng(Number(municipio.lat), Number(municipio.lon))
    allWaypoints.push(latLng)
    bounds.push([Number(municipio.lat), Number(municipio.lon)])

    // Marcador azul para rota base
    const icon = L.divIcon({
      className: 'custom-marker',
      html: `
        <div style="
          background-color: #2196F3;
          width: 32px;
          height: 32px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-weight: bold;
          font-size: 12px;
          border: 3px solid white;
          box-shadow: 0 2px 8px rgba(0,0,0,0.3);
          z-index: ${1000 + index};
        ">
          ${municipio.spararmuseq}
        </div>
      `,
      iconSize: [32, 32],
      iconAnchor: [16, 16]
    })

    const marker = L.marker(latLng, {
      icon,
      title: `Rota ${municipio.spararmuseq}: ${municipio.desmun}`
    }).addTo(map.value!)

    marker.bindPopup(`
      <div style="padding: 8px; font-family: sans-serif;">
        <h6 style="margin: 0 0 8px 0; color: #2196F3;"><strong>🗺️ Rota Padrão ${municipio.spararmuseq}</strong></h6>
        <p style="margin: 2px 0; font-size: 13px;"><strong>${municipio.desmun} - ${municipio.desest}</strong></p>
        <p style="margin: 2px 0; font-size: 12px; color: #666;">IBGE: ${municipio.cdibge}</p>
        <p style="margin: 2px 0; font-size: 12px; color: #666;">Lat: ${municipio.lat?.toFixed(6)}, Lon: ${municipio.lon?.toFixed(6)}</p>
      </div>
    `)

    markers.value.push(marker)
  })

  // 2. Processar entregas do pacote (laranja/verde/vermelho)
  entregas.value.forEach((entrega, index) => {
    const latLng = L.latLng(Number(entrega.lat), Number(entrega.lon))
    allWaypoints.push(latLng)
    bounds.push([Number(entrega.lat), Number(entrega.lon)])

    // Cor baseada na posição
    let fillColor = '#FF9800' // Laranja padrão
    if (index === 0) fillColor = '#4CAF50' // Verde primeira
    if (index === entregas.value.length - 1) fillColor = '#F44336' // Vermelho última

    const markerLabel = validMunicipios.length + index + 1

    const icon = L.divIcon({
      className: 'custom-marker',
      html: `
        <div style="
          background-color: ${fillColor};
          width: 32px;
          height: 32px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-weight: bold;
          font-size: 12px;
          border: 3px solid white;
          box-shadow: 0 2px 8px rgba(0,0,0,0.3);
          z-index: ${2000 + index};
        ">
          ${markerLabel}
        </div>
      `,
      iconSize: [32, 32],
      iconAnchor: [16, 16]
    })

    const marker = L.marker(latLng, {
      icon,
      title: `Entrega ${entrega.seqent}: ${entrega.razcli}`
    }).addTo(map.value!)

    marker.bindPopup(`
      <div style="padding: 8px; font-family: sans-serif; min-width: 200px;">
        <h6 style="margin: 0 0 8px 0; color: ${fillColor};"><strong>📦 Entrega ${entrega.seqent}</strong></h6>
        <p style="margin: 2px 0; font-size: 13px;"><strong>${entrega.razcli}</strong></p>
        <p style="margin: 2px 0; font-size: 12px; color: #666;">Cliente: ${entrega.codcli}</p>
        <hr style="margin: 8px 0; border: none; border-top: 1px solid #ddd;">
        <p style="margin: 2px 0; font-size: 12px;">📍 ${entrega.desend}</p>
        <p style="margin: 2px 0; font-size: 12px;">${entrega.desbai}, ${entrega.desmun} - ${entrega.uf}</p>
        <hr style="margin: 8px 0; border: none; border-top: 1px solid #ddd;">
        <p style="margin: 2px 0; font-size: 12px;">💰 Valor: R$ ${entrega.valnot?.toFixed(2)}</p>
        <p style="margin: 2px 0; font-size: 12px;">⚖️ Peso: ${entrega.peso?.toFixed(1)} kg</p>
        <p style="margin: 2px 0; font-size: 12px;">📐 Volume: ${entrega.volume?.toFixed(2)} m³</p>
      </div>
    `)

    markers.value.push(marker)
  })

  addDebugLog('success', 'SIMULATION', 'Marcadores criados', {
    rota: validMunicipios.length,
    entregas: entregas.value.length,
    total: markers.value.length
  })

  // 3. Calcular rota combinada (rota + entregas) com cor magenta/rosa vibrante usando MapService
  if (allWaypoints.length > 1) {
    addDebugLog('info', 'SIMULATION', `Calculando rota combinada com ${allWaypoints.length} pontos via MapService`)

    // Converter waypoints para formato MapService [lat, lon]
    const mapServiceWaypoints = allWaypoints.map(w => [w.lat, w.lng] as [number, number])

    // Calcular rota com MapService
    const routeResult = await calculateRouteWithMapService(mapServiceWaypoints)

    if (routeResult && routeResult.coordinates.length > 0) {
      // Atualizar distância total
      distanciaTotal.value = Number(routeResult.distance_km)

      // Converter coordenadas para Leaflet LatLng
      const routeLatLngs = routeResult.coordinates.map(coord => [coord[0], coord[1]] as L.LatLngExpression)

      // Adicionar rota com cor magenta/rosa para simulação
      routingControl.value = L.polyline(routeLatLngs, {
        color: '#E91E63', // Magenta/Pink vibrante para simulação
        weight: 4,
        opacity: 0.7
      }).addTo(map.value!)

      // Ajustar bounds
      if (bounds.length > 0) {
        map.value.fitBounds(bounds, { padding: [50, 50] })
      }
    } else {
      // Fallback: linha reta
      addDebugLog('warn', 'SIMULATION', 'Usando linha reta como fallback')

      const polylinePoints = allWaypoints.map(w => [w.lat, w.lng] as L.LatLngExpression)

      routingControl.value = L.polyline(polylinePoints, {
        color: '#E91E63',
        weight: 3,
        opacity: 0.5,
        dashArray: '10, 10'
      }).addTo(map.value!)

      // Calcular distância aproximada
      let totalDist = 0
      for (let i = 0; i < allWaypoints.length - 1; i++) {
        totalDist += allWaypoints[i].distanceTo(allWaypoints[i + 1])
      }
      distanciaTotal.value = totalDist / 1000

      // Ajustar bounds
      if (bounds.length > 0) {
        map.value.fitBounds(bounds, { padding: [50, 50] })
      }
    }
  } else if (bounds.length > 0) {
    map.value.fitBounds(bounds, { padding: [50, 50] })
  }
}

// Handler para iniciar simulação
const handleSimulate = async () => {
  if (!selectedPacote.value) return

  addDebugLog('info', 'SIMULATION', 'Iniciando simulação', selectedPacote.value)

  const success = await startSimulation()

  if (success) {
    addDebugLog('success', 'SIMULATION', `Simulação iniciada: ${totalEntregas.value} entregas carregadas`)
    await updateMapWithSimulation()
  } else {
    addDebugLog('error', 'SIMULATION', 'Falha ao iniciar simulação')
  }
}

// Handler para parar simulação
const handleStopSimulation = async () => {
  addDebugLog('info', 'SIMULATION', 'Parando simulação')
  stopSimulation()
  await updateMapMarkers(true) // Voltar para visualização normal
}

// Lifecycle
onMounted(async () => {
  console.log('✅ Leaflet + OpenStreetMap carregado')
  fetchRota()
  fetchMunicipios()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-3 mb-6">
      <div class="d-flex align-center gap-3">
        <VBtn
          icon="tabler-arrow-left"
          variant="tonal"
          size="small"
          @click="goBack"
        />
        <div>
          <h4 class="text-h4 font-weight-medium mb-1">
            {{ editMode ? 'Editar' : 'Visualizar' }} Rota Padrão
          </h4>
          <p class="text-body-1 mb-0" v-if="rota">
            {{ rota.desspararrot }}
          </p>
        </div>
      </div>

      <div class="d-flex gap-2">
        <!-- Botão Debug -->
        <VBtn
          :color="showDebugPanel ? 'warning' : 'default'"
          :variant="showDebugPanel ? 'flat' : 'tonal'"
          prepend-icon="tabler-bug"
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

        <!-- Botão Toggle Praças de Pedágio -->
        <VBtn
          :color="mostrarPracas ? 'error' : 'default'"
          :variant="mostrarPracas ? 'flat' : 'tonal'"
          prepend-icon="tabler-coin"
          @click="togglePracas"
          :loading="loadingPracas"
          title="Mostrar/ocultar praças de pedágio próximas à rota"
        >
          {{ mostrarPracas ? 'Ocultar' : 'Mostrar' }} Praças
          <VBadge
            v-if="mostrarPracas && pracas.length > 0"
            :content="pracas.length"
            color="white"
            text-color="error"
            inline
            class="ms-2"
          />
        </VBtn>

        <VBtn
          v-if="!editMode && rota"
          color="primary"
          prepend-icon="tabler-edit"
          @click="ativarEdicao"
        >
          Editar Rota
        </VBtn>

        <VBtn
          v-if="editMode"
          color="success"
          prepend-icon="tabler-check"
          :loading="saving"
          @click="salvarAlteracoes"
        >
          Salvar Alterações
        </VBtn>

        <VBtn
          v-if="editMode"
          color="error"
          variant="tonal"
          prepend-icon="tabler-x"
          @click="cancelarEdicao"
        >
          Cancelar
        </VBtn>
      </div>
    </div>

    <!-- Painel de Debug Flutuante -->
    <VDialog
      v-model="showDebugPanel"
      max-width="1200"
      scrollable
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center bg-warning">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-bug" />
            <span>Painel de Debug - Rotas Padrão</span>
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
                  <div class="text-h5 font-weight-bold text-primary">{{ debugStats.mapUpdates }}</div>
                  <div class="text-caption">Atualizações do Mapa</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-green-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-success">{{ debugStats.successfulGeocodes }}</div>
                  <div class="text-caption">Geocodes Bem-Sucedidos</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-red-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-error">{{ debugStats.failedGeocodes }}</div>
                  <div class="text-caption">Geocodes Falhados</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-purple-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-purple">{{ debugStats.cachedGeocodes }}</div>
                  <div class="text-caption">Uso de Cache</div>
                </div>
              </VCol>
            </VRow>
            <div class="mt-2 text-caption text-medium-emphasis">
              Última atualização: {{ debugStats.lastUpdate ? debugStats.lastUpdate.toLocaleTimeString('pt-BR') : 'N/A' }}
            </div>
          </div>

          <!-- Estado Atual dos Municípios -->
          <div class="mb-4">
            <h6 class="text-h6 mb-3">🗺️ Estado dos Municípios</h6>
            <VDataTable
              :items="municipios"
              :headers="[
                { title: 'Seq', key: 'spararmuseq', width: '60px' },
                { title: 'Município', key: 'desmun' },
                { title: 'UF', key: 'desest', width: '60px' },
                { title: 'Latitude', key: 'lat', width: '120px' },
                { title: 'Longitude', key: 'lon', width: '120px' },
                { title: 'Status', key: 'geocodingStatus', width: '120px' }
              ]"
              density="compact"
              class="elevation-1"
              :items-per-page="10"
            >
              <template #item.lat="{ item }">
                <span :class="isValidCoordinate(item.lat, item.lon) ? 'text-success' : 'text-error'">
                  {{ item.lat ? item.lat.toFixed(6) : 'N/A' }}
                </span>
              </template>
              <template #item.lon="{ item }">
                <span :class="isValidCoordinate(item.lat, item.lon) ? 'text-success' : 'text-error'">
                  {{ item.lon ? item.lon.toFixed(6) : 'N/A' }}
                </span>
              </template>
              <template #item.geocodingStatus="{ item }">
                <VChip
                  :color="item.geocodingStatus === 'success' ? 'success' : item.geocodingStatus === 'error' ? 'error' : item.geocodingStatus === 'loading' ? 'warning' : 'default'"
                  size="small"
                  variant="flat"
                >
                  {{ item.geocodingStatus || 'pending' }}
                </VChip>
              </template>
            </VDataTable>
          </div>

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
                class="mb-2 pa-3"
              >
                <div class="d-flex justify-space-between align-center mb-1">
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      :icon="log.level === 'error' ? 'tabler-alert-circle' : log.level === 'warn' ? 'tabler-alert-triangle' : log.level === 'success' ? 'tabler-circle-check' : 'tabler-info-circle'"
                      size="18"
                    />
                    <VChip size="x-small" variant="flat">{{ log.category }}</VChip>
                    <span class="text-body-2 font-weight-bold">{{ log.message }}</span>
                  </div>
                  <span class="text-caption text-medium-emphasis">
                    {{ new Date(log.timestamp).toLocaleTimeString('pt-BR') }}
                  </span>
                </div>
                <div v-if="log.data" class="text-caption mt-2 font-mono pa-2 bg-grey-darken-4 rounded">
                  <pre style="margin: 0; font-size: 11px; max-height: 150px; overflow: auto;">{{ JSON.stringify(log.data, null, 2) }}</pre>
                </div>
              </VCard>

              <div v-if="debugLogs.length === 0" class="text-center text-medium-emphasis py-8">
                Nenhum log ainda. As ações serão registradas aqui.
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Alerta de Geocoding -->
    <VAlert
      v-if="!loading && municipios.length > 0 && municipios.some(m => !m.lat || !m.lon)"
      type="warning"
      variant="tonal"
      class="mb-4"
      closable
    >
      <VAlertTitle class="d-flex align-center">
        <VIcon icon="tabler-map-pin-off" class="me-2" />
        Coordenadas GPS Ausentes
      </VAlertTitle>
      <div class="text-body-2 mt-2">
        Alguns municípios não possuem coordenadas GPS. O sistema tentará buscar automaticamente usando Google Maps Geocoding.
        <br>
        <strong>Municípios sem GPS:</strong>
        {{ municipios.filter(m => !m.lat || !m.lon).map(m => m.desmun?.trim() || 'N/A').join(', ') }}
      </div>
    </VAlert>

    <VRow v-if="!loading && rota">
      <!-- Coluna Esquerda: Informações e Lista -->
      <VCol cols="12" md="4">
        <!-- Card de Informações -->
        <VCard class="mb-4">
          <VCardTitle>
            <VIcon icon="tabler-info-circle" class="me-2" />
            Informações da Rota
          </VCardTitle>
          <VCardText>
            <!-- Estatísticas em Cards pequenos -->
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-route" color="primary" size="20" />
                  <span class="text-body-2">Distância Total</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ distanciaTotal.toFixed(0) }} km</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-clock" color="info" size="20" />
                  <span class="text-body-2">Tempo Viagem</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ rota.tempoviagem }} dias</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-map-pin" color="success" size="20" />
                  <span class="text-body-2">Municípios</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ municipios.length }}</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-building" color="warning" size="20" />
                  <span class="text-body-2">Tipo</span>
                </div>
                <VChip
                  :color="rota.flgcd ? 'primary' : 'secondary'"
                  size="small"
                >
                  {{ rota.flgcd ? 'CD' : 'Outros' }}
                </VChip>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-refresh" color="secondary" size="20" />
                  <span class="text-body-2">Retorno</span>
                </div>
                <VIcon
                  :icon="rota.flgretorno ? 'tabler-check' : 'tabler-x'"
                  :color="rota.flgretorno ? 'success' : 'error'"
                />
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Card de Municípios -->
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-map-pins" class="me-2" />
            Sequência de Municípios
          </VCardTitle>

          <!-- Adicionar Município (apenas em modo edição) -->
          <VCardText v-if="editMode">
            <VAutocomplete
              v-model="selectedMunicipio"
              :items="municipiosOptions"
              :loading="loadingMunicipios"
              label="Adicionar Município"
              placeholder="Digite para buscar..."
              prepend-inner-icon="tabler-search"
              clearable
              item-title="title"
              return-object
              @update:search="fetchMunicipios"
              no-data-text="Nenhum município encontrado"
              class="mb-3"
            >
              <template #append>
                <VBtn
                  color="primary"
                  icon="tabler-plus"
                  size="small"
                  variant="flat"
                  @click="adicionarMunicipio"
                  :disabled="!selectedMunicipio"
                />
              </template>
            </VAutocomplete>
          </VCardText>

          <VCardText class="pa-0">
            <!-- Lista com Drag & Drop -->
            <draggable
              v-model="municipios"
              :disabled="!editMode"
              handle=".drag-handle"
              item-key="cdibge"
              @end="onDragEnd"
            >
              <template #item="{ element, index }">
                <div
                  class="d-flex align-center pa-3 border-b"
                  :class="{ 'bg-grey-lighten-5': editMode }"
                >
                  <!-- Handle para arrastar -->
                  <VIcon
                    v-if="editMode"
                    icon="tabler-grip-vertical"
                    class="drag-handle me-2 cursor-move"
                    color="grey"
                  />

                  <!-- Número da sequência -->
                  <VChip
                    :color="editMode ? 'warning' : 'primary'"
                    size="small"
                    class="me-3"
                  >
                    {{ element.spararmuseq }}
                  </VChip>

                  <!-- Informações do município -->
                  <div class="flex-grow-1">
                    <div class="text-body-2 font-weight-medium">
                      {{ element.desmun }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ element.desest }}
                      <VChip
                        v-if="element.lat && element.lon"
                        color="success"
                        size="x-small"
                        variant="text"
                        class="ms-1"
                      >
                        <VIcon icon="tabler-map-pin-check" size="12" />
                        GPS
                      </VChip>
                      <VChip
                        v-else
                        color="error"
                        size="x-small"
                        variant="text"
                        class="ms-1"
                      >
                        <VIcon icon="tabler-map-pin-x" size="12" />
                        Sem GPS
                      </VChip>
                    </div>
                  </div>

                  <!-- Botão remover (apenas em modo edição) -->
                  <VBtn
                    v-if="editMode"
                    icon="tabler-x"
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="removerMunicipio(index)"
                  />
                </div>
              </template>
            </draggable>

            <!-- Mensagem quando vazio -->
            <div v-if="municipios.length === 0" class="text-center pa-6 text-medium-emphasis">
              <VIcon icon="tabler-map-off" size="48" class="mb-3" />
              <p class="text-body-2">Nenhum município na rota</p>
              <p class="text-caption" v-if="editMode">Adicione municípios usando o campo acima</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Coluna Direita: Mapa -->
      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardTitle class="d-flex align-center flex-wrap gap-3">
            <div class="d-flex align-center">
              <VIcon icon="tabler-map-2" class="me-2" />
              Visualização no Mapa
              <VChip
                v-if="editMode"
                color="warning"
                size="small"
                class="ms-2"
              >
                Modo Edição
              </VChip>
              <VChip
                v-if="simulationActive"
                color="success"
                size="small"
                class="ms-2"
              >
                <VIcon icon="tabler-route" size="16" class="me-1" />
                Simulação Ativa
              </VChip>
            </div>

            <VSpacer />

            <!-- Autocomplete de Pacotes + Botão Simular -->
            <div v-if="!editMode" class="d-flex align-center gap-2" style="min-width: 350px;">
              <VAutocomplete
                v-model="selectedPacote"
                v-model:search="searchPacote"
                :items="pacotesOptions"
                :loading="loadingPacotes"
                item-title="label"
                item-value="codpac"
                return-object
                placeholder="Buscar pacote..."
                density="compact"
                variant="outlined"
                clearable
                hide-no-data
                hide-details
                :disabled="simulationActive"
                @update:search="fetchPacotesAutocomplete"
                style="flex: 1; min-width: 220px;"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-package" size="20" />
                </template>
              </VAutocomplete>

              <VBtn
                v-if="!simulationActive"
                color="success"
                :disabled="!selectedPacote || loadingSimulation"
                :loading="loadingSimulation"
                @click="handleSimulate"
                size="small"
              >
                <VIcon icon="tabler-route" class="me-1" />
                Simular
              </VBtn>

              <VBtn
                v-else
                color="error"
                @click="handleStopSimulation"
                size="small"
              >
                <VIcon icon="tabler-square" class="me-1" />
                Parar
              </VBtn>
            </div>
          </VCardTitle>
          <VCardText class="pa-0">
            <div
              ref="mapContainer"
              style="height: 700px; width: 100%;"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Loading -->
    <div v-if="loading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>
  </div>
</template>


<style scoped>
.cursor-move {
  cursor: move !important;
}

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

.font-mono {
  font-family: 'Courier New', Courier, monospace;
}

.drag-handle {
  cursor: grab !important;
}

.drag-handle:active {
  cursor: grabbing !important;
}

.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}

.border-b:last-child {
  border-bottom: none;
}

/* Leaflet custom marker style */
:deep(.custom-marker) {
  background: transparent !important;
  border: none !important;
}
</style>