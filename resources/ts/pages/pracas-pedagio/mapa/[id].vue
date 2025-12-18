<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { getApiUrl } from '@/config/api'

// ============================================================================
// INTERFACES
// ============================================================================

interface PracaPedagio {
  id: number
  concessionaria: string
  praca: string
  rodovia: string
  uf: string
  km: string
  municipio: string
  ano_pnv: number | null
  tipo_pista: string | null
  sentido: string | null
  situacao: string
  data_inativacao: string | null
  latitude: string
  longitude: string
  fonte: string
  data_importacao: string
}

// ============================================================================
// STATE
// ============================================================================

const route = useRoute()
const router = useRouter()
const pracaId = ref<number>(parseInt(route.params.id as string))

const loading = ref(false)
const praca = ref<PracaPedagio | null>(null)
const map = ref<L.Map | null>(null)
const marker = ref<L.Marker | null>(null)

// ============================================================================
// METHODS
// ============================================================================

const loadPraca = async () => {
  loading.value = true
  try {
    const response = await fetch(getApiUrl(`/pracas-pedagio/${pracaId.value}`))
    const data = await response.json()

    if (data.success) {
      praca.value = data.data
      loading.value = false

      // Esperar o DOM renderizar completamente antes de inicializar o mapa
      await nextTick()

      // Timeout adicional para garantir que o elemento está no DOM
      setTimeout(() => {
        const mapElement = document.getElementById('map')
        if (mapElement) {
          initializeMap()
        } else {
          console.error('Elemento #map não encontrado no DOM')
        }
      }, 100)
    } else {
      console.error('Erro ao carregar praça:', data.error)
      loading.value = false
      router.push({ name: 'pracas-pedagio' })
    }
  } catch (error) {
    console.error('Erro ao carregar praça:', error)
    loading.value = false
    router.push({ name: 'pracas-pedagio' })
  }
}

const initializeMap = () => {
  if (!praca.value) return

  const lat = parseFloat(praca.value.latitude)
  const lon = parseFloat(praca.value.longitude)

  if (isNaN(lat) || isNaN(lon)) {
    console.error('Coordenadas inválidas:', praca.value.latitude, praca.value.longitude)
    return
  }

  // Inicializar mapa Leaflet com OpenStreetMap
  map.value = L.map('map').setView([lat, lon], 15)

  // Adicionar tile layer do OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map.value)

  // Criar ícone customizado para a praça
  const pracaIcon = L.divIcon({
    html: `
      <div style="
        background-color: #F44336;
        border: 3px solid white;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
      ">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 6v12"></path>
        </svg>
      </div>
    `,
    className: 'praca-pedagio-marker-main',
    iconSize: [48, 48],
    iconAnchor: [24, 24],
    popupAnchor: [0, -24]
  })

  // Adicionar marcador
  marker.value = L.marker([lat, lon], { icon: pracaIcon }).addTo(map.value)

  // Popup com informações detalhadas
  const popupContent = `
    <div style="min-width: 280px; font-family: system-ui;">
      <h5 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #F44336;">
        ${praca.value.praca}
      </h5>
      <div style="font-size: 13px; line-height: 1.8;">
        <p style="margin: 6px 0; display: flex; justify-content: space-between;">
          <strong style="color: #666;">Rodovia:</strong>
          <span style="font-weight: 600;">${praca.value.rodovia}</span>
        </p>
        <p style="margin: 6px 0; display: flex; justify-content: space-between;">
          <strong style="color: #666;">KM:</strong>
          <span>${parseFloat(praca.value.km).toFixed(1)} km</span>
        </p>
        <p style="margin: 6px 0; display: flex; justify-content: space-between;">
          <strong style="color: #666;">Município:</strong>
          <span>${praca.value.municipio}/${praca.value.uf}</span>
        </p>
        <p style="margin: 6px 0;">
          <strong style="color: #666;">Concessionária:</strong><br>
          <span>${praca.value.concessionaria}</span>
        </p>
        ${praca.value.tipo_pista ? `
          <p style="margin: 6px 0; display: flex; justify-content: space-between;">
            <strong style="color: #666;">Tipo de Pista:</strong>
            <span>${praca.value.tipo_pista}</span>
          </p>
        ` : ''}
        ${praca.value.sentido ? `
          <p style="margin: 6px 0; display: flex; justify-content: space-between;">
            <strong style="color: #666;">Sentido:</strong>
            <span>${praca.value.sentido}</span>
          </p>
        ` : ''}
        <p style="margin: 6px 0; display: flex; justify-content: space-between;">
          <strong style="color: #666;">Status:</strong>
          <span style="
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            background-color: ${praca.value.situacao === 'Ativo' ? '#4CAF50' : '#F44336'};
            color: white;
            font-size: 11px;
            font-weight: 600;
          ">
            ${praca.value.situacao}
          </span>
        </p>
        <hr style="margin: 12px 0; border: none; border-top: 1px solid #eee;">
        <p style="margin: 6px 0; font-size: 11px; color: #999;">
          <strong>Coordenadas:</strong> ${lat.toFixed(6)}, ${lon.toFixed(6)}
        </p>
      </div>
    </div>
  `

  marker.value.bindPopup(popupContent, { maxWidth: 320 })

  // Abrir popup automaticamente
  marker.value.openPopup()

  // Adicionar círculo de raio ao redor da praça (5km)
  L.circle([lat, lon], {
    color: '#F44336',
    fillColor: '#F44336',
    fillOpacity: 0.1,
    radius: 5000 // 5km
  }).addTo(map.value)
}

const voltarParaLista = () => {
  router.push({ name: 'pracas-pedagio' })
}

const formatKm = (km: string) => {
  return parseFloat(km).toFixed(1) + ' km'
}

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(async () => {
  await loadPraca()
})

onUnmounted(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-y-4 mb-6">
      <div>
        <div class="d-flex align-center gap-2 mb-2">
          <VBtn
            icon
            size="small"
            variant="text"
            @click="voltarParaLista"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <h4 class="text-h4 font-weight-medium">
            {{ praca?.praca || 'Carregando...' }}
          </h4>
        </div>
        <div class="text-body-2 text-medium-emphasis ms-12">
          {{ praca?.rodovia }} - {{ praca?.municipio }}/{{ praca?.uf }}
        </div>
      </div>

      <div class="d-flex gap-4">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          @click="voltarParaLista"
        >
          Voltar para lista
        </VBtn>
      </div>
    </div>

    <!-- Loading State -->
    <VCard v-if="loading">
      <VCardText class="text-center pa-8">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
          class="mb-4"
        />
        <p class="text-body-1 text-medium-emphasis">
          Carregando informações da praça...
        </p>
      </VCardText>
    </VCard>

    <!-- Main Content -->
    <div v-else-if="praca">
      <VRow>
        <!-- Info Cards -->
        <VCol cols="12" md="4">
          <VCard class="mb-4">
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="tabler-info-circle" color="primary" />
              Informações da Praça
            </VCardTitle>
            <VCardText>
              <div class="info-item">
                <span class="label">Rodovia:</span>
                <span class="value">{{ praca.rodovia }}</span>
              </div>
              <div class="info-item">
                <span class="label">KM:</span>
                <span class="value">{{ formatKm(praca.km) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Município:</span>
                <span class="value">{{ praca.municipio }}/{{ praca.uf }}</span>
              </div>
              <div class="info-item">
                <span class="label">Concessionária:</span>
                <span class="value">{{ praca.concessionaria }}</span>
              </div>
              <div v-if="praca.tipo_pista" class="info-item">
                <span class="label">Tipo de Pista:</span>
                <span class="value">{{ praca.tipo_pista }}</span>
              </div>
              <div v-if="praca.sentido" class="info-item">
                <span class="label">Sentido:</span>
                <span class="value">{{ praca.sentido }}</span>
              </div>
              <div v-if="praca.ano_pnv" class="info-item">
                <span class="label">Ano PNV:</span>
                <span class="value">{{ praca.ano_pnv }}</span>
              </div>
              <div class="info-item">
                <span class="label">Situação:</span>
                <VChip
                  :color="praca.situacao === 'Ativo' ? 'success' : 'error'"
                  size="small"
                >
                  {{ praca.situacao }}
                </VChip>
              </div>
            </VCardText>
          </VCard>

          <VCard>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="tabler-map-pin" color="primary" />
              Coordenadas
            </VCardTitle>
            <VCardText>
              <div class="info-item">
                <span class="label">Latitude:</span>
                <span class="value">{{ parseFloat(praca.latitude).toFixed(6) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Longitude:</span>
                <span class="value">{{ parseFloat(praca.longitude).toFixed(6) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Fonte:</span>
                <span class="value">{{ praca.fonte }}</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Map -->
        <VCol cols="12" md="8">
          <VCard>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="tabler-map" color="primary" />
              Localização no Mapa (OpenStreetMap)
            </VCardTitle>
            <VCardText class="pa-0">
              <div
                id="map"
                style="height: 600px; width: 100%;"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.info-item:last-child {
  border-bottom: none;
}

.info-item .label {
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  opacity: 0.7;
}

.info-item .value {
  text-align: right;
  font-weight: 500;
}

/* Leaflet popup customization */
:deep(.leaflet-popup-content-wrapper) {
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

:deep(.leaflet-popup-content) {
  margin: 16px;
}

:deep(.leaflet-popup-tip) {
  background: white;
}
</style>
