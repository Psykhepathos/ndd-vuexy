<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'

// Interfaces
interface SemPararRota {
  spararrotid: number
  desspararrot: string
  tempoviagem: number
  flgcd: boolean
  flgretorno: boolean
  datatu: string | null
  resatu: string | null
}

interface RotaMunicipio {
  spararmmuseq: number
  codmun: number
  codest: number
  desmun: string
  desest: string
  cdibge: number
  lat?: number
  lon?: number
}

// Composables
const route = useRoute()
const router = useRouter()

// Estados reativos
const rota = ref<SemPararRota | null>(null)
const municipios = ref<RotaMunicipio[]>([])
const loading = ref(false)
const saving = ref(false)
const editMode = ref(false)
const map = ref<google.maps.Map | null>(null)
const mapContainer = ref<HTMLElement>()
const markers = ref<google.maps.Marker[]>([])
const polyline = ref<google.maps.Polyline | null>(null)
const distanciaTotal = ref(0)

// Autocomplete
const loadingMunicipios = ref(false)
const municipiosOptions = ref<Array<{title: string, value: any}>>([])
const selectedMunicipio = ref<any>(null)
const searchMunicipio = ref('')

// ID da rota
const rotaId = computed(() => route.params.id as string)

// Carregar dados da rota
const fetchRota = async () => {
  loading.value = true

  try {
    const response = await fetch(`http://localhost:8002/api/semparar-rotas/${rotaId.value}/municipios`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success) {
      rota.value = data.data.rota
      municipios.value = data.data.municipios || []

      // Garantir sequência correta
      municipios.value.sort((a, b) => a.spararmmuseq - b.spararmmuseq)

      await nextTick()
      initMap()
    } else {
      console.error('Erro na API:', data.message)
    }
  } catch (error) {
    console.error('Erro ao carregar rota SemParar:', error)
  } finally {
    loading.value = false
  }
}

// Buscar municípios para autocomplete
const fetchMunicipios = async (search: string = '') => {
  if (search.length < 2 && search !== '') return

  loadingMunicipios.value = true

  try {
    const params = new URLSearchParams({
      search: search
    })

    const response = await fetch(`http://localhost:8002/api/semparar-rotas/municipios?${params}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })

    const data = await response.json()

    if (data.success && data.data) {
      municipiosOptions.value = data.data.map((m: any) => ({
        title: `${m.desmun} - ${m.desest}`,
        value: {
          codmun: m.codmun,
          codest: m.codest,
          desmun: m.desmun,
          desest: m.desest,
          cdibge: m.cdibge,
          lat: m.lat,
          lon: m.lon
        }
      }))
    }
  } catch (error) {
    console.error('Erro ao buscar municípios:', error)
    municipiosOptions.value = []
  } finally {
    loadingMunicipios.value = false
  }
}

// Inicializar mapa Google Maps
const initMap = async () => {
  if (!mapContainer.value) return

  // Aguardar Google Maps carregar
  if (typeof google === 'undefined' || !google.maps) {
    setTimeout(initMap, 500)
    return
  }

  // Criar mapa centrado no Brasil
  map.value = new google.maps.Map(mapContainer.value, {
    center: { lat: -14.2350, lng: -51.9253 },
    zoom: 4,
    mapTypeControl: true,
    streetViewControl: false,
    fullscreenControl: true
  })

  // Plotar pontos no mapa
  updateMapMarkers()
}

// Atualizar marcadores e polyline no mapa
const updateMapMarkers = async () => {
  if (!map.value) return

  // Limpar marcadores antigos
  markers.value.forEach(marker => marker.setMap(null))
  markers.value = []

  // Limpar polyline antiga
  if (polyline.value) {
    polyline.value.setMap(null)
  }

  const path: google.maps.LatLngLiteral[] = []
  const bounds = new google.maps.LatLngBounds()

  // Processar municípios sem coordenadas
  for (const municipio of municipios.value) {
    if (!municipio.lat || !municipio.lon) {
      const coords = await geocodeByIBGE(municipio)
      if (coords) {
        municipio.lat = coords.lat
        municipio.lon = coords.lon
      }
    }
  }

  // Criar novos marcadores
  municipios.value.forEach((municipio, index) => {
    if (municipio.lat && municipio.lon) {
      const position = { lat: Number(municipio.lat), lng: Number(municipio.lon) }
      path.push(position)
      bounds.extend(position)

      // Criar marcador numerado
      const marker = new google.maps.Marker({
        position,
        map: map.value!,
        title: `${municipio.spararmmuseq}. ${municipio.desmun}`,
        label: {
          text: String(municipio.spararmmuseq),
          color: 'white',
          fontSize: '12px',
          fontWeight: 'bold'
        },
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 12,
          fillColor: editMode.value ? '#ff9800' : '#1976d2',
          fillOpacity: 1,
          strokeColor: 'white',
          strokeWeight: 2
        }
      })

      // Adicionar InfoWindow
      const infoWindow = new google.maps.InfoWindow({
        content: `
          <div style="padding: 8px;">
            <h6 style="margin: 0 0 4px 0;"><strong>${municipio.desmun}</strong></h6>
            <p style="margin: 2px 0;">Estado: ${municipio.desest}</p>
            <p style="margin: 2px 0;">Sequência: ${municipio.spararmmuseq}</p>
            <p style="margin: 2px 0;">IBGE: ${municipio.cdibge}</p>
          </div>
        `
      })

      marker.addListener('click', () => {
        infoWindow.open(map.value!, marker)
      })

      markers.value.push(marker)
    }
  })

  // Desenhar polyline se houver mais de um ponto
  if (path.length > 1) {
    polyline.value = new google.maps.Polyline({
      path,
      geodesic: true,
      strokeColor: editMode.value ? '#ff9800' : '#1976d2',
      strokeOpacity: 0.7,
      strokeWeight: 3
    })
    polyline.value.setMap(map.value)

    // Calcular distância total
    calcularDistanciaTotal(path)

    // Ajustar zoom para mostrar todos os pontos
    map.value.fitBounds(bounds)
  }
}

// Calcular distância total da rota
const calcularDistanciaTotal = (path: google.maps.LatLngLiteral[]) => {
  let total = 0
  for (let i = 0; i < path.length - 1; i++) {
    const from = new google.maps.LatLng(path[i].lat, path[i].lng)
    const to = new google.maps.LatLng(path[i + 1].lat, path[i + 1].lng)
    total += google.maps.geometry.spherical.computeDistanceBetween(from, to)
  }
  distanciaTotal.value = total / 1000 // Converter para km
}

// Função para geocoding usando código IBGE via Google Maps
const geocodeByIBGE = async (municipio: RotaMunicipio): Promise<{lat: number, lon: number} | null> => {
  try {
    // Usar o nome do município + estado + Brasil para busca
    const searchQuery = `${municipio.desmun}, ${municipio.desest}, Brasil`

    // Alternativa: buscar usando código IBGE diretamente
    // Algumas APIs aceitam queries como "IBGE:3106200 Brasil"
    const alternativeQuery = `IBGE:${municipio.cdibge} Brasil`

    const geocoder = new google.maps.Geocoder()

    // Tentar primeiro com nome da cidade
    const result = await new Promise<google.maps.GeocoderResult[]>((resolve, reject) => {
      geocoder.geocode({ address: searchQuery }, (results, status) => {
        if (status === 'OK' && results) {
          resolve(results)
        } else {
          // Se falhar, tentar com código IBGE
          geocoder.geocode({ address: alternativeQuery }, (results2, status2) => {
            if (status2 === 'OK' && results2) {
              resolve(results2)
            } else {
              reject(new Error(`Geocoding falhou: ${status}`))
            }
          })
        }
      })
    })

    if (result.length > 0) {
      const location = result[0].geometry.location
      return {
        lat: location.lat(),
        lon: location.lng()
      }
    }
  } catch (error) {
    console.error('Erro ao fazer geocoding:', error)
  }

  return null
}

// Adicionar município à rota
const adicionarMunicipio = async () => {
  if (!selectedMunicipio.value) return

  const novoMunicipio: RotaMunicipio = {
    spararmmuseq: municipios.value.length + 1,
    codmun: selectedMunicipio.value.codmun,
    codest: selectedMunicipio.value.codest,
    desmun: selectedMunicipio.value.desmun,
    desest: selectedMunicipio.value.desest,
    cdibge: selectedMunicipio.value.cdibge,
    lat: selectedMunicipio.value.lat,
    lon: selectedMunicipio.value.lon
  }

  // Se não tiver coordenadas, buscar via geocoding
  if (!novoMunicipio.lat || !novoMunicipio.lon) {
    const coords = await geocodeByIBGE(novoMunicipio)
    if (coords) {
      novoMunicipio.lat = coords.lat
      novoMunicipio.lon = coords.lon
    }
  }

  municipios.value.push(novoMunicipio)
  selectedMunicipio.value = null
  searchMunicipio.value = ''

  // Atualizar mapa
  updateMapMarkers()
}

// Remover município
const removerMunicipio = (index: number) => {
  municipios.value.splice(index, 1)

  // Reajustar sequências
  municipios.value.forEach((m, i) => {
    m.spararmmuseq = i + 1
  })

  // Atualizar mapa
  updateMapMarkers()
}

// Função para quando terminar de arrastar
const onDragEnd = () => {
  // Reajustar sequências após drag & drop
  municipios.value.forEach((m, i) => {
    m.spararmmuseq = i + 1
  })

  // Atualizar mapa com nova ordem
  updateMapMarkers()
}

// Salvar alterações
const salvarAlteracoes = async () => {
  if (!rota.value) return

  saving.value = true

  try {
    const payload = {
      nome: rota.value.desspararrot,
      tempo_viagem: rota.value.tempoviagem,
      flg_cd: rota.value.flgcd,
      flg_retorno: rota.value.flgretorno,
      municipios: municipios.value.map(m => ({
        cod_est: m.codest,
        cod_mun: m.codmun,
        des_est: m.desest,
        des_mun: m.desmun,
        cdibge: m.cdibge,
        sequencia: m.spararmmuseq
      }))
    }

    const response = await fetch(`http://localhost:8002/api/semparar-rotas/${rotaId.value}`, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (data.success) {
      editMode.value = false
      await fetchRota() // Recarregar dados
    } else {
      alert('Erro ao salvar: ' + data.message)
    }
  } catch (error) {
    console.error('Erro ao salvar alterações:', error)
    alert('Erro ao salvar alterações')
  } finally {
    saving.value = false
  }
}

// Cancelar edição
const cancelarEdicao = () => {
  editMode.value = false
  fetchRota() // Recarregar dados originais
}

// Ativar modo edição
const ativarEdicao = () => {
  editMode.value = true
  updateMapMarkers()
}

// Formatar data
const formatDate = (date: string | null) => {
  if (!date) return 'N/D'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('pt-BR')
}

// Voltar para listagem
const goBack = () => {
  router.push('/rotas-semparar')
}

// Watch para atualizar mapa quando municipios mudarem em modo edição
watch(() => municipios.value.length, () => {
  if (editMode.value) {
    updateMapMarkers()
  }
})

// Lifecycle
onMounted(() => {
  fetchRota()
  fetchMunicipios()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-3 mb-6">
      <div class="d-flex align-center gap-3">
        <VBtn
          icon="tabler-arrow-left"
          variant="tonal"
          size="small"
          @click="goBack"
        />
        <div>
          <h4 class="text-h4 font-weight-medium mb-1">
            {{ editMode ? 'Editar' : 'Visualizar' }} Rota SemParar
          </h4>
          <p class="text-body-1 mb-0" v-if="rota">
            {{ rota.desspararrot }}
          </p>
        </div>
      </div>

      <div class="d-flex gap-2">
        <VBtn
          v-if="!editMode && rota"
          color="primary"
          prepend-icon="tabler-edit"
          @click="ativarEdicao"
        >
          Editar Rota
        </VBtn>

        <VBtn
          v-if="editMode"
          color="success"
          prepend-icon="tabler-check"
          :loading="saving"
          @click="salvarAlteracoes"
        >
          Salvar Alterações
        </VBtn>

        <VBtn
          v-if="editMode"
          color="error"
          variant="tonal"
          prepend-icon="tabler-x"
          @click="cancelarEdicao"
        >
          Cancelar
        </VBtn>
      </div>
    </div>

    <VRow v-if="!loading && rota">
      <!-- Coluna Esquerda: Informações e Lista -->
      <VCol cols="12" md="4">
        <!-- Card de Informações -->
        <VCard class="mb-4">
          <VCardTitle>
            <VIcon icon="tabler-info-circle" class="me-2" />
            Informações da Rota
          </VCardTitle>
          <VCardText>
            <!-- Estatísticas em Cards pequenos -->
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-route" color="primary" size="20" />
                  <span class="text-body-2">Distância Total</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ distanciaTotal.toFixed(0) }} km</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-clock" color="info" size="20" />
                  <span class="text-body-2">Tempo Viagem</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ rota.tempoviagem }} dias</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-map-pin" color="success" size="20" />
                  <span class="text-body-2">Municípios</span>
                </div>
                <span class="text-body-1 font-weight-bold">{{ municipios.length }}</span>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-building" color="warning" size="20" />
                  <span class="text-body-2">Tipo</span>
                </div>
                <VChip
                  :color="rota.flgcd ? 'primary' : 'secondary'"
                  size="small"
                >
                  {{ rota.flgcd ? 'CD' : 'Outros' }}
                </VChip>
              </div>

              <div class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4 rounded">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-refresh" color="secondary" size="20" />
                  <span class="text-body-2">Retorno</span>
                </div>
                <VIcon
                  :icon="rota.flgretorno ? 'tabler-check' : 'tabler-x'"
                  :color="rota.flgretorno ? 'success' : 'error'"
                />
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Card de Municípios -->
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-map-pins" class="me-2" />
            Sequência de Municípios
          </VCardTitle>

          <!-- Adicionar Município (apenas em modo edição) -->
          <VCardText v-if="editMode">
            <VAutocomplete
              v-model="selectedMunicipio"
              :items="municipiosOptions"
              :loading="loadingMunicipios"
              label="Adicionar Município"
              placeholder="Digite para buscar..."
              prepend-inner-icon="tabler-search"
              clearable
              item-title="title"
              item-value="value"
              return-object
              @update:search="fetchMunicipios"
              no-data-text="Nenhum município encontrado"
              class="mb-3"
            >
              <template #append>
                <VBtn
                  color="primary"
                  icon="tabler-plus"
                  size="small"
                  variant="flat"
                  @click="adicionarMunicipio"
                  :disabled="!selectedMunicipio"
                />
              </template>
            </VAutocomplete>
          </VCardText>

          <VCardText class="pa-0">
            <!-- Lista com Drag & Drop -->
            <draggable
              v-model="municipios"
              :disabled="!editMode"
              handle=".drag-handle"
              item-key="cdibge"
              @end="onDragEnd"
            >
              <template #item="{ element, index }">
                <div
                  class="d-flex align-center pa-3 border-b"
                  :class="{ 'bg-grey-lighten-5': editMode }"
                >
                  <!-- Handle para arrastar -->
                  <VIcon
                    v-if="editMode"
                    icon="tabler-grip-vertical"
                    class="drag-handle me-2 cursor-move"
                    color="grey"
                  />

                  <!-- Número da sequência -->
                  <VChip
                    :color="editMode ? 'warning' : 'primary'"
                    size="small"
                    class="me-3"
                  >
                    {{ element.spararmmuseq }}
                  </VChip>

                  <!-- Informações do município -->
                  <div class="flex-grow-1">
                    <div class="text-body-2 font-weight-medium">
                      {{ element.desmun }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ element.desest }}
                      <VChip
                        v-if="element.lat && element.lon"
                        color="success"
                        size="x-small"
                        variant="text"
                        class="ms-1"
                      >
                        <VIcon icon="tabler-map-pin-check" size="12" />
                        GPS
                      </VChip>
                      <VChip
                        v-else
                        color="error"
                        size="x-small"
                        variant="text"
                        class="ms-1"
                      >
                        <VIcon icon="tabler-map-pin-x" size="12" />
                        Sem GPS
                      </VChip>
                    </div>
                  </div>

                  <!-- Botão remover (apenas em modo edição) -->
                  <VBtn
                    v-if="editMode"
                    icon="tabler-x"
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="removerMunicipio(index)"
                  />
                </div>
              </template>
            </draggable>

            <!-- Mensagem quando vazio -->
            <div v-if="municipios.length === 0" class="text-center pa-6 text-medium-emphasis">
              <VIcon icon="tabler-map-off" size="48" class="mb-3" />
              <p class="text-body-2">Nenhum município na rota</p>
              <p class="text-caption" v-if="editMode">Adicione municípios usando o campo acima</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Coluna Direita: Mapa -->
      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardTitle>
            <VIcon icon="tabler-map-2" class="me-2" />
            Visualização no Mapa
            <VChip
              v-if="editMode"
              color="warning"
              size="small"
              class="ms-2"
            >
              Modo Edição
            </VChip>
          </VCardTitle>
          <VCardText class="pa-0">
            <div
              ref="mapContainer"
              style="height: 700px; width: 100%;"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Loading -->
    <div v-if="loading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>
  </div>
</template>

<style scoped>
.cursor-move {
  cursor: move !important;
}

.drag-handle {
  cursor: grab !important;
}

.drag-handle:active {
  cursor: grabbing !important;
}

.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}

.border-b:last-child {
  border-bottom: none;
}
</style>