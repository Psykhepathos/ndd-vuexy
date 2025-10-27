<template>
  <div>
    <h1>Teste OpenRouteService - Simples</h1>
    <div id="test-map" style="height: 600px; width: 100%;"></div>
    <p>Status: {{ status }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet-routing-machine'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'
import '@gegeweb/leaflet-routing-machine-openroute'

const status = ref('Carregando...')

onMounted(() => {
  try {
    // Verificar se as libraries foram carregadas
    status.value = 'Verificando libraries...'

    // @ts-ignore
    if (!L.Routing) {
      status.value = '❌ L.Routing não existe'
      return
    }

    // @ts-ignore
    if (!L.Routing.openrouteservice) {
      status.value = '❌ L.Routing.openrouteservice não existe'
      return
    }

    status.value = '✅ Libraries carregadas! Criando mapa...'

    // Criar mapa
    const map = L.map('test-map').setView([-15.7801, -47.9292], 12)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    }).addTo(map)

    status.value = '✅ Mapa criado! Configurando router...'

    // Criar router OpenRouteService
    // @ts-ignore
    const orsRouter = L.Routing.openrouteservice('5b3ce3597851110001cf6248b08a59258c184f5fab1b0c27a6c53cb7', {
      timeout: 30000,
      profile: 'driving-car'
    })

    status.value = '✅ Router criado! Calculando rota...'

    // Criar controle de rota com apenas 2 pontos (teste simples)
    // @ts-ignore
    L.Routing.control({
      waypoints: [
        L.latLng(-19.9191, -43.9386), // Belo Horizonte
        L.latLng(-19.9227, -43.9450)  // Ponto próximo em BH
      ],
      router: orsRouter,
      routeWhileDragging: false
    }).on('routesfound', function(e: any) {
      const route = e.routes[0]
      const distance = (route.summary.totalDistance / 1000).toFixed(1)
      const time = Math.round(route.summary.totalTime / 60)

      status.value = `✅ SUCESSO! Rota: ${distance} km, ${time} min`
      console.log('✅ OpenRouteService funcionou!', route)
    }).on('routingerror', function(e: any) {
      status.value = `❌ Erro no routing: ${JSON.stringify(e)}`
      console.error('❌ Erro:', e)
    }).addTo(map)

  } catch (error) {
    status.value = `❌ Exceção: ${error}`
    console.error('❌ Exceção:', error)
  }
})
</script>
