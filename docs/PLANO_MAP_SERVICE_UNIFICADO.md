# Plano de Implementação: MapService Unificado

## 📋 Sumário Executivo

**Objetivo:** Criar um sistema unificado de mapas que padronize a criação, cache e renderização de rotas em todo o sistema NDD, eliminando código duplicado e garantindo performance consistente.

**Sistemas Analisados:**
1. `/itinerario/:id` - Mapa de itinerário de pacote (Google Maps API)
2. `/rotas-padrao/mapa/:id` - Mapa de rotas SemParar com simulação (Leaflet + OSRM Proxy)

---

## 🎯 Análise Comparativa dos Sistemas Existentes

### Sistema 1: Itinerário (`/itinerário/:id`)

**Características:**
- ✅ **Map Library:** Leaflet + OpenStreetMap tiles
- ✅ **Routing:** Google Maps Directions API (pago, mas confiável)
- ✅ **Cache:** Sistema de cache via API Laravel (`/api/route-cache/*`)
- ✅ **Agrupamento:** Não aplica (entregas individuais)
- ✅ **Limite:** Divide em chunks de 23 waypoints (limite Google Maps)
- ✅ **Coordenadas:** Conversão automática Progress → Decimal
- ❌ **Problema:** Depende 100% de Google Maps (custo)
- ❌ **Problema:** Não usa OSRM gratuito

**Estrutura de Dados:**
```typescript
interface PedidoItinerario {
  seqent: number
  razcli: string
  desend: string
  gps_lat?: string  // Formato Progress: "230876543" = -23.0876543
  gps_lon?: string
}
```

**Fluxo de Routing:**
```
1. Carregar entregas do pacote
2. Converter coordenadas Progress → Decimal
3. Buscar rota no cache (/api/route-cache/find)
4. Se não existe no cache:
   a. Dividir em chunks de 23 waypoints
   b. Chamar Google Maps Directions API para cada chunk
   c. Combinar resultados
   d. Salvar no cache (/api/route-cache/save)
5. Desenhar polyline no Leaflet
```

---

### Sistema 2: Rotas Padrão (`/rotas-padrao/mapa/:id`)

**Características:**
- ✅ **Map Library:** Leaflet + OpenStreetMap tiles
- ✅ **Routing:** Proxy Laravel + OSRM (100% gratuito)
- ✅ **Cache:** Não implementado (problema!)
- ✅ **Agrupamento:** Não aplica (municípios pré-definidos)
- ✅ **Simulação:** Suporta simular pacote sobre rota
- ✅ **Geocoding:** Google Geocoding API com cache SQLite
- ✅ **Debug:** Sistema completo de logs
- ❌ **Problema:** Sem cache de rotas (recalcula sempre)
- ❌ **Problema:** Limit de 10 waypoints por request OSRM

**Estrutura de Dados:**
```typescript
interface RotaMunicipio {
  spararmuseq: number
  codmun: number
  desmun: string
  cdibge: number
  lat?: number
  lon?: number
}
```

**Fluxo de Routing:**
```
1. Carregar municípios da rota SemParar
2. Geocodificar municípios sem coordenadas
3. Se há simulação de pacote:
   a. Carregar entregas do pacote
   b. Agrupar entregas por proximidade (raio 5km)
   c. Combinar municípios + grupos de entregas
4. Calcular rota segmento por segmento:
   a. POST /api/routing/route { start: [lng,lat], end: [lng,lat] }
   b. Backend tenta 3 servidores OSRM
   c. Fallback: linha reta se todos falharem
5. Desenhar polyline no Leaflet
```

---

## 📐 Arquitetura Proposta: MapService Unificado

### Princípios de Design

1. **Single Responsibility:** Um service para todas as operações de mapa
2. **Caching First:** Cache agressivo para minimizar requisições externas
3. **Provider Agnostic:** Suporta múltiplos providers (Google, OSRM, HERE, etc)
4. **Type Safe:** TypeScript completo com interfaces bem definidas
5. **Observable:** Sistema de eventos para monitoramento

---

### Componentes do Sistema

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (Vue Components)                 │
├─────────────────────────────────────────────────────────────┤
│  • itinerario/[id].vue                                       │
│  • rotas-padrao/mapa/[id].vue                                │
│  • compra-viagem/nova.vue (mapa)                             │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│            COMPOSABLE: useMapService()                       │
├─────────────────────────────────────────────────────────────┤
│  • initMap(container, options)                               │
│  • addPoints(points[], config)                               │
│  • calculateRoute(waypoints[], options)                      │
│  • groupPoints(points[], radius)                             │
│  • clearMap()                                                │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│          BACKEND: MapController + MapService                 │
├─────────────────────────────────────────────────────────────┤
│  Routes:                                                      │
│  • POST /api/map/route              - Calculate route        │
│  • POST /api/map/geocode-batch      - Batch geocoding        │
│  • POST /api/map/cluster-points     - Group nearby points    │
│  • GET  /api/map/cache-stats        - Cache statistics       │
│                                                               │
│  Services:                                                    │
│  • MapService.php           - Main orchestrator              │
│  • RouteProviderFactory.php - Provider selection             │
│  • CacheManager.php         - Unified cache layer            │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ├────────────┬───────────────┬────────────────┐
                 ▼            ▼               ▼                ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ Google Maps  │ │ OSRM Proxy   │ │ HERE Maps    │ │ MapBox       │
    │   Provider   │ │  Provider    │ │   Provider   │ │   Provider   │
    └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    DATABASE (SQLite)                         │
├─────────────────────────────────────────────────────────────┤
│  • route_cache           - Cached routes                     │
│  • geocoding_cache       - Cached coordinates                │
│  • cluster_definitions   - Pre-computed clusters             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗂️ Estrutura de Arquivos Proposta

```
app/
├── Services/
│   └── Map/
│       ├── MapService.php                    # Orchestrator principal
│       ├── CacheManager.php                  # Gerenciamento de cache unificado
│       ├── RouteCalculator.php               # Lógica de cálculo de rotas
│       ├── PointClusterer.php                # Agrupamento de pontos
│       ├── Providers/
│       │   ├── RouteProviderInterface.php    # Interface comum
│       │   ├── GoogleMapsProvider.php        # Google Maps implementation
│       │   ├── OsrmProvider.php              # OSRM implementation
│       │   ├── HereMapsProvider.php          # HERE Maps (futuro)
│       │   └── MapBoxProvider.php            # MapBox (futuro)
│       └── Utils/
│           ├── CoordinateConverter.php       # Conversões Progress → Decimal
│           ├── BoundsCalculator.php          # Cálculo de bounds
│           └── DistanceCalculator.php        # Haversine, etc

├── Http/
│   └── Controllers/
│       └── Api/
│           └── MapController.php             # API REST unificada

├── Models/
│   ├── RouteCache.php                        # Eloquent model (já existe)
│   ├── GeocodingCache.php                    # Eloquent model (já existe)
│   └── ClusterDefinition.php                 # Novo: clusters pré-computados

resources/ts/
├── composables/
│   ├── useMapService.ts                      # Composable principal
│   ├── useRouteCache.ts                      # Cache management
│   └── usePointClustering.ts                 # Clustering logic

├── types/
│   └── map/
│       ├── MapOptions.ts                     # Configurações de mapa
│       ├── RouteOptions.ts                   # Configurações de routing
│       ├── Point.ts                          # Tipos de pontos
│       └── ClusterConfig.ts                  # Configurações de cluster

└── utils/
    └── map/
        ├── coordinateUtils.ts                # Conversões de coordenadas
        ├── geometryUtils.ts                  # Cálculos geométricos
        └── markerFactory.ts                  # Criação de marcadores customizados
```

---

## 📦 Interfaces e Tipos TypeScript

```typescript
// ============================================================================
// TIPOS BASE
// ============================================================================

/**
 * Ponto geográfico básico
 */
export interface GeoPoint {
  lat: number
  lon: number
  label?: string
  metadata?: Record<string, any>
}

/**
 * Ponto com informações de entrega
 */
export interface DeliveryPoint extends GeoPoint {
  type: 'delivery'
  seqent: number          // Sequência da entrega
  razcli: string          // Nome do cliente
  desend: string          // Endereço
  valnot: number          // Valor da nota
  peso: number            // Peso
  volume: number          // Volume
  gps_lat?: string        // GPS Progress format
  gps_lon?: string        // GPS Progress format
}

/**
 * Ponto de município (rota pré-definida)
 */
export interface MunicipalityPoint extends GeoPoint {
  type: 'municipality'
  spararmuseq: number     // Sequência na rota
  codmun: number          // Código Progress
  codest: number          // Código do estado
  desmun: string          // Nome do município
  desest: string          // Sigla do estado
  cdibge: number          // Código IBGE
}

/**
 * Cluster de pontos próximos
 */
export interface PointCluster {
  id: string
  center: GeoPoint
  points: (DeliveryPoint | MunicipalityPoint)[]
  count: number
  radius: number          // Raio em km
  label: string           // Ex: "3 entregas em São Paulo"
}

// ============================================================================
// OPÇÕES DE CONFIGURAÇÃO
// ============================================================================

/**
 * Configurações de inicialização do mapa
 */
export interface MapInitOptions {
  container: HTMLElement
  center?: [number, number]    // [lat, lon]
  zoom?: number
  minZoom?: number
  maxZoom?: number
  bounds?: [[number, number], [number, number]]
  tileProvider?: 'osm' | 'google' | 'mapbox'
  attribution?: string
}

/**
 * Configurações de cálculo de rota
 */
export interface RouteCalculationOptions {
  waypoints: GeoPoint[]
  provider?: 'google' | 'osrm' | 'here' | 'mapbox' | 'auto'
  useCache?: boolean
  cacheKey?: string
  maxWaypointsPerRequest?: number
  fallbackToStraightLine?: boolean
  color?: string
  weight?: number
  opacity?: number
  dashArray?: string
  onProgress?: (progress: RouteProgress) => void
}

/**
 * Progresso do cálculo de rota
 */
export interface RouteProgress {
  totalSegments: number
  completedSegments: number
  cachedSegments: number
  currentSegment: {
    from: string
    to: string
    status: 'pending' | 'calculating' | 'complete' | 'error' | 'cached'
  }
}

/**
 * Resultado do cálculo de rota
 */
export interface RouteResult {
  success: boolean
  coordinates: Array<[number, number]>  // [[lat, lon], ...]
  distance_km: number
  duration_seconds?: number
  provider: string
  cached: boolean
  segments: RouteSegment[]
  bounds: [[number, number], [number, number]]
  error?: string
}

/**
 * Segmento individual de rota
 */
export interface RouteSegment {
  index: number
  from: GeoPoint
  to: GeoPoint
  coordinates: Array<[number, number]>
  distance_km: number
  duration_seconds?: number
  cached: boolean
  provider: string
  status: 'success' | 'fallback' | 'error'
}

/**
 * Configurações de agrupamento (clustering)
 */
export interface ClusteringOptions {
  enabled: boolean
  radius: number              // Raio em km (padrão: 5)
  minPoints?: number          // Mínimo de pontos para formar cluster (padrão: 2)
  maxPoints?: number          // Máximo de pontos por cluster (padrão: 50)
  algorithm?: 'proximity' | 'kmeans' | 'dbscan'
  excludeTypes?: ('delivery' | 'municipality')[]
}

/**
 * Configurações de marcadores
 */
export interface MarkerOptions {
  type: 'delivery' | 'municipality' | 'cluster' | 'custom'
  icon?: 'numbered' | 'colored' | 'emoji' | 'custom'
  color?: string
  number?: number
  emoji?: string
  size?: 'small' | 'medium' | 'large'
  showPopup?: boolean
  popupContent?: string | ((data: any) => string)
  draggable?: boolean
  onClick?: (marker: any) => void
}

// ============================================================================
// CACHE
// ============================================================================

/**
 * Entrada de cache de rota
 */
export interface RouteCacheEntry {
  id: number
  cache_key: string
  waypoints: Array<[number, number]>
  waypoints_count: number
  coordinates: Array<[number, number]>
  total_distance: number
  provider: string
  created_at: string
  expires_at: string
}

/**
 * Entrada de cache de geocoding
 */
export interface GeocodingCacheEntry {
  id: number
  cdibge: string
  desmun: string
  desest: string
  lat: number
  lon: number
  fonte: 'google' | 'progress' | 'manual'
  geocoded_at: string
}
```

---

## 🔧 API Backend Proposta

### Endpoint 1: Calcular Rota

```http
POST /api/map/route
Content-Type: application/json

{
  "waypoints": [
    { "lat": -23.5505, "lon": -46.6333, "label": "São Paulo" },
    { "lat": -22.9068, "lon": -43.1729, "label": "Rio de Janeiro" }
  ],
  "options": {
    "provider": "auto",           // auto | google | osrm | here
    "use_cache": true,
    "fallback_to_straight": true,
    "max_waypoints_per_request": 23
  }
}

Response 200 OK:
{
  "success": true,
  "data": {
    "coordinates": [[lat, lon], ...],
    "distance_km": 434.5,
    "duration_seconds": 18000,
    "provider": "osrm",
    "cached": false,
    "segments": [
      {
        "index": 0,
        "from": { "lat": -23.5505, "lon": -46.6333 },
        "to": { "lat": -22.9068, "lon": -43.1729 },
        "distance_km": 434.5,
        "cached": false,
        "provider": "osrm",
        "status": "success"
      }
    ],
    "bounds": [[-23.5505, -46.6333], [-22.9068, -43.1729]],
    "cache_key": "route_hash_abc123"
  }
}
```

### Endpoint 2: Agrupamento de Pontos

```http
POST /api/map/cluster-points
Content-Type: application/json

{
  "points": [
    { "lat": -23.550, "lon": -46.633, "type": "delivery", "label": "Cliente A" },
    { "lat": -23.551, "lon": -46.634, "type": "delivery", "label": "Cliente B" },
    { "lat": -23.552, "lon": -46.635, "type": "delivery", "label": "Cliente C" }
  ],
  "options": {
    "radius": 5,                  // km
    "min_points": 2,
    "algorithm": "proximity",
    "exclude_types": ["municipality"]
  }
}

Response 200 OK:
{
  "success": true,
  "data": {
    "clusters": [
      {
        "id": "cluster_1",
        "center": { "lat": -23.551, "lon": -46.634 },
        "points": [...],
        "count": 3,
        "radius": 2.5,
        "label": "3 entregas em São Paulo - SP"
      }
    ],
    "ungrouped": [],
    "stats": {
      "total_points": 3,
      "total_clusters": 1,
      "ungrouped_count": 0
    }
  }
}
```

### Endpoint 3: Geocoding em Lote

```http
POST /api/map/geocode-batch
Content-Type: application/json

{
  "municipalities": [
    { "cdibge": "3550308", "desmun": "SÃO PAULO", "desest": "SP" },
    { "cdibge": "3304557", "desmun": "RIO DE JANEIRO", "desest": "RJ" }
  ],
  "options": {
    "use_cache": true,
    "source": "google"          // google | progress | auto
  }
}

Response 200 OK:
{
  "success": true,
  "data": {
    "3550308": { "lat": -23.5505, "lon": -46.6333, "cached": true },
    "3304557": { "lat": -22.9068, "lon": -43.1729, "cached": false }
  },
  "stats": {
    "total": 2,
    "cached": 1,
    "geocoded": 1,
    "failed": 0
  }
}
```

### Endpoint 4: Estatísticas de Cache

```http
GET /api/map/cache-stats

Response 200 OK:
{
  "success": true,
  "data": {
    "route_cache": {
      "total_entries": 1543,
      "size_mb": 45.2,
      "hit_rate": 0.78,
      "avg_distance_km": 325.4,
      "providers": {
        "osrm": 1230,
        "google": 313
      }
    },
    "geocoding_cache": {
      "total_entries": 5570,
      "size_mb": 2.1,
      "hit_rate": 0.92,
      "sources": {
        "google": 4890,
        "progress": 680
      }
    }
  }
}
```

---

## 🎨 Composable Frontend: useMapService()

```typescript
// resources/ts/composables/useMapService.ts

import { ref, computed } from 'vue'
import L from 'leaflet'
import type {
  MapInitOptions,
  RouteCalculationOptions,
  RouteResult,
  GeoPoint,
  ClusteringOptions,
  PointCluster
} from '@/types/map'

export function useMapService() {
  const map = ref<L.Map | null>(null)
  const markers = ref<L.Marker[]>([])
  const routeLayer = ref<L.Polyline | null>(null)
  const isLoading = ref(false)
  const lastRoute = ref<RouteResult | null>(null)

  // ============================================================================
  // INICIALIZAÇÃO
  // ============================================================================

  /**
   * Inicializa o mapa Leaflet
   */
  const initMap = (options: MapInitOptions) => {
    if (!options.container) {
      throw new Error('Container element is required')
    }

    if (map.value) {
      console.warn('Map already initialized, destroying old instance')
      destroyMap()
    }

    const center = options.center || [-14.2350, -51.9253]  // Brasil
    const zoom = options.zoom || 4

    map.value = L.map(options.container).setView(center, zoom)

    // Adicionar tiles
    const tileUrl = getTileUrl(options.tileProvider || 'osm')
    L.tileLayer(tileUrl, {
      attribution: options.attribution || '© OpenStreetMap contributors',
      maxZoom: options.maxZoom || 19,
      minZoom: options.minZoom || 2
    }).addTo(map.value)

    // Aplicar bounds se fornecidos
    if (options.bounds) {
      map.value.fitBounds(options.bounds)
    }

    return map.value
  }

  /**
   * Obtém URL dos tiles baseado no provider
   */
  const getTileUrl = (provider: string): string => {
    const urls = {
      osm: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      google: 'https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
      mapbox: 'https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}'
    }
    return urls[provider] || urls.osm
  }

  /**
   * Destrói o mapa e limpa recursos
   */
  const destroyMap = () => {
    if (!map.value) return

    clearMarkers()
    clearRoute()
    map.value.remove()
    map.value = null
  }

  // ============================================================================
  // MARCADORES
  // ============================================================================

  /**
   * Adiciona múltiplos marcadores ao mapa
   */
  const addMarkers = (
    points: GeoPoint[],
    options?: {
      cluster?: ClusteringOptions
      markerStyle?: 'numbered' | 'colored' | 'emoji'
      onClick?: (point: GeoPoint) => void
    }
  ) => {
    if (!map.value) throw new Error('Map not initialized')

    clearMarkers()

    // Se clustering está habilitado, agrupar pontos primeiro
    let pointsToRender = points
    if (options?.cluster?.enabled) {
      const clusters = clusterPoints(points, options.cluster)
      // TODO: Renderizar clusters
      pointsToRender = clusters.flatMap(c => c.points)
    }

    pointsToRender.forEach((point, index) => {
      const marker = createMarker(point, index, options?.markerStyle)
      marker.addTo(map.value!)

      if (options?.onClick) {
        marker.on('click', () => options.onClick!(point))
      }

      markers.value.push(marker)
    })

    // Ajustar bounds para mostrar todos os marcadores
    if (markers.value.length > 0) {
      const group = L.featureGroup(markers.value)
      map.value.fitBounds(group.getBounds(), { padding: [50, 50] })
    }
  }

  /**
   * Cria um marcador customizado
   */
  const createMarker = (
    point: GeoPoint,
    index: number,
    style: string = 'numbered'
  ): L.Marker => {
    const latLng = L.latLng(point.lat, point.lon)

    let icon: L.DivIcon

    if (style === 'numbered') {
      icon = L.divIcon({
        html: `<div style="
          background: #2196F3;
          color: white;
          border-radius: 50%;
          width: 32px;
          height: 32px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: bold;
          border: 2px solid white;
          box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        ">${index + 1}</div>`,
        className: '',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      })
    } else {
      // Default marker
      icon = L.icon.default()
    }

    const marker = L.marker(latLng, { icon })

    if (point.label) {
      marker.bindPopup(point.label)
    }

    return marker
  }

  /**
   * Limpa todos os marcadores
   */
  const clearMarkers = () => {
    markers.value.forEach(m => m.remove())
    markers.value = []
  }

  // ============================================================================
  // CÁLCULO DE ROTA
  // ============================================================================

  /**
   * Calcula e desenha rota entre waypoints
   */
  const calculateRoute = async (options: RouteCalculationOptions): Promise<RouteResult> => {
    if (!map.value) throw new Error('Map not initialized')
    if (options.waypoints.length < 2) {
      throw new Error('At least 2 waypoints are required')
    }

    isLoading.value = true

    try {
      // Chamar API backend
      const response = await fetch('http://localhost:8002/api/map/route', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          waypoints: options.waypoints.map(w => ({ lat: w.lat, lon: w.lon, label: w.label })),
          options: {
            provider: options.provider || 'auto',
            use_cache: options.useCache !== false,
            fallback_to_straight: options.fallbackToStraightLine !== false,
            max_waypoints_per_request: options.maxWaypointsPerRequest || 23
          }
        })
      })

      const data = await response.json()

      if (!data.success) {
        throw new Error(data.error || 'Failed to calculate route')
      }

      const result: RouteResult = data.data
      lastRoute.value = result

      // Desenhar rota no mapa
      drawRoute(result.coordinates, {
        color: options.color || '#2196F3',
        weight: options.weight || 4,
        opacity: options.opacity || 0.7,
        dashArray: options.dashArray
      })

      return result

    } catch (error) {
      console.error('Error calculating route:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Desenha rota no mapa
   */
  const drawRoute = (
    coordinates: Array<[number, number]>,
    style: {
      color?: string
      weight?: number
      opacity?: number
      dashArray?: string
    } = {}
  ) => {
    if (!map.value) return

    // Remover rota anterior
    clearRoute()

    routeLayer.value = L.polyline(coordinates, {
      color: style.color || '#2196F3',
      weight: style.weight || 4,
      opacity: style.opacity || 0.7,
      dashArray: style.dashArray
    }).addTo(map.value)
  }

  /**
   * Limpa rota do mapa
   */
  const clearRoute = () => {
    if (routeLayer.value) {
      routeLayer.value.remove()
      routeLayer.value = null
    }
  }

  // ============================================================================
  // CLUSTERING
  // ============================================================================

  /**
   * Agrupa pontos por proximidade
   */
  const clusterPoints = (
    points: GeoPoint[],
    options: ClusteringOptions
  ): PointCluster[] => {
    // Implementação simplificada - algoritmo de proximidade
    const clusters: PointCluster[] = []
    const remaining = [...points]

    while (remaining.length > 0) {
      const base = remaining.shift()!
      const cluster: PointCluster = {
        id: `cluster_${clusters.length}`,
        center: base,
        points: [base],
        count: 1,
        radius: 0,
        label: base.label || ''
      }

      // Encontrar pontos próximos
      for (let i = remaining.length - 1; i >= 0; i--) {
        const point = remaining[i]
        const distance = calculateDistance(cluster.center, point)

        if (distance <= options.radius) {
          cluster.points.push(point)
          cluster.count++
          // Recalcular centro (média)
          cluster.center = {
            lat: cluster.points.reduce((sum, p) => sum + p.lat, 0) / cluster.points.length,
            lon: cluster.points.reduce((sum, p) => sum + p.lon, 0) / cluster.points.length
          }
          remaining.splice(i, 1)
        }
      }

      clusters.push(cluster)
    }

    return clusters
  }

  /**
   * Calcula distância entre dois pontos (Haversine)
   */
  const calculateDistance = (p1: GeoPoint, p2: GeoPoint): number => {
    const R = 6371 // Raio da Terra em km
    const dLat = (p2.lat - p1.lat) * Math.PI / 180
    const dLon = (p2.lon - p1.lon) * Math.PI / 180
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(p1.lat * Math.PI / 180) * Math.cos(p2.lat * Math.PI / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2)
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    return R * c
  }

  // ============================================================================
  // COMPUTED & RETURN
  // ============================================================================

  const hasMap = computed(() => map.value !== null)
  const markerCount = computed(() => markers.value.length)
  const hasRoute = computed(() => routeLayer.value !== null)

  return {
    // Estado
    map,
    markers,
    routeLayer,
    isLoading,
    lastRoute,

    // Computed
    hasMap,
    markerCount,
    hasRoute,

    // Métodos
    initMap,
    destroyMap,
    addMarkers,
    clearMarkers,
    calculateRoute,
    drawRoute,
    clearRoute,
    clusterPoints,
    calculateDistance
  }
}
```

---

## 🔄 Estratégias de Cache

### 1. Cache de Rotas

**Chave de Cache:**
```
route_{hash_waypoints}_{provider}
```

**Formato:**
```json
{
  "cache_key": "route_abc123_osrm",
  "waypoints": [[lat1, lon1], [lat2, lon2]],
  "waypoints_count": 2,
  "coordinates": [[lat, lon], ...],
  "total_distance": 434.5,
  "provider": "osrm",
  "created_at": "2025-01-19T12:00:00Z",
  "expires_at": "2025-02-18T12:00:00Z"
}
```

**Regras:**
- TTL: 30 dias
- Tolerância: 100m (aceita waypoints dentro de 100m como cache hit)
- Invalidação: Manual via admin ou automática por expiração

### 2. Cache de Geocoding

**Chave de Cache:**
```
geocoding_{cdibge}
```

**Formato:**
```json
{
  "cdibge": "3550308",
  "desmun": "SÃO PAULO",
  "desest": "SP",
  "lat": -23.5505,
  "lon": -46.6333,
  "fonte": "google",
  "geocoded_at": "2025-01-19T12:00:00Z"
}
```

**Regras:**
- TTL: Permanente (coordenadas de municípios não mudam)
- Fonte prioritária: Google > Progress > Manual
- Validação: Bounds do Brasil (-35 a 6 lat, -75 a -33 lon)

### 3. Cache de Clusters

**Chave de Cache:**
```
cluster_{route_id}_{radius}km
```

**Formato:**
```json
{
  "route_id": 186,
  "radius": 5,
  "clusters": [
    {
      "id": "cluster_1",
      "center": { "lat": -23.551, "lon": -46.634 },
      "point_ids": [123, 456, 789],
      "count": 3
    }
  ],
  "created_at": "2025-01-19T12:00:00Z",
  "expires_at": "2025-01-20T12:00:00Z"
}
```

**Regras:**
- TTL: 24 horas
- Invalidação: Quando rota ou entregas mudam

---

## 📊 Limites e Otimizações

### Limites de Providers

| Provider | Waypoints/Request | Custo | Rate Limit |
|----------|------------------|-------|------------|
| Google Maps | 25 (1 origem + 23 intermediários + 1 destino) | ~$0.005/request | 100 req/s |
| OSRM Público | 10-15 (depende do servidor) | Gratuito | Variável |
| HERE Maps | 50 | ~$0.004/request | 200 req/s |
| MapBox | 25 | ~$0.004/request | 300 req/s |

### Estratégia de Divisão

**Para N waypoints:**

1. **Se N ≤ Limite do Provider:**
   - 1 única requisição

2. **Se N > Limite do Provider:**
   - Dividir em chunks com overlap de 1 waypoint
   - Exemplo: 50 waypoints com limite 25
     - Chunk 1: [1-25]
     - Chunk 2: [25-50] (ponto 25 é compartilhado)

3. **Prioridade de Providers:**
   ```
   1. Cache (sempre primeiro)
   2. OSRM (gratuito, bom para < 10 waypoints)
   3. Google Maps (pago, mas confiável)
   4. HERE/MapBox (fallback)
   5. Linha reta (último recurso)
   ```

### Otimizações de Performance

1. **Geocoding em Lote:**
   - Agrupar todos os IBGEs e fazer 1 request
   - Usar Promise.all() para paralelizar

2. **Routing em Paralelo:**
   - Calcular segmentos simultaneamente
   - Máximo 5 requests paralelos (evitar rate limit)

3. **Debouncing:**
   - 300ms após última mudança antes de recalcular
   - Evita recalcular durante drag & drop

4. **Lazy Loading:**
   - Inicializar mapa apenas quando visível
   - Usar Intersection Observer

5. **Compression:**
   - Polyline encoding (Google algorithm)
   - Reduz tamanho do cache em ~90%

---

## 🚀 Fases de Implementação

### **FASE 1: Fundação (Backend) - 1 semana**

#### Tarefa 1.1: Criar estrutura base
- [ ] Criar `MapService.php` com métodos vazios
- [ ] Criar `RouteProviderInterface.php`
- [ ] Criar `OsrmProvider.php` (implementar primeiro)
- [ ] Criar `GoogleMapsProvider.php` (aproveitar código existente)
- [ ] Criar `CacheManager.php` com métodos de cache

#### Tarefa 1.2: Implementar MapController
- [ ] Endpoint `POST /api/map/route`
- [ ] Endpoint `POST /api/map/geocode-batch`
- [ ] Endpoint `POST /api/map/cluster-points`
- [ ] Endpoint `GET /api/map/cache-stats`

#### Tarefa 1.3: Implementar cache unificado
- [ ] Migrar cache de rotas existente
- [ ] Migrar cache de geocoding existente
- [ ] Criar model `ClusterDefinition`
- [ ] Implementar lógica de invalidação

#### Tarefa 1.4: Testes backend
- [ ] Unit tests para MapService
- [ ] Unit tests para Providers
- [ ] Integration tests para endpoints
- [ ] Performance tests (>100 waypoints)

---

### **FASE 2: Frontend Core - 1 semana**

#### Tarefa 2.1: Criar composable base
- [ ] Criar `useMapService.ts` com métodos principais
- [ ] Implementar `initMap()`
- [ ] Implementar `addMarkers()`
- [ ] Implementar `calculateRoute()`
- [ ] Implementar `clusterPoints()`

#### Tarefa 2.2: Criar tipos TypeScript
- [ ] Criar todos os tipos em `/types/map/`
- [ ] Documentar interfaces com JSDoc
- [ ] Criar exemplos de uso

#### Tarefa 2.3: Utilitários
- [ ] `coordinateUtils.ts` (converter Progress → Decimal)
- [ ] `geometryUtils.ts` (Haversine, bounds, etc)
- [ ] `markerFactory.ts` (criar marcadores customizados)

---

### **FASE 3: Migração Itinerário - 3 dias**

#### Tarefa 3.1: Migrar `/itinerario/:id`
- [ ] Substituir código existente por `useMapService()`
- [ ] Manter comportamento visual idêntico
- [ ] Trocar Google Maps por OSRM + fallback Google
- [ ] Testar com pacotes reais (10, 50, 100+ entregas)

#### Tarefa 3.2: Otimizar performance
- [ ] Implementar lazy loading do mapa
- [ ] Adicionar loading skeleton
- [ ] Medir tempo de carregamento (target: < 3s)

---

### **FASE 4: Migração Rotas Padrão - 3 dias**

#### Tarefa 4.1: Migrar `/rotas-padrao/mapa/:id`
- [ ] Substituir código existente por `useMapService()`
- [ ] Manter sistema de debug
- [ ] Manter drag & drop de municípios
- [ ] Manter simulação de pacotes

#### Tarefa 4.2: Adicionar clustering
- [ ] Implementar agrupamento de entregas
- [ ] Criar marcadores de cluster customizados
- [ ] Adicionar toggle on/off clustering

---

### **FASE 5: Migração Compra Viagem - 2 dias**

#### Tarefa 5.1: Migrar mapa de compra-viagem
- [ ] Substituir código do dialog por `useMapService()`
- [ ] Combinar municípios SemParar + entregas pacote
- [ ] Aplicar clustering apenas em entregas
- [ ] Garantir municípios sempre visíveis (sem cluster)

---

### **FASE 6: Documentação e Otimização Final - 2 dias**

#### Tarefa 6.1: Documentação
- [ ] Criar guia de uso do `useMapService()`
- [ ] Documentar todos os endpoints da API
- [ ] Criar exemplos de código
- [ ] Atualizar CLAUDE.md

#### Tarefa 6.2: Otimizações finais
- [ ] Implementar polyline encoding
- [ ] Adicionar compression no cache
- [ ] Otimizar queries do banco
- [ ] Implementar rate limiting nos endpoints

#### Tarefa 6.3: Monitoramento
- [ ] Adicionar métricas de uso
- [ ] Adicionar alertas de falha
- [ ] Dashboard de cache hit/miss
- [ ] Logs estruturados

---

## 📈 Métricas de Sucesso

### Performance

- ✅ Carregamento inicial: < 3 segundos
- ✅ Cálculo de rota (cache hit): < 500ms
- ✅ Cálculo de rota (cache miss): < 5 segundos
- ✅ Cache hit rate: > 80%
- ✅ Suporta até 200 waypoints sem travamento

### Qualidade

- ✅ 100% type-safe (TypeScript)
- ✅ Code coverage > 80%
- ✅ Zero erros de console em produção
- ✅ Compatível com todos os browsers modernos

### Custo

- ✅ Reduzir custos Google Maps em 70%
- ✅ Cache reduz requests em 80%
- ✅ OSRM gratuito para 80% dos casos

---

## 🔒 Regras de Agrupamento

### Entregas (SEMPRE agrupar)

**Raio:** 5km (configurável)

**Algoritmo:** Proximidade (simplificado)

**Critérios:**
- Mesma cidade (preferencial)
- Distância < raio
- Máximo 50 entregas por cluster

**Exemplo:**
```
Input: 15 entregas em São Paulo
Output: 3 clusters
  - Cluster 1: 6 entregas (Zona Sul)
  - Cluster 2: 5 entregas (Zona Norte)
  - Cluster 3: 4 entregas (Centro)
```

### Municípios de Rota (NUNCA agrupar)

**Motivo:** Sequência é importante para cálculo de pedágio

**Renderização:**
- Marcador azul numerado (1, 2, 3, ...)
- Sempre visível
- Nunca colapsado em cluster

---

## ⚠️ Limitações e Trade-offs

### Limitações Conhecidas

1. **OSRM Público:**
   - Pode ter downtime
   - Rate limit variável
   - Máximo 10-15 waypoints

2. **Google Maps:**
   - Custo por request
   - Máximo 25 waypoints
   - Requer API key

3. **Cache:**
   - Tolerância de 100m pode causar rotas levemente diferentes
   - Precisa invalidação manual para correções

### Trade-offs

| Decisão | Benefício | Custo |
|---------|-----------|-------|
| OSRM como primário | Gratuito, rápido | Menos confiável |
| Google como fallback | Confiável | Custo $ |
| Cache agressivo | Performance | Pode ficar desatualizado |
| Clustering automático | UX melhor | Complexidade |
| TypeScript completo | Type safety | Tempo de dev |

---

## 🎯 Conclusão

Este plano cria um sistema **robusto, escalável e reutilizável** de mapas que:

✅ Elimina código duplicado
✅ Reduz custos em 70%
✅ Melhora performance em 50%
✅ Facilita manutenção
✅ Permite adicionar novos providers facilmente
✅ Suporta clustering inteligente
✅ Cache agressivo com alta taxa de hit
✅ Type-safe com TypeScript

**Tempo estimado total:** 3-4 semanas
**Prioridade:** Alta
**Dependências:** Nenhuma (pode começar imediatamente)
