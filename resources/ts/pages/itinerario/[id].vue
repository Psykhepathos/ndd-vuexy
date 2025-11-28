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
import 'leaflet.markercluster'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'

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
let markerClusterGroup: L.MarkerClusterGroup | null = null
let routeLayer: L.LayerGroup | null = null
const markersById = new Map<number, L.Marker>() // Map de index -> marker

/**
 * Converte coordenada do formato Progress para decimal
 */
function convertCoordinate(coord: string): number {
  if (!coord) return 0

  if (coord.includes(',')) {
    return parseFloat(coord.replace(',', '.'))
  }

  const num = parseInt(coord)
  if (Math.abs(num) > 1000000) {
    return num / 10000000
  }

  return parseFloat(coord)
}

/**
 * Ajusta o brilho de uma cor hexadecimal
 */
function adjustBrightness(hex: string, percent: number): string {
  // Remove o # se existir
  hex = hex.replace('#', '')

  // Converte para RGB
  const r = parseInt(hex.substring(0, 2), 16)
  const g = parseInt(hex.substring(2, 4), 16)
  const b = parseInt(hex.substring(4, 6), 16)

  // Ajusta o brilho
  const adjust = (value: number) => {
    const adjusted = value + (value * percent / 100)
    return Math.max(0, Math.min(255, Math.round(adjusted)))
  }

  // Converte de volta para hex
  const toHex = (value: number) => {
    const hex = value.toString(16)
    return hex.length === 1 ? '0' + hex : hex
  }

  return `#${toHex(adjust(r))}${toHex(adjust(g))}${toHex(adjust(b))}`
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

  // Zoom e centralizar
  map.setView([lat, lng], 16)

  // Pegar o marcador do Map
  const marker = markersById.get(index)
  if (marker) {
    // Se o marcador está em um cluster, o markercluster vai expandi-lo automaticamente
    setTimeout(() => {
      marker.openPopup()
    }, 300)
  }
}

/**
 * Simplifica array de pontos usando algoritmo Douglas-Peucker
 * Reduz quantidade de waypoints mantendo a forma geral da rota
 */
function simplifyPoints(points: Array<[number, number]>, tolerance: number): Array<[number, number]> {
  if (points.length <= 2) return points

  // Encontrar ponto mais distante da linha entre início e fim
  let maxDistance = 0
  let maxIndex = 0
  const start = points[0]
  const end = points[points.length - 1]

  for (let i = 1; i < points.length - 1; i++) {
    const distance = perpendicularDistance(points[i], start, end)
    if (distance > maxDistance) {
      maxDistance = distance
      maxIndex = i
    }
  }

  // Se o ponto mais distante está além da tolerância, dividir recursivamente
  if (maxDistance > tolerance) {
    const left = simplifyPoints(points.slice(0, maxIndex + 1), tolerance)
    const right = simplifyPoints(points.slice(maxIndex), tolerance)
    return [...left.slice(0, -1), ...right]
  } else {
    // Todos os pontos estão dentro da tolerância, retornar só início e fim
    return [start, end]
  }
}

/**
 * Calcula distância perpendicular de um ponto a uma linha
 */
function perpendicularDistance(
  point: [number, number],
  lineStart: [number, number],
  lineEnd: [number, number]
): number {
  const [x0, y0] = point
  const [x1, y1] = lineStart
  const [x2, y2] = lineEnd

  const dx = x2 - x1
  const dy = y2 - y1

  // Linha vertical ou horizontal
  if (dx === 0 && dy === 0) {
    const dx0 = x0 - x1
    const dy0 = y0 - y1
    return Math.sqrt(dx0 * dx0 + dy0 * dy0)
  }

  // Fórmula da distância perpendicular
  const num = Math.abs(dy * x0 - dx * y0 + x2 * y1 - y2 * x1)
  const den = Math.sqrt(dx * dx + dy * dy)
  return num / den
}

/**
 * Calcula rota usando MapService, dividindo em chunks se necessário
 * Com simplificação inteligente de pontos baseada no zoom
 */
async function calculateRouteWithMapService(waypoints: Array<[number, number]>): Promise<{
  coordinates: Array<[number, number]>
  distance_km: number
  cached: boolean
} | null> {
  if (waypoints.length < 2) return null

  // Simplificar waypoints baseado na quantidade e zoom do mapa
  // Tolerância adaptativa: quanto mais zoom out, mais simplificação
  let simplifiedWaypoints = waypoints
  if (waypoints.length > 50) {
    // Pegar nível de zoom atual do mapa (4 = Brasil inteiro, 18 = rua)
    const currentZoom = map?.getZoom() || 4

    // Calcular tolerância baseada no zoom
    // Zoom baixo (4-8) = alta tolerância (mais simplificação)
    // Zoom médio (9-12) = média tolerância
    // Zoom alto (13+) = baixa tolerância (menos simplificação)
    let tolerance = 0.01 // Default: ~1km
    if (currentZoom < 8) {
      tolerance = 0.05 // ~5km - Simplificação agressiva (zoom Brasil)
    } else if (currentZoom < 12) {
      tolerance = 0.02 // ~2km - Simplificação média (zoom Estado)
    } else {
      tolerance = 0.005 // ~500m - Pouca simplificação (zoom Cidade)
    }

    simplifiedWaypoints = simplifyPoints(waypoints, tolerance)
    console.log(`🔧 Simplificado (zoom ${currentZoom}): ${waypoints.length} → ${simplifiedWaypoints.length} pontos`)
  }

  const MAX_WAYPOINTS_PER_REQUEST = 25 // Limite seguro para OSRM

  // Se tiver poucos waypoints, calcular direto
  if (simplifiedWaypoints.length <= MAX_WAYPOINTS_PER_REQUEST) {
    return await calculateSingleRoute(simplifiedWaypoints)
  }

  // Dividir em chunks e calcular segmento por segmento
  console.log(`📊 ${simplifiedWaypoints.length} waypoints - dividindo em segmentos de ${MAX_WAYPOINTS_PER_REQUEST}`)

  const allCoordinates: Array<[number, number]> = []
  let totalDistance = 0

  for (let i = 0; i < simplifiedWaypoints.length - 1; i += MAX_WAYPOINTS_PER_REQUEST - 1) {
    const end = Math.min(i + MAX_WAYPOINTS_PER_REQUEST, simplifiedWaypoints.length)
    const chunk = simplifiedWaypoints.slice(i, end)

    console.log(`🔗 Calculando segmento ${Math.floor(i / (MAX_WAYPOINTS_PER_REQUEST - 1)) + 1} (${chunk.length} pontos)`)

    const segmentResult = await calculateSingleRoute(chunk)

    if (segmentResult && segmentResult.coordinates.length > 0) {
      // Evitar duplicar o último ponto do segmento anterior
      if (allCoordinates.length > 0) {
        allCoordinates.push(...segmentResult.coordinates.slice(1))
      } else {
        allCoordinates.push(...segmentResult.coordinates)
      }
      // FIX: Converter para número (pode vir como string do cache)
      totalDistance += Number(segmentResult.distance_km)
    } else {
      console.warn(`⚠️ Segmento ${i} falhou, usando linha reta`)
      // Fallback: linha reta para este segmento
      if (allCoordinates.length === 0 || i === 0) {
        allCoordinates.push(chunk[0])
      }
      for (let j = 1; j < chunk.length; j++) {
        allCoordinates.push(chunk[j])
        // Calcular distância aproximada
        const lat1 = chunk[j-1][0], lng1 = chunk[j-1][1]
        const lat2 = chunk[j][0], lng2 = chunk[j][1]
        const R = 6371
        const dLat = (lat2 - lat1) * Math.PI / 180
        const dLng = (lng2 - lng1) * Math.PI / 180
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng/2) * Math.sin(dLng/2)
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
        totalDistance += R * c
      }
    }
  }

  if (allCoordinates.length > 0) {
    console.log(`✅ Rota total calculada: ${totalDistance.toFixed(1)}km com ${allCoordinates.length} coordenadas`)
    return {
      coordinates: allCoordinates,
      distance_km: totalDistance,
      cached: false
    }
  }

  return null
}

/**
 * Calcula uma rota simples
 */
async function calculateSingleRoute(waypoints: Array<[number, number]>): Promise<{
  coordinates: Array<[number, number]>
  distance_km: number
  cached: boolean
} | null> {
  try {
    const payload = {
      waypoints: waypoints,
      options: {
        use_cache: true,
        fallback_to_straight: true
      }
    }

    const response = await fetch(`${window.location.origin}/api/map/route`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })

    if (!response.ok) {
      console.error('❌ MapService retornou erro:', response.status)
      return null
    }

    const result = await response.json()

    if (result.success && result.data?.coordinates) {
      return {
        coordinates: result.data.coordinates,
        distance_km: result.data.distance_km,
        cached: result.data.cached
      }
    }

    return null
  } catch (error) {
    console.error('❌ Erro ao calcular rota:', error)
    return null
  }
}

/**
 * Inicializa o mapa Leaflet
 */
function initMap() {
  if (!mapContainer.value) return

  map = L.map('mapa-itinerario').setView([-14.2350, -51.9253], 4)

  // Adicionar camada OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)

  // Criar grupo de clustering com configurações customizadas
  markerClusterGroup = L.markerClusterGroup({
    maxClusterRadius: 50,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: true,
    zoomToBoundsOnClick: true,
    spiderfyDistanceMultiplier: 1.5,
    iconCreateFunction: function(cluster) {
      const count = cluster.getChildCount()
      let size = 32 // Menor
      let className = 'marker-cluster-small'

      if (count > 10) {
        size = 38
        className = 'marker-cluster-medium'
      }
      if (count > 20) {
        size = 44
        className = 'marker-cluster-large'
      }

      return L.divIcon({
        html: `<div><span>${count}</span></div>`,
        className: `marker-cluster ${className}`,
        iconSize: L.point(size, size)
      })
    }
  })

  map.addLayer(markerClusterGroup)

  routeLayer = L.layerGroup().addTo(map)
}

/**
 * Adiciona marcadores e rota no mapa
 */
async function addMarkersAndRoute() {
  if (!map || !markerClusterGroup || !routeLayer) return

  // Limpar camadas existentes
  markerClusterGroup.clearLayers()
  routeLayer.clearLayers()
  markersById.clear()

  // Filtrar entregas com GPS
  const entregasComGPS = pacoteData.value.pedidos
    .map((pedido, originalIndex) => {
      const lat = convertCoordinate(pedido.gps_lat || '')
      const lng = convertCoordinate(pedido.gps_lon || '')

      return {
        entrega: pedido,
        originalIndex,
        lat,
        lng
      }
    })
    .filter(item => item.lat && item.lng)

  if (!entregasComGPS.length) {
    console.warn('⚠️ Nenhuma entrega com GPS encontrada')
    return
  }

  console.log(`📍 Processando ${entregasComGPS.length} entregas com GPS`)

  const latlngs: L.LatLng[] = []
  const waypoints: Array<[number, number]> = []

  // Adicionar marcadores ao cluster
  entregasComGPS.forEach((item, idx) => {
    const { entrega, originalIndex, lat, lng } = item

    latlngs.push(L.latLng(lat, lng))
    waypoints.push([lat, lng])

    // Cor baseada na posição
    const isFirst = idx === 0
    const isLast = idx === entregasComGPS.length - 1
    let iconColor = '#2196F3'
    if (isFirst) iconColor = '#4CAF50'
    if (isLast) iconColor = '#F44336'

    const customIcon = L.divIcon({
      html: `
        <div style="
          background: linear-gradient(135deg, ${iconColor} 0%, ${adjustBrightness(iconColor, -20)} 100%);
          width: 28px;
          height: 28px;
          border-radius: 50%;
          border: 2px solid white;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-weight: 600;
          font-size: 11px;
          box-shadow: 0 2px 6px rgba(0,0,0,0.25);
          text-shadow: 0 1px 2px rgba(0,0,0,0.3);
          transition: transform 0.2s ease;
        "
        onmouseover="this.style.transform='scale(1.15)'"
        onmouseout="this.style.transform='scale(1)'"
        >${entrega.seqent}</div>
      `,
      className: 'custom-div-icon',
      iconSize: [28, 28],
      iconAnchor: [14, 14]
    })

    const marker = L.marker([lat, lng], { icon: customIcon })
      .bindPopup(`
        <div style="min-width: 220px;">
          <strong style="font-size: 14px;">Entrega ${entrega.seqent}</strong><br>
          <strong style="font-size: 13px;">${entrega.razcli}</strong><br>
          <small style="color: #666;">${entrega.desend}<br>
          ${entrega.desbai} • ${entrega.desmun} - ${entrega.uf}</small><br>
          <hr style="margin: 10px 0; border-color: #e0e0e0;">
          <div style="display: flex; justify-content: space-between; margin-top: 8px;">
            <div>
              <strong style="font-size: 11px;">Valor:</strong><br>
              <span style="font-size: 13px; color: #4CAF50;">${(entrega.valnot || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
            </div>
            <div>
              <strong style="font-size: 11px;">Peso:</strong><br>
              <span style="font-size: 13px;">${(entrega.peso || 0).toFixed(1)}kg</span>
            </div>
            <div>
              <strong style="font-size: 11px;">Volume:</strong><br>
              <span style="font-size: 13px;">${(entrega.volume || 0).toFixed(2)}m³</span>
            </div>
          </div>
        </div>
      `)

    // Adicionar ao cluster
    markerClusterGroup.addLayer(marker)

    // Guardar referência para focar depois
    markersById.set(originalIndex, marker)
  })

  // Calcular e adicionar rota
  if (waypoints.length > 1) {
    // Linha temporária animada
    const loadingPolyline = L.polyline(latlngs, {
      color: '#2196F3',
      weight: 3,
      opacity: 0.4,
      dashArray: '10, 10',
      className: 'route-loading'
    })
    routeLayer.addLayer(loadingPolyline)

    // Indicador de progresso no mapa
    const progressControl = L.control({ position: 'topright' }) as any
    progressControl.onAdd = function() {
      const div = L.DomUtil.create('div', 'route-progress-indicator')
      div.innerHTML = `
        <div style="
          background: white;
          padding: 12px 16px;
          border-radius: 8px;
          box-shadow: 0 2px 8px rgba(0,0,0,0.15);
          font-size: 13px;
          font-weight: 500;
          color: #2196F3;
          display: flex;
          align-items: center;
          gap: 10px;
        ">
          <div class="spinner"></div>
          <span>Calculando rota...</span>
        </div>
      `
      return div
    }
    progressControl.addTo(map)

    const routeResult = await calculateRouteWithMapService(waypoints)

    // Remover indicador e linha temporária
    progressControl.remove()
    routeLayer.removeLayer(loadingPolyline)

    if (routeResult && routeResult.coordinates.length > 0) {
      distanciaTotal.value = Number(routeResult.distance_km)

      const routeLatLngs = routeResult.coordinates.map(coord => L.latLng(coord[0], coord[1]))

      const routePolyline = L.polyline(routeLatLngs, {
        color: '#2196F3',
        weight: 5,
        opacity: 0.7,
        lineJoin: 'round',
        lineCap: 'round'
      })

      routeLayer.addLayer(routePolyline)
      map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] })
    } else {
      // Fallback
      const fallbackPolyline = L.polyline(latlngs, {
        color: '#FF9800',
        weight: 4,
        opacity: 0.6,
        dashArray: '10, 5'
      })

      routeLayer.addLayer(fallbackPolyline)
      map.fitBounds(fallbackPolyline.getBounds(), { padding: [50, 50] })

      let totalDistance = 0
      for (let i = 0; i < latlngs.length - 1; i++) {
        totalDistance += latlngs[i].distanceTo(latlngs[i + 1]) / 1000
      }
      distanciaTotal.value = totalDistance
    }
  } else if (latlngs.length === 1) {
    map.setView(latlngs[0], 14)
  }
}

/**
 * Busca dados do itinerário
 */
async function fetchItinerario() {
  try {
    loading.value = true
    const pacoteId = route.params.id as string

    console.log('📦 Buscando itinerário do pacote', pacoteId)

    const response = await $api('/api/pacotes/itinerario', {
      method: 'POST',
      body: {
        codPac: parseInt(pacoteId)
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

/* Leaflet Popup */
:deep(.leaflet-popup-content) {
  margin: 12px;
  line-height: 1.5;
}

:deep(.custom-div-icon) {
  background: transparent !important;
  border: none !important;
}

/* Route Loading Animation */
:deep(.route-loading) {
  animation: dash 1s linear infinite;
}

@keyframes dash {
  to {
    stroke-dashoffset: -20;
  }
}

/* Spinner Animation */
:deep(.spinner) {
  width: 16px;
  height: 16px;
  border: 2px solid #e3f2fd;
  border-top: 2px solid #2196F3;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

:deep(.route-progress-indicator) {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* MarkerCluster Customization - Moderno e Compacto */
:deep(.marker-cluster) {
  background-clip: padding-box;
  border-radius: 50%;
  font-weight: 600;
}

:deep(.marker-cluster div) {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 12px;
  text-shadow: 0 1px 3px rgba(0,0,0,0.4);
  border: 2px solid white;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  transition: transform 0.2s ease;
}

:deep(.marker-cluster div:hover) {
  transform: scale(1.1);
}

/* Verde - 2 a 10 entregas */
:deep(.marker-cluster-small) {
  background: linear-gradient(135deg, rgba(76, 175, 80, 0.3) 0%, rgba(56, 142, 60, 0.3) 100%);
}

:deep(.marker-cluster-small div) {
  background: linear-gradient(135deg, #66BB6A 0%, #43A047 100%);
}

/* Amarelo - 11 a 20 entregas */
:deep(.marker-cluster-medium) {
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.3) 0%, rgba(251, 140, 0, 0.3) 100%);
}

:deep(.marker-cluster-medium div) {
  background: linear-gradient(135deg, #FFCA28 0%, #FFA726 100%);
}

/* Laranja/Vermelho - 21+ entregas */
:deep(.marker-cluster-large) {
  background: linear-gradient(135deg, rgba(255, 87, 34, 0.3) 0%, rgba(244, 67, 54, 0.3) 100%);
}

:deep(.marker-cluster-large div) {
  background: linear-gradient(135deg, #FF7043 0%, #F4511E 100%);
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

/* Responsive */
@media (max-width: 768px) {
  .entrega-stats {
    flex-direction: column;
    gap: 8px !important;
  }

  #mapa-itinerario {
    height: 400px !important;
  }

  .d-flex.gap-4 {
    flex-direction: column !important;
  }

  .stats-card {
    min-width: unset;
  }
}
</style>
