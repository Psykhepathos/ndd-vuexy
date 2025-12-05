# Integração de Praças de Pedágio com Mapas

## 📋 Overview

Sistema completo para importação e visualização de praças de pedágio da ANTT em mapas Leaflet.

**Status:** ✅ Implementação completa (Backend + Frontend + Integração com mapas)

---

## 🏗️ Arquitetura

```
Backend (Laravel)                Frontend (Vue 3)
├── Migration: pracas_pedagio    ├── Página: pracas-pedagio/index.vue
├── Model: PracaPedagio          ├── Composable: usePracasPedagio.ts
├── Service: Import + Stats      └── Navegação: Menu "Praças de Pedágio"
├── Controller: 6 endpoints
└── API Routes: /api/pracas-pedagio/*
```

---

## 🗄️ Backend

### Database Schema
```sql
CREATE TABLE pracas_pedagio (
    id BIGINT PRIMARY KEY,
    concessionaria VARCHAR(100),
    praca VARCHAR(100),
    rodovia VARCHAR(20),        -- BR-XXX
    uf VARCHAR(2),
    km DECIMAL(8,3),
    municipio VARCHAR(100),
    ano_pnv INTEGER,
    tipo_pista VARCHAR(50),
    sentido VARCHAR(50),
    situacao ENUM('Ativo', 'Inativo'),
    data_inativacao DATE,
    latitude DECIMAL(10,7),     -- ⚠️ CRÍTICO para mapas
    longitude DECIMAL(10,7),    -- ⚠️ CRÍTICO para mapas
    fonte VARCHAR(50),
    data_importacao DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_situacao (situacao),
    INDEX idx_rodovia (rodovia),
    INDEX idx_uf (uf),
    INDEX idx_coords (latitude, longitude)  -- ⚠️ Otimiza busca de proximidade
);
```

### API Endpoints

#### 1. Listagem com Filtros (GET)
```bash
GET /api/pracas-pedagio?page=1&per_page=15&uf=SP&rodovia=BR-381&search=Mairiporã&situacao=Ativo
```
**Resposta:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "total": 239,
    "per_page": 15,
    "last_page": 16
  }
}
```

#### 2. Buscar Praças Próximas (POST) ⚠️ PRINCIPAL PARA MAPAS
```bash
POST /api/pracas-pedagio/proximidade
Content-Type: application/json

{
  "lat": -23.3222980,
  "lon": -46.5810970,
  "raio_km": 50
}
```
**Resposta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "praca": "1 Norte (Mairiporã)",
      "rodovia": "BR-381",
      "uf": "SP",
      "km": "65.700",
      "municipio": "Mairiporã",
      "latitude": "-23.3222980",
      "longitude": "-46.5810970",
      "concessionaria": "AUTOPISTA FERNÃO DIAS",
      "situacao": "Ativo"
    }
  ],
  "meta": {
    "lat": -23.322298,
    "lon": -46.581097,
    "raio_km": 50,
    "total": 3
  }
}
```

#### 3. Importar CSV (POST)
```bash
POST /api/pracas-pedagio/importar
Content-Type: multipart/form-data

file: dados_das_pracas_de_pedagio.csv
```
**Resposta:**
```json
{
  "success": true,
  "message": "Importação concluída com sucesso!",
  "data": {
    "imported": 239,
    "errors": 0,
    "duration": "0.24s"
  }
}
```

#### 4. Estatísticas (GET)
```bash
GET /api/pracas-pedagio/estatisticas
```
**Resposta:**
```json
{
  "success": true,
  "data": {
    "total": 239,
    "ativas": 239,
    "inativas": 0,
    "por_uf": [
      { "uf": "SP", "total": 73 },
      { "uf": "MG", "total": 45 }
    ],
    "por_concessionaria": [
      { "concessionaria": "RIOSP", "total": 67 }
    ]
  }
}
```

---

## 🎨 Frontend

### Página de Gestão
**URL:** http://localhost:8002/pracas-pedagio
**Menu:** Sistema NDD → Praças de Pedágio

**Features:**
- ✅ Cards de estatísticas (Total, Ativas, Inativas, Estados)
- ✅ Upload e importação de CSV da ANTT
- ✅ DataTable com paginação server-side
- ✅ Filtros: UF, Rodovia, Situação, Busca
- ✅ Ação: "Ver no mapa" (abre página interna com OpenStreetMap)
- ✅ Dialog de importação com feedback em tempo real

### Página de Visualização de Praça
**URL:** http://localhost:8002/pracas-pedagio/mapa/{id}
**Navegação:** Botão "Ver no mapa" na listagem

**Features:**
- ✅ Mapa interativo Leaflet + OpenStreetMap centralizado na praça
- ✅ Marcador customizado vermelho (ícone de moeda)
- ✅ Popup automático com informações completas
- ✅ Círculo de raio 5km ao redor da praça
- ✅ Cards laterais com detalhes (rodovia, km, município, concessionária)
- ✅ Informações de coordenadas geográficas
- ✅ Botão "Voltar para lista"

---

## 🗺️ Integração com Mapas Leaflet

### Composable: `usePracasPedagio.ts`

Carrega e exibe praças de pedágio em mapas Leaflet de forma automática.

#### Exemplo de Uso Simples
```typescript
import { usePracasPedagio } from '@/composables/usePracasPedagio'
import L from 'leaflet'

// No componente Vue
const { loadAndDisplayPracas, loading } = usePracasPedagio()

// Depois de criar o mapa e calcular waypoints da rota
const map = L.map('map')
const waypoints = [
  { lat: -23.550520, lon: -46.633308 },  // São Paulo
  { lat: -22.906847, lon: -43.172896 }   // Rio de Janeiro
]

// Carregar e exibir praças próximas automaticamente
const pracas = await loadAndDisplayPracas(
  map,
  waypoints,
  50,  // Raio de busca em km
  {
    color: '#F44336',      // Vermelho
    showPopup: true,
    zIndex: 1000
  }
)

console.log(`${pracas.length} praças encontradas e exibidas no mapa`)
```

#### Exemplo Avançado (Controle Granular)
```typescript
import { usePracasPedagio } from '@/composables/usePracasPedagio'

const {
  loading,
  pracas,
  loadPracasProximas,
  addPracasToMap,
  removePracasFromMap
} = usePracasPedagio()

// 1. Carregar praças (sem exibir ainda)
const pracasData = await loadPracasProximas(waypoints, 50)

// 2. Processar/filtrar praças
const pracasAtivas = pracasData.filter(p => p.situacao === 'Ativo')

// 3. Adicionar ao mapa com customização
addPracasToMap(map, pracasAtivas, {
  color: '#4CAF50',  // Verde para ativas
  showPopup: true,
  zIndex: 1000
})

// 4. Remover quando necessário
removePracasFromMap()
```

---

## 🔧 Como Integrar em Componentes Existentes

### 1. Mapa de Rotas SemParar
**Arquivo:** `resources/ts/pages/rotas-padrao/mapa/[id].vue`

```vue
<script setup lang="ts">
import { usePracasPedagio } from '@/composables/usePracasPedagio'
import L from 'leaflet'

const { loadAndDisplayPracas } = usePracasPedagio()

// Depois de criar o mapa e calcular a rota
const exibirPracas = async () => {
  if (!map || waypoints.length === 0) return

  // Carregar praças em um raio de 30km da rota
  const pracas = await loadAndDisplayPracas(map, waypoints, 30, {
    color: '#F44336',
    showPopup: true
  })

  console.log(`✅ ${pracas.length} praças exibidas no mapa da rota`)
}

// Chamar após desenhar a rota
watch(() => routeDrawn.value, (drawn) => {
  if (drawn) {
    exibirPracas()
  }
})
</script>
```

### 2. Mapa de Compra de Viagem
**Arquivo:** `resources/ts/pages/compra-viagem/components/CompraViagemMapaFixo.vue`

```vue
<script setup lang="ts">
import { usePracasPedagio } from '@/composables/usePracasPedagio'

const { loadAndDisplayPracas } = usePracasPedagio()

// Adicionar botão toggle para mostrar/ocultar praças
const mostrarPracas = ref(true)

const togglePracas = async () => {
  if (mostrarPracas.value) {
    // Carregar praças da rota selecionada + entregas do pacote
    const waypoints = [
      ...formData.value.rota.municipios.map(m => ({ lat: m.lat!, lon: m.lon! })),
      ...formData.value.pacote.entregas_com_gps.map(e => ({ lat: e.lat!, lon: e.lon! }))
    ]

    await loadAndDisplayPracas(map, waypoints, 40)
  } else {
    removePracasFromMap()
  }
}
</script>

<template>
  <!-- Adicionar botão no toolbar do mapa -->
  <VBtn @click="togglePracas" :color="mostrarPracas ? 'primary' : 'default'">
    <VIcon icon="tabler-coin" class="me-2" />
    {{ mostrarPracas ? 'Ocultar' : 'Mostrar' }} Praças de Pedágio
  </VBtn>
</template>
```

---

## 📊 Dados Importados

**Fonte:** ANTT (Agência Nacional de Transportes Terrestres)
**Arquivo:** `dados_das_pracas_de_pedagio.csv`
**Total importado:** 239 praças (todas ativas)

**Distribuição por Estado:**
- SP: 73 praças
- MG: 45 praças
- PR: 28 praças
- RJ: 24 praças
- GO: 16 praças
- RS: 12 praças
- MT: 11 praças
- SC: 11 praças
- MS: 9 praças
- ES: 7 praças
- TO: 2 praças
- PA: 1 praça

**Top Concessionárias:**
- RIOSP: 67 praças
- ECOVIAS RIO MINAS: 13 praças
- CONCEBRA: 11 praças
- AUTOPISTA FERNÃO DIAS: 10 praças

---

## 🎯 Features do Composable

### ✅ Funcionalidades
1. **Busca de proximidade**: Encontra praças próximas a múltiplos waypoints
2. **Deduplicação automática**: Remove praças duplicadas ao processar múltiplos pontos
3. **Marcadores customizados**: Ícone circular vermelho com símbolo de moeda
4. **Popups informativos**: Exibe dados completos da praça (rodovia, km, município, etc.)
5. **Controle de z-index**: Praças sempre visíveis acima de outros elementos
6. **Limpeza de marcadores**: Remove todos os marcadores quando necessário
7. **API única**: Carrega e exibe em uma única chamada

### ⚙️ Opções de Customização
```typescript
interface Options {
  color?: string        // Cor do marcador (padrão: #F44336)
  icon?: string         // Ícone (reservado para uso futuro)
  showPopup?: boolean   // Exibir popup ao clicar (padrão: true)
  zIndex?: number       // z-index do marcador (padrão: 1000)
}
```

### 📈 Performance
- ⚡ Busca de proximidade otimizada com índice geográfico
- 🔄 Deduplicação via Set para evitar múltiplas requisições
- 🎯 Rate limiting: 60 req/min (proteção do servidor)
- 💾 Raio configurável (padrão: 50km, ajustável por uso)

---

## 🚀 Próximos Passos (Opcional)

### Melhorias Futuras
1. **Cache de praças**: Armazenar praças em cache do navegador
2. **Clustering**: Agrupar praças próximas quando zoom for baixo
3. **Filtro dinâmico**: Adicionar filtros de concessionária/situação no mapa
4. **Cálculo de custo**: Integrar com API SemParar para calcular custo total
5. **Atualização automática**: Sincronizar com ANTT periodicamente

### Integrações Sugeridas
- ✅ Mapa de Rotas SemParar (`rotas-padrao/mapa/[id].vue`)
- ✅ Mapa de Compra de Viagem (`compra-viagem/components/CompraViagemMapaFixo.vue`)
- ⏳ Mapa de Vale Pedágio (se houver)
- ⏳ Dashboard com visualização de praças por região

---

## 🧪 Testes Realizados

### Backend
```bash
# 1. Importação CSV
✅ 239 praças importadas em 0.24s
✅ 0 erros de importação
✅ Encoding Windows-1252 → UTF-8 funcionando

# 2. Busca de proximidade (raio 10km em Mairiporã/SP)
✅ 3 praças encontradas
✅ Coordenadas válidas
✅ Dados completos retornados

# 3. Listagem com filtros
✅ Filtro por UF: SP → 73 praças
✅ Filtro por rodovia: BR-381 → 4 praças
✅ Paginação server-side funcionando
```

### Frontend
```bash
# 1. Página de gestão
✅ Estatísticas carregando corretamente
✅ DataTable com paginação funcional
✅ Filtros aplicados com debounce
✅ Upload de CSV funcionando

# 2. Composable
✅ Integração com Leaflet OK
✅ Marcadores exibidos corretamente
✅ Popups formatados
✅ Deduplicação funcionando
```

---

## 📝 Commits Sugeridos

```bash
# Commit 1: Backend
feat: add toll plaza import system with ANTT CSV support

- Create migration for pracas_pedagio table
- Add PracaPedagio model with scopes (ativas, porRodovia, proximasDe)
- Implement PracaPedagioImportService with encoding conversion
- Add PracaPedagioController with 6 endpoints (CRUD + proximity search)
- Add API routes with rate limiting

Features:
- Import 239 toll plazas from ANTT CSV
- Geographic proximity search (Haversine approximation)
- Statistics by UF and concessionaria
- Windows-1252 to UTF-8 encoding conversion

# Commit 2: Frontend
feat: add toll plaza management page with import UI

- Create pracas-pedagio/index.vue with DataTable
- Add statistics cards (total, active, inactive, states)
- Implement CSV import dialog with progress feedback
- Add filters (UF, rodovia, situacao, search)
- Add navigation menu entry

# Commit 3: Map Integration
feat: integrate toll plazas with Leaflet maps

- Create usePracasPedagio composable
- Implement proximity search for route waypoints
- Add custom markers with popups
- Support for multiple maps integration
- Add documentation (INTEGRACAO_PRACAS_PEDAGIO.md)
```

---

## 📚 Referências

- **ANTT:** https://www.gov.br/antt/pt-br/assuntos/rodovias/pracas-de-pedagio
- **Leaflet:** https://leafletjs.com/
- **API Docs:** Ver `routes/api.php` linhas 114-141
- **Composable:** `resources/ts/composables/usePracasPedagio.ts`
- **Frontend Listagem:** `resources/ts/pages/pracas-pedagio/index.vue`
- **Frontend Mapa:** `resources/ts/pages/pracas-pedagio/mapa/[id].vue`
