<script setup lang="ts">
// @ts-nocheck - Leaflet type incompatibilities (known @types/leaflet issue)
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { usePracasPedagio } from '@/composables/usePracasPedagio'
import type { CompraViagemFormData, MapMarker } from '../types'

// Props
const props = defineProps<{
  formData: CompraViagemFormData
}>()

// State
const mapContainer = ref<HTMLElement | null>(null)
const map = ref<L.Map | null>(null)
const markersLayer = ref<L.LayerGroup | null>(null)
const routeLayer = ref<L.LayerGroup | null>(null)
const distanciaTotal = ref(0)

// Composable para praças de pedágio ANTT (banco de dados)
const {
  loading: loadingPracasANTT,
  pracas: pracasANTT,
  loadAndDisplayPracas,
  removePracasFromMap
} = usePracasPedagio()

// Estado para controlar exibição de praças ANTT
const mostrarPracasANTT = ref(true)

// Computed
const estatisticas = computed(() => {
  const municipios = props.formData.rota.municipios.length
  const entregas = props.formData.pacote.entregas_com_gps.length
  const pedagios = props.formData.preco.pracas.length

  return {
    municipios,
    entregas,
    pedagios,
    totalPontos: municipios + entregas,
    distanciaKm: distanciaTotal.value
  }
})

// Methods
const initMap = async () => {
  if (!mapContainer.value) return

  // Criar mapa centralizado no Brasil
  map.value = L.map(mapContainer.value, {
    center: [-14.2350, -51.9253],
    zoom: 4,
    zoomControl: true
  })

  // Adicionar tile layer OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map.value)

  // Criar layers
  markersLayer.value = L.layerGroup().addTo(map.value)
  routeLayer.value = L.layerGroup().addTo(map.value)

  console.log('🗺️ Mapa inicializado')
}

const atualizarMapa = async () => {
  if (!map.value || !markersLayer.value || !routeLayer.value) return

  // Limpar layers
  markersLayer.value.clearLayers()
  routeLayer.value.clearLayers()

  const markers: MapMarker[] = []
  const waypoints: L.LatLng[] = []

  // === 1. MUNICÍPIOS DA ROTA ===
  const municipios = props.formData.rota.municipios.filter(m => m.lat && m.lon)

  municipios.forEach((mun, index) => {
    if (!mun.lat || !mun.lon) return

    markers.push({
      id: `mun-${mun.cdibge}`,
      lat: mun.lat,
      lon: mun.lon,
      tipo: 'municipio',
      label: mun.desMun,
      sequencia: index + 1,
      popup: `<strong>${index + 1}. ${mun.desMun}</strong><br>` +
             `${mun.desEst}<br>` +
             `IBGE: ${mun.cdibge}`
    })

    waypoints.push(L.latLng(mun.lat, mun.lon))
  })

  // === 2. ENTREGAS DO PACOTE ===
  const entregas = props.formData.pacote.entregas_com_gps
  const totalEntregas = entregas.length

  entregas.forEach((entrega, index) => {
    if (!entrega.lat || !entrega.lon) return

    // Determinar se é primeira, última ou intermediária
    const isPrimeira = index === 0
    const isUltima = index === totalEntregas - 1
    const isIntermediaria = !isPrimeira && !isUltima

    markers.push({
      id: `entrega-${entrega.numseqped}`,
      lat: entrega.lat,
      lon: entrega.lon,
      tipo: 'entrega',
      label: entrega.razcli,
      sequencia: municipios.length + index + 1,
      popup: `<strong>Entrega #${index + 1}</strong><br>` +
             `${entrega.razcli}<br>` +
             `${entrega.cidcli} - ${entrega.sigufs}`,
      isIntermediaria: isIntermediaria  // Flag para opacidade
    })

    // ⚠️ IMPORTANTE: Para roteirização OSRM, adiciona apenas primeira e última entrega
    // Entregas intermediárias aparecem no mapa mas não na rota calculada
    if (isPrimeira || isUltima) {
      waypoints.push(L.latLng(entrega.lat, entrega.lon))
    }
  })

  // === 3. PRAÇAS DE PEDÁGIO ===
  props.formData.preco.pracas.forEach((praca, index) => {
    if (praca.lat && praca.lon) {
      markers.push({
        id: `pedagio-${praca.id}`,
        lat: praca.lat,
        lon: praca.lon,
        tipo: 'pedagio',
        label: praca.nome,
        popup: `<strong>🚧 Pedágio</strong><br>` +
               `${praca.nome}<br>` +
               `${praca.cidade} - ${praca.uf}<br>` +
               `R$ ${praca.valor.toFixed(2)}`
      })
    }
  })

  // === 4. RENDERIZAR MARCADORES ===
  markers.forEach((marker) => {
    const icon = criarIconeCustomizado(marker)
    const leafletMarker = L.marker([marker.lat, marker.lon], { icon })

    if (marker.popup) {
      leafletMarker.bindPopup(marker.popup)
    }

    leafletMarker.addTo(markersLayer.value!)
  })

  // === 5. CALCULAR E DESENHAR ROTA ===
  // Só calcular rota se tiver municípios da rota (Step 3+)
  // No Step 1 (só entregas), apenas mostra marcadores sem rota
  if (waypoints.length >= 2 && municipios.length > 0) {
    console.log(`🗺️ Roteirizando com ${waypoints.length} waypoints (municípios + primeira/última entrega)`)
    console.log(`📍 Total de marcadores exibidos no mapa: ${markers.length}`)
    await calcularRota(waypoints)
  }

  // === 5.5. CARREGAR TODAS AS PRAÇAS ANTT ===
  if (mostrarPracasANTT.value) {
    await loadPracasANTT()
  }

  // === 6. AJUSTAR ZOOM ===
  if (markers.length > 0) {
    const bounds = L.latLngBounds(markers.map(m => [m.lat, m.lon]))
    map.value!.fitBounds(bounds, { padding: [50, 50], animate: false })
  }
}

const criarIconeCustomizado = (marker: MapMarker): L.DivIcon => {
  let bgColor = '#2196F3' // Azul para municípios
  let icon = 'tabler-map-pin'
  let opacity = 1.0 // Opacidade padrão

  if (marker.tipo === 'entrega') {
    // Verde (primeiro), Laranja (meio), Vermelho (último)
    const totalEntregas = props.formData.pacote.entregas_com_gps.length
    const indexEntrega = marker.sequencia! - props.formData.rota.municipios.length

    if (indexEntrega === 1) {
      bgColor = '#4CAF50' // Verde (primeira entrega - destaque)
      opacity = 1.0
    } else if (indexEntrega === totalEntregas) {
      bgColor = '#F44336' // Vermelho (última entrega - destaque)
      opacity = 1.0
    } else {
      bgColor = '#FF9800' // Laranja (intermediárias)
      opacity = 0.3 // ⚠️ Transparente para entregas intermediárias
    }
  } else if (marker.tipo === 'pedagio') {
    bgColor = '#FFC107' // Amarelo
    icon = 'tabler-road'
  }

  return L.divIcon({
    html: `
      <div style="
        background: ${bgColor};
        color: white;
        border: 3px solid white;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        opacity: ${opacity};
      ">
        ${marker.sequencia || ''}
      </div>
    `,
    className: 'custom-marker',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16]
  })
}

const calcularRota = async (waypoints: L.LatLng[]) => {
  if (waypoints.length < 2 || !routeLayer.value) return

  try {
    console.log('🗺️ Calculando rota com MapService para', waypoints.length, 'waypoints')

    // Converter waypoints para formato MapService [lat, lon]
    const mapServiceWaypoints = waypoints.map(w => [w.lat, w.lng] as [number, number])

    // Chamar MapService
    const response = await fetch(`${window.location.origin}/api/map/route`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        waypoints: mapServiceWaypoints,
        options: {
          use_cache: true,
          fallback_to_straight: true
        }
      })
    })

    if (!response.ok) {
      console.error('❌ MapService retornou erro:', response.status)
      desenharLinhaReta(waypoints)
      return
    }

    const result = await response.json()

    if (result.success && result.data?.coordinates) {
      console.log(`✅ Rota calculada: ${result.data.distance_km}km via ${result.data.provider}`)
      console.log(`💾 Cache: ${result.data.cached ? 'HIT' : 'MISS'}`)

      // Atualizar distância
      distanciaTotal.value = Number(result.data.distance_km)

      // Converter coordenadas para Leaflet
      const routeLatLngs = result.data.coordinates.map((coord: [number, number]) =>
        L.latLng(coord[0], coord[1])
      )

      // Desenhar rota rosa/magenta (#E91E63 = compra-viagem)
      const routePolyline = L.polyline(routeLatLngs, {
        color: '#E91E63',
        weight: 4,
        opacity: 0.7
      })

      routePolyline.addTo(routeLayer.value!)

    } else {
      console.warn('⚠️ MapService falhou, usando linha reta')
      desenharLinhaReta(waypoints)
    }

  } catch (error) {
    console.error('❌ Erro ao calcular rota:', error)
    desenharLinhaReta(waypoints)
  }
}

const desenharLinhaReta = (waypoints: L.LatLng[]) => {
  if (!routeLayer.value) return

  const fallbackPolyline = L.polyline(waypoints, {
    color: '#E91E63',
    weight: 3,
    opacity: 0.5,
    dashArray: '10, 10'
  })

  fallbackPolyline.addTo(routeLayer.value)

  console.log('📍 Linha reta desenhada (fallback)')
}

/**
 * Carrega e exibe TODAS as praças de pedágio ANTT
 */
const loadPracasANTT = async () => {
  if (!map.value || !mostrarPracasANTT.value) {
    return
  }

  try {
    console.log('🏛️ Carregando TODAS as praças ANTT...')

    const pracasEncontradas = await loadAndDisplayPracas(
      map.value,
      {
        color: '#9C27B0', // Roxo para diferenciar das praças SemParar (amarelas)
        showPopup: true,
        zIndex: 999 // Menor que praças SemParar
      }
    )

    console.log(`✅ ${pracasEncontradas.length} praças ANTT exibidas no mapa`)
  } catch (error) {
    console.error('❌ Erro ao carregar praças ANTT:', error)
  }
}

/**
 * Toggle para mostrar/ocultar praças ANTT
 */
const togglePracasANTT = async () => {
  mostrarPracasANTT.value = !mostrarPracasANTT.value

  if (mostrarPracasANTT.value) {
    // Carregar TODAS as praças
    await loadPracasANTT()
  } else {
    // Remover praças do mapa
    removePracasFromMap()
    console.log('🏛️ Praças ANTT ocultadas')
  }
}

// Watchers
watch(() => props.formData, async () => {
  await nextTick()
  atualizarMapa()
}, { deep: true })

// Lifecycle
onMounted(async () => {
  await nextTick()
  await initMap()

  // Atualizar mapa se já houver dados
  if (props.formData.rota.municipios.length > 0) {
    atualizarMapa()
  }
})

onBeforeUnmount(() => {
  // ⚠️ CRÍTICO: Remover praças ANTES de destruir o mapa
  // Senão os marcadores ficam "órfãos" e causam erro: _latLngToNewLayerPoint
  removePracasFromMap()

  if (map.value) {
    map.value.remove()
    map.value = null
  }
})

// Expor funções e estados para componente pai
defineExpose({
  togglePracasANTT,
  mostrarPracasANTT,
  pracasANTT,
  loadingPracasANTT
})
</script>

<template>
  <div class="mapa-fixo-container">
    <!-- Estatísticas Flutuantes -->
    <VCard
      class="stats-card"
      variant="elevated"
      elevation="4"
    >
      <VCardText class="pa-3">
        <VRow dense>
          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">
                Municípios
              </div>
              <div class="text-h6 text-primary">
                {{ estatisticas.municipios }}
              </div>
            </div>
          </VCol>

          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">
                Entregas
              </div>
              <div class="text-h6 text-success">
                {{ estatisticas.entregas }}
              </div>
            </div>
          </VCol>

          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">
                Pedágios
              </div>
              <div class="text-h6 text-warning">
                {{ estatisticas.pedagios }}
              </div>
            </div>
          </VCol>

          <VCol cols="6">
            <div class="text-center">
              <div class="text-caption text-medium-emphasis">
                Distância
              </div>
              <div class="text-h6 text-info">
                {{ estatisticas.distanciaKm.toFixed(0) }}km
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Container do Mapa -->
    <div
      ref="mapContainer"
      class="map-container"
    />
  </div>
</template>

<style scoped>
.mapa-fixo-container {
  position: relative;
  height: calc(100vh - 200px);
  min-height: 500px;
}

.map-container {
  width: 100%;
  height: 100%;
  border-radius: 8px;
  overflow: hidden;
}

.stats-card {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 1000;
  min-width: 240px;
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(8px);
}

/* Responsive para mobile */
@media (max-width: 960px) {
  .mapa-fixo-container {
    height: 400px;
  }

  .stats-card {
    top: 8px;
    right: 8px;
    min-width: auto;
  }
}
</style>
