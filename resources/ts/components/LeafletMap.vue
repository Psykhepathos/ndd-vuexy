<template>
  <div ref="mapContainer" style="height: 700px; width: 100%;"></div>
</template>

<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'

interface Municipio {
  spararmuseq: number
  desmun: string
  desest: string
  lat?: number | null
  lon?: number | null
}

interface Props {
  municipios: Municipio[]
  editMode: boolean
}

const props = defineProps<Props>()

const mapContainer = ref<HTMLElement | null>(null)
let map: L.Map | null = null

// Função para destruir completamente o mapa
const destroyMap = () => {
  if (map) {
    map.remove()
    map = null
  }
}

// Função para criar o mapa do zero
const createMap = () => {
  if (!mapContainer.value) {
    console.error('❌ Container do mapa não encontrado')
    return
  }

  // Destruir mapa anterior se existir
  destroyMap()

  console.log('🗺️ Criando novo mapa Leaflet')

  try {
    // Criar novo mapa
    map = L.map(mapContainer.value, {
      center: [-15.7801, -47.9292], // Centro do Brasil
      zoom: 5,
      zoomControl: true,
      attributionControl: true
    })

    // Adicionar tiles do OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map)

    // Filtrar municípios com coordenadas válidas
    const municipiosValidos = props.municipios.filter(m => {
      const lat = Number(m.lat)
      const lon = Number(m.lon)
      return m.lat !== null &&
             m.lat !== undefined &&
             m.lon !== null &&
             m.lon !== undefined &&
             !isNaN(lat) &&
             !isNaN(lon) &&
             lat >= -90 && lat <= 90 &&
             lon >= -180 && lon <= 180
    })

    console.log(`📍 Municípios válidos: ${municipiosValidos.length} de ${props.municipios.length}`)

    if (municipiosValidos.length === 0) {
      console.warn('⚠️ Nenhum município com coordenadas válidas')
      return
    }

    // Array para bounds (auto-zoom)
    const latLngs: L.LatLngExpression[] = []

    // Cor baseada no modo
    const markerColor = props.editMode ? '#FF5252' : '#4CAF50'
    const lineColor = props.editMode ? '#FF5252' : '#2196F3'

    // Criar marcadores
    municipiosValidos.forEach((municipio) => {
      const lat = Number(municipio.lat)
      const lon = Number(municipio.lon)
      const latLng: L.LatLngExpression = [lat, lon]

      latLngs.push(latLng)

      // Criar ícone customizado (círculo numerado)
      const divIcon = L.divIcon({
        className: 'leaflet-custom-marker',
        html: `
          <div style="
            background-color: ${markerColor};
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
          ">
            ${municipio.spararmuseq}
          </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
      })

      // Criar marcador
      const marker = L.marker(latLng, { icon: divIcon })

      // Adicionar popup
      marker.bindPopup(`
        <div style="min-width: 200px;">
          <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold;">
            ${municipio.spararmuseq}. ${municipio.desmun}
          </h3>
          <p style="margin: 4px 0; color: #666; font-size: 13px;">
            Estado: ${municipio.desest}
          </p>
          <p style="margin: 4px 0; color: #999; font-size: 12px;">
            Coordenadas: ${lat.toFixed(6)}, ${lon.toFixed(6)}
          </p>
        </div>
      `)

      marker.addTo(map!)
    })

    // Criar linha conectando os pontos
    if (latLngs.length > 1) {
      L.polyline(latLngs, {
        color: lineColor,
        weight: 3,
        opacity: 0.7,
      }).addTo(map!)
    }

    // Auto-zoom para mostrar todos os pontos
    if (latLngs.length > 0) {
      const bounds = L.latLngBounds(latLngs)
      map.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom: 12,
      })
    }

    console.log('✅ Mapa criado com sucesso')
  } catch (error) {
    console.error('❌ Erro ao criar mapa:', error)
  }
}

// Watch para recriar mapa quando dados ou modo mudarem
watch(() => [props.municipios, props.editMode], () => {
  console.log('🔄 Detectada mudança, recriando mapa...')
  nextTick(() => {
    createMap()
  })
}, { deep: true })

onMounted(() => {
  console.log('🚀 LeafletMap montado, criando mapa inicial...')
  nextTick(() => {
    createMap()
  })
})

onBeforeUnmount(() => {
  console.log('🗑️ Destruindo mapa no unmount')
  destroyMap()
})
</script>

<style>
/* Remover estilos padrão do Leaflet que podem causar problemas */
.leaflet-custom-marker {
  background: transparent !important;
  border: none !important;
}
</style>
