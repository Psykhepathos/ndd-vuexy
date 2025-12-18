import { ref } from 'vue'
import L from 'leaflet'
import { getApiUrl } from '@/config/api'

/**
 * Composable para carregar e exibir praças de pedágio em mapas Leaflet
 *
 * Uso:
 * const { loadPracasProximas, addPracasToMap } = usePracasPedagio()
 *
 * // Carregar praças próximas a uma rota
 * const pracas = await loadPracasProximas(waypoints, raioKm)
 *
 * // Adicionar marcadores ao mapa
 * addPracasToMap(map, pracas)
 */

export interface PracaPedagio {
  id: number
  concessionaria: string
  praca: string
  rodovia: string
  uf: string
  km: string
  municipio: string
  situacao: string
  latitude: string
  longitude: string
}

interface Waypoint {
  lat: number
  lon: number
}

export function usePracasPedagio() {
  const loading = ref(false)
  const pracas = ref<PracaPedagio[]>([])
  const pracaMarkers = ref<L.Marker[]>([])

  /**
   * Carrega TODAS as praças de pedágio ativas do banco de dados
   * @returns Array de todas as praças
   */
  const loadTodasPracas = async (): Promise<PracaPedagio[]> => {
    loading.value = true

    try {
      console.log('📍 Carregando TODAS as praças de pedágio...')

      const response = await fetch(getApiUrl('/pracas-pedagio?per_page=500&situacao=Ativo'), {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        }
      })

      const data = await response.json()

      if (data.success && data.data) {
        pracas.value = data.data
        console.log(`✅ usePracasPedagio: ${data.data.length} praças carregadas`)
        return data.data
      } else {
        console.warn('⚠️ Falha ao carregar praças:', data)
        return []
      }
    } catch (error) {
      console.error('❌ usePracasPedagio: Erro ao carregar todas as praças:', error)
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Carrega praças de pedágio próximas a uma lista de waypoints
   * @param waypoints - Lista de coordenadas (waypoints de uma rota)
   * @param raioKm - Raio de busca em km (padrão: 50km)
   * @returns Array de praças encontradas
   */
  const loadPracasProximas = async (
    waypoints: Waypoint[],
    raioKm: number = 50
  ): Promise<PracaPedagio[]> => {
    if (!waypoints || waypoints.length === 0) {
      console.warn('usePracasPedagio: waypoints vazios')
      return []
    }

    loading.value = true
    const allPracas: PracaPedagio[] = []
    const pracasIds = new Set<number>() // Para evitar duplicatas

    try {
      // Buscar praças próximas a cada waypoint
      for (const waypoint of waypoints) {
        const response = await fetch(getApiUrl('/pracas-pedagio/proximidade'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            lat: waypoint.lat,
            lon: waypoint.lon,
            raio_km: raioKm
          })
        })

        const data = await response.json()

        if (data.success && data.data) {
          // Adicionar praças únicas (evitar duplicatas)
          data.data.forEach((praca: PracaPedagio) => {
            if (!pracasIds.has(praca.id)) {
              pracasIds.add(praca.id)
              allPracas.push(praca)
            }
          })
        }
      }

      pracas.value = allPracas
      console.log(`✅ usePracasPedagio: ${allPracas.length} praças encontradas`)
      return allPracas
    } catch (error) {
      console.error('❌ usePracasPedagio: Erro ao carregar praças:', error)
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Adiciona marcadores de praças de pedágio ao mapa Leaflet
   * @param map - Instância do mapa Leaflet
   * @param pracasData - Array de praças a exibir
   * @param options - Opções de customização
   */
  const addPracasToMap = (
    map: L.Map,
    pracasData: PracaPedagio[],
    options: {
      color?: string
      icon?: string
      showPopup?: boolean
      zIndex?: number
    } = {}
  ) => {
    // Remove marcadores anteriores
    removePracasFromMap()

    if (!map || !pracasData || pracasData.length === 0) {
      return
    }

    const {
      color = '#F44336', // Vermelho para praças de pedágio
      icon = 'tabler-coin',
      showPopup = true,
      zIndex = 1000
    } = options

    pracasData.forEach(praca => {
      const lat = parseFloat(praca.latitude)
      const lon = parseFloat(praca.longitude)

      if (isNaN(lat) || isNaN(lon)) {
        console.warn(`⚠️ Coordenadas inválidas para praça ${praca.id}`)
        return
      }

      // Criar ícone customizado
      const pracaIcon = L.divIcon({
        html: `
          <div style="
            background-color: ${color};
            border: 2px solid white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
          ">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <circle cx="12" cy="12" r="8"></circle>
              <path d="M12 6v12"></path>
            </svg>
          </div>
        `,
        className: 'praca-pedagio-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16]
      })

      // Criar marcador
      const marker = L.marker([lat, lon], {
        icon: pracaIcon,
        zIndexOffset: zIndex
      })

      // Adicionar popup se solicitado
      if (showPopup) {
        const popupContent = `
          <div style="min-width: 200px;">
            <h6 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">
              ${praca.praca}
            </h6>
            <div style="font-size: 12px; line-height: 1.6;">
              <p style="margin: 4px 0;"><strong>Rodovia:</strong> ${praca.rodovia}</p>
              <p style="margin: 4px 0;"><strong>KM:</strong> ${parseFloat(praca.km).toFixed(1)}</p>
              <p style="margin: 4px 0;"><strong>Município:</strong> ${praca.municipio}/${praca.uf}</p>
              <p style="margin: 4px 0;"><strong>Concessionária:</strong> ${praca.concessionaria}</p>
              <p style="margin: 4px 0;">
                <strong>Status:</strong>
                <span style="
                  display: inline-block;
                  padding: 2px 8px;
                  border-radius: 4px;
                  background-color: ${praca.situacao === 'Ativo' ? '#4CAF50' : '#F44336'};
                  color: white;
                  font-size: 10px;
                ">
                  ${praca.situacao}
                </span>
              </p>
            </div>
          </div>
        `
        marker.bindPopup(popupContent)
      }

      // Adicionar ao mapa
      marker.addTo(map)
      pracaMarkers.value.push(marker)
    })

    console.log(`✅ usePracasPedagio: ${pracaMarkers.value.length} marcadores adicionados ao mapa`)
  }

  /**
   * Remove todos os marcadores de praças do mapa
   */
  const removePracasFromMap = () => {
    pracaMarkers.value.forEach(marker => {
      marker.remove()
    })
    pracaMarkers.value = []
  }

  /**
   * Carrega e exibe TODAS as praças de pedágio no mapa (padrão)
   * @param map - Instância do mapa Leaflet
   * @param options - Opções de customização dos marcadores
   */
  const loadAndDisplayPracas = async (
    map: L.Map,
    options: Parameters<typeof addPracasToMap>[2] = {}
  ): Promise<PracaPedagio[]> => {
    // SEMPRE carrega TODAS as praças (comportamento padrão)
    const pracasData = await loadTodasPracas()

    if (pracasData.length > 0) {
      addPracasToMap(map, pracasData, options)
    }
    return pracasData
  }

  return {
    loading,
    pracas,
    pracaMarkers,
    loadTodasPracas,
    loadPracasProximas,
    addPracasToMap,
    removePracasFromMap,
    loadAndDisplayPracas
  }
}
