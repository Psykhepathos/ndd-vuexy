import { ref, computed } from 'vue'
import { API_ENDPOINTS, apiFetch } from '@/config/api'

// ===================================
// INTERFACES
// ===================================

export interface PacoteAutocomplete {
  codpac: number
  codrot: string
  datforpac: string
  sitpac: string
  nroped: number
  nomtrn: string
  label: string
}

export interface PacoteEntrega {
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
  lat?: number
  lon?: number
}

export interface SimulationMarker {
  type: 'route' | 'delivery' | 'connection'
  index: number
  lat: number
  lng: number
  label: string
  color: string
  info: any
}

// ===================================
// COMPOSABLE
// ===================================

export function usePackageSimulation() {
  // Estados
  const loadingPacotes = ref(false)
  const pacotesOptions = ref<PacoteAutocomplete[]>([])
  const selectedPacote = ref<PacoteAutocomplete | null>(null)
  const searchPacote = ref('')
  const entregas = ref<PacoteEntrega[]>([])
  const simulationActive = ref(false)
  const loadingSimulation = ref(false)

  /**
   * Processa coordenada GPS do formato Progress para decimal
   * Formato Progress: "230876543" -> "-23,0876543"
   * Baseado na lógica do ProgressService.php
   */
  const processGpsCoordinate = (coordinate: string | undefined): number | undefined => {
    if (!coordinate) return undefined

    // Limpar coordenada (remover W, N, E, S, -, ., ,)
    let coord = coordinate.toString().trim()
    coord = coord.replace(/[WNES]/g, '')
    coord = coord.replace(/[-.,]/g, '')

    if (coord.length >= 3) {
      // Formato brasileiro: "-14,0876543" (sinal negativo + 2 dígitos + vírgula + demais dígitos)
      const intPart = coord.substring(0, 2)
      const decPart = coord.substring(2)
      const formatted = `-${intPart}.${decPart}` // Usar ponto para JS

      const parsed = parseFloat(formatted)
      return isNaN(parsed) ? undefined : parsed
    }

    return undefined
  }

  /**
   * Busca pacotes para autocomplete
   */
  const fetchPacotesAutocomplete = async (search: string = '') => {
    if (search.length < 1 && search !== '') return

    loadingPacotes.value = true

    try {
      const params = new URLSearchParams({
        search: search
      })

      const response = await apiFetch(`${API_ENDPOINTS.pacoteAutocomplete}?${params}`)

      const data = await response.json()

      if (data.success) {
        pacotesOptions.value = data.data
      } else {
        console.error('Erro ao buscar pacotes:', data.message)
        pacotesOptions.value = []
      }
    } catch (error) {
      console.error('Erro ao buscar pacotes:', error)
      pacotesOptions.value = []
    } finally {
      loadingPacotes.value = false
    }
  }

  /**
   * Carrega entregas do pacote selecionado
   */
  const loadPacoteEntregas = async (codPac: number): Promise<boolean> => {
    loadingSimulation.value = true

    try {
      const response = await apiFetch(API_ENDPOINTS.pacoteItinerario, {
        method: 'POST',
        body: JSON.stringify({
          Pacote: {
            codPac: codPac
          }
        })
      })

      const data = await response.json()

      if (data.success && data.data?.pedidos) {
        // Processar coordenadas GPS
        const entregasProcessadas = data.data.pedidos.map((entrega: any) => {
          const lat = processGpsCoordinate(entrega.gps_lat)
          const lon = processGpsCoordinate(entrega.gps_lon)

          return {
            ...entrega,
            lat,
            lon
          }
        })

        // Filtrar apenas entregas com coordenadas válidas
        entregas.value = entregasProcessadas.filter((e: PacoteEntrega) =>
          e.lat !== undefined && e.lon !== undefined
        )

        console.log(`✅ Carregadas ${entregas.value.length} entregas com GPS de ${entregasProcessadas.length} total`)

        return entregas.value.length > 0
      } else {
        console.error('Erro ao carregar itinerário:', data.message)
        return false
      }
    } catch (error) {
      console.error('Erro ao carregar entregas:', error)
      return false
    } finally {
      loadingSimulation.value = false
    }
  }

  /**
   * Inicia simulação de entregas
   */
  const startSimulation = async () => {
    if (!selectedPacote.value) {
      console.warn('Nenhum pacote selecionado')
      return false
    }

    console.log('🚀 Iniciando simulação para pacote:', selectedPacote.value.codpac)

    const success = await loadPacoteEntregas(selectedPacote.value.codpac)

    if (success) {
      simulationActive.value = true
      console.log('✅ Simulação ativada')
      return true
    } else {
      console.error('❌ Falha ao iniciar simulação - sem entregas com GPS')
      return false
    }
  }

  /**
   * Para simulação e limpa dados
   */
  const stopSimulation = () => {
    simulationActive.value = false
    entregas.value = []
    selectedPacote.value = null
    searchPacote.value = ''
    console.log('⏹️ Simulação parada')
  }

  /**
   * Calcula cor do marcador baseado no tipo e index
   */
  const getMarkerColor = (type: 'route' | 'delivery', index: number, total: number): string => {
    if (type === 'route') {
      // Rota SemParar: azul (gradiente de claro a escuro)
      const intensity = Math.floor(200 - (index / total) * 50)
      return `rgb(33, ${intensity}, 243)` // #2196F3 variando
    } else {
      // Entregas: laranja/verde (gradiente)
      if (index === 0) return '#4CAF50' // Verde para primeira entrega
      if (index === total - 1) return '#F44336' // Vermelho para última
      return '#FF9800' // Laranja para intermediárias
    }
  }

  /**
   * Cria marcadores combinados (rota + entregas)
   */
  const createCombinedMarkers = (
    routePoints: Array<{ lat: number, lon: number, desmun: string, desest: string, spararmuseq: number }>,
    deliveryPoints: PacoteEntrega[]
  ): SimulationMarker[] => {
    const markers: SimulationMarker[] = []

    // 1. Adicionar pontos da rota SemParar (azul)
    routePoints.forEach((point, index) => {
      markers.push({
        type: 'route',
        index: index + 1,
        lat: point.lat,
        lng: point.lon,
        label: `${index + 1}`,
        color: '#2196F3', // Azul para rota base
        info: {
          title: `Rota ${index + 1}`,
          municipio: point.desmun,
          estado: point.desest,
          tipo: 'Rota SemParar'
        }
      })
    })

    // 2. Adicionar entregas (laranja/verde/vermelho)
    deliveryPoints.forEach((entrega, index) => {
      const markerIndex = routePoints.length + index + 1

      markers.push({
        type: 'delivery',
        index: markerIndex,
        lat: entrega.lat!,
        lng: entrega.lon!,
        label: `${markerIndex}`,
        color: getMarkerColor('delivery', index, deliveryPoints.length),
        info: {
          title: `Entrega ${entrega.seqent}`,
          cliente: entrega.razcli,
          endereco: entrega.desend,
          bairro: entrega.desbai,
          municipio: entrega.desmun,
          uf: entrega.uf,
          valor: entrega.valnot,
          peso: entrega.peso,
          volume: entrega.volume,
          tipo: 'Entrega'
        }
      })
    })

    return markers
  }

  /**
   * Cria waypoints para roteamento (rota + entregas)
   */
  const createCombinedWaypoints = (
    routePoints: Array<{ lat: number, lon: number }>,
    deliveryPoints: PacoteEntrega[]
  ): Array<{ lat: number, lng: number }> => {
    const waypoints: Array<{ lat: number, lng: number }> = []

    // 1. Adicionar todos os pontos da rota SemParar
    routePoints.forEach(point => {
      waypoints.push({ lat: point.lat, lng: point.lon })
    })

    // 2. Adicionar todas as entregas (começando do último ponto da rota)
    deliveryPoints.forEach(entrega => {
      waypoints.push({ lat: entrega.lat!, lng: entrega.lon! })
    })

    return waypoints
  }

  // Computeds
  const hasSimulation = computed(() => simulationActive.value && entregas.value.length > 0)
  const totalEntregas = computed(() => entregas.value.length)
  const entregasComGps = computed(() => entregas.value.filter(e => e.lat && e.lon).length)

  return {
    // Estados
    loadingPacotes,
    pacotesOptions,
    selectedPacote,
    searchPacote,
    entregas,
    simulationActive,
    loadingSimulation,

    // Computeds
    hasSimulation,
    totalEntregas,
    entregasComGps,

    // Métodos
    processGpsCoordinate,
    fetchPacotesAutocomplete,
    loadPacoteEntregas,
    startSimulation,
    stopSimulation,
    getMarkerColor,
    createCombinedMarkers,
    createCombinedWaypoints,
  }
}
