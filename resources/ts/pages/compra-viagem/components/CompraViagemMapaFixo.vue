<script setup lang="ts">
// @ts-nocheck - Leaflet type incompatibilities (known @types/leaflet issue)
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { usePracasPedagio } from '@/composables/usePracasPedagio'
import { $api } from '@/utils/api'
import type { CompraViagemFormData } from '../types'

// ============================================================================
// TYPES
// ============================================================================

interface MapMarker {
  id: string
  lat: number
  lon: number
  tipo: 'municipio' | 'pedagio' | 'pedagio_incerto' | 'origem' | 'destino' | 'entrega_inicio' | 'entrega_fim' | 'entrega_intermediaria'
  label: string
  sequencia?: number
  popup?: string
  matchIncerto?: boolean
  totalMatches?: number
}

// Debounce helper
let updateDebounceTimer: ReturnType<typeof setTimeout> | null = null
const DEBOUNCE_MS = 300

// ============================================================================
// PROPS & EMITS
// ============================================================================

const props = defineProps<{
  formData: CompraViagemFormData
}>()

// ============================================================================
// STATE
// ============================================================================

const mapContainer = ref<HTMLElement | null>(null)
// IMPORTANTE: Usar shallowRef para objetos Leaflet!
// Vue's Proxy interfere com os métodos internos do Leaflet causando erros como:
// "Cannot read properties of undefined (reading '_leaflet_pos')"
const map = shallowRef<L.Map | null>(null)
const markersLayer = shallowRef<L.LayerGroup | null>(null)
const routeLayer = shallowRef<L.LayerGroup | null>(null)

// Estatísticas
const distanciaTotal = ref(0)
const tempoEstimado = ref(0)

// Estado do mapa
const isMapReady = ref(false)
const isLoadingRoute = ref(false)
const routeError = ref<string | null>(null)

// Composable para praças de pedágio ANTT (banco de dados)
const {
  loading: loadingPracasANTT,
  pracas: pracasANTT,
  loadAndDisplayPracas,
  removePracasFromMap
} = usePracasPedagio()

// Estado para controlar exibição de praças ANTT
const mostrarPracasANTT = ref(false)

// ============================================================================
// COLORS - Compra Viagem Theme (Pink/Green)
// ============================================================================

const CV_COLORS = {
  // Municípios da rota - Rosa/Magenta
  municipio: '#E91E63',
  municipioSecondary: '#F48FB1',

  // Praças de pedágio - Verde (match único/certo)
  pedagio: '#10B981',
  pedagioSecondary: '#34D399',

  // Praças de pedágio - Laranja (match incerto/múltiplos)
  pedagioIncerto: '#F97316',
  pedagioIncertoSecondary: '#FB923C',

  // Entregas
  entregaInicio: '#22C55E',   // Verde - primeira entrega
  entregaFim: '#EF4444',      // Vermelho - última entrega
  entregaIntermediaria: '#FF9800', // Laranja - intermediárias

  // Rota desenhada - Rosa
  route: '#E91E63',
  routeSecondary: '#F06292',

  // Estados
  error: '#EF4444',
  warning: '#F59E0B',
  success: '#10B981',
  info: '#3B82F6'
}

// ============================================================================
// COMPUTED
// ============================================================================

const estatisticas = computed(() => {
  const municipios = props.formData.rota.municipios.length
  const entregas = props.formData.pacote.entregas_com_gps?.length || 0
  const pedagios = props.formData.preco.pracas?.length || 0
  const valorTotal = props.formData.preco.valor || 0

  return {
    municipios,
    entregas,
    pedagios,
    valorTotal,
    distanciaKm: distanciaTotal.value,
    tempoMin: tempoEstimado.value,
    temRotaSelecionada: !!props.formData.rota.rota,
    temPrecoCalculado: props.formData.preco.calculado
  }
})

const hasDataToShow = computed(() => {
  return props.formData.rota.municipios.length > 0 ||
         (props.formData.preco.pracas?.length || 0) > 0 ||
         (props.formData.pacote.entregas_com_gps?.length || 0) > 0
})

// ============================================================================
// METHODS - MAP INITIALIZATION
// ============================================================================

const initMap = async () => {
  if (!mapContainer.value) return

  // Criar mapa centralizado no Brasil
  map.value = L.map(mapContainer.value, {
    center: [-14.2350, -51.9253],
    zoom: 4,
    zoomControl: true,
    attributionControl: true
  })

  // Adicionar tile layer OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map.value)

  // Criar layers
  markersLayer.value = L.layerGroup().addTo(map.value)
  routeLayer.value = L.layerGroup().addTo(map.value)

  isMapReady.value = true
  console.log('🗺️ Compra Viagem Mapa inicializado')
}

// ============================================================================
// METHODS - MAP UPDATE
// ============================================================================

// Guard para evitar atualizações concorrentes
let isUpdatingMap = false

const atualizarMapa = async () => {
  // Evitar atualizações concorrentes
  if (isUpdatingMap) {
    console.log('🗺️ Ignorando atualização concorrente do mapa')
    return
  }

  if (!map.value || !markersLayer.value || !routeLayer.value) return

  isUpdatingMap = true

  try {
    // Parar qualquer animação em andamento e desabilitar interações temporariamente
    map.value.stop()
    map.value.scrollWheelZoom.disable()
    map.value.dragging.disable()

    // Limpar layers
    markersLayer.value.clearLayers()
    routeLayer.value.clearLayers()
    routeError.value = null

    const markers: MapMarker[] = []
    const waypoints: L.LatLng[] = []

    // === 1. MUNICÍPIOS DA ROTA ===
    const municipios = props.formData.rota.municipios.filter(m => m.lat && m.lon)

    municipios.forEach((mun, index) => {
      if (!mun.lat || !mun.lon) return

      const isFirst = index === 0
      const isLast = index === municipios.length - 1

      markers.push({
        id: `mun-${mun.cdibge}`,
        lat: mun.lat,
        lon: mun.lon,
        tipo: isFirst ? 'origem' : (isLast ? 'destino' : 'municipio'),
        label: mun.desMun,
        sequencia: index + 1,
        popup: `<strong>${index + 1}. ${mun.desMun}</strong><br>` +
               `${mun.desEst}<br>` +
               `IBGE: ${mun.cdibge}`
      })

      waypoints.push(L.latLng(mun.lat, mun.lon))
    })

    // === 2. ENTREGAS DO PACOTE (primeira, intermediárias e última) ===
    const entregas = props.formData.pacote.entregas_com_gps || []
    const totalEntregas = entregas.length

    entregas.forEach((entrega, index) => {
      if (entrega.lat && entrega.lon) {
        const isFirst = index === 0
        const isLast = index === totalEntregas - 1
        const isIntermediaria = !isFirst && !isLast

        let tipo: MapMarker['tipo'] = 'entrega_inicio'
        if (isLast) tipo = 'entrega_fim'
        else if (isIntermediaria) tipo = 'entrega_intermediaria'

        markers.push({
          id: `entrega-${entrega.numseqped}`,
          lat: entrega.lat,
          lon: entrega.lon,
          tipo,
          label: entrega.razcli,
          popup: `<div style="min-width: 150px;">` +
                 `<strong style="color: ${isFirst ? CV_COLORS.entregaInicio : (isLast ? CV_COLORS.entregaFim : CV_COLORS.entregaIntermediaria)};">` +
                 `📦 ${isFirst ? 'Primeira' : (isLast ? 'Última' : 'Intermediária')} Entrega</strong><br>` +
                 `<strong>${entrega.razcli}</strong><br>` +
                 `${entrega.cidcli} - ${entrega.sigufs}` +
                 `</div>`
        })

        // Adicionar primeira e última entrega aos waypoints para roteirização
        if (isFirst || isLast) {
          waypoints.push(L.latLng(entrega.lat, entrega.lon))
        }
      }
    })

    // === 3. PRAÇAS DE PEDÁGIO ===
    const pracas = props.formData.preco.pracas || []
    console.log('🗺️ CompraViagemMapa: Processando praças', {
      totalPracas: pracas.length,
      pracasComMatchIncerto: pracas.filter((p: any) => p.match_incerto).length,
    })

    pracas.forEach((praca: any, index) => {
      const matchIncerto = praca.match_incerto === true
      const totalMatches = praca.total_matches || 1

      // Debug: log cada praça para ver os dados
      console.log(`🗺️ Praça ${index + 1}:`, {
        nome: praca.nome || praca.praca,
        matchIncerto,
        totalMatches,
        lat: praca.lat,
        lon: praca.lon,
      })

      if (praca.lat && praca.lon) {
        const corPraca = matchIncerto ? CV_COLORS.pedagioIncerto : CV_COLORS.pedagio

        markers.push({
          id: `pedagio-${praca.id || index}`,
          lat: praca.lat,
          lon: praca.lon,
          tipo: matchIncerto ? 'pedagio_incerto' : 'pedagio',
          label: praca.nome || praca.praca || 'Pedágio',
          matchIncerto: matchIncerto,
          totalMatches: totalMatches,
          popup: `<div style="min-width: 180px;">` +
                 `<strong style="color: ${corPraca};">` +
                 `${matchIncerto ? '⚠️' : '🚧'} Pedágio</strong>` +
                 `${matchIncerto ? `<br><span style="color: ${CV_COLORS.warning}; font-size: 11px;">` +
                 `(${totalMatches} possíveis matches)</span>` : ''}<br>` +
                 `<strong>${praca.nome || praca.praca || 'N/A'}</strong><br>` +
                 `${praca.rodovia || ''} ${praca.km ? `km ${praca.km}` : ''}<br>` +
                 `${praca.cidade || praca.municipio_antt || ''} ${praca.uf || praca.uf_antt ? `- ${praca.uf || praca.uf_antt}` : ''}<br>` +
                 `<span style="color: ${corPraca}; font-weight: bold;">` +
                 `R$ ${(praca.valor || 0).toFixed(2)}</span>` +
                 `</div>`
        })
      }
    })

    // === 4. RENDERIZAR MARCADORES ===
    const markersPorTipo = {
      municipio: markers.filter(m => m.tipo === 'municipio' || m.tipo === 'origem' || m.tipo === 'destino').length,
      pedagio: markers.filter(m => m.tipo === 'pedagio').length,
      pedagioIncerto: markers.filter(m => m.tipo === 'pedagio_incerto').length,
      entrega: markers.filter(m => m.tipo.startsWith('entrega')).length,
    }
    console.log('🗺️ CompraViagemMapa: Markers a renderizar', {
      total: markers.length,
      porTipo: markersPorTipo,
    })

    markers.forEach((marker) => {
      const icon = criarIconeCustomizado(marker)
      const leafletMarker = L.marker([marker.lat, marker.lon], { icon })

      if (marker.popup) {
        leafletMarker.bindPopup(marker.popup)
      }

      leafletMarker.addTo(markersLayer.value!)
    })

    // === 5. CALCULAR E DESENHAR ROTA ===
    if (waypoints.length >= 2 && municipios.length > 0) {
      console.log(`🗺️ Calculando rota Compra Viagem com ${waypoints.length} waypoints`)
      await calcularRota(waypoints)
    }

    // === 6. CARREGAR PRAÇAS ANTT (se toggle ativo) ===
    if (mostrarPracasANTT.value) {
      await loadPracasANTT()
    }

    // === 7. AJUSTAR ZOOM (com safety check) ===
    if (markers.length > 0 && map.value && isMapReady.value) {
      try {
        // Parar animações antes de ajustar zoom
        map.value.stop()
        const bounds = L.latLngBounds(markers.map(m => [m.lat, m.lon]))
        map.value.fitBounds(bounds, { padding: [50, 50], animate: false, maxZoom: 15 })
      } catch (error) {
        console.warn('Erro ao ajustar zoom:', error)
      }
    }

  } finally {
    isUpdatingMap = false
    // Re-habilitar interações do mapa com pequeno delay
    // para evitar eventos de scroll que já estavam na fila
    if (map.value) {
      setTimeout(() => {
        if (map.value && isMapReady.value) {
          map.value.scrollWheelZoom.enable()
          map.value.dragging.enable()
        }
      }, 100)
    }
  }
}

// ============================================================================
// METHODS - CUSTOM MARKERS
// ============================================================================

const criarIconeCustomizado = (marker: MapMarker): L.DivIcon => {
  let bgColor = CV_COLORS.municipio
  let borderColor = 'white'
  let textColor = 'white'
  let size = 32
  let borderWidth = 3
  let opacity = 1.0

  switch (marker.tipo) {
    case 'origem':
      bgColor = CV_COLORS.municipio
      size = 36
      borderWidth = 4
      break

    case 'destino':
      bgColor = '#9C27B0' // Purple for destination
      size = 36
      borderWidth = 4
      break

    case 'municipio':
      bgColor = CV_COLORS.municipioSecondary
      size = 28
      break

    case 'pedagio':
      bgColor = CV_COLORS.pedagio
      textColor = 'white'
      size = 28
      break

    case 'pedagio_incerto':
      bgColor = CV_COLORS.pedagioIncerto
      textColor = 'white'
      size = 28
      borderColor = '#FFF'
      break

    case 'entrega_inicio':
      bgColor = CV_COLORS.entregaInicio
      size = 34
      borderWidth = 4
      break

    case 'entrega_fim':
      bgColor = CV_COLORS.entregaFim
      size = 34
      borderWidth = 4
      break

    case 'entrega_intermediaria':
      bgColor = CV_COLORS.entregaIntermediaria
      size = 26
      opacity = 0.6
      break
  }

  let displayText = ''
  switch (marker.tipo) {
    case 'pedagio':
      displayText = '₱'
      break
    case 'pedagio_incerto':
      displayText = '?'
      break
    case 'entrega_inicio':
      displayText = '📦'
      break
    case 'entrega_fim':
      displayText = '🏁'
      break
    case 'entrega_intermediaria':
      displayText = '•'
      break
    default:
      displayText = String(marker.sequencia || '')
  }

  return L.divIcon({
    html: `
      <div style="
        background: ${bgColor};
        color: ${textColor};
        border: ${borderWidth}px solid ${borderColor};
        border-radius: 50%;
        width: ${size}px;
        height: ${size}px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: ${size > 32 ? 14 : 12}px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: transform 0.2s;
        opacity: ${opacity};
      ">
        ${displayText}
      </div>
    `,
    className: 'cv-custom-marker',
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2]
  })
}

// ============================================================================
// METHODS - ROUTING
// ============================================================================

const calcularRota = async (waypoints: L.LatLng[]) => {
  if (waypoints.length < 2 || !routeLayer.value) return

  isLoadingRoute.value = true
  routeError.value = null

  try {
    console.log('🗺️ Calculando rota Compra Viagem com MapService para', waypoints.length, 'waypoints')

    // Converter waypoints para formato MapService [lat, lon]
    const mapServiceWaypoints = waypoints.map(w => [w.lat, w.lng] as [number, number])

    // Chamar MapService usando $api (que inclui token automaticamente)
    const result = await $api('/map/route', {
      method: 'POST',
      body: {
        waypoints: mapServiceWaypoints,
        options: {
          use_cache: true,
          fallback_to_straight: true
        }
      }
    })

    if (result.success && result.data?.coordinates) {
      console.log(`✅ Rota Compra Viagem calculada: ${result.data.distance_km}km via ${result.data.provider}`)
      console.log(`💾 Cache: ${result.data.cached ? 'HIT' : 'MISS'}`)

      // Atualizar estatísticas
      distanciaTotal.value = Number(result.data.distance_km) || 0
      tempoEstimado.value = Number(result.data.duration_minutes) || 0

      // Converter coordenadas para Leaflet
      const routeLatLngs = result.data.coordinates.map((coord: [number, number]) =>
        L.latLng(coord[0], coord[1])
      )

      // Desenhar rota ROSA (tema Compra Viagem)
      const routePolyline = L.polyline(routeLatLngs, {
        color: CV_COLORS.route,
        weight: 5,
        opacity: 0.8,
        lineCap: 'round',
        lineJoin: 'round'
      })

      routePolyline.addTo(routeLayer.value!)

      // Limpar erro se existia
      routeError.value = null

    } else {
      console.warn('⚠️ MapService falhou, usando linha reta')
      desenharLinhaReta(waypoints)
    }

  } catch (error: any) {
    console.error('❌ Erro ao calcular rota Compra Viagem:', error)
    desenharLinhaReta(waypoints)
  } finally {
    isLoadingRoute.value = false
  }
}

const desenharLinhaReta = (waypoints: L.LatLng[]) => {
  if (!routeLayer.value) return

  const fallbackPolyline = L.polyline(waypoints, {
    color: CV_COLORS.route,
    weight: 3,
    opacity: 0.5,
    dashArray: '10, 10'
  })

  fallbackPolyline.addTo(routeLayer.value)

  console.log('📍 Linha reta desenhada (fallback)')
}

// ============================================================================
// METHODS - ANTT TOLL PLAZAS
// ============================================================================

const loadPracasANTT = async () => {
  if (!map.value || !mostrarPracasANTT.value) return

  try {
    console.log('🏛️ Carregando praças ANTT...')

    const pracasEncontradas = await loadAndDisplayPracas(
      map.value,
      {
        color: '#6B7280', // Cinza para diferenciar das praças Compra Viagem (verdes)
        showPopup: true,
        zIndex: 500
      }
    )

    console.log(`✅ ${pracasEncontradas.length} praças ANTT exibidas`)
  } catch (error) {
    console.error('❌ Erro ao carregar praças ANTT:', error)
  }
}

const togglePracasANTT = async () => {
  mostrarPracasANTT.value = !mostrarPracasANTT.value

  if (mostrarPracasANTT.value) {
    await loadPracasANTT()
  } else {
    removePracasFromMap()
    console.log('🏛️ Praças ANTT ocultadas')
  }
}

// ============================================================================
// WATCHERS
// ============================================================================

// Atualizar mapa quando formData mudar (com debounce para evitar múltiplas atualizações)
watch(() => props.formData, async () => {
  // Cancelar timer anterior se existir
  if (updateDebounceTimer) {
    clearTimeout(updateDebounceTimer)
  }

  // Debounce para evitar múltiplas atualizações rápidas
  updateDebounceTimer = setTimeout(async () => {
    await nextTick()
    if (isMapReady.value && map.value) {
      atualizarMapa()
    }
  }, DEBOUNCE_MS)
}, { deep: true })

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(async () => {
  await nextTick()
  await initMap()

  // Atualizar mapa se já houver dados
  if (hasDataToShow.value) {
    atualizarMapa()
  }
})

onBeforeUnmount(() => {
  // Remover praças ANTT antes de destruir o mapa
  removePracasFromMap()

  if (map.value) {
    map.value.remove()
    map.value = null
  }
})

// ============================================================================
// EXPOSE
// ============================================================================

defineExpose({
  togglePracasANTT,
  mostrarPracasANTT,
  pracasANTT,
  loadingPracasANTT,
  routeError
})
</script>

<template>
  <div class="cv-mapa-container">
    <!-- Header com Controles -->
    <div class="map-header">
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-map" size="20" color="primary" />
        <span class="text-body-1 font-weight-medium">Visualização da Rota</span>
      </div>

      <div class="d-flex align-center gap-2">
        <!-- Toggle Praças ANTT -->
        <VBtn
          :variant="mostrarPracasANTT ? 'flat' : 'outlined'"
          :color="mostrarPracasANTT ? 'secondary' : 'default'"
          size="small"
          :loading="loadingPracasANTT"
          @click="togglePracasANTT"
        >
          <VIcon icon="tabler-building" start size="16" />
          {{ mostrarPracasANTT ? 'Ocultar ANTT' : 'Mostrar ANTT' }}
        </VBtn>
      </div>
    </div>

    <!-- Alerta de Erro de Roteamento -->
    <VAlert
      v-if="routeError"
      type="error"
      variant="tonal"
      class="mx-3 mb-2"
      closable
      @click:close="routeError = null"
    >
      <template #prepend>
        <VIcon icon="tabler-route-off" />
      </template>
      <div class="text-body-2">
        <strong>Erro no roteamento:</strong> {{ routeError }}
      </div>
      <template #append>
        <VBtn
          variant="text"
          size="small"
          @click="atualizarMapa"
        >
          Tentar Novamente
        </VBtn>
      </template>
    </VAlert>

    <!-- Estatísticas Flutuantes -->
    <VCard
      v-if="estatisticas.temRotaSelecionada || estatisticas.entregas > 0"
      class="stats-card"
      variant="elevated"
      elevation="4"
    >
      <VCardText class="pa-3">
        <VRow dense>
          <!-- Municípios -->
          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">Municípios</div>
              <div class="text-h6" :style="{ color: CV_COLORS.municipio }">
                {{ estatisticas.municipios }}
              </div>
            </div>
          </VCol>

          <!-- Entregas -->
          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">Entregas</div>
              <div class="text-h6" :style="{ color: CV_COLORS.entregaInicio }">
                {{ estatisticas.entregas }}
              </div>
            </div>
          </VCol>

          <!-- Praças -->
          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">Pedágios</div>
              <div class="text-h6" :style="{ color: CV_COLORS.pedagio }">
                {{ estatisticas.pedagios }}
              </div>
            </div>
          </VCol>

          <!-- Distância -->
          <VCol v-if="distanciaTotal > 0" cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">Distância</div>
              <div class="text-h6 text-info">
                {{ distanciaTotal.toFixed(0) }} km
              </div>
            </div>
          </VCol>

          <!-- Valor Total -->
          <VCol v-if="estatisticas.temPrecoCalculado && estatisticas.valorTotal > 0" cols="12">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">Valor Total</div>
              <div class="text-h5" :style="{ color: CV_COLORS.pedagio }">
                R$ {{ estatisticas.valorTotal.toFixed(2) }}
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading Indicator -->
    <div v-if="isLoadingRoute" class="loading-overlay">
      <VProgressCircular indeterminate color="primary" size="48" />
      <div class="text-body-2 mt-2">Calculando rota...</div>
    </div>

    <!-- Container do Mapa -->
    <div
      ref="mapContainer"
      class="map-container"
    />

    <!-- Legenda -->
    <VCard class="legend-card" variant="flat">
      <VCardText class="pa-2">
        <div class="d-flex flex-wrap gap-3 justify-center">
          <!-- Origem -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: CV_COLORS.municipio }"
            >1</div>
            <span class="text-caption">Origem</span>
          </div>

          <!-- Destino -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: '#9C27B0' }"
            >N</div>
            <span class="text-caption">Destino</span>
          </div>

          <!-- Primeira Entrega -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: CV_COLORS.entregaInicio }"
            >📦</div>
            <span class="text-caption">1ª Entrega</span>
          </div>

          <!-- Última Entrega -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: CV_COLORS.entregaFim }"
            >🏁</div>
            <span class="text-caption">Última</span>
          </div>

          <!-- Pedágio -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: CV_COLORS.pedagio }"
            >₱</div>
            <span class="text-caption">Pedágios</span>
          </div>

          <!-- Pedágio Incerto -->
          <div class="d-flex align-center gap-1">
            <div
              class="legend-marker"
              :style="{ background: CV_COLORS.pedagioIncerto }"
            >?</div>
            <span class="text-caption">Incerto</span>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Placeholder quando não há dados -->
    <div v-if="!hasDataToShow" class="empty-state">
      <VIcon icon="tabler-map-search" size="64" color="disabled" />
      <div class="text-body-1 text-medium-emphasis mt-4">
        Selecione um pacote para visualizar no mapa
      </div>
      <div class="text-caption text-disabled mt-1">
        Os municípios, entregas e praças de pedágio serão exibidos automaticamente
      </div>
    </div>
  </div>
</template>

<style scoped>
.cv-mapa-container {
  position: relative;
  height: calc(100vh - 200px);
  min-height: 500px;
  max-height: 800px;
  display: flex;
  flex-direction: column;
  border-radius: 8px;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
}

.map-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: rgb(var(--v-theme-surface));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  flex-shrink: 0;
}

.map-container {
  flex: 1;
  width: 100%;
  min-height: 0;
}

.stats-card {
  position: absolute;
  top: 70px;
  right: 16px;
  z-index: 1000;
  min-width: 200px;
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(8px);
}

.legend-card {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(8px);
}

.legend-marker {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 10px;
  font-weight: bold;
  border: 2px solid white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.loading-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1001;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 12px;
  backdrop-filter: blur(8px);
}

.empty-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  padding: 32px;
}

/* Dark mode support */
.v-theme--dark {
  .stats-card,
  .legend-card,
  .loading-overlay {
    background: rgba(var(--v-theme-surface), 0.95) !important;
  }
}

/* Responsive */
@media (max-width: 960px) {
  .cv-mapa-container {
    height: 400px;
    min-height: 350px;
    max-height: 500px;
  }

  .stats-card {
    top: 60px;
    right: 8px;
    min-width: 160px;
  }

  .legend-card {
    bottom: 8px;
  }
}

/* Custom marker - remover estilo padrão do Leaflet DivIcon */
:deep(.cv-custom-marker) {
  background: transparent !important;
  border: none !important;
}

/* Custom marker hover effect */
:deep(.cv-custom-marker:hover > div) {
  transform: scale(1.1);
}
</style>
