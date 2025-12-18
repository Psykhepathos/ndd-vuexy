<template>
  <div>
    <!-- Header simples -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold text-high-emphasis mb-1">
          Calculadora de Vale Pedágio
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Calcule rotas, pedágios e combustível de forma inteligente
        </p>
      </div>

      <div class="d-flex gap-2">
        <VBtn
          prepend-icon="tabler-history"
          variant="outlined"
          color="primary"
        >
          Histórico
        </VBtn>
        <VBtn
          :prepend-icon="isDrawerOpen ? 'tabler-x' : 'tabler-calculator'"
          :variant="isDrawerOpen ? 'tonal' : 'flat'"
          :color="isDrawerOpen ? 'error' : 'primary'"
          @click="toggleDrawer"
        >
          {{ isDrawerOpen ? 'Fechar' : 'Abrir' }} Calculator
        </VBtn>
      </div>
    </div>

    <!-- Layout Principal -->
    <VRow class="match-height">
      <!-- Coluna do Mapa -->
      <VCol
        :cols="12"
        :lg="isDrawerOpen ? 8 : 12"
        class="transition-all duration-300"
      >
        <!-- Card do Mapa -->
        <VCard class="h-100 position-relative overflow-hidden">
          <VCardText class="pa-0 h-100">
            <div id="map-container" class="w-100 h-100" style="min-height: 600px;"></div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Coluna da Sidebar (Calculator) -->
      <VCol
        v-if="isDrawerOpen"
        :cols="12"
        :lg="4"
      >
        <div class="d-flex flex-column gap-4">
          <!-- Resultados da Rota -->
          <VCard>
            <VCardItem>
              <VCardTitle class="text-h6 font-weight-medium">
                Resultados da Rota
              </VCardTitle>
            </VCardItem>

            <VCardText>
              <!-- Resultados em linha única com ícones -->
              <div class="d-flex justify-space-around align-center pa-2">
                <!-- Combustível -->
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-gas-station" color="primary" size="20" />
                  <div>
                    <div class="text-body-2 font-weight-bold">R$ {{ combustivel.valor }}</div>
                    <div class="text-caption text-medium-emphasis">Combustível</div>
                  </div>
                </div>

                <!-- Tempo -->
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-clock" color="success" size="20" />
                  <div>
                    <div class="text-body-2 font-weight-bold">{{ tempo.valor }}h</div>
                    <div class="text-caption text-medium-emphasis">Tempo</div>
                  </div>
                </div>

                <!-- Consumo -->
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-car" color="info" size="20" />
                  <div>
                    <div class="text-body-2 font-weight-bold">{{ consumo.valor }}</div>
                    <div class="text-caption text-medium-emphasis">KM/L</div>
                  </div>
                </div>

                <!-- Eixos -->
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-road" color="warning" size="20" />
                  <div>
                    <div class="text-body-2 font-weight-bold">{{ eixos.valor }}</div>
                    <div class="text-caption text-medium-emphasis">Eixos</div>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Pontos da Rota -->
          <VCard>
            <VCardItem>
              <VCardTitle class="text-h6 font-weight-medium">
                Pontos da Rota ({{ pontosRota.length }})
              </VCardTitle>
              <template #append>
                <VBtn
                  icon="tabler-plus"
                  variant="tonal"
                  color="primary"
                  size="small"
                  @click="adicionarPonto"
                />
              </template>
            </VCardItem>

            <VCardText class="pa-0" style="max-height: 300px; overflow-y: auto;">
              <draggable
                v-model="pontosRotaExibidos"
                @end="onDragEnd"
                item-key="id"
                handle=".drag-handle"
                ghost-class="ghost-item"
                chosen-class="chosen-item"
              >
                <template #item="{ element: ponto, index }">
                  <VListItem class="draggable-item">
                    <template #prepend>
                      <div class="d-flex align-center gap-2">
                        <!-- Handle de arrastar -->
                        <VIcon
                          icon="tabler-grip-vertical"
                          size="16"
                          class="drag-handle"
                          color="medium-emphasis"
                        />
                        <!-- Avatar com número centralizado -->
                        <div
                          class="route-number-circle"
                          :class="{
                            'route-number-start': getGlobalIndex(index) === 0,
                            'route-number-end': getGlobalIndex(index) === pontosRota.length - 1,
                            'route-number-middle': getGlobalIndex(index) !== 0 && getGlobalIndex(index) !== pontosRota.length - 1
                          }"
                        >
                          <span class="route-number-text">{{ getGlobalIndex(index) + 1 }}</span>
                        </div>
                      </div>
                    </template>

                    <VListItemTitle class="text-body-2">
                      {{ ponto.cidade }}
                    </VListItemTitle>
                    <VListItemSubtitle class="text-caption">
                      {{ ponto.endereco }}
                    </VListItemSubtitle>

                    <template #append>
                      <VBtn
                        v-if="pontosRota.length > 2"
                        icon="tabler-x"
                        variant="text"
                        color="error"
                        size="x-small"
                        @click="removerPonto(index)"
                      />
                    </template>
                  </VListItem>
                </template>
              </draggable>
            </VCardText>

            <!-- Paginação -->
            <VCardActions v-if="totalPages > 1" class="justify-center">
              <VPagination
                v-model="currentPage"
                :length="totalPages"
                :total-visible="3"
                size="small"
                color="primary"
              />
            </VCardActions>
          </VCard>

          <!-- Configurações -->
          <VCard>
            <VCardItem>
              <VCardTitle class="text-h6 font-weight-medium">
                Configurações
              </VCardTitle>
            </VCardItem>

            <VCardText>
              <!-- Tipo de Rota -->
              <div class="mb-4">
                <div class="text-body-2 font-weight-medium mb-2">Tipo de Rota</div>
                <div class="d-flex gap-2">
                  <VBtn
                    :variant="preferencia === 'rapida' ? 'flat' : 'outlined'"
                    :color="preferencia === 'rapida' ? 'primary' : 'default'"
                    class="flex-grow-1"
                    @click="preferencia = 'rapida'"
                  >
                    Rápida
                  </VBtn>
                  <VBtn
                    :variant="preferencia === 'curta' ? 'flat' : 'outlined'"
                    :color="preferencia === 'curta' ? 'primary' : 'default'"
                    class="flex-grow-1"
                    @click="preferencia = 'curta'"
                  >
                    Curta
                  </VBtn>
                  <VBtn
                    :variant="preferencia === 'economica' ? 'flat' : 'outlined'"
                    :color="preferencia === 'economica' ? 'primary' : 'default'"
                    class="flex-grow-1"
                    @click="preferencia = 'economica'"
                  >
                    Econômica
                  </VBtn>
                </div>
              </div>

              <!-- Opções -->
              <div class="mb-4">
                <div class="d-flex gap-4">
                  <VCheckbox
                    v-model="maisOpcoes.evitarPedagio"
                    label="Evitar pedágios"
                    color="warning"
                    density="compact"
                    hide-details
                  />
                  <VCheckbox
                    v-model="maisOpcoes.priorizarRodovias"
                    label="Priorizar rodovias"
                    color="primary"
                    density="compact"
                    hide-details
                  />
                </div>
              </div>

              <!-- Botão Calcular -->
              <VBtn
                color="primary"
                size="large"
                block
                @click="calcularRota"
                :loading="loading"
                prepend-icon="tabler-calculator"
              >
                Calcular Rota
              </VBtn>
            </VCardText>
          </VCard>
        </div>
      </VCol>
    </VRow>
  </div>
</template>

<script setup lang="ts">
import L from 'leaflet'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { API_BASE_URL, apiFetch } from '@/config/api'

// Interfaces
interface Pedido {
  gps_lat: string
  gps_lon: string
  [key: string]: any
}

// Definição da página usando o padrão Vuexy
definePage({
  meta: {
    title: 'Calculadora de Vale Pedágio'
  }
})

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

// Estados do drawer e formulário
const isDrawerOpen = ref(true)
const loading = ref(false)
const distanciaTotal = ref<number>(0)

// Mapa
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let routeLayer: L.LayerGroup | null = null

// Lista de pontos da rota (15 pontos do pacote 3043368)
const pontosRota = ref([
  { id: 1, cidade: 'Belo Horizonte', endereco: 'Rua da Bahia, 1148 - Centro' },
  { id: 2, cidade: 'Contagem', endereco: 'Av. João César de Oliveira, 1355 - Eldorado' },
  { id: 3, cidade: 'Betim', endereco: 'Rua Goiás, 367 - Centro' },
  { id: 4, cidade: 'Ibirité', endereco: 'Av. Comendador Lúcio Pereira, 250 - Centro' },
  { id: 5, cidade: 'Sarzedo', endereco: 'Rua Principal, 128 - Centro' },
  { id: 6, cidade: 'Brumadinho', endereco: 'Praça Harmonia, 45 - Centro' },
  { id: 7, cidade: 'Igarapé', endereco: 'Rua Tiradentes, 89 - Centro' },
  { id: 8, cidade: 'Juatuba', endereco: 'Av. Brasil, 234 - Centro' },
  { id: 9, cidade: 'Mateus Leme', endereco: 'Rua São João, 156 - Centro' },
  { id: 10, cidade: 'Itaúna', endereco: 'Praça Getúlio Vargas, 78 - Centro' },
  { id: 11, cidade: 'Divinópolis', endereco: 'Av. Paraná, 345 - Centro' },
  { id: 12, cidade: 'Santo Antônio do Monte', endereco: 'Rua Direita, 123 - Centro' },
  { id: 13, cidade: 'Oliveira', endereco: 'Praça da Matriz, 67 - Centro' },
  { id: 14, cidade: 'Passos', endereco: 'Av. Juca Stockler, 234 - Centro' },
  { id: 15, cidade: 'São Sebastião do Paraíso', endereco: 'Rua XV de Novembro, 189 - Centro' }
])

// Paginação dinâmica baseada na altura disponível
const currentPage = ref(1)
const itemsPerPage = computed(() => 6) // Mostrar 6 itens por página para forçar paginação
const totalPages = computed(() => Math.ceil(pontosRota.value.length / itemsPerPage.value))

const maisOpcoes = ref({
  priorizarRodovias: true,
  evitarPedagio: false,
  evitarBalsa: true
})

const preferencia = ref('rapida')
const tipoVeiculo = ref('caminhao')

// Dados da rota (preenchidos com valores realistas)
const combustivel = ref({ valor: '87,65' })
const consumo = ref({ valor: '8,2' })
const tempo = ref({ valor: '6' })
const eixos = ref({ valor: '3' })

// Pontos exibidos (mutável para drag & drop)
const pontosRotaExibidos = ref<Array<{ id: number, cidade: string, endereco: string }>>([])

// Computed para pontos paginados (apenas para leitura)
const pontosRotaPaginados = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return pontosRota.value.slice(start, end)
})

// Watcher para sincronizar pontosRotaExibidos com pontosRotaPaginados
watch(pontosRotaPaginados, (newValue) => {
  pontosRotaExibidos.value = [...newValue]
}, { immediate: true })

// Função para inicializar o mapa
function initMap() {
  const container = document.getElementById('map-container')
  if (!container) return

  // Criar o mapa centrado no Brasil
  map = L.map(container, {
    zoomControl: false
  }).setView([-14.2350, -51.9253], 4)

  // Adicionar tile layer do OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)

  // Adicionar controle de zoom no canto direito
  L.control.zoom({
    position: 'topright'
  }).addTo(map)

  // Criar camadas para marcadores e rotas
  markersLayer = L.layerGroup().addTo(map)
  routeLayer = L.layerGroup().addTo(map)

  // Adicionar pontos de exemplo
  addExamplePoints()
}

// Função para carregar rota do banco de dados
async function loadRotaFromDatabase() {
  try {
    // Buscar rota do pacote 3043368 da API usando POST com parâmetro correto
    const response = await apiFetch(`${API_BASE_URL}/api/pacotes/itinerario`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        codPac: 3043368
      })
    })

    const data = await response.json()
    console.log('Resposta da API:', data)

    if (data.success && data.data && data.data.pedidos && data.data.pedidos.length > 0) {
      // Extrair coordenadas GPS dos pedidos
      // CORREÇÃO: Backend agora retorna number (float) após BUG MODERADO #1
      const coordenadas = data.data.pedidos.map((pedido: Pedido) => {
        // Type guard: Se já é number, usar direto; se é string, converter
        const lat = typeof pedido.gps_lat === 'number'
          ? pedido.gps_lat
          : parseFloat(pedido.gps_lat.replace(',', '.'))
        const lon = typeof pedido.gps_lon === 'number'
          ? pedido.gps_lon
          : parseFloat(pedido.gps_lon.replace(',', '.'))
        return [lat, lon]
      }).filter((coords: number[]) => !isNaN(coords[0]) && !isNaN(coords[1]))

      console.log('Coordenadas extraídas do banco:', coordenadas.length, 'pontos')
      return coordenadas
    }

    console.warn('Rota não encontrada no banco, usando fallback')
    return null
  } catch (error) {
    console.error('Erro ao carregar rota do banco:', error)
    return null
  }
}

// Função wrapper para manter compatibilidade
async function addExamplePoints() {
  if (!map || !markersLayer || !routeLayer) return

  // Limpar camadas existentes
  markersLayer.clearLayers()
  routeLayer.clearLayers()

  // Tentar carregar dados do banco de dados primeiro
  const apiData = await loadRotaFromDatabase()

  if (!apiData || apiData.length === 0) {
    console.warn('Nenhum dado encontrado no banco, usando pontos de exemplo')
    // Fallback com pontos de exemplo
    const exemploCoords = [
      [-19.4342, -43.8322], // Exemplo Jaboticatubas
      [-19.4341, -43.8332],
      [-19.4340, -43.8323]
    ]

    exemploCoords.forEach((coord, index) => {
      const marker = L.marker([coord[0], coord[1]], {
        icon: L.divIcon({
          html: `
            <div style="
              background-color: #2196F3;
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
            ">${index + 1}</div>
          `,
          className: 'custom-div-icon',
          iconSize: [30, 30],
          iconAnchor: [15, 15]
        })
      })
      marker.bindPopup(`<strong>Ponto ${index + 1}</strong><br>Exemplo de entrega`)
      markersLayer?.addLayer(marker)
    })

    // @ts-expect-error - Leaflet type incompatibility (known issue)
    const polyline = L.polyline(exemploCoords, {
      color: '#2196F3',
      weight: 5,
      opacity: 0.8
    })
    routeLayer?.addLayer(polyline)
    map?.fitBounds(polyline.getBounds(), { padding: [20, 20] })
    return
  }

  // Processar dados reais do banco
  const latlngs: L.LatLng[] = []

  // Adicionar marcadores para cada ponto da rota
  apiData.forEach((coord: number[], index: number) => {
    const [lat, lng] = coord

    if (lat && lng) {
      latlngs.push(L.latLng(lat, lng))

      // Criar ícone personalizado baseado na sequência (igual ao itinerário)
      const isFirst = index === 0
      const isLast = index === apiData.length - 1

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
          ">${index + 1}</div>
        `,
        className: 'custom-div-icon',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      })

      const marker = L.marker([lat, lng], {
        icon: customIcon
      })
        .bindPopup(`
          <div style="min-width: 200px;">
            <strong>Entrega ${index + 1}</strong><br>
            <small>Pacote 3043368 - Rota PC7</small><br>
            <hr style="margin: 8px 0;">
            <strong>Coordenadas:</strong><br>
            Lat: ${lat.toFixed(6)}<br>
            Lng: ${lng.toFixed(6)}
          </div>
        `)

      markersLayer?.addLayer(marker)
    }
  })

  // Buscar e adicionar rota real seguindo as ruas (igual ao itinerário)
  if (apiData.length > 1) {
    // Converter para formato Google Maps: [longitude, latitude]
    const routeCoordinates = apiData.map((coord: number[]) => [coord[1], coord[0]]) // [lng, lat]

    // Mostrar um indicador de carregamento na rota
    const loadingPolyline = L.polyline(latlngs, {
      color: '#cccccc',
      weight: 2,
      opacity: 0.5,
      dashArray: '5, 5'
    })
    routeLayer?.addLayer(loadingPolyline)

    console.log('🗺️ Buscando rota real para', routeCoordinates.length, 'pontos')

    // Buscar rota real usando Google Maps
    const realRoute = await fetchRealRoute(routeCoordinates)

    // Remover linha temporária
    routeLayer?.removeLayer(loadingPolyline)

    if (realRoute) {
      // Adicionar rota real seguindo as ruas
      const realPolyline = L.polyline(realRoute, {
        color: '#2196F3',
        weight: 5,
        opacity: 0.8,
        lineJoin: 'round',
        lineCap: 'round'
      })

      routeLayer?.addLayer(realPolyline)

      console.log(`✅ Rota real carregada: ${realRoute.length} pontos, ${distanciaTotal.value.toFixed(1)}km`)

      // Ajustar zoom para mostrar toda a rota real
      map?.fitBounds(realPolyline.getBounds(), { padding: [20, 20] })
    } else {
      // Fallback: usar linha reta se não conseguir buscar rota real
      console.warn('⚠️ Usando rota fallback (linha reta)')
      const fallbackPolyline = L.polyline(latlngs, {
        color: '#FF9800', // Laranja para indicar que é fallback
        weight: 4,
        opacity: 0.7,
        dashArray: '10, 5'
      })

      routeLayer?.addLayer(fallbackPolyline)
      map?.fitBounds(fallbackPolyline.getBounds(), { padding: [20, 20] })

      // Calcular distância usando Haversine como fallback
      let totalDistance = 0
      for (let i = 0; i < apiData.length - 1; i++) {
        const [lat1, lng1] = apiData[i]
        const [lat2, lng2] = apiData[i + 1]
        totalDistance += calculateDistance(lat1, lng1, lat2, lng2)
      }
      distanciaTotal.value = totalDistance
    }
  } else if (latlngs.length === 1) {
    // Se há apenas um ponto, centralizar nele
    map?.setView(latlngs[0], 14)
  }
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
    const response = await apiFetch(`${API_BASE_URL}/api/route-cache/find`, {
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

    const response = await apiFetch(`${API_BASE_URL}/api/route-cache/save`, {
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

// Função para calcular índice global considerando a paginação
function getGlobalIndex(localIndex: number): number {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return start + localIndex
}

// Funções
function toggleDrawer() {
  isDrawerOpen.value = !isDrawerOpen.value

  // Redimensionar mapa após transição suave
  setTimeout(() => {
    if (map) {
      map.invalidateSize()
    }
  }, 350)
}

// Funções para manipular pontos da rota
function adicionarPonto() {
  const novoPonto = {
    id: Date.now(),
    cidade: 'Nova Cidade',
    endereco: 'Novo Endereço'
  }
  pontosRota.value.push(novoPonto)
}

function removerPonto(index: number) {
  if (pontosRota.value.length > 2) {
    pontosRota.value.splice(index, 1)
  }
}

function onDragEnd() {
  console.log('🔄 Reordenando pontos da página', currentPage.value)

  // Atualizar array principal com nova ordem dos pontos exibidos
  const start = (currentPage.value - 1) * itemsPerPage.value

  // Substituir itens na posição correta do array completo
  pontosRotaExibidos.value.forEach((item, index) => {
    if (start + index < pontosRota.value.length) {
      pontosRota.value[start + index] = { ...item }
    }
  })

  // Atualizar IDs após reordenação para manter consistência
  pontosRota.value.forEach((ponto, index) => {
    ponto.id = index + 1
  })

  console.log(`✅ Página ${currentPage.value} reordenada:`, pontosRotaExibidos.value.map((p, idx) => `${start + idx + 1}. ${p.cidade}`))
  console.log('📋 Array completo:', pontosRota.value.map((p, idx) => `${idx + 1}. ${p.cidade}`))

  // Atualizar mapa com nova ordem completa
  addExamplePoints()
}

async function calcularRota() {
  if (pontosRota.value.length < 2) {
    // Mostrar erro de validação
    return
  }

  loading.value = true

  try {
    // Aqui você faria a chamada para a API de cálculo de rota
    await new Promise(resolve => setTimeout(resolve, 2000))

    // Atualizar dados da rota
    combustivel.value.valor = '45,80'
    consumo.value.valor = '12,5'
    tempo.value.valor = '3'
    eixos.value.valor = '2'

    console.log('Rota calculada:', {
      pontosRota: pontosRota.value,
      preferencia: preferencia.value,
      tipoVeiculo: tipoVeiculo.value,
      maisOpcoes: maisOpcoes.value
    })
  } catch (error) {
    console.error('Erro ao calcular rota:', error)
  } finally {
    loading.value = false
  }
}

// Inicializar o mapa quando o componente for montado
onMounted(async () => {
  await nextTick()
  initMap()
})
</script>

<style scoped>
/* === Otimizações para Dark/Light Themes === */

/* Transições suaves para mudança de tema */
* {
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

/* Mapa - Controles Leaflet otimizados */
:deep(.leaflet-container) {
  background: rgba(var(--v-theme-surface), 1);
  border-radius: inherit;
}

:deep(.leaflet-control-zoom) {
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(var(--v-theme-shadow-key-umbra-opacity), 0.2);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

:deep(.leaflet-control-zoom a) {
  background-color: rgba(var(--v-theme-surface), 1);
  color: rgb(var(--v-theme-on-surface));
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

:deep(.leaflet-control-zoom a:hover) {
  background-color: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

/* Popups do mapa */
:deep(.leaflet-popup-content-wrapper) {
  background: rgba(var(--v-theme-surface), 1);
  color: rgb(var(--v-theme-on-surface));
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(var(--v-theme-shadow-key-umbra-opacity), 0.15);
}

:deep(.leaflet-popup-tip) {
  background: rgba(var(--v-theme-surface), 1);
}

/* Ícones customizados dos marcadores */
:deep(.custom-div-icon) {
  background: transparent !important;
  border: none !important;
}

/* Layout responsivo melhorado */
.transition-all {
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Scrollbar personalizada que respeita o tema */
:deep(.v-card-text) {
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-on-surface-variant), 0.2) transparent;
}

:deep(.v-card-text::-webkit-scrollbar) {
  width: 6px;
  height: 6px;
}

:deep(.v-card-text::-webkit-scrollbar-track) {
  background: rgba(var(--v-theme-surface-variant), 0.1);
  border-radius: 3px;
}

:deep(.v-card-text::-webkit-scrollbar-thumb) {
  background: rgba(var(--v-theme-on-surface-variant), 0.2);
  border-radius: 3px;
}

:deep(.v-card-text::-webkit-scrollbar-thumb:hover) {
  background: rgba(var(--v-theme-on-surface-variant), 0.4);
}

/* Estados de hover e focus melhorados */
:deep(.v-list-item:hover) {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

:deep(.v-btn:hover) {
  transform: translateY(-1px);
}

:deep(.v-card:hover) {
  box-shadow: 0 8px 24px rgba(var(--v-theme-shadow-key-umbra-opacity), 0.12);
}

/* Otimização para telas pequenas */
@media (max-width: 1279px) {
  :deep(.v-col) {
    padding-inline: 8px;
  }
}

/* Garante que o conteúdo nunca vaze */
.overflow-hidden {
  overflow: hidden;
}

/* Loading states mais suaves */
:deep(.v-btn--loading) {
  pointer-events: none;
}

:deep(.v-progress-circular) {
  color: rgb(var(--v-theme-primary));
}

/* === Círculos dos números das rotas === */
.route-number-circle {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  flex-shrink: 0;
  position: relative;
}

.route-number-start {
  background-color: rgb(var(--v-theme-success));
}

.route-number-end {
  background-color: rgb(var(--v-theme-error));
}

.route-number-middle {
  background-color: rgb(var(--v-theme-primary));
}

.route-number-text {
  color: white;
  font-size: 12px;
  font-weight: bold;
  line-height: 1;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

/* === Drag and Drop === */
.drag-handle {
  cursor: grab;
  opacity: 0.6;
  transition: all 0.2s ease;
}

.drag-handle:hover {
  opacity: 1;
  color: rgb(var(--v-theme-primary));
}

.drag-handle:active {
  cursor: grabbing;
}

.draggable-item {
  transition: all 0.2s ease;
}

.ghost-item {
  opacity: 0.3;
  background-color: rgba(var(--v-theme-primary), 0.1);
}

.chosen-item {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>