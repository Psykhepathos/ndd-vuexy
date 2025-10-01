<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'

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

// Estados reativos
const rota = ref<SemPararRota | null>(null)
const municipios = ref<RotaMunicipio[]>([])
const loading = ref(false)
const saving = ref(false)
const editMode = ref(false)
const map = ref<google.maps.Map | null>(null)
const mapContainer = ref<HTMLElement>()
const markers = ref<google.maps.Marker[]>([])
const polyline = ref<google.maps.Polyline | null>(null)
const distanciaTotal = ref(0)

// Controle de sincronização
const isUpdatingMap = ref(false)
const updateMapDebounceTimer = ref<number | null>(null)
const geocodingQueue = ref<Set<number>>(new Set()) // Track municípios being geocoded

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
    const response = await fetch(`http://localhost:8002/api/semparar-rotas/${rotaId.value}/municipios`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success) {
      rota.value = data.data.rota
      municipios.value = data.data.municipios || []

      console.log('✅ Rota carregada:', rota.value)
      console.log('✅ Municípios carregados:', municipios.value.length, municipios.value)

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

// Buscar municípios para autocomplete
const fetchMunicipios = async (search: string = '') => {
  if (search.length < 2 && search !== '') return

  loadingMunicipios.value = true

  try {
    const params = new URLSearchParams({
      search: search
    })

    const response = await fetch(`http://localhost:8002/api/semparar-rotas/municipios?${params}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success && data.data) {
      municipiosOptions.value = data.data.map((m: any) => ({
        title: `${m.desmun} - ${m.desest}`,
        value: {
          codmun: m.codmun,
          codest: m.codest,
          desmun: m.desmun,
          desest: m.desest,
          cdibge: m.cdibge,
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

// Inicializar mapa Google Maps
const initMap = async () => {
  console.log('🗺️ initMap() chamado')
  console.log('📦 mapContainer.value:', mapContainer.value)
  console.log('🌍 google disponível?:', typeof google !== 'undefined')
  console.log('🗺️ google.maps disponível?:', typeof google !== 'undefined' && typeof google.maps !== 'undefined')

  if (!mapContainer.value) {
    console.error('❌ mapContainer não encontrado!')
    return
  }

  // Aguardar Google Maps carregar
  if (typeof google === 'undefined' || !google.maps) {
    console.log('⏳ Aguardando Google Maps carregar...')
    setTimeout(initMap, 500)
    return
  }

  console.log('✅ Criando mapa Google Maps...')

  // Criar mapa centrado no Brasil
  map.value = new google.maps.Map(mapContainer.value, {
    center: { lat: -14.2350, lng: -51.9253 },
    zoom: 4,
    mapTypeControl: true,
    streetViewControl: false,
    fullscreenControl: true
  })

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
    markers.value.forEach(marker => marker.setMap(null))
    markers.value = []

    // Limpar polyline antiga
    if (polyline.value) {
      polyline.value.setMap(null)
      polyline.value = null
    }

    const path: google.maps.LatLngLiteral[] = []
    const bounds = new google.maps.LatLngBounds()

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

    // Criar marcadores apenas para municípios com coordenadas válidas
    let validMunicipios = 0
    municipios.value.forEach((municipio, index) => {
      if (isValidCoordinate(municipio.lat, municipio.lon)) {
        validMunicipios++
        const position = { lat: Number(municipio.lat), lng: Number(municipio.lon) }
        path.push(position)
        bounds.extend(position)

        // Cor do marcador baseado no status
        let fillColor = '#1976d2' // Azul padrão
        if (editMode.value) fillColor = '#ff9800' // Laranja em modo edição
        if (municipio.geocodingStatus === 'error') fillColor = '#f44336' // Vermelho se erro

        // Criar marcador numerado
        const marker = new google.maps.Marker({
          position,
          map: map.value!,
          title: `${municipio.spararmuseq}. ${municipio.desmun}`,
          label: {
            text: String(municipio.spararmuseq),
            color: 'white',
            fontSize: '12px',
            fontWeight: 'bold'
          },
          icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12,
            fillColor,
            fillOpacity: 1,
            strokeColor: 'white',
            strokeWeight: 2
          }
        })

        // InfoWindow com mais informações de debug
        const infoWindow = new google.maps.InfoWindow({
          content: `
            <div style="padding: 8px; font-family: sans-serif;">
              <h6 style="margin: 0 0 8px 0;"><strong>${municipio.desmun}</strong></h6>
              <p style="margin: 2px 0; font-size: 13px;">Estado: <strong>${municipio.desest}</strong></p>
              <p style="margin: 2px 0; font-size: 13px;">Sequência: <strong>${municipio.spararmuseq}</strong></p>
              <p style="margin: 2px 0; font-size: 13px;">IBGE: <strong>${municipio.cdibge}</strong></p>
              <p style="margin: 2px 0; font-size: 12px; color: #666;">Lat: ${municipio.lat?.toFixed(6)}</p>
              <p style="margin: 2px 0; font-size: 12px; color: #666;">Lon: ${municipio.lon?.toFixed(6)}</p>
              ${municipio.geocodingStatus === 'error' ? `<p style="margin: 4px 0; color: #f44336; font-size: 12px;">⚠️ ${municipio.geocodingError}</p>` : ''}
            </div>
          `
        })

        marker.addListener('click', () => {
          infoWindow.open(map.value!, marker)
        })

        markers.value.push(marker)
      }
    })

    addDebugLog('info', 'MAP_UPDATE', `Marcadores criados`, {
      total: municipios.value.length,
      validos: validMunicipios,
      invalidos: municipios.value.length - validMunicipios
    })

    // Calcular rota real usando Google Directions API com cache
    if (path.length > 1) {
      addDebugLog('info', 'ROUTING', `Calculando rota com ${path.length} waypoints`)
      await calculateAndDrawRoute(path, bounds)
    } else if (path.length === 1) {
      // Se apenas 1 ponto, centralizar nele
      map.value.setCenter(path[0])
      map.value.setZoom(10)
      addDebugLog('warn', 'ROUTING', 'Apenas 1 município válido, não é possível calcular rota')
    } else {
      addDebugLog('error', 'ROUTING', 'Nenhum município com coordenadas válidas')
    }

  } catch (error: any) {
    addDebugLog('error', 'MAP_UPDATE', 'Erro ao atualizar mapa', error)
  } finally {
    isUpdatingMap.value = false
  }
}

// Calcular e desenhar rota usando Google Directions API com cache
const calculateAndDrawRoute = async (waypoints: google.maps.LatLngLiteral[], bounds: google.maps.LatLngBounds) => {
  const startTime = performance.now()

  try {
    addDebugLog('info', 'ROUTING', `Solicitando cálculo de rota`, {
      waypoints: waypoints.length,
      primeiroPonto: waypoints[0],
      ultimoPonto: waypoints[waypoints.length - 1]
    })

    const response = await fetch('http://localhost:8002/api/routing/calculate', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        waypoints: waypoints.map(w => ({ lat: w.lat, lng: w.lng }))
      })
    })

    const data = await response.json()
    const endTime = performance.now()
    const duration = ((endTime - startTime) / 1000).toFixed(2)

    if (data.success && data.data) {
      debugStats.value.cachedGeocodes += data.data.cached_segments || 0

      addDebugLog('success', 'ROUTING', `Rota calculada em ${duration}s`, {
        cached: data.data.cached_segments,
        novos: data.data.new_segments,
        distanciaKm: (data.data.total_distance_meters / 1000).toFixed(2),
        duracaoMinutos: (data.data.total_duration_seconds / 60).toFixed(0)
      })

      // Decodificar polylines e desenhar no mapa
      const allCoords: google.maps.LatLngLiteral[] = []

      for (const polylineEncoded of data.data.polylines) {
        const decoded = google.maps.geometry.encoding.decodePath(polylineEncoded)
        decoded.forEach(latLng => {
          allCoords.push({ lat: latLng.lat(), lng: latLng.lng() })
        })
      }

      if (allCoords.length === 0) {
        addDebugLog('error', 'ROUTING', 'Polyline decodificado está vazio')
        drawStraightLine(waypoints, bounds)
        return
      }

      // Desenhar polyline com a rota real
      polyline.value = new google.maps.Polyline({
        path: allCoords,
        geodesic: false,
        strokeColor: editMode.value ? '#ff9800' : '#1976d2',
        strokeOpacity: 0.8,
        strokeWeight: 4
      })

      polyline.value.setMap(map.value!)

      // Atualizar distância total
      distanciaTotal.value = data.data.total_distance_meters / 1000

      // Ajustar zoom
      map.value!.fitBounds(bounds)

      addDebugLog('success', 'ROUTING', `Polyline desenhada com ${allCoords.length} pontos`)
    } else {
      addDebugLog('warn', 'ROUTING', 'API retornou erro, usando linha reta', data)
      drawStraightLine(waypoints, bounds)
    }
  } catch (error: any) {
    const endTime = performance.now()
    const duration = ((endTime - startTime) / 1000).toFixed(2)

    addDebugLog('error', 'ROUTING', `Erro ao calcular rota após ${duration}s`, error)
    drawStraightLine(waypoints, bounds)
  }
}

// Desenhar linha reta como fallback
const drawStraightLine = (waypoints: google.maps.LatLngLiteral[], bounds: google.maps.LatLngBounds) => {
  polyline.value = new google.maps.Polyline({
    path: waypoints,
    geodesic: true,
    strokeColor: editMode.value ? '#ff9800' : '#1976d2',
    strokeOpacity: 0.7,
    strokeWeight: 3
  })

  polyline.value.setMap(map.value!)
  calcularDistanciaTotal(waypoints)
  map.value!.fitBounds(bounds)
}

// Calcular distância total da rota
const calcularDistanciaTotal = (path: google.maps.LatLngLiteral[]) => {
  let total = 0
  for (let i = 0; i < path.length - 1; i++) {
    const from = new google.maps.LatLng(path[i].lat, path[i].lng)
    const to = new google.maps.LatLng(path[i + 1].lat, path[i + 1].lng)
    total += google.maps.geometry.spherical.computeDistanceBetween(from, to)
  }
  distanciaTotal.value = total / 1000 // Converter para km
}

// Função para geocoding usando código IBGE via Google Maps
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

    const response = await fetch('http://localhost:8002/api/geocoding/lote', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        municipios: [{
          cdibge: codigoIBGE,
          desmun: nomeMunicipio,
          desest: nomeEstado
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

  addDebugLog('info', 'EDIT', 'Adicionando novo município', {
    municipio: selectedMunicipio.value.desmun,
    posicao: municipios.value.length + 1
  })

  const novoMunicipio: RotaMunicipio = {
    spararmuseq: municipios.value.length + 1,
    codmun: selectedMunicipio.value.codmun,
    codest: selectedMunicipio.value.codest,
    desmun: selectedMunicipio.value.desmun,
    desest: selectedMunicipio.value.desest,
    cdibge: selectedMunicipio.value.cdibge,
    lat: sanitizeCoordinate(selectedMunicipio.value.lat),
    lon: sanitizeCoordinate(selectedMunicipio.value.lon),
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
  if (!rota.value) return

  saving.value = true

  try {
    const payload = {
      nome: rota.value.desspararrot,
      tempo_viagem: rota.value.tempoviagem,
      flg_cd: rota.value.flgcd,
      flg_retorno: rota.value.flgretorno,
      municipios: municipios.value.map(m => ({
        cod_est: m.codest,
        cod_mun: m.codmun,
        des_est: m.desest,
        des_mun: m.desmun,
        cdibge: m.cdibge,
        sequencia: m.spararmuseq
      }))
    }

    const response = await fetch(`http://localhost:8002/api/semparar-rotas/${rotaId.value}`, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (data.success) {
      editMode.value = false
      await fetchRota() // Recarregar dados
    } else {
      alert('Erro ao salvar: ' + data.message)
    }
  } catch (error) {
    console.error('Erro ao salvar alterações:', error)
    alert('Erro ao salvar alterações')
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
  router.push('/rotas-semparar')
}

// Nota: Não usamos watch aqui pois updateMapMarkers já é chamado
// explicitamente em todas as operações (adicionar, remover, drag)

// Carregar Google Maps API
const loadGoogleMaps = (): Promise<void> => {
  return new Promise((resolve, reject) => {
    if (typeof google !== 'undefined' && google.maps) {
      resolve()
      return
    }

    const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || ''

    if (!GOOGLE_MAPS_API_KEY) {
      console.error('Google Maps API key não configurada')
      reject(new Error('Google Maps API key não configurada'))
      return
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=geometry,places`
    script.async = true
    script.defer = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Erro ao carregar Google Maps'))
    document.head.appendChild(script)
  })
}

// Lifecycle
onMounted(async () => {
  try {
    await loadGoogleMaps()
    console.log('✅ Google Maps API carregada com sucesso')
  } catch (error) {
    console.error('❌ Erro ao carregar Google Maps:', error)
  }

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
            {{ editMode ? 'Editar' : 'Visualizar' }} Rota SemParar
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
            <span>Painel de Debug - Rotas SemParar</span>
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
        {{ municipios.filter(m => !m.lat || !m.lon).map(m => m.desmun.trim()).join(', ') }}
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
              item-value="value"
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
          <VCardTitle>
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
</style>