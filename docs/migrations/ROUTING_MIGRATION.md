# Migração: leaflet-routing-machine → Laravel OSRM Proxy

**Data:** 2025-10-30
**Status:** ✅ COMPLETA

## Problema

O sistema de rotas estava usando `leaflet-routing-machine` para chamar OSRM diretamente do frontend:

```typescript
// ❌ CÓDIGO ANTIGO - NÃO FUNCIONA MAIS!
import 'leaflet-routing-machine'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'

const osrmRouter = L.Routing.osrmv1({
  serviceUrl: 'https://routing.openstreetmap.de/routed-car/route/v1',
  profile: 'driving',
  timeout: 30000
})

L.Routing.control({
  waypoints: waypoints,
  router: osrmRouter,
  // ...
}).addTo(map)
```

**Por que parou de funcionar:**
- ❌ Servidores OSRM públicos bloqueiam requisições diretas do frontend (CORS)
- ❌ Timeouts frequentes (>30s)
- ❌ Rate limiting agressivo
- ❌ Erro: `net::ERR_CONNECTION_RESET` ou `net::ERR_TIMED_OUT`

## Solução

Descobrimos que o projeto **JÁ TINHA** um proxy Laravel que contorna esses problemas!

### Backend Proxy (JÁ EXISTIA!)

**Arquivo:** `app/Http/Controllers/Api/RoutingController.php`

**Endpoints:**
- `POST /api/routing/route` - Rota entre 2 pontos
- `POST /api/routing/calculate` - Rota com múltiplos waypoints (usa Google, pago)

**Features do proxy:**
- ✅ Tenta 3 servidores OSRM diferentes:
  - `https://router.project-osrm.org`
  - `https://routing.openstreetmap.de/routed-car`
  - `http://router.project-osrm.org` (fallback HTTP)
- ✅ Retry automático com 15s timeout
- ✅ Fallback inteligente: cria rota interpolada se todos falharem
- ✅ Sem problemas de CORS
- ✅ 100% GRATUITO

### Novo Código Frontend

**Arquivo modificado:** `resources/ts/pages/rotas-padrao/mapa/[id].vue`

```typescript
// ✅ CÓDIGO NOVO - FUNCIONA!

// 1. Remover imports do leaflet-routing-machine
// import 'leaflet-routing-machine'  // DELETAR
// import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'  // DELETAR

// 2. Calcular rota segmento por segmento via proxy Laravel
const allCoordinates: L.LatLngExpression[] = []
let totalDistance = 0
let segmentPromises: Promise<any>[] = []

// Criar promise para cada segmento A→B, B→C, C→D
for (let i = 0; i < waypoints.length - 1; i++) {
  const start = waypoints[i]
  const end = waypoints[i + 1]

  const segmentPromise = fetch('http://localhost:8002/api/routing/route', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      start: [start.lng, start.lat], // OSRM usa [lng, lat]
      end: [end.lng, end.lat]
    })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success && data.coordinates && data.coordinates.length > 0) {
        return {
          success: true,
          coordinates: data.coordinates, // Já vem em [lat, lng]
          distance: data.distance_km || 0,
          index: i
        }
      } else {
        // Fallback: linha reta para este segmento
        return {
          success: false,
          coordinates: [[start.lat, start.lng], [end.lat, end.lng]],
          distance: start.distanceTo(end) / 1000,
          index: i
        }
      }
    })
    .catch(() => {
      // Fallback em caso de erro
      return {
        success: false,
        coordinates: [[start.lat, start.lng], [end.lat, end.lng]],
        distance: start.distanceTo(end) / 1000,
        index: i
      }
    })

  segmentPromises.push(segmentPromise)
}

// Aguardar todos os segmentos e combinar
Promise.all(segmentPromises)
  .then(segments => {
    segments.sort((a, b) => a.index - b.index)

    // Combinar coordenadas
    segments.forEach((segment, idx) => {
      if (idx === 0) {
        allCoordinates.push(...segment.coordinates)
      } else {
        // Pular primeiro ponto (duplicado)
        allCoordinates.push(...segment.coordinates.slice(1))
      }
      totalDistance += segment.distance
    })

    // Desenhar polyline
    if (routingControl.value) {
      map.value?.removeControl(routingControl.value)
    }

    routingControl.value = L.polyline(allCoordinates, {
      color: '#2196F3',
      weight: 4,
      opacity: 0.7
    }).addTo(map.value!)

    distanciaTotal.value = totalDistance
  })
```

## Formato da API

### Request
```json
POST /api/routing/route

{
  "start": [-46.6333, -23.5505],  // [longitude, latitude]
  "end": [-43.1729, -22.9068]      // [longitude, latitude]
}
```

### Response (Success)
```json
{
  "success": true,
  "api_used": "osrm",
  "coordinates": [
    [-23.5505, -46.6333],  // [latitude, longitude]
    [-23.5506, -46.6330],
    [-23.5507, -46.6328],
    // ... centenas de pontos
    [-22.9068, -43.1729]
  ],
  "distance_km": 429.3
}
```

### Response (Fallback)
```json
{
  "success": false,
  "error": "Nenhuma API de roteamento disponível",
  "fallback": "usar_linha_reta"
}
```

## Benefícios da Migração

✅ **Rotas funcionando novamente** - Era o problema principal
✅ **Sem custos** - OSRM é 100% gratuito
✅ **Mais confiável** - Backend tenta 3 servidores + fallback
✅ **Sem CORS** - Proxy Laravel contorna limitações do browser
✅ **Melhor observabilidade** - Logs detalhados no Laravel
✅ **Fácil manutenção** - Adicionar novos servidores OSRM é trivial

## Como Testar

1. Acesse qualquer rota: http://localhost:8002/rotas-padrao/mapa/208
2. Verifique que as linhas azuis seguem estradas reais (não linhas retas)
3. Abra o console do navegador
4. Procure por logs: `✅ [ROUTING] Rota calculada via Laravel proxy`
5. Verifique: `segmentosOSRM: 2` (deve ser > 0 se OSRM funcionou)

## Arquivos Modificados

- ✅ `resources/ts/pages/rotas-padrao/mapa/[id].vue` - Frontend
  - Removido: imports leaflet-routing-machine
  - Adicionado: chamadas fetch ao proxy Laravel
  - Linhas: 449-610
- ✅ `app/Http/Controllers/Api/RoutingController.php` - Backend (apenas docs)
  - Adicionado: PHPDoc detalhado
- ✅ `CLAUDE.md` - Documentação
  - Adicionado: Seção "🚨 ALERTA CRÍTICO - OSRM Routing"
  - Adicionado: Seção "OSRM Routing Proxy"

## Referências

- **Código funcional:** `resources/ts/pages/rotas-padrao/mapa/[id].vue` (linhas 449-610)
- **Backend proxy:** `app/Http/Controllers/Api/RoutingController.php` (linhas 73-343)
- **OSRM oficial:** https://project-osrm.org/
- **Demo OSRM:** http://map.project-osrm.org/

## Para Futuros Desenvolvedores

⚠️ **NUNCA reverta para leaflet-routing-machine chamando OSRM diretamente!**

Se precisar adicionar rotas em uma nova página:
1. ✅ Use o proxy Laravel: `POST /api/routing/route`
2. ✅ Copie o padrão de `rotas-padrao/mapa/[id].vue`
3. ✅ Leia a seção "🚨 ALERTA CRÍTICO" no CLAUDE.md

---

**Desenvolvido por:** Claude + Psykhepathos
**Última atualização:** 2025-10-30
