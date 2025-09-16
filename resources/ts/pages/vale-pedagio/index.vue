<template>
  <div class="page-container">
    <!-- Mapa de Fundo -->
    <div 
      class="map-container" 
      :class="{ 'map-compressed': isDrawerOpen }"
    >
      <div id="map-container" class="map-inner"></div>
      
      <!-- Botão FAB sempre visível -->
      <VBtn
        class="calcular-rota-fab"
        color="warning"
        size="large"
        elevation="8"
        @click="toggleDrawer"
      >
        <VIcon :icon="isDrawerOpen ? 'tabler-x' : 'tabler-calculator'" start />
        {{ isDrawerOpen ? 'Fechar' : 'Calcular rota' }}
      </VBtn>
    </div>

    <!-- Navigation Drawer -->
    <VNavigationDrawer
      v-model="isDrawerOpen"
      location="end"
      :width="drawerWidth"
      temporary
      class="elevation-4 sidebar-drawer"
      :scrim="false"
    >
      <!-- Header -->
      <div class="d-flex align-center justify-space-between pa-4 border-b">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-calculator" size="24" class="text-primary" />
          <div>
            <h6 class="text-h6 font-weight-bold text-high-emphasis">Calculadora de Pedágio</h6>
            <p class="text-body-2 text-medium-emphasis mb-0">Configure sua rota</p>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isDrawerOpen = false"
        />
      </div>

      <!-- Content without scroll -->
      <div class="sidebar-content">
        <VForm class="pa-2">
          <!-- Card principal: Pontos da Rota -->
          <VCard 
            variant="elevated" 
            elevation="2" 
            class="mb-4"
            style="background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.08), rgba(var(--v-theme-primary), 0.03)); border-radius: 12px; border: 1px solid rgba(var(--v-theme-primary), 0.1);"
          >
            <VCardText class="pa-3">
              <div class="d-flex align-center gap-2 mb-3">
                <VIcon icon="tabler-map-pin" size="20" color="primary" />
                <h6 class="text-h6 font-weight-bold text-high-emphasis">Pontos da Rota</h6>
                <VSpacer />
                <VBtn
                  icon="tabler-plus"
                  variant="tonal"
                  color="primary"
                  size="small"
                  @click="adicionarPonto"
                />
              </div>
              
              <div class="route-points-container" style="height: 320px; overflow-y: auto;">
                <VList density="compact" class="pa-0">
                  <draggable
                    v-model="pontosRotaPaginados"
                    :itemKey="(item) => item.id"
                    handle=".drag-handle"
                    @end="onDragEnd"
                  >
                    <template #item="{ element, index }">
                      <VListItem
                        class="pa-1 mb-1 route-point-item"
                        :class="{ 'route-point-origin': index === 0, 'route-point-destination': index === pontosRotaPaginados.length - 1 }"
                      >
                        <template #prepend>
                          <div class="d-flex align-center gap-2">
                            <VIcon
                              icon="tabler-grip-vertical"
                              size="16"
                              class="drag-handle cursor-grab"
                              color="primary"
                              style="opacity: 0.7;"
                            />
                            <VAvatar
                              size="28"
                              :color="index === 0 ? 'success' : (index === pontosRotaPaginados.length - 1 ? 'error' : 'primary')"
                            >
                              <span style="font-size: 0.75rem; font-weight: bold;">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</span>
                            </VAvatar>
                          </div>
                        </template>
                        
                        <VListItemTitle class="text-body-2 font-weight-medium">
                          {{ element.cidade }}
                        </VListItemTitle>
                        <VListItemSubtitle class="text-caption">
                          {{ element.endereco }}
                        </VListItemSubtitle>
                        
                        <template #append>
                          <VBtn
                            icon="tabler-x"
                            variant="text"
                            color="error"
                            size="small"
                            @click="removerPonto((currentPage - 1) * itemsPerPage + index)"
                            v-if="pontosRota.length > 2"
                            class="remove-point-btn"
                          />
                        </template>
                      </VListItem>
                    </template>
                  </draggable>
                </VList>
              </div>
              
              <!-- Paginação Padrão Vuexy -->
              <div v-if="totalPages > 1" class="d-flex align-center justify-center pa-2 border-t">
                <VPagination
                  v-model="currentPage"
                  :length="totalPages"
                  active-color="primary"
                  :total-visible="$vuetify.display.xs ? 3 : Math.min(totalPages, 5)"
                />
              </div>
            </VCardText>
          </VCard>

          <!-- Card secundário: Configurações Completas -->
          <VCard 
            variant="elevated" 
            elevation="1" 
            class="mb-3"
            style="background-color: rgba(var(--v-theme-surface)); border-radius: 8px; border: 1px solid rgba(var(--v-theme-outline), 0.12);"
          >
            <VCardText class="pa-3">
              <div class="d-flex align-center gap-2 mb-3">
                <VIcon icon="tabler-settings" size="18" color="secondary" />
                <span class="text-subtitle-2 font-weight-semibold text-high-emphasis">Configurações da Rota</span>
              </div>
              
              <!-- Data/Hora Saída e Expiração -->
              <VRow class="mb-3">
                <VCol cols="6">
                  <AppTextField
                    v-model="dataHoraFormatted"
                    label="Data Saída"
                    prepend-inner-icon="tabler-calendar"
                    readonly
                    density="compact"
                    hide-details
                  />
                </VCol>
                <VCol cols="6">
                  <AppTextField
                    v-model="dataExpiracaoFormatted"
                    label="Expiração Vale"
                    prepend-inner-icon="tabler-calendar-x"
                    readonly
                    density="compact"
                    hide-details
                  />
                </VCol>
              </VRow>

              <!-- Estatísticas da Rota em linha única -->
              <div class="d-flex gap-2 mb-3">
                <VCard variant="flat" color="primary" class="pa-3 text-center stats-card-improved flex-fill">
                  <VIcon icon="tabler-gas-station" size="16" color="white" class="mb-1" />
                  <div class="text-caption font-weight-bold text-white" style="font-size: 0.8rem;">R$ {{ combustivel.valor }}</div>
                  <div class="text-white" style="font-size: 0.65rem; opacity: 0.9;">Combustível</div>
                </VCard>
                
                <VCard variant="flat" color="success" class="pa-3 text-center stats-card-improved flex-fill">
                  <VIcon icon="tabler-car" size="16" color="white" class="mb-1" />
                  <div class="text-caption font-weight-bold text-white" style="font-size: 0.8rem;">{{ consumo.valor }} KM/L</div>
                  <div class="text-white" style="font-size: 0.65rem; opacity: 0.9;">Consumo</div>
                </VCard>
                
                <VCard variant="flat" color="warning" class="pa-3 text-center stats-card-improved flex-fill">
                  <VIcon icon="tabler-clock" size="16" color="white" class="mb-1" />
                  <div class="text-caption font-weight-bold text-white" style="font-size: 0.8rem;">{{ tempo.valor }}h</div>
                  <div class="text-white" style="font-size: 0.65rem; opacity: 0.9;">Tempo</div>
                </VCard>
                
                <VCard variant="flat" color="info" class="pa-3 text-center stats-card-improved flex-fill">
                  <VIcon icon="tabler-road" size="16" color="white" class="mb-1" />
                  <div class="text-caption font-weight-bold text-white" style="font-size: 0.8rem;">{{ eixos.valor }} eixos</div>
                  <div class="text-white" style="font-size: 0.65rem; opacity: 0.9;">Veículo</div>
                </VCard>
              </div>

              <!-- Opções da rota com cards bonitos -->
              <VRow class="mb-3">
                <VCol cols="4">
                  <VCard 
                    variant="outlined" 
                    :color="maisOpcoes.priorizarRodovias ? 'primary' : 'default'"
                    class="pa-2 text-center option-card cursor-pointer"
                    @click="maisOpcoes.priorizarRodovias = !maisOpcoes.priorizarRodovias"
                    :class="{ 'option-active': maisOpcoes.priorizarRodovias }"
                  >
                    <VIcon 
                      icon="tabler-road-sign" 
                      size="16" 
                      :color="maisOpcoes.priorizarRodovias ? 'primary' : 'grey'"
                      class="mb-1"
                    />
                    <div class="text-caption font-weight-medium">Rodovias</div>
                  </VCard>
                </VCol>
                <VCol cols="4">
                  <VCard 
                    variant="outlined" 
                    :color="maisOpcoes.evitarPedagio ? 'warning' : 'default'"
                    class="pa-2 text-center option-card cursor-pointer"
                    @click="maisOpcoes.evitarPedagio = !maisOpcoes.evitarPedagio"
                    :class="{ 'option-active': maisOpcoes.evitarPedagio }"
                  >
                    <VIcon 
                      icon="tabler-cash-off" 
                      size="16" 
                      :color="maisOpcoes.evitarPedagio ? 'warning' : 'grey'"
                      class="mb-1"
                    />
                    <div class="text-caption font-weight-medium">Evitar Pedágio</div>
                  </VCard>
                </VCol>
                <VCol cols="4">
                  <VCard 
                    variant="outlined" 
                    :color="maisOpcoes.evitarBalsa ? 'info' : 'default'"
                    class="pa-2 text-center option-card cursor-pointer"
                    @click="maisOpcoes.evitarBalsa = !maisOpcoes.evitarBalsa"
                    :class="{ 'option-active': maisOpcoes.evitarBalsa }"
                  >
                    <VIcon 
                      icon="tabler-ship-off" 
                      size="16" 
                      :color="maisOpcoes.evitarBalsa ? 'info' : 'grey'"
                      class="mb-1"
                    />
                    <div class="text-caption font-weight-medium">Evitar Balsa</div>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Tipo de rota -->
              <div class="d-flex align-center gap-3 mb-3">
                <span class="text-body-2 font-weight-semibold text-medium-emphasis" style="min-width: 100px;">
                  <VIcon icon="tabler-route" size="14" class="me-1" />
                  Tipo de rota:
                </span>
                <div class="d-flex gap-1" style="flex: 1;">
                  <VBtn
                    :variant="preferencia === 'rapida' ? 'flat' : 'outlined'"
                    :color="preferencia === 'rapida' ? 'primary' : 'default'"
                    @click="preferencia = 'rapida'"
                    size="small"
                    class="text-caption"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-rocket" size="12" class="me-1" />
                    Rápida
                  </VBtn>
                  <VBtn
                    :variant="preferencia === 'curta' ? 'flat' : 'outlined'"
                    :color="preferencia === 'curta' ? 'primary' : 'default'"
                    @click="preferencia = 'curta'"
                    size="small"
                    class="text-caption"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-route-2" size="12" class="me-1" />
                    Curta
                  </VBtn>
                  <VBtn
                    :variant="preferencia === 'economica' ? 'flat' : 'outlined'"
                    :color="preferencia === 'economica' ? 'primary' : 'default'"
                    @click="preferencia = 'economica'"
                    size="small"
                    class="text-caption"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-coin" size="12" class="me-1" />
                    Econ.
                  </VBtn>
                </div>
              </div>
              
              <!-- Veículo -->
              <div class="d-flex align-center gap-3 mb-0">
                <span class="text-body-2 font-weight-semibold text-medium-emphasis" style="min-width: 100px;">
                  <VIcon icon="tabler-car" size="14" class="me-1" />
                  Veículo:
                </span>
                <div class="d-flex gap-1" style="flex: 1;">
                  <VBtn
                    :variant="tipoVeiculo === 'carro' ? 'flat' : 'outlined'"
                    :color="tipoVeiculo === 'carro' ? 'success' : 'default'"
                    @click="tipoVeiculo = 'carro'"
                    size="small"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-car" size="14" />
                  </VBtn>
                  <VBtn
                    :variant="tipoVeiculo === 'moto' ? 'flat' : 'outlined'"
                    :color="tipoVeiculo === 'moto' ? 'success' : 'default'"
                    @click="tipoVeiculo = 'moto'"
                    size="small"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-motorbike" size="14" />
                  </VBtn>
                  <VBtn
                    :variant="tipoVeiculo === 'caminhao' ? 'flat' : 'outlined'"
                    :color="tipoVeiculo === 'caminhao' ? 'success' : 'default'"
                    @click="tipoVeiculo = 'caminhao'"
                    size="small"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-truck" size="14" />
                  </VBtn>
                  <VBtn
                    :variant="tipoVeiculo === 'van' ? 'flat' : 'outlined'"
                    :color="tipoVeiculo === 'van' ? 'success' : 'default'"
                    @click="tipoVeiculo = 'van'"
                    size="small"
                    style="flex: 1;"
                  >
                    <VIcon icon="tabler-bus" size="14" />
                  </VBtn>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Botão Calcular -->
          <VBtn
            color="primary" 
            variant="flat"
            size="large"
            block
            @click="calcularRota"
            :loading="loading"
            elevation="2"
            class="font-weight-medium text-white mt-4 calcular-btn-improved"
            style="border-radius: 10px; height: 44px; text-transform: none; letter-spacing: 0.25px;"
          >
            <VIcon icon="tabler-calculator" start size="18" />
            Calcular Rota
          </VBtn>

          </VForm>
        </div>
    </VNavigationDrawer>
  </div>
</template>

<script setup lang="ts">
import L from 'leaflet'
import { computed, nextTick, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'

// Definição da página usando o padrão Vuexy
definePage({
  meta: {
    title: 'Calculadora de Pedágio'
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
const drawerWidth = computed(() => window.innerWidth > 768 ? Math.floor(window.innerWidth * 0.45) : '100%')
const dataHora = ref(new Date())
const dataExpiracao = ref(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) // 30 dias a partir de hoje
const loading = ref(false)
const distanciaTotal = ref<number>(0)

// Mapa
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let routeLayer: L.LayerGroup | null = null

// Computed para formatar data e hora
const dataHoraFormatted = computed(() => {
  return dataHora.value.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

const dataExpiracaoFormatted = computed(() => {
  return dataExpiracao.value.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

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
const containerHeight = ref(400) // altura padrão maior
const itemHeight = 55 // altura mais precisa de cada item da lista
const itemsPerPage = computed(() => {
  // Calcular quantos itens cabem sem scroll - valor fixo menor para testar paginação
  return 6 // Mostrar 6 itens por página para forçar paginação
})
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
    const response = await fetch('http://localhost:8002/api/pacotes/itinerario', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        Pacote: {
          codPac: 3043368
        }
      })
    })
    
    const data = await response.json()
    console.log('Resposta da API:', data)
    
    if (data.success && data.data && data.data.pedidos && data.data.pedidos.length > 0) {
      // Extrair coordenadas GPS dos pedidos e converter vírgulas para pontos
      const coordenadas = data.data.pedidos.map(pedido => {
        const lat = parseFloat(pedido.gps_lat.replace(',', '.'))
        const lon = parseFloat(pedido.gps_lon.replace(',', '.'))
        return [lat, lon]
      }).filter(coords => !isNaN(coords[0]) && !isNaN(coords[1]))
      
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

// Função para converter coordenadas do formato Progress para decimal
function convertCoordinate(coord: string): number {
  if (!coord) return 0
  
  // Formato: "-23,2041" -> -23.2041
  const cleanCoord = coord.replace(',', '.')
  return parseFloat(cleanCoord)
}

// Função para adicionar marcadores e rota igual ao itinerário
async function addMarkersAndRoute() {
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
  apiData.forEach((coord, index) => {
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
    const routeCoordinates = apiData.map(coord => [coord[1], coord[0]]) // [lng, lat]
    
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

// Função wrapper para manter compatibilidade
async function addExamplePoints() {
  await addMarkersAndRoute()
}

// Funções
function toggleDrawer() {
  isDrawerOpen.value = !isDrawerOpen.value
  
  // Redimensionar mapa após transição
  setTimeout(() => {
    if (map) {
      map.invalidateSize()
    }
  }, 300)
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
  // Reconstruir array completo com a nova ordem da página atual
  const start = (currentPage.value - 1) * itemsPerPage.value
  const paginatedItems = pontosRotaPaginados.value
  
  // Substituir itens na posição correta do array completo
  paginatedItems.forEach((item, index) => {
    pontosRota.value[start + index] = item
  })
  
  // Atualizar IDs após reordenação
  pontosRota.value.forEach((ponto, index) => {
    ponto.id = index + 1
  })
  
  console.log('Pontos reordenados:', pontosRota.value.map(p => p.cidade))
}

// Computed para pontos paginados
const pontosRotaPaginados = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return pontosRota.value.slice(start, end)
})

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

// Função para calcular altura dinâmica
function updateContainerHeight() {
  const vh = window.innerHeight
  const headerHeight = 80 // altura do header da sidebar
  const formPadding = 32 // padding do form
  const containerPadding = 48 // padding do container agrupado
  const dateFieldsHeight = 60 // altura aproximada dos campos de data
  const statsCardsHeight = 60 // altura aproximada dos cards de estatísticas
  const optionsCardsHeight = 60 // altura aproximada dos cards de opções
  const pointsHeaderHeight = 40 // altura do header "Pontos da Rota"
  const paginationHeight = 40 // altura da paginação
  const typeVehicleHeight = 100 // altura das seções tipo/veículo
  const buttonHeight = 60 // altura do botão calcular
  const margin = 20 // margem extra
  
  const usedHeight = headerHeight + formPadding + containerPadding + 
                   dateFieldsHeight + statsCardsHeight + optionsCardsHeight + 
                   pointsHeaderHeight + paginationHeight + typeVehicleHeight + 
                   buttonHeight + margin
                   
  containerHeight.value = Math.max(200, vh - usedHeight)
}

// Inicializar o mapa quando o componente for montado
onMounted(async () => {
  await nextTick()
  initMap()
  updateContainerHeight()
  
  // Atualizar altura quando a janela redimensionar
  window.addEventListener('resize', updateContainerHeight)
})
</script>

<style scoped>
/* Page Layout */
.page-container {
  position: relative;
  min-height: auto;
  margin-top: 0;
}

/* Map Container */
.map-container {
  position: relative;
  width: 100%;
  height: 70vh;
  transition: all 0.3s ease;
  z-index: 1;
}

.map-container.map-compressed {
  right: 45%; /* Mapa comprimido à esquerda quando sidebar aberta */
}

.map-inner {
  width: 100%;
  height: 100%;
  position: relative;
  z-index: 1;
}

/* Botão Calcular Rota - Padrão Vuexy FAB */
.calcular-rota-fab {
  position: absolute;
  top: 80px;
  right: 20px;
  z-index: 1000;
  border-radius: 16px !important;
  text-transform: none !important;
  font-weight: 600 !important;
  letter-spacing: normal !important;
  height: 48px !important;
  padding: 0 20px !important;
}

.calcular-rota-fab:hover {
  transform: translateY(-1px);
}

/* Sidebar Drawer */
.sidebar-drawer {
  height: 100vh !important;
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  z-index: 1001;
  pointer-events: auto;
}

/* Remove overlay cinza */
:deep(.v-overlay__scrim) {
  display: none !important;
}

:deep(.v-navigation-drawer__scrim) {
  display: none !important;
}

.sidebar-content {
  height: calc(100vh - 80px);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.sidebar-content .pa-2 {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  overflow: hidden;
  padding: 4px 8px 4px 8px !important;
}

/* Header border */
.border-b {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}

/* Layout overflow control */
.page-container {
  overflow: visible;
}

.map-container {
  overflow: hidden;
}

.map-inner {
  overflow: hidden;
  pointer-events: auto !important;
}

/* Garante que o mapa sempre seja interativo */
:deep(.leaflet-container) {
  pointer-events: auto !important;
  z-index: 1 !important;
}

:deep(.leaflet-map-pane) {
  pointer-events: auto !important;
}

/* Botão Calcular Melhorado */
.calcular-btn-improved {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.8) 100%) !important;
  box-shadow: 0 3px 10px rgba(var(--v-theme-primary), 0.3) !important;
}

.calcular-btn-improved:hover {
  transform: translateY(-1px) !important;
  box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.4) !important;
}

.calcular-btn-improved:active {
  transform: translateY(0) !important;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.3) !important;
}

/* Paginação agora usa o padrão Vuexy - sem customização */

/* Leaflet map styling */
:deep(.leaflet-popup-content) {
  margin: 8px 12px;
  line-height: 1.4;
}

:deep(.custom-div-icon) {
  background: transparent !important;
  border: none !important;
}

/* Leaflet controls positioning - avoid FAB */
:deep(.leaflet-control-zoom) {
  margin-left: 10px !important;
  margin-bottom: 80px !important; /* Space for FAB */
}

/* Compact spacing */
.route-section {
  margin-bottom: 0.25rem;
}

.route-section:last-child {
  margin-bottom: 0;
}

/* Ultra compact components */
:deep(.v-checkbox .v-label) {
  font-size: 0.7rem !important;
  line-height: 1.2 !important;
}

:deep(.v-field__input) {
  min-height: 28px !important;
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

:deep(.v-btn--size-x-small) {
  min-height: 20px !important;
  padding: 0 6px !important;
  font-size: 0.7rem !important;
}

:deep(.v-btn--size-small) {
  min-height: 28px !important;
  padding: 0 8px !important;
}

:deep(.v-card) {
  min-height: auto !important;
}

:deep(.v-checkbox) {
  margin-bottom: 0 !important;
}

:deep(.v-input--density-compact) {
  --v-input-control-height: 28px;
  --v-field-input-padding-top: 2px;
  --v-field-input-padding-bottom: 2px;
}

/* Reduzir margens entre seções */
.mb-0 {
  margin-bottom: 0 !important;
}

.mb-1 {
  margin-bottom: 0.1rem !important;
}

.mb-2 {
  margin-bottom: 0.2rem !important;
}

/* Forçar redução de espaçamento apenas nas seções específicas */
.compact-section :deep(.v-row) {
  margin-bottom: 0 !important;
}

.compact-section :deep(.v-col) {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

/* Cards ultra compactos */
:deep(.v-card .v-card-text) {
  padding: 4px !important;
}

/* Reduzir altura específica dos cards */
.pa-1 {
  padding: 2px !important;
}

/* Botão inverter mais visível */
.invert-btn {
  min-height: 32px !important;
  width: 32px !important;
  margin: 4px 0 !important;
}

/* Cards de estatísticas com melhor contraste */
.stats-card {
  min-height: 60px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
}

/* Cards compactos para linha única */
.stats-card-compact {
  min-height: 50px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
}

/* Cards mini ainda menores */
.stats-card-mini {
  min-height: 40px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
  gap: 1px !important;
}

/* Cards de estatísticas melhorados */
.stats-card-improved {
  min-height: 70px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
  align-items: center !important;
  border-radius: 10px !important;
  transition: all 0.2s ease !important;
  cursor: default;
}

.stats-card-improved:hover {
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}


/* Espaçamento reduzido geral */
.sidebar-content .pa-2 {
  gap: 0.1rem !important;
  padding: 2px 6px 2px 6px !important;
}

/* Cards de opções bonitos e interativos */
.option-card {
  min-height: 48px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
  transition: all 0.2s ease !important;
  border-width: 2px !important;
  cursor: pointer !important;
}

.option-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.12) !important;
}

.option-active {
  background-color: rgba(var(--v-theme-surface-variant), 0.1) !important;
  border-width: 2px !important;
}

.option-active:hover {
  background-color: rgba(var(--v-theme-surface-variant), 0.15) !important;
}

/* Scrollbar personalizada para sidebar */
.sidebar-content::-webkit-scrollbar {
  width: 4px;
}

.sidebar-content::-webkit-scrollbar-track {
  background: rgba(var(--v-theme-surface-variant), 0.1);
}

.sidebar-content::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.3);
  border-radius: 2px;
}

/* Botão FAB sempre visível */
.calcular-rota-fab {
  position: fixed !important;
  top: 80px;
  right: 20px;
  z-index: 1000;
}

/* Melhorar visibilidade do drag handle */
.drag-handle {
  cursor: grab !important;
  opacity: 0.7;
  transition: opacity 0.2s ease;
}

.drag-handle:hover {
  opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

/* Estilo para itens da lista arrastáveis */
.route-point-item {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px;
  background-color: rgba(var(--v-theme-surface), 1);
  transition: all 0.2s ease;
}

.route-point-item:hover {
  border-color: rgba(var(--v-theme-primary), 0.3);
  background-color: rgba(var(--v-theme-primary), 0.05);
}

/* Estilo para origem e destino */
.route-point-origin {
  border-color: rgba(var(--v-theme-success), 0.3) !important;
}

.route-point-destination {
  border-color: rgba(var(--v-theme-error), 0.3) !important;
}

/* Botão de remoção mais visível */
.remove-point-btn {
  opacity: 0.7;
  transition: opacity 0.2s ease;
}

.remove-point-btn:hover {
  opacity: 1 !important;
}

/* Cursor durante drag */
.sortable-drag {
  opacity: 0.8;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.sortable-ghost {
  opacity: 0.4;
}
</style>
