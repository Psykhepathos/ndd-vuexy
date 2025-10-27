<script setup lang="ts">
import { ref, onMounted } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const mapContainer = ref<HTMLElement | null>(null)
const testStatus = ref('Inicializando...')

onMounted(() => {
  try {
    testStatus.value = 'Criando mapa...'

    if (!mapContainer.value) {
      testStatus.value = 'ERRO: Container não encontrado'
      return
    }

    // Criar mapa simples
    const map = L.map(mapContainer.value).setView([-15.7801, -47.9292], 5)

    // Adicionar tiles do OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    }).addTo(map)

    // Adicionar um marcador de teste
    const marker = L.marker([-15.7801, -47.9292]).addTo(map)
    marker.bindPopup('🗺️ Brasília - Teste do Leaflet!').openPopup()

    testStatus.value = '✅ Mapa carregado com sucesso!'
  } catch (error) {
    testStatus.value = `❌ Erro: ${error}`
    console.error('Erro ao criar mapa:', error)
  }
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        🧪 Teste do Leaflet (OpenStreetMap)
      </VCardTitle>

      <VCardText>
        <VAlert type="info" class="mb-4">
          Status: {{ testStatus }}
        </VAlert>

        <!-- Container do mapa -->
        <div
          ref="mapContainer"
          style="height: 600px; width: 100%; border: 2px solid #ccc; border-radius: 8px;"
        />

        <VDivider class="my-4" />

        <div>
          <h3>✅ Checklist:</h3>
          <ul>
            <li>Leaflet está instalado? {{ typeof L !== 'undefined' ? '✅' : '❌' }}</li>
            <li>Container renderizado? {{ mapContainer ? '✅' : '❌' }}</li>
            <li>Mapa carregou? {{ testStatus.includes('sucesso') ? '✅' : '⏳' }}</li>
          </ul>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
