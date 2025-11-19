<template>
  <div class="itinerario-mapa">
    <!-- Header da Página -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Itinerário do Pacote {{ pacoteData.codpac }}
        </h4>
        <p class="text-body-1 mb-0 text-medium-emphasis">
          Rota: {{ pacoteData.rota }} • {{ pacoteData.pedidos?.length || 0 }} entregas
        </p>
      </div>

      <VBtn
        prepend-icon="tabler-arrow-left"
        variant="outlined"
        color="secondary"
        @click="$router.back()"
      >
        Voltar
      </VBtn>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="d-flex gap-4 mb-6">
      <VCard class="stats-card flex-fill">
        <VCardText class="text-center pa-4">
          <VIcon icon="tabler-map-pin" size="28" class="text-primary mb-2" />
          <div class="text-h4 font-weight-bold mb-1">{{ pacoteData.pedidos?.length || 0 }}</div>
          <p class="text-body-2 mb-0 text-medium-emphasis">Entregas</p>
        </VCardText>
      </VCard>

      <VCard class="stats-card flex-fill">
        <VCardText class="text-center pa-4">
          <VIcon icon="tabler-route" size="28" class="text-success mb-2" />
          <div class="text-h4 font-weight-bold mb-1">{{ distanciaTotal.toFixed(0) }}km</div>
          <p class="text-body-2 mb-0 text-medium-emphasis">Distância</p>
        </VCardText>
      </VCard>

      <VCard class="stats-card flex-fill">
        <VCardText class="text-center pa-4">
          <VIcon icon="tabler-weight" size="28" class="text-warning mb-2" />
          <div class="text-h4 font-weight-bold mb-1">{{ (pacoteData.peso || 0).toFixed(0) }}kg</div>
          <p class="text-body-2 mb-0 text-medium-emphasis">Peso Total</p>
        </VCardText>
      </VCard>

      <VCard class="stats-card flex-fill">
        <VCardText class="text-center pa-4">
          <VIcon icon="tabler-currency-real" size="28" class="text-info mb-2" />
          <div class="text-h4 font-weight-bold mb-1">{{ formatCurrency(pacoteData.valor || 0) }}</div>
          <p class="text-body-2 mb-0 text-medium-emphasis">Valor Total</p>
        </VCardText>
      </VCard>
    </div>

    <!-- Mapa -->
    <VCard class="mb-6">
      <VCardText>
        <div
          ref="mapContainer"
          id="mapa-itinerario"
          style="height: 600px; width: 100%"
          class="rounded border"
        ></div>
      </VCardText>
    </VCard>

    <!-- Lista de Entregas -->
    <VCard>
      <VCardText>
        <h5 class="text-h5 font-weight-medium mb-4">
          <VIcon icon="tabler-list-details" class="me-2" />
          Lista de Entregas
        </h5>

        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="text-body-2 mt-4 mb-0 text-medium-emphasis">Carregando itinerário...</p>
        </div>

        <div v-else-if="pacoteData.pedidos?.length" class="entregas-list">
          <div
            v-for="(entrega, index) in pacoteData.pedidos"
            :key="entrega.seqent"
            class="entrega-item d-flex align-start py-4"
            :class="{ 'border-b': index < pacoteData.pedidos.length - 1 }"
            @click="focusOnMarker(index)"
            style="cursor: pointer"
          >
            <!-- Número da Entrega -->
            <div class="entrega-numero me-4 flex-shrink-0">
              <VAvatar
                :color="entrega.gps_lat && entrega.gps_lon ? 'primary' : 'secondary'"
                size="32"
              >
                <span class="text-body-2 font-weight-semibold">{{ entrega.seqent }}</span>
              </VAvatar>
            </div>

            <!-- Dados da Entrega -->
            <div class="flex-grow-1">
              <div class="d-flex justify-space-between align-start mb-2">
                <div>
                  <h6 class="text-h6 font-weight-medium mb-1">
                    {{ entrega.razcli }}
                  </h6>
                  <p class="text-body-2 mb-1 text-medium-emphasis">
                    Cliente: {{ entrega.codcli }}
                  </p>
                </div>
                <VChip
                  :color="entrega.gps_lat && entrega.gps_lon ? 'success' : 'warning'"
                  size="small"
                  variant="flat"
                >
                  <VIcon
                    :icon="entrega.gps_lat && entrega.gps_lon ? 'tabler-map-pin-check' : 'tabler-map-pin-x'"
                    size="14"
                    class="me-1"
                  />
                  {{ entrega.gps_lat && entrega.gps_lon ? 'Com GPS' : 'Sem GPS' }}
                </VChip>
              </div>

              <div class="address-info mb-3">
                <p class="text-body-2 mb-1">
                  <VIcon icon="tabler-map-pin" size="16" class="me-1 text-primary" />
                  {{ entrega.desend }}
                </p>
                <p class="text-body-2 mb-0 text-medium-emphasis">
                  {{ entrega.desbai }} • {{ entrega.desmun }} - {{ entrega.uf }}
                </p>
              </div>

              <div class="entrega-stats d-flex gap-4">
                <div class="d-flex align-center">
                  <VIcon icon="tabler-currency-real" size="16" class="me-1 text-success" />
                  <span class="text-body-2">{{ (entrega.valnot || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</span>
                </div>
                <div class="d-flex align-center">
                  <VIcon icon="tabler-weight" size="16" class="me-1 text-warning" />
                  <span class="text-body-2">{{ (entrega.peso || 0).toFixed(1) }}kg</span>
                </div>
                <div class="d-flex align-center">
                  <VIcon icon="tabler-box" size="16" class="me-1 text-info" />
                  <span class="text-body-2">{{ (entrega.volume || 0).toFixed(2) }}m³</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8">
          <VIcon icon="tabler-map-off" size="48" class="text-disabled mb-4" />
          <p class="text-h6 mb-2">Nenhuma entrega encontrada</p>
          <p class="text-body-2 mb-0 text-medium-emphasis">Este pacote não possui entregas com coordenadas GPS.</p>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { $api } from '@/utils/api'
import L from 'leaflet'

// Fix para ícones do Leaflet
delete (L.Icon.Default.prototype as any)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png'
})

interface PedidoItinerario {
  seqent: number
  codcli: number
  razcli: string
  uf: string
  desmun: string
  desbai: string
  desend: string
  valnot: number
  peso: number
  volume: number
  gps_lat?: string
  gps_lon?: string
}

interface ItinerarioData {
  codpac: string
  rota: string
  motorista: number
  peso: number
  volume: number
  valor: number
  frete: number
  pedidos: PedidoItinerario[]
}

const route = useRoute()
const mapContainer = ref<HTMLElement | null>(null)
const loading = ref(true)
const pacoteData = ref<ItinerarioData>({
  codpac: '',
  rota: '',
  motorista: 0,
  peso: 0,
  volume: 0,
  valor: 0,
  frete: 0,
  pedidos: []
})
const distanciaTotal = ref<number>(0)

let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let routeLayer: L.LayerGroup | null = null

/**
 * Converte coordenada do formato Progress para decimal
 * Progress: "-23,2041" ou "230876543" (implícito)
 * Decimal: -23.2041 ou -23.0876543
 */
function convertCoordinate(coord: string): number {
  if (!coord) return 0

  // Se contém vírgula, é formato "-23,2041"
  if (coord.includes(',')) {
    return parseFloat(coord.replace(',', '.'))
  }

  // Se é número puro "230876543", divide por 10^7
  const num = parseInt(coord)
  if (Math.abs(num) > 1000000) {
    return num / 10000000
  }

  return parseFloat(coord)
}

/**
 * Formata valores de moeda de forma compacta
 */
function formatCurrency(value: number): string {
  if (value >= 1000000) {
    return `R$ ${(value / 1000000).toFixed(1)}M`
  } else if (value >= 1000) {
    return `R$ ${(value / 1000).toFixed(0)}k`
  } else {
    return `R$ ${value.toFixed(0)}`
  }
}

/**
 * Foca no marcador de uma entrega específica
 */
function focusOnMarker(index: number) {
  const entrega = pacoteData.value.pedidos[index]
  if (!entrega.gps_lat || !entrega.gps_lon || !map) return

  const lat = convertCoordinate(entrega.gps_lat)
  const lng = convertCoordinate(entrega.gps_lon)

  map.setView([lat, lng], 16)

  // Abrir popup do marcador
  if (markersLayer) {
    markersLayer.eachLayer((layer: any) => {
      if (layer.options?.entregaIndex === index) {
        layer.openPopup()
      }
    })
  }
}

/**
 * Calcula rota usando MapService unificado
 */
async function calculateRouteWithMapService(waypoints: Array<[number, number]>): Promise<{
  coordinates: Array<[number, number]>
  distance_km: number
  cached: boolean
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

    console.log('🗺️ Calculando rota com MapService para', waypoints.length, 'waypoints')
    console.log('📤 Payload:', JSON.stringify(payload, null, 2))

    const response = await fetch('http://localhost:8002/api/map/route', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error('❌ MapService retornou erro:', response.status)
      console.error('📥 Resposta:', errorText)
      try {
        const errorJson = JSON.parse(errorText)
        console.error('🔍 Erro detalhado:', errorJson)
      } catch (e) {
        // Não é JSON
      }
      return null
    }

    const result = await response.json()

    if (result.success && result.data?.coordinates) {
      console.log(`✅ Rota calculada: ${result.data.distance_km}km via ${result.data.provider}`)
      console.log(`💾 Cache: ${result.data.cached ? 'HIT' : 'MISS'}`)

      return {
        coordinates: result.data.coordinates,
        distance_km: result.data.distance_km,
        cached: result.data.cached
      }
    }

    console.warn('⚠️ MapService não retornou coordenadas válidas')
    return null
  } catch (error) {
    console.error('❌ Erro ao calcular rota com MapService:', error)
    return null
  }
}

/**
 * Inicializa o mapa Leaflet
 */
function initMap() {
  if (!mapContainer.value) return

  map = L.map('mapa-itinerario').setView([-14.2350, -51.9253], 4) // Brasil como centro

  // Adicionar camada OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)
  routeLayer = L.layerGroup().addTo(map)
}

/**
 * Adiciona marcadores e rota no mapa usando MapService
 */
async function addMarkersAndRoute() {
  if (!map || !markersLayer || !routeLayer) return

  // Limpar camadas existentes
  markersLayer.clearLayers()
  routeLayer.clearLayers()

  // Filtrar entregas com GPS e manter índice original
  const entregasComGPS = pacoteData.value.pedidos
    .map((pedido, originalIndex) => ({ ...pedido, originalIndex }))
    .filter(p => p.gps_lat && p.gps_lon)

  if (!entregasComGPS.length) {
    console.warn('⚠️ Nenhuma entrega com GPS encontrada')
    return
  }

  console.log(`📍 Processando ${entregasComGPS.length} entregas com GPS`)

  const latlngs: L.LatLng[] = []
  const waypoints: Array<[number, number]> = [] // [lat, lon] para MapService

  // Adicionar marcadores
  entregasComGPS.forEach((entrega, index) => {
    const lat = convertCoordinate(entrega.gps_lat!)
    const lng = convertCoordinate(entrega.gps_lon!)

    if (lat && lng) {
      latlngs.push(L.latLng(lat, lng))
      waypoints.push([lat, lng]) // MapService espera [lat, lon]

      // Criar ícone personalizado baseado na sequência
      const isFirst = index === 0
      const isLast = index === entregasComGPS.length - 1

      let iconColor = '#2196F3' // Azul padrão
      if (isFirst) iconColor = '#4CAF50' // Verde para início
      if (isLast) iconColor = '#F44336' // Vermelho para fim

      const customIcon = L.divIcon({
        html: `
          <div style="
            background-color: ${iconColor};
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
          ">${entrega.seqent}</div>
        `,
        className: 'custom-div-icon',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      })

      const marker = L.marker([lat, lng], {
        icon: customIcon,
        entregaIndex: entrega.originalIndex
      } as any)
        .bindPopup(`
          <div style="min-width: 200px;">
            <strong>Entrega ${entrega.seqent}</strong><br>
            <strong>${entrega.razcli}</strong><br>
            <small>${entrega.desend}<br>
            ${entrega.desbai} • ${entrega.desmun} - ${entrega.uf}</small><br>
            <hr style="margin: 8px 0;">
            <strong>Valor:</strong> ${(entrega.valnot || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}<br>
            <strong>Peso:</strong> ${(entrega.peso || 0).toFixed(1)}kg<br>
            <strong>Volume:</strong> ${(entrega.volume || 0).toFixed(2)}m³
          </div>
        `)

      markersLayer.addLayer(marker)
    }
  })

  // Calcular e adicionar rota
  if (waypoints.length > 1) {
    // Mostrar linha tracejada enquanto carrega
    const loadingPolyline = L.polyline(latlngs, {
      color: '#cccccc',
      weight: 2,
      opacity: 0.5,
      dashArray: '5, 5'
    })
    routeLayer.addLayer(loadingPolyline)

    // Calcular rota com MapService
    const routeResult = await calculateRouteWithMapService(waypoints)

    // Remover linha de loading
    routeLayer.removeLayer(loadingPolyline)

    if (routeResult && routeResult.coordinates.length > 0) {
      // Atualizar distância total (garantir que é número)
      distanciaTotal.value = Number(routeResult.distance_km)

      // Converter coordenadas para Leaflet LatLng
      const routeLatLngs = routeResult.coordinates.map(coord => L.latLng(coord[0], coord[1]))

      // Adicionar rota real
      const routePolyline = L.polyline(routeLatLngs, {
        color: '#2196F3',
        weight: 5,
        opacity: 0.8,
        lineJoin: 'round',
        lineCap: 'round'
      })

      routeLayer.addLayer(routePolyline)

      // Ajustar zoom para mostrar toda a rota
      map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] })
    } else {
      // Fallback: linha reta tracejada
      console.warn('⚠️ Usando linha reta como fallback')

      const fallbackPolyline = L.polyline(latlngs, {
        color: '#FF9800',
        weight: 4,
        opacity: 0.7,
        dashArray: '10, 5'
      })

      routeLayer.addLayer(fallbackPolyline)
      map.fitBounds(fallbackPolyline.getBounds(), { padding: [50, 50] })

      // Calcular distância aproximada (Haversine)
      let totalDistance = 0
      for (let i = 0; i < latlngs.length - 1; i++) {
        const distance = latlngs[i].distanceTo(latlngs[i + 1]) / 1000 // em km
        totalDistance += distance
      }
      distanciaTotal.value = totalDistance
    }
  } else if (latlngs.length === 1) {
    // Apenas um ponto
    map.setView(latlngs[0], 14)
  }
}

/**
 * Busca dados do itinerário via API
 */
async function fetchItinerario() {
  try {
    loading.value = true
    const pacoteId = route.params.id as string

    console.log('📦 Buscando itinerário do pacote', pacoteId)

    const response = await $api('/api/pacotes/itinerario', {
      method: 'POST',
      body: {
        Pacote: {
          codPac: parseInt(pacoteId)
        }
      }
    })

    if (response.success && response.data) {
      pacoteData.value = response.data

      console.log(`✅ Itinerário carregado: ${response.data.pedidos.length} entregas`)

      await nextTick()
      await addMarkersAndRoute()
    } else {
      console.error('❌ Erro ao buscar itinerário:', response.message)
    }
  } catch (error) {
    console.error('❌ Erro ao buscar itinerário:', error)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await nextTick()
  initMap()
  await fetchItinerario()
})
</script>

<style scoped>
.itinerario-mapa {
  padding: 24px;
}

.entrega-item {
  transition: background-color 0.2s ease;
  border-radius: 8px;
  padding: 16px !important;
  margin: 0 -16px;
}

.entrega-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.address-info {
  background-color: rgba(var(--v-theme-surface), 0.5);
  padding: 12px;
  border-radius: 6px;
  border-left: 3px solid rgb(var(--v-theme-primary));
}

.entrega-stats {
  flex-wrap: wrap;
}

/* Leaflet map styling */
:deep(.leaflet-popup-content) {
  margin: 8px 12px;
  line-height: 1.4;
}

:deep(.custom-div-icon) {
  background: transparent !important;
  border: none !important;
}

/* Stats Cards */
.stats-card {
  min-width: 120px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-on-surface), 0.12);
}

.stats-card .v-card-text {
  padding: 16px !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .entrega-stats {
    flex-direction: column;
    gap: 8px !important;
  }

  #mapa-itinerario {
    height: 400px !important;
  }

  /* Stack cards vertically on mobile */
  .d-flex.gap-4 {
    flex-direction: column !important;
  }

  .stats-card {
    min-width: unset;
  }
}
</style>
