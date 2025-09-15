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
          <div class="text-h4 font-weight-bold mb-1">{{ typeof distanciaTotal === 'number' ? distanciaTotal.toFixed(0) : '0' }}km</div>
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

// Google Maps API
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY
let googleMapsLoaded = false
declare global {
  interface Window {
    google: any
    initGoogleMaps: () => void
  }
}

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

// Função para converter coordenadas do formato Progress para decimal
function convertCoordinate(coord: string): number {
  if (!coord) return 0
  
  // Formato: "-23,2041" -> -23.2041
  const cleanCoord = coord.replace(',', '.')
  return parseFloat(cleanCoord)
}

// Função para calcular distância entre dois pontos (fórmula de Haversine)
function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371 // Raio da Terra em km
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLon = (lon2 - lon1) * Math.PI / 180
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon/2) * Math.sin(dLon/2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
  return R * c
}

// Função para formatar moeda
function formatCurrency(value: number): string {
  if (value >= 1000000) {
    return `R$ ${(value / 1000000).toFixed(1)}M`
  } else if (value >= 1000) {
    return `R$ ${(value / 1000).toFixed(0)}k`
  } else {
    return `R$ ${value.toFixed(0)}`
  }
}

// Função para calcular distância total da rota (fallback usando Haversine)
function calculateTotalDistance() {
  if (!pacoteData.value.pedidos?.length) return
  
  let total = 0
  const entregasComGPS = pacoteData.value.pedidos.filter(p => p.gps_lat && p.gps_lon)
  
  for (let i = 0; i < entregasComGPS.length - 1; i++) {
    const current = entregasComGPS[i]
    const next = entregasComGPS[i + 1]
    
    const lat1 = convertCoordinate(current.gps_lat!)
    const lon1 = convertCoordinate(current.gps_lon!)
    const lat2 = convertCoordinate(next.gps_lat!)
    const lon2 = convertCoordinate(next.gps_lon!)
    
    total += calculateDistance(lat1, lon1, lat2, lon2)
  }
  
  distanciaTotal.value = Number(total) || 0
}

// Função para focar em um marcador específico
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

// Função para carregar Google Maps API
function loadGoogleMapsAPI(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (googleMapsLoaded) {
      resolve()
      return
    }

    window.initGoogleMaps = () => {
      googleMapsLoaded = true
      console.log('✅ Google Maps API carregada com sucesso!')
      resolve()
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=geometry,places&callback=initGoogleMaps`
    script.async = true
    script.defer = true
    script.onerror = reject
    document.head.appendChild(script)
  })
}

// Função para calcular rota usando Google Maps Directions API com múltiplas chamadas
async function getGoogleRoute(waypoints: Array<[number, number]>): Promise<L.LatLng[] | null> {
  if (!googleMapsLoaded || waypoints.length < 2) return null

  try {
    console.log('🗺️ Calculando rota com Google Maps para', waypoints.length, 'pontos')
    
    const directionsService = new window.google.maps.DirectionsService()
    const allRouteCoordinates: L.LatLng[] = []
    
    // Resetar distância total
    distanciaTotal.value = 0
    
    // Google Maps suporta máximo 25 waypoints por request (origem + 23 intermediários + destino)
    const MAX_WAYPOINTS_PER_REQUEST = 23
    
    if (waypoints.length <= MAX_WAYPOINTS_PER_REQUEST + 2) {
      // Único request - processo normal
      console.log('📍 Processando rota única com', waypoints.length, 'pontos')
      return await processSingleGoogleRoute(directionsService, waypoints)
    } else {
      // Múltiplos requests necessários
      console.log('📊 Dividindo', waypoints.length, 'pontos em múltiplas chamadas Google Maps')
      
      // Dividir waypoints em chunks
      let currentIndex = 0
      let segmentNumber = 1
      
      while (currentIndex < waypoints.length - 1) {
        const remainingPoints = waypoints.length - currentIndex
        const segmentSize = Math.min(MAX_WAYPOINTS_PER_REQUEST + 1, remainingPoints)
        
        const segmentWaypoints = waypoints.slice(currentIndex, currentIndex + segmentSize)
        
        console.log(`🚗 Processando segmento ${segmentNumber}: pontos ${currentIndex + 1} a ${currentIndex + segmentSize}`)
        
        const segmentRoute = await processSingleGoogleRoute(directionsService, segmentWaypoints)
        
        if (segmentRoute && segmentRoute.length > 0) {
          // Conectar com o segmento anterior (evitar duplicação do ponto de conexão)
          const coordsToAdd = segmentNumber === 1 ? segmentRoute : segmentRoute.slice(1)
          allRouteCoordinates.push(...coordsToAdd)
          
          console.log(`✅ Segmento ${segmentNumber}: ${segmentRoute.length} pontos adicionados`)
        } else {
          console.warn(`⚠️ Segmento ${segmentNumber} falhou, usando linha reta`)
          
          // Fallback para linha reta
          if (segmentNumber === 1) {
            allRouteCoordinates.push(L.latLng(segmentWaypoints[0][1], segmentWaypoints[0][0]))
          }
          allRouteCoordinates.push(L.latLng(segmentWaypoints[segmentWaypoints.length - 1][1], segmentWaypoints[segmentWaypoints.length - 1][0]))
        }
        
        // Mover para o próximo segmento (sobrepondo 1 ponto)
        currentIndex += segmentSize - 1
        segmentNumber++
        
        // Pausa entre requests para evitar rate limiting
        if (currentIndex < waypoints.length - 1) {
          await new Promise(resolve => setTimeout(resolve, 200))
        }
      }
      
      console.log(`✅ Rota completa: ${allRouteCoordinates.length} pontos de ${segmentNumber - 1} segmentos`)
      return allRouteCoordinates.length > 0 ? allRouteCoordinates : null
    }
  } catch (error) {
    console.error('❌ Erro na requisição Google Maps:', error)
    return null
  }
}

// Função auxiliar para processar um único segmento de rota
async function processSingleGoogleRoute(directionsService: any, waypoints: Array<[number, number]>): Promise<L.LatLng[] | null> {
  if (waypoints.length < 2) return null
  
  return new Promise((resolve, reject) => {
    const origin = new window.google.maps.LatLng(waypoints[0][1], waypoints[0][0])
    const destination = new window.google.maps.LatLng(waypoints[waypoints.length - 1][1], waypoints[waypoints.length - 1][0])
    
    // Waypoints intermediários
    const intermediateWaypoints = waypoints.slice(1, -1).map(point => ({
      location: new window.google.maps.LatLng(point[1], point[0]),
      stopover: true
    }))

    const request = {
      origin,
      destination,
      waypoints: intermediateWaypoints,
      travelMode: window.google.maps.TravelMode.DRIVING,
      optimizeWaypoints: false, // Não otimizar para manter ordem
      unitSystem: window.google.maps.UnitSystem.METRIC
    }

    directionsService.route(request, (result: any, status: any) => {
      if (status === 'OK' && result?.routes?.[0]) {
        const route = result.routes[0]
        const routeCoordinates: L.LatLng[] = []
        
        // Usar overview_polyline para ter a rota completa
        if (route.overview_polyline?.points) {
          const decodedPath = window.google.maps.geometry.encoding.decodePath(route.overview_polyline.points)
          decodedPath.forEach((point: any) => {
            routeCoordinates.push(L.latLng(point.lat(), point.lng()))
          })
        } else {
          // Fallback: usar os steps
          route.legs.forEach((leg: any) => {
            leg.steps.forEach((step: any) => {
              if (step.polyline?.points) {
                const stepPath = window.google.maps.geometry.encoding.decodePath(step.polyline.points)
                stepPath.forEach((point: any) => {
                  routeCoordinates.push(L.latLng(point.lat(), point.lng()))
                })
              }
            })
          })
        }
        
        // Atualizar distância total
        const segmentDistance = route.legs.reduce((total: number, leg: any) => total + leg.distance.value, 0) / 1000
        distanciaTotal.value += Number(segmentDistance) || 0
        
        resolve(routeCoordinates)
      } else {
        console.warn('❌ Erro no Google Maps segment:', status)
        resolve(null)
      }
    })
  })
}

// Função para inicializar o mapa
function initMap() {
  if (!mapContainer.value) return
  
  map = L.map('mapa-itinerario').setView([-23.5505, -46.6333], 10) // São Paulo como centro inicial
  
  // Adicionar camada do mapa
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)
  
  markersLayer = L.layerGroup().addTo(map)
  routeLayer = L.layerGroup().addTo(map)
}


// Função principal usando Google Maps com cache
async function fetchRealRoute(coordinates: Array<[number, number]>): Promise<L.LatLng[] | null> {
  console.log('🛣️ Buscando rotas reais para', coordinates.length, 'pontos')
  if (coordinates.length < 2) return null
  
  try {
    // PRIMEIRO: Verificar se existe no cache
    console.log('🔍 Verificando cache de rotas...')
    const cachedRoute = await getCachedRoute(coordinates)
    if (cachedRoute) {
      console.log('✅ Rota encontrada no cache! Economizando API request')
      return cachedRoute
    }
    
    console.log('📡 Rota não encontrada no cache, usando Google Maps API...')
    
    // Carregar Google Maps API se necessário
    if (!googleMapsLoaded) {
      console.log('🌐 Carregando Google Maps API...')
      await loadGoogleMapsAPI()
    }
    
    // Usar Google Maps para calcular a rota
    const googleRoute = await getGoogleRoute(coordinates)
    if (googleRoute && googleRoute.length > 0) {
      console.log('✅ Rota calculada com Google Maps!')
      
      // SALVAR no cache para próximas vezes
      await saveRouteToCache(coordinates, googleRoute, distanciaTotal.value)
      
      return googleRoute
    }
    
    console.warn('❌ Google Maps falhou, usando linhas retas')
    return null
  } catch (error) {
    console.error('❌ Erro no sistema de rotas:', error)
    return null
  }
}

// Função para buscar rota no cache via API Laravel
async function getCachedRoute(coordinates: Array<[number, number]>): Promise<L.LatLng[] | null> {
  try {
    const response = await fetch('http://localhost:8002/api/route-cache/find', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        waypoints: coordinates
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      if (data.success && data.route) {
        console.log(`💾 Cache hit! Rota de ${data.route.waypoints_count} pontos encontrada`)
        
        // Converter coordenadas do cache para Leaflet
        const cachedCoords = data.route.coordinates.map((coord: [number, number]) => 
          L.latLng(coord[0], coord[1])
        )
        
        // Atualizar distância total
        distanciaTotal.value = Number(data.route.total_distance) || 0
        
        return cachedCoords
      }
    }
  } catch (error) {
    console.warn('⚠️ Erro ao buscar cache:', error)
  }
  
  return null
}

// Função para salvar rota no cache via API Laravel
async function saveRouteToCache(
  coordinates: Array<[number, number]>, 
  routeCoords: L.LatLng[], 
  totalDistance: number
): Promise<void> {
  try {
    console.log('💾 Salvando rota no cache...')
    
    // Converter Leaflet coords para array simples
    const coordsArray = routeCoords.map(coord => [coord.lat, coord.lng])
    
    const response = await fetch('http://localhost:8002/api/route-cache/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        waypoints: coordinates,
        route_coordinates: coordsArray,
        total_distance: totalDistance,
        source: 'google_maps'
      })
    })
    
    if (response.ok) {
      console.log('✅ Rota salva no cache com sucesso!')
    } else {
      console.warn('⚠️ Falha ao salvar no cache')
    }
  } catch (error) {
    console.warn('⚠️ Erro ao salvar cache:', error)
  }
}


// Função para adicionar marcadores e rota
async function addMarkersAndRoute() {
  if (!map || !markersLayer || !routeLayer) return
  
  // Limpar camadas existentes
  markersLayer.clearLayers()
  routeLayer.clearLayers()
  
  const entregasComGPS = pacoteData.value.pedidos
    .map((pedido, originalIndex) => ({ ...pedido, originalIndex }))
    .filter(p => p.gps_lat && p.gps_lon)
  
  if (!entregasComGPS.length) return
  
  const latlngs: L.LatLng[] = []
  const routeCoordinates: Array<[number, number]> = []
  
  // Adicionar marcadores
  entregasComGPS.forEach((entrega, index) => {
    const lat = convertCoordinate(entrega.gps_lat!)
    const lng = convertCoordinate(entrega.gps_lon!)
    
    if (lat && lng) {
      latlngs.push(L.latLng(lat, lng))
      routeCoordinates.push([lng, lat]) // Para OSRM: [longitude, latitude]
      
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
  
  // Buscar e adicionar rota real seguindo as ruas
  if (routeCoordinates.length > 1) {
    // Mostrar um indicador de carregamento na rota
    const loadingPolyline = L.polyline(latlngs, {
      color: '#cccccc',
      weight: 2,
      opacity: 0.5,
      dashArray: '5, 5'
    })
    routeLayer.addLayer(loadingPolyline)
    
    // Buscar rota real
    const realRoute = await fetchRealRoute(routeCoordinates)
    
    // Remover linha temporária
    routeLayer.removeLayer(loadingPolyline)
    
    if (realRoute) {
      // Adicionar rota real seguindo as ruas
      const realPolyline = L.polyline(realRoute, {
        color: '#2196F3',
        weight: 5,
        opacity: 0.8,
        lineJoin: 'round',
        lineCap: 'round'
      })
      
      routeLayer.addLayer(realPolyline)
      
      // Ajustar zoom para mostrar toda a rota real
      map.fitBounds(realPolyline.getBounds(), { padding: [20, 20] })
    } else {
      // Fallback: usar linha reta se não conseguir buscar rota real
      const fallbackPolyline = L.polyline(latlngs, {
        color: '#FF9800', // Laranja para indicar que é fallback
        weight: 4,
        opacity: 0.7,
        dashArray: '10, 5'
      })
      
      routeLayer.addLayer(fallbackPolyline)
      map.fitBounds(fallbackPolyline.getBounds(), { padding: [20, 20] })
      
      // Calcular distância usando Haversine como fallback
      calculateTotalDistance()
    }
  } else if (latlngs.length === 1) {
    // Se há apenas um ponto, centralizar nele
    map.setView(latlngs[0], 14)
  }
}

// Função para buscar dados do itinerário
async function fetchItinerario() {
  try {
    loading.value = true
    const pacoteId = route.params.id as string
    
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
      
      await nextTick()
      addMarkersAndRoute()
    } else {
      console.error('Erro ao buscar itinerário:', response.message)
    }
  } catch (error) {
    console.error('Erro ao buscar itinerário:', error)
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