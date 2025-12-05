<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { API_ENDPOINTS, apiFetch } from '@/config/api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
// @ts-ignore - leaflet-routing-machine doesn't have TypeScript definitions
import 'leaflet-routing-machine'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'
// @ts-ignore - OpenRouteService doesn't have TypeScript definitions
import '@gegeweb/leaflet-routing-machine-openroute'

interface Entrega {
  seqent: number
  razcli: string
  endcli: string
  baicli: string
  cidcli: string
  sigufs: string
  lat?: number
  lon?: number
}

const mapContainer = ref<HTMLElement | null>(null)
const testStatus = ref('Carregando dados do pacote...')
const routeInfo = ref('')
const entregas = ref<Entrega[]>([])
const loading = ref(true)
let map: L.Map | null = null
let routingControl: any = null

// Calcular distância entre dois pontos (Haversine formula) em km
const calcularDistancia = (lat1: number, lon1: number, lat2: number, lon2: number): number => {
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

const loadPacote = async () => {
  try {
    const response = await apiFetch(API_ENDPOINTS.pacoteItinerario, {
      method: 'POST',
      body: JSON.stringify({
        Pacote: {
          codPac: 3043368
        }
      })
    })

    const data = await response.json()
    console.log('Resposta da API:', data)

    if (data.success && data.data?.pedidos) {
      // Mapear pedidos para formato de entregas
      // CORREÇÃO: Backend agora retorna number (float) após BUG MODERADO #1
      entregas.value = data.data.pedidos.map(pedido => ({
        seqent: pedido.seqent,
        razcli: pedido.razcli,
        endcli: pedido.endcli,
        baicli: pedido.baicli,
        cidcli: pedido.cidcli,
        sigufs: pedido.sigufs,
        lat: typeof pedido.gps_lat === 'number'
          ? pedido.gps_lat
          : parseFloat(pedido.gps_lat?.replace(',', '.') || '0'),
        lon: typeof pedido.gps_lon === 'number'
          ? pedido.gps_lon
          : parseFloat(pedido.gps_lon?.replace(',', '.') || '0')
      }))

      testStatus.value = `✅ ${entregas.value.length} entregas carregadas`

      // Criar mapa após carregar dados
      createMap()
    } else {
      testStatus.value = `❌ Erro ao carregar pacote: ${data.message || 'Sem dados'}`
      console.error('Dados da resposta:', data)
    }
  } catch (error) {
    testStatus.value = `❌ Erro: ${error}`
    console.error('Erro:', error)
  } finally {
    loading.value = false
  }
}

const createMap = async () => {
  if (!mapContainer.value) {
    testStatus.value = '❌ Container não encontrado'
    return
  }

  try {
    // Criar mapa
    map = L.map(mapContainer.value).setView([-15.7801, -47.9292], 5)

    // Adicionar tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    }).addTo(map)

    // Filtrar entregas com coordenadas válidas
    const entregasValidas = entregas.value.filter(e =>
      e.lat && e.lon && !isNaN(e.lat) && !isNaN(e.lon)
    )

    if (entregasValidas.length === 0) {
      testStatus.value = '⚠️ Nenhuma entrega com coordenadas válidas'
      return
    }

    // Criar waypoints para o Leaflet Routing Machine
    testStatus.value = '🚗 Calculando rota com Leaflet Routing Machine...'

    // OpenRouteService free tier: 40 req/min, 50 waypoints max
    const maxWaypoints = 15
    const entregasParaRotear = entregasValidas.slice(0, maxWaypoints)

    if (entregasValidas.length > maxWaypoints) {
      console.warn(`⚠️ Muitos pontos (${entregasValidas.length}). Usando apenas ${maxWaypoints} primeiros.`)
      testStatus.value = `⚠️ Mostrando rota com ${maxWaypoints} de ${entregasValidas.length} entregas`
    }

    const waypoints = entregasParaRotear.map(entrega =>
      L.latLng(Number(entrega.lat), Number(entrega.lon))
    )

    // ✅ SOLUÇÃO FUNCIONANDO: OpenStreetMap.de OSRM (100% GRATUITO!)
    // Testado e funcionando em: 2025-10-21
    // @ts-ignore
    const osrmRouter = L.Routing.osrmv1({
      serviceUrl: 'https://routing.openstreetmap.de/routed-car/route/v1',
      profile: 'driving',
      timeout: 30000
    })

    // @ts-ignore
    routingControl = L.Routing.control({
      waypoints: waypoints,
      router: osrmRouter,
      language: 'pt-BR',
      plan: L.Routing.plan(waypoints, {
        createMarker: function(i: number, waypoint: any, n: number) {
          const entrega = entregasParaRotear[i]

          // Cor do marcador
          let color = '#FF9800' // Laranja padrão
          if (i === 0) color = '#4CAF50' // Verde para primeira
          if (i === n - 1) color = '#F44336' // Vermelho para última

          const icon = L.divIcon({
            className: 'custom-marker',
            html: `
              <div style="
                background-color: ${color};
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
                ${entrega.seqent}
              </div>
            `,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
          })

          const marker = L.marker(waypoint.latLng, {
            draggable: false,
            icon
          })

          // Popup
          marker.bindPopup(`
            <div style="min-width: 250px;">
              <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">
                Entrega ${entrega.seqent}
              </h3>
              <p style="margin: 4px 0; font-size: 13px;"><strong>${entrega.razcli}</strong></p>
              <p style="margin: 2px 0; font-size: 12px; color: #666;">${entrega.endcli}</p>
              <p style="margin: 2px 0; font-size: 12px; color: #666;">${entrega.baicli} - ${entrega.cidcli}/${entrega.sigufs}</p>
            </div>
          `)

          return marker
        }
      }),
      draggableWaypoints: false,
      addWaypoints: false,
      fitSelectedRoutes: 'smart',
      lineOptions: {
        styles: [{
          color: '#2196F3',
          weight: 4,
          opacity: 0.7
        }]
      },
      routeWhileDragging: false,
    }).on('routesfound', function(e: any) {
      const route = e.routes[0]
      const distance = (route.summary.totalDistance / 1000).toFixed(1)
      const time = Math.round(route.summary.totalTime / 60)

      routeInfo.value = `🛣️ Rota: ${distance} km, ~${time} min`
      testStatus.value = `✅ Mapa criado com ${entregasParaRotear.length} entregas e rota real!`

      console.log('✅ OpenRouteService funcionou! Rota calculada:', {
        distancia: distance + ' km',
        tempo: time + ' min',
        pontos: route.coordinates.length,
        waypoints: entregasParaRotear.length
      })

      console.log('Coordenadas da rota:', route.coordinates)
    }).on('routingerror', function(e: any) {
      console.error('❌ OpenRouteService falhou:', e)

      testStatus.value = '⚠️ Erro no routing - mostrando apenas pontos'
      routeInfo.value = '❌ Falha no cálculo da rota'

      // Remover o routing control que falhou
      if (routingControl) {
        map!.removeControl(routingControl)
      }

      // Desenhar linha reta entre pontos (fallback visual)
      const polylinePoints = entregasParaRotear.map(e => [Number(e.lat), Number(e.lon)] as L.LatLngExpression)
      L.polyline(polylinePoints, {
        color: '#FF9800',
        weight: 2,
        opacity: 0.4,
        dashArray: '10, 10'
      }).addTo(map!)

      // Auto-zoom
      const bounds = L.latLngBounds(polylinePoints)
      map!.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 })
    }).addTo(map!)
  } catch (error) {
    testStatus.value = `❌ Erro ao criar mapa: ${error}`
    console.error('Erro:', error)
  }
}

onMounted(() => {
  loadPacote()
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        🧪 Teste Leaflet com Pacote Real (ID: 3043368)
      </VCardTitle>

      <VCardText>
        <VAlert
          :type="loading ? 'info' : testStatus.includes('✅') ? 'success' : 'error'"
          class="mb-4"
        >
          {{ testStatus }}
        </VAlert>

        <VAlert
          v-if="routeInfo"
          :type="routeInfo.includes('OSRM') && !routeInfo.includes('falhou') ? 'success' : 'warning'"
          class="mb-4"
        >
          <strong>Roteamento:</strong> {{ routeInfo }}
        </VAlert>

        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" />

        <!-- Container do mapa -->
        <div
          ref="mapContainer"
          style="height: 600px; width: 100%; border: 2px solid #ccc; border-radius: 8px;"
        />

        <VDivider class="my-4" />

        <div>
          <h3>📊 Estatísticas:</h3>
          <ul>
            <li>Total de entregas: {{ entregas.length }}</li>
            <li>Com coordenadas: {{ entregas.filter(e => e.lat && e.lon).length }}</li>
            <li>Mapa criado: {{ map ? '✅' : '❌' }}</li>
          </ul>
        </div>

        <VDivider class="my-4" />

        <h3>📍 Lista de Entregas:</h3>
        <VTable density="compact" class="mt-2">
          <thead>
            <tr>
              <th>Seq</th>
              <th>Cliente</th>
              <th>Cidade</th>
              <th>Coordenadas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entrega in entregas" :key="entrega.seqent">
              <td>{{ entrega.seqent }}</td>
              <td>{{ entrega.razcli }}</td>
              <td>{{ entrega.cidcli }}/{{ entrega.sigufs }}</td>
              <td>
                <span v-if="entrega.lat && entrega.lon" class="text-success">
                  ✅ {{ entrega.lat.toFixed(4) }}, {{ entrega.lon.toFixed(4) }}
                </span>
                <span v-else class="text-error">❌ Sem coords</span>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.custom-marker {
  background: transparent !important;
  border: none !important;
}
</style>
