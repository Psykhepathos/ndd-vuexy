# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Start

**Laravel + Vue.js unified transport management system using Vuexy template, connected to Progress OpenEdge via ODBC.**

```bash
# Start development servers
php artisan serve --port=8002  # Laravel API (Backend)
pnpm run dev                   # Vue frontend (Vite)

# Testing & validation
pnpm run typecheck            # TypeScript validation
pnpm run lint                 # ESLint with auto-fix
php artisan test              # Backend tests
composer test                 # Clear cache + run tests

# Build for production
pnpm run build                # Frontend production build
```

**IMPORTANTE - URLs de Acesso:**
- **Sistema completo (Frontend + API):** http://localhost:8002
- **Vite Dev Server (desenvolvimento apenas):** http://localhost:5173/5174/5176 (NÃO usar para visualização)
- **Login:** admin@ndd.com / 123456

**⚠️ ATENÇÃO:** SEMPRE use http://localhost:8002 para acessar o sistema! O Vite (porta 517x) é apenas para desenvolvimento/hot-reload.

## 🆕 Atualizações Recentes

### ✅ FASE 1A: SemParar SOAP Core - COMPLETA (2025-10-27)

**Status:** Integração SOAP base com SemParar API está funcional

**Implementado:**
- ✅ Cliente SOAP com TLS 1.2/1.3 (`app/Services/SemParar/SemPararSoapClient.php`)
- ✅ Autenticação com cache de token de 1 hora
- ✅ Verificação de status de veículo
- ✅ Endpoints REST de teste (`/api/semparar/*`)
- ✅ Rate limiting configurado

**Teste rápido:**
```bash
curl http://localhost:8002/api/semparar/test-connection
# Deve retornar: {"success": true, "token_length": 19, ...}
```

**⚠️ Descoberta importante:**
```php
// ❌ ERRADO - Causa "Array to string conversion"
$client->__soapCall('autenticarUsuario', [['cnpj' => $x, 'login' => $y, 'senha' => $z]]);

// ✅ CORRETO - Parâmetros posicionais
$client->autenticarUsuario($cnpj, $user, $password);
// Retorna: stdClass { sessao: "3642419762017373443", status: 0 }
```

**Documentação completa:** `CHECKPOINT_FASE_1A.md`

---

### ✅ FASE 1B: SemParar SOAP Routing - COMPLETA (2025-10-27)

**Status:** Roteirização de praças de pedágio funcional

**Implementado:**
- ✅ XML Builder para datasets Progress (`app/Services/SemParar/XmlBuilders/PontosParadaBuilder.php`)
- ✅ `roteirizarPracasPedagio()` - Calcula praças de pedágio em rota
- ✅ `cadastrarRotaTemporaria()` - Cadastra rota temporária
- ✅ `obterCustoRota()` - Calcula custo total
- ✅ Endpoints REST + interface de teste

**Bug Crítico Resolvido:**
```php
// ❌ ERRADO - PHP SoapClient envia XML vazio
$client->roteirizarPracasPedagio($pontosXml, $opcoesXml, $token);

// ✅ CORRETO - Usar SoapVar com XSD_ANYXML
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);
$client->roteirizarPracasPedagio($pontosParam, $opcoesParam, $token);
```

**Testes bem-sucedidos:**
- Rota SP→RJ: **6 praças** encontradas
- Rota 183 + Pacote 3043368 (19 pontos): **12 praças** encontradas

**Documentação:** `SEMPARAR_FASE1B_COMPLETO.md`

---

### ✅ FASE 2A: SemParar Trip Purchase - COMPLETA (2025-10-27)

**Status:** Compra de viagens SemParar funcional

**Implementado:**
- ✅ `comprarViagem()` no SemPararService (105 linhas)
- ✅ Endpoint REST `POST /api/semparar/comprar-viagem`
- ✅ Validação de dados de compra
- ✅ Interface de teste com confirmação

**Fluxo completo:**
1. Roteirizar praças → 2. Cadastrar rota temporária → 3. Obter custo → 4. **Comprar viagem**

**Endpoint:**
```bash
POST /api/semparar/comprar-viagem
{
  "nome_rota": "TESTE_SP_RJ",
  "placa": "ABC1234",
  "eixos": 2,
  "data_inicio": "2025-10-27",
  "data_fim": "2025-10-27",
  "item_fin1": "PEDAGIO"
}

# Response:
{
  "success": true,
  "data": {
    "cod_viagem": "123456789",
    "status": 0
  }
}
```

**⚠️ ATENÇÃO:** Esta operação EFETIVA a compra no SemParar! Use com cuidado.

**Página de teste:** http://localhost:8002/test-semparar-fase1b.html

**Próxima fase:** FASE 2B - Integração com Progress (salvar viagens no banco)

---

### 🗺️ MIGRAÇÃO: Google Maps → Leaflet + OpenStreetMap + OSRM (100% GRATUITO!)

**Data:** 2025-10-21 (Atualizado: 2025-10-27)
**Impacto:** Sistema de mapas agora é 100% gratuito, sem dependência de API keys do Google Maps

**O que mudou:**
- ❌ **REMOVIDO:** Google Maps API (tiles + routing)
- ✅ **ADICIONADO:** Leaflet.js + OpenStreetMap (tiles gratuitos)
- ✅ **ADICIONADO:** OSRM OpenStreetMap.de (routing gratuito, sem API key)
- ✅ **MANTIDO:** Google Geocoding API (apenas para IBGE → coordenadas, com cache agressivo)

**Tecnologias:**
```typescript
// Frontend
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet-routing-machine'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'

// Mapa
L.map(container).setView([-14.2350, -51.9253], 4)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map)

// Routing GRATUITO
const osrmRouter = L.Routing.osrmv1({
  serviceUrl: 'https://routing.openstreetmap.de/routed-car/route/v1',
  profile: 'driving',
  timeout: 30000
})
```

**Arquivos modificados:**
- `resources/ts/pages/rotas-semparar/mapa/[id].vue` - Migrado para Leaflet
- `resources/ts/pages/test-leaflet-pacote.vue` - Teste funcional com pacote real

**Features mantidas:**
- ✅ Marcadores numerados customizados
- ✅ Popups com informações
- ✅ Rotas seguindo estradas reais (não linhas retas)
- ✅ Geocoding automático
- ✅ Sistema de debug visual
- ✅ Simulação de pacotes
- ✅ Drag & drop de municípios
- ✅ Fallback para linha reta em caso de erro

**Limitações conhecidas:**
- ⚠️ OSRM público pode ter downtime ocasional (fallback implementado)
- ⚠️ Limite de ~25-50 waypoints por rota (limite do OSRM público)
- ✅ Solução futura: Hospedar OSRM próprio via Docker

**Benefícios:**
- 💰 **Custo ZERO** - Sem mais custos de Google Maps API
- 🚀 **Performance** - OpenStreetMap é rápido e confiável
- 🔓 **Open Source** - Stack 100% open source

**URLs de Teste:**
- Rota SemParar: http://localhost:8002/rotas-semparar/mapa/204
- Teste Pacote: http://localhost:8002/test-leaflet-pacote

**Documentação:**
- Análise completa: `ANALISE_ROTAS_SEMPARAR.md`
- Debug system: `DEBUG_MAPA_ROTAS.md`

---

### 1. Sistema de Debug para Mapa de Rotas SemParar
Implementado sistema completo de debug e diagnóstico para resolver problemas de geocoding e renderização de mapas.

**Arquivo principal**: `resources/ts/pages/rotas-semparar/mapa/[id].vue`
**Documentação completa**: `DEBUG_MAPA_ROTAS.md`

**Recursos implementados**:
- 🐛 **Painel de Debug Visual**: Acessível via botão "Debug" no header
- 📊 **Métricas em Tempo Real**: Geocodes, cache hits, atualizações do mapa
- 📋 **Logging Estruturado**: 4 níveis (info/warn/error/success) e 6 categorias
- ✅ **Validação de Coordenadas**: `isValidCoordinate()` e `sanitizeCoordinate()`
- 🔄 **Controle de Sincronização**: Debounce (300ms), lock anti-concorrência, queue de geocoding
- 🗺️ **Indicadores Visuais**: Marcadores coloridos por status, InfoWindow detalhado

**Problemas solucionados**:
- ✅ Race conditions no geocoding (processamento agora é sequencial)
- ✅ Validação inadequada de coordenadas (validação rigorosa implementada)
- ✅ Múltiplas atualizações do mapa (debouncing de 300ms)
- ✅ Watch inadequado (removido, substituído por chamadas explícitas)
- ✅ Falta de observabilidade (sistema completo de logs e métricas)

**Como usar o Debug**:
1. Acesse http://localhost:8002/rotas-semparar/mapa/{id}
2. Clique no botão "Debug" no header
3. Veja estatísticas, estado dos municípios e logs do sistema
4. Use para diagnosticar problemas de geocoding ou renderização

### 2. Suporte a UPDATE/INSERT/DELETE no Progress Database
Progress JDBC **NÃO suporta transações**. Sistema atualizado para executar comandos de modificação sem transações.

**Java Connector** (`storage/app/java/ProgressJDBCConnector.java`):
- Nova ação `update` para UPDATE/INSERT/DELETE
- Validação de segurança (apenas comandos permitidos)
- Retorna número de linhas afetadas

**ProgressService** (`app/Services/ProgressService.php`):
- **Novo método**: `executeUpdate($sql)` - Executa UPDATE/INSERT/DELETE
- **Método existente**: `executeCustomQuery($sql)` - Apenas SELECT (segurança)
- **Métodos atualizados**: `updateSemPararRota()`, `deleteSemPararRota()` agora usam `executeUpdate()`
- **REMOVIDO**: Suporte a transações (beginTransaction/commit/rollBack não funcionam com ODBC)

**Outros Services:**
- **GeocodingService**: Converte códigos IBGE → lat/lon usando Google Geocoding API
- **RoutingService**: Calcula rotas entre coordenadas usando Google Directions API

**⚠️ IMPORTANTE**:
```php
// ❌ ERRADO - Progress JDBC não suporta transações
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql);
DB::connection('progress')->commit();

// ✅ CORRETO - Executar queries individuais
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
$this->executeUpdate($sql3);
```

**SQL deve ser em linha única** (Progress não gosta de quebras de linha):
```php
// ❌ ERRADO - Multi-linha
$sql = "UPDATE PUB.semPararRot SET
  desSPararRot = 'Teste',
  tempoViagem = 5
  WHERE sPararRotID = 204";

// ✅ CORRETO - Single-line
$sql = "UPDATE PUB.semPararRot SET desSPararRot = 'Teste', tempoViagem = 5 WHERE sPararRotID = 204";
```

### 3. Sistema de Geocoding e Routing com Cache

**Geocoding** (converte IBGE → lat/lon):
- **API**: `POST /api/geocoding/ibge` e `POST /api/geocoding/lote`
- **Service**: `GeocodingService.php` - Google Geocoding API + cache local
- **Model**: `MunicipioCoordenada.php` - Cache de coordenadas por código IBGE
- **Cache**: Tabela `municipio_coordenadas` (persistente, sem expiração)

**Routing** (calcula rotas com estradas reais):
- **API**: `POST /api/routing/calculate`
- **Service**: `RoutingService.php` - Google Directions API + cache de segmentos
- **Model**: `RouteSegment.php` - Cache de segmentos origem→destino
- **Cache**: Tabela `route_segments` (30 dias, tolerância ~100m)
- **Rate Limiting**: 200ms entre novas requisições ao Google

**Benefícios**:
- Cache reduz 80%+ de chamadas à API do Google após primeira visualização
- Rotas são desenhadas com estradas reais, não linhas retas
- Segmentos são reutilizados entre diferentes rotas

## Architecture Overview

```
Vue/Vuexy ← REST API → Laravel ← ODBC → Progress Database
```

- **Frontend**: Vue 3.5.14 + TypeScript + Vuexy template + Vuetify 3.8.5
- **Backend**: Laravel 12.15.0 + Laravel Sanctum authentication
- **Database**: Progress OpenEdge via ODBC (direct connection, no Kafka)
- **Build**: Vite 6.3.5 + PNPM package manager

## Critical Development Rules

### 1. Vuexy Template Usage (MANDATORY)
**NEVER create UI from scratch. ALWAYS copy from existing Vuexy templates:**
- Lists: `resources/ts/pages/apps/user/list/index.vue`
- Forms: `resources/ts/pages/apps/user/view/UserBioPanel.vue`
- Dashboards: `resources/ts/pages/apps/logistics/dashboard.vue`

**Use Vuexy components:**
- `AppTextField` instead of `VTextField`
- `AppSelect` instead of `VSelect`
- `VDataTableServer` for paginated tables
- Theme classes: `text-high-emphasis`, `text-medium-emphasis`

### 2. Progress Database Access
**ALWAYS use JDBC direct connection, NOT Eloquent for Progress tables:**
```php
// ✅ CORRECT - Direct JDBC for Progress tables
DB::connection('progress')->select('SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]);
$this->progressService->executeCustomQuery($sql);

// ❌ WRONG - Never use Eloquent for Progress tables
Pacote::find(123);  // Won't work with JDBC!

// ✅ CORRECT - Eloquent CAN be used for Laravel internal tables (SQLite, MySQL)
$coords = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();  // Cache table (SQLite)
$user = User::find($userId);  // Laravel users table
$segment = RouteSegment::where('origin_lat', $lat)->first();  // Cache table (SQLite)
```

**Summary:**
- **Progress tables (PUB.*)** → Raw JDBC via ProgressService ✅
- **Laravel tables (users, cache, etc)** → Eloquent ORM ✅

### 3. Git Commits
- **NEVER** mention Claude, AI, or use emojis in commits
- Use technical, descriptive messages
- Configure: `git config --global user.name "Psykhepathos"`

## Key Services & APIs

### ProgressService Methods
**Core Connection:**
- `testConnection()` - Test JDBC connection
- `executeCustomQuery($sql)` - Run custom SQL (SELECT only, for security)
- `executeUpdate($sql)` - Run UPDATE/INSERT/DELETE (NEW in 2025-09-30)
- `executeJavaConnector($action, ...$params)` - Execute JDBC Java connector

**Transportes:**
- `getTransportesPaginated($filters)` - Get transporters with pagination
- `getTransporteById($id)` - Get specific transporter
- `getMotoristasPorTransportador($id)` - Get drivers by transporter
- `getVeiculosPorTransportador($id)` - Get vehicles by transporter

**Pacotes:**
- `getPacotesPaginated($filters)` - Get packages with pagination
- `getPacoteById($id)` - Get specific package
- `getItinerarioPacote($codPac)` - Get full package itinerary with deliveries

**Rotas & Autocomplete:**
- `getRotas($search)` - Autocomplete for routes
- `getMunicipiosForAutocomplete($search, $estadoId)` - City search
- `getEstadosForAutocomplete()` - State list

**SemParar Routes:**
- `getSemPararRotas($filters)` - List SemParar routes with pagination
- `getSemPararRota($id)` - Get specific route with municipalities
- `createSemPararRota($data)` - Create new route
- `updateSemPararRota($id, $data)` - Update route
- `deleteSemPararRota($id)` - Delete route
- `updateSemPararRotaMunicipios($id, $municipios)` - Update municipalities

### API Endpoints
**Progress Database:**
- `GET /api/progress/test-connection` - Test database connection
- `POST /api/progress/query` - Execute custom SQL queries
- `GET /api/progress/transportes` - List transporters
- `GET /api/progress/transportes/{id}` - Get specific transporter

**Transportes:**
- `GET /api/transportes` - List transporters (paginated)
- `GET /api/transportes/{id}` - Get transporter details
- `GET /api/transportes/statistics` - Get statistics
- `GET /api/transportes/schema` - Get table schema

**Pacotes:**
- `GET /api/pacotes` - List packages (paginated with filters)
- `GET /api/pacotes/{id}` - Get package details
- `POST /api/pacotes/itinerario` - Get package itinerary with deliveries
- `GET /api/pacotes/statistics` - Get statistics

**Rotas:**
- `GET /api/rotas?search={term}` - Autocomplete for routes

**SemParar Rotas:**
- `GET /api/semparar-rotas` - List routes (paginated with filters)
- `GET /api/semparar-rotas/{id}` - Get specific route
- `GET /api/semparar-rotas/{id}/municipios` - Get route with municipalities
- `POST /api/semparar-rotas` - Create new route
- `PUT /api/semparar-rotas/{id}` - Update route
- `PUT /api/semparar-rotas/{id}/municipios` - Update municipalities
- `DELETE /api/semparar-rotas/{id}` - Delete route
- `GET /api/semparar-rotas/municipios?search={term}` - City autocomplete
- `GET /api/semparar-rotas/estados` - List states

**Geocoding:**
- `POST /api/geocoding/ibge` - Get coordinates from single IBGE code
- `POST /api/geocoding/lote` - Get coordinates from multiple IBGE codes (batch)

**Routing & Maps:**
- `GET /api/routing/test` - Test routing service
- `POST /api/routing/route` - Calculate route
- `POST /api/routing/calculate` - Calculate route with waypoints
- `POST /api/route-cache/find` - Find cached route
- `POST /api/route-cache/save` - Save route to cache
- `GET /api/route-cache/stats` - Cache statistics
- `DELETE /api/route-cache/clear-expired` - Clear expired cache entries

**Google Maps Quota:**
- `GET /api/google-maps/quota` - Get current API usage statistics
- `POST /api/google-maps/reset-counters` - Reset usage counters (admin)

### Progress SQL Conventions
- **Schema:** Always use `PUB.tablename` (e.g., `PUB.transporte`, `PUB.pacote`)
- **Limit:** Use `SELECT TOP 10` (not LIMIT)
- **Offset:** Progress lacks native OFFSET - simulate with subqueries or fetch all + array_slice in PHP
- **Case:** Progress is case-sensitive for table/column names
- **Strings:** Use single quotes `'value'`
- **Joins:** Use `LEFT JOIN` syntax, not nested subqueries
- **Transactions:** ⚠️ **NUNCA USE TRANSAÇÕES** - Progress JDBC não suporta `beginTransaction()/commit()/rollBack()`
- **SQL Format:** Use single-line queries (Progress JDBC tem problemas com quebras de linha)

**Common Tables:**
- `PUB.transporte` - Transporters (codtrn, nomtrn, flgautonomo, codcnpjcpf)
- `PUB.pacote` - Packages (codpac, codtrn, codmot, sitpac, datforpac)
- `PUB.carga` - Loads (codcar, codpac)
- `PUB.pedido` - Orders/Deliveries (numseqped, codcar, codcli)
- `PUB.introt` - Routes (codrot, desrot)
- `PUB.semPararRot` - SemParar Routes (sPararRotID, desSPararRot, flgCD)
- `PUB.semPararRotMu` - SemParar Municipalities (sPararRotID, codMun, codEst)
- `PUB.municipio` - Cities (codmun, desmun, cdibge)
- `PUB.estado` - States (codest, nomest, siglaest)

## API Rate Limiting & Security

**Rate limits configured in `routes/api.php`:**
- **Test endpoints**: 10 req/min (`/api/transportes/test-connection`)
- **Statistics/Schema**: 10 req/min (expensive queries)
- **CRUD operations**: 60 req/min (standard operations)
- **Custom queries**: 5 req/min (admin-only, requires authentication)

**Authentication:**
- **Public endpoints** (no auth required):
  - All Progress test connections
  - Transporter/package listings
  - Geocoding and routing services
  - SemParar routes (read-only)

- **Protected endpoints** (require `auth:sanctum`):
  - `POST /api/transportes/query` - Custom SQL queries (admin-only)
  - `POST /api/auth/logout`
  - `GET /api/auth/user`

**Auth flow:**
1. `POST /api/auth/login` → Returns Sanctum token
2. Include token in header: `Authorization: Bearer {token}`
3. `POST /api/auth/logout` when done

## Project Structure

```
ndd-vuexy/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php              # Authentication
│   │   ├── TransporteController.php        # Transporters
│   │   ├── PacoteController.php            # Packages
│   │   ├── MotoristaController.php         # Drivers
│   │   ├── RotaController.php              # Routes autocomplete
│   │   ├── SemPararRotaController.php      # SemParar routes CRUD
│   │   ├── GeocodingController.php         # IBGE → lat/lon conversion
│   │   ├── RoutingController.php           # Route calculation proxy
│   │   ├── RouteCacheController.php        # Route cache management
│   │   ├── GoogleMapsQuotaController.php   # API quota monitoring
│   │   └── ProgressController.php          # Raw Progress queries
│   └── Services/
│       ├── ProgressService.php             # Main Progress DB service (1500+ lines)
│       ├── GeocodingService.php            # Google Geocoding API integration
│       └── RoutingService.php              # Google Directions API integration
├── resources/ts/
│   ├── pages/
│   │   ├── transportes/                    # Transporters module
│   │   ├── pacotes/                        # Packages module
│   │   ├── vale-pedagio/                   # Toll pass calculator
│   │   ├── rotas-semparar/                 # SemParar routes with map
│   │   └── apps/                           # Vuexy example pages (reference templates)
│   ├── @layouts/                           # Layout components
│   ├── navigation/vertical/ndd.ts          # Left sidebar menu
│   └── plugins/                            # Vue plugins (router, vuetify, etc)
├── routes/api.php                          # API routes
├── storage/app/java/
│   ├── ProgressJDBCConnector.java          # JDBC connector for Progress
│   └── gson-2.8.9.jar                      # JSON library for Java
└── database/migrations/                    # SQLite migrations (NOT Progress)

## Development Workflow

### Creating New Features
1. Check Progress table structure via `/api/progress/query`
2. Add method to `ProgressService.php`
3. Create controller in `app/Http/Controllers/Api/`
4. Register route in `routes/api.php`
5. Copy similar Vuexy template for frontend
6. Test with curl before frontend integration

### Testing Checklist
- [ ] ODBC connection: `curl http://localhost:8002/api/progress/test-connection`
- [ ] TypeScript: `pnpm run typecheck`
- [ ] Linting: `pnpm run lint`
- [ ] Backend tests: `php artisan test`
- [ ] Manual testing in browser

## Common Issues & Solutions

### Port conflicts
```bash
netstat -ano | findstr :8002  # Check port usage
taskkill /PID [PID] /F        # Kill process
```

### Vue compilation errors
```bash
rm -rf node_modules/.vite     # Clear Vite cache
pnpm run dev                  # Restart
```

### Progress connection issues
```bash
# Test via API
curl "http://localhost:8002/api/progress/test-connection"
```

## Environment Configuration

```env
# Progress Database
PROGRESS_HOST=192.168.80.113
PROGRESS_DATABASE=tambasa
PROGRESS_USERNAME=sysprogress
PROGRESS_PASSWORD=sysprogress

# Google Maps API (for geocoding and routing)
GOOGLE_MAPS_API_KEY=your_api_key_here

# API URLs
LARAVEL_API=http://localhost:8002
VUE_FRONTEND=http://localhost:5174
```

## 🛒 Sistema de Compra de Viagem SemParar - API Backend (FASE 1A + 1B + 2A + 2B + 2C ✅)

**Status:** Backend completo e funcional (roteirização, compra, persistência, recibo). Frontend em desenvolvimento.

**Visão Geral:**
Sistema de compra de viagens integrado com API SOAP SemParar para gestão de pedágios e rotas de transporte. O backend está 100% funcional e testado.

### FASE 1A - SOAP Core (✅ COMPLETA)
**Implementação:** `app/Services/SemParar/SemPararService.php`, `app/Services/SemParar/SoapClient.php`

**Funcionalidades:**
- ✅ Autenticação SOAP (`autenticarUsuario()`)
- ✅ Cache de token (duração da sessão)
- ✅ Status de veículo (`statusVeiculo()`)
- ✅ Gestão de sessão SOAP

**Endpoints:**
- `GET /api/semparar/test-connection` - Test SOAP connection
- `POST /api/semparar/status-veiculo` - Verify vehicle status
- `GET /api/semparar/debug/token` - Get cached token (debug only)
- `POST /api/semparar/debug/clear-cache` - Clear token cache

### FASE 1B - Roteirização (✅ COMPLETA)
**Funcionalidades:**
- ✅ Roteirizar praças de pedágio entre municípios (`roteirizarPracasPedagio()`)
- ✅ Cadastrar rota temporária (`cadastrarRotaTemporaria()`)
- ✅ Obter custo da rota (`obterCustoRota()`)
- ✅ Suporte a SoapVar para parâmetros XML

**Endpoints:**
- `POST /api/semparar/roteirizar` - Route toll plazas between municipalities
- `POST /api/semparar/rota-temporaria` - Create temporary route
- `POST /api/semparar/custo-rota` - Get route cost

**Exemplo de uso:**
```bash
# 1. Roteirizar municípios
curl -X POST http://localhost:8002/api/semparar/roteirizar \
  -H "Content-Type: application/json" \
  -d '{"pontos": [{"cod_ibge": 3118601, "desc": "CONTAGEM", "latitude": -19.9384589, "longitude": -44.0518344}], "alternativas": false}'

# 2. Cadastrar rota temporária
curl -X POST http://localhost:8002/api/semparar/rota-temporaria \
  -H "Content-Type: application/json" \
  -d '{"praca_ids": [1030, 1028, 1026], "nome_rota": "ROTA_TEMP_123456"}'

# 3. Obter custo
curl -X POST http://localhost:8002/api/semparar/custo-rota \
  -H "Content-Type: application/json" \
  -d '{"nome_rota": "ROTA_TEMP_123456", "placa": "ABC1234", "eixos": 2, "data_inicio": "2025-10-27", "data_fim": "2025-11-03"}'
```

### FASE 2A - Compra de Viagem (✅ COMPLETA)
**Implementação:** `app/Services/SemParar/SemPararService.php` - `comprarViagem()` (105 lines)

**Funcionalidades:**
- ✅ Comprar viagem via SOAP (`comprarViagem()`)
- ✅ Extração do código da viagem do XML response
- ✅ Tratamento de erros SOAP
- ✅ Rate limiting (10 req/min)

**Endpoint:**
- `POST /api/semparar/comprar-viagem` - Purchase trip

**Parâmetros obrigatórios:**
- `nome_rota` (string) - Nome da rota temporária criada
- `placa` (string) - Placa do veículo (7-8 chars)
- `eixos` (int) - Número de eixos (2-9)
- `data_inicio` (date) - Data início formato YYYY-MM-DD
- `data_fim` (date) - Data fim (>= data_inicio)
- `item_fin1` (string, opcional) - Item financeiro 1 (default: "")

**Retorno:**
```json
{
  "success": true,
  "message": "Viagem comprada com sucesso",
  "data": {
    "success": true,
    "cod_viagem": "68470838",
    "status": "0"
  }
}
```

### FASE 2B - Persistência no Progress Database (✅ COMPLETA)
**Implementação:**
- `app/Services/ProgressService.php` - `salvarViagemSemParar()` (109 lines)
- `app/Http/Controllers/Api/SemPararController.php` - Integration (lines 325-344)

**Funcionalidades:**
- ✅ Salvar viagem no Progress após compra bem-sucedida
- ✅ Validação de campos obrigatórios
- ✅ SQL escaping para prevenir injection
- ✅ Persistência opcional (só salva se `cod_pac` fornecido)
- ✅ Non-blocking (compra funciona mesmo se Progress falhar)

**Tabela Progress:**
```sql
PUB.sPararViagem
├── codviagem (string) - Código da viagem no SemParar
├── codpac (int) - Código do pacote
├── numpla (string) - Placa do veículo
├── nomrotsemparar (string) - Nome da rota
├── valviagem (decimal) - Valor da viagem
├── codtrn (int) - Código do transportador
├── codrotcreatesp (string) - Código da rota criada
├── spararrotid (int) - ID da rota SemParar
├── rescompra (string) - Responsável pela compra
├── datacompra (date) - Data da compra
├── flgcancelado (bool) - Flag de cancelamento
└── rescancel (string) - Responsável pelo cancelamento
```

**Endpoint (integrado):**
- `POST /api/semparar/comprar-viagem` - Purchase trip + save to Progress

**Parâmetros opcionais (FASE 2B):**
- `cod_pac` (int) - Package ID (triggers Progress save)
- `cod_trn` (int) - Transporter ID
- `s_parar_rot_id` (int) - SemParar route ID
- `cod_rota_create_sp` (string) - Route creation code
- `valor_viagem` (decimal) - Trip cost
- `res_compra` (string) - Purchase responsible

**Retorno com Progress:**
```json
{
  "success": true,
  "message": "Viagem comprada com sucesso",
  "data": {
    "success": true,
    "cod_viagem": "68470838",
    "status": "0",
    "progress_saved": true
  }
}
```

**Exemplo completo (FASE 2A + 2B):**
```bash
curl -X POST http://localhost:8002/api/semparar/comprar-viagem \
  -H "Content-Type: application/json" \
  -d '{
    "nome_rota": "ROTA_TEMP_123456",
    "placa": "ABC1234",
    "eixos": 2,
    "data_inicio": "2025-10-27",
    "data_fim": "2025-11-03",
    "item_fin1": "PEDAGIO",
    "cod_pac": 3043368,
    "cod_trn": 5576,
    "s_parar_rot_id": 204,
    "cod_rota_create_sp": "ROTA_TEMP_123456",
    "valor_viagem": 123.45,
    "res_compra": "sistema"
  }'
```

### FASE 2C - Recibo PDF (✅ COMPLETA + Envio WhatsApp)
**Implementação:**
- `app/Services/SemParar/SemPararService.php` - `obterRecibo()` (118 lines) + `gerarRecibo()` (130 lines)
- `app/Http/Controllers/Api/SemPararController.php` - `obterRecibo()` + `gerarRecibo()` endpoints

**Funcionalidades:**
- ✅ Obter recibo em PDF da viagem comprada (base64)
- ✅ Gerar recibo e enviar por WhatsApp/Email (via serviço Node.js)
- ✅ Download automático no browser
- ✅ Validação de código de viagem e telefone
- ✅ Tratamento de erros (viagem não encontrada, recibo indisponível, serviço offline)

**Endpoints:**

#### 1. Obter Recibo PDF (download direto)
- `POST /api/semparar/obter-recibo` - Get trip receipt PDF in base64

**Parâmetros:**
- `cod_viagem` (string, obrigatório) - Trip code from comprarViagem()

**Retorno com sucesso:**
```json
{
  "success": true,
  "message": "Recibo obtido com sucesso",
  "data": {
    "recibo_pdf": "JVBERi0xLjQKJe...",  // Base64 encoded PDF
    "pdf_size_bytes": 45678,
    "status": 0,
    "status_mensagem": "Sucesso"
  }
}
```

**Status codes SemParar:**
- `0` - Sucesso (PDF disponível)
- `15` - Recibo não disponível (viagem antiga/usada/inválida)
- `999` - Erro desconhecido

**Exemplo de uso:**
```bash
curl -X POST http://localhost:8002/api/semparar/obter-recibo \
  -H "Content-Type: application/json" \
  -d '{"cod_viagem": "68470838"}'
```

#### 2. Gerar e Enviar Recibo por WhatsApp/Email (recomendado)
- `POST /api/semparar/gerar-recibo` - Generate receipt and send via WhatsApp/Email

**Parâmetros:**
- `cod_viagem` (string, obrigatório) - Trip code
- `telefone` (string, obrigatório) - Phone in format 5531988892076 (country+ddd+number)
- `email` (string, opcional) - Email address
- `flg_imprime` (boolean, opcional) - Print/display flag (default: true)

**Retorno com sucesso:**
```json
{
  "success": true,
  "message": "Recibo gerado e enviado com sucesso",
  "data": {
    "success": true,
    "message": "Recibo gerado e enviado com sucesso",
    "status": "success",
    "telefone": "5531988892076",
    "email": "user@example.com"
  }
}
```

**Fluxo interno (seguindo Progress):**
1. Chama SOAP `obterReciboViagem()` para pegar dados
2. Envia para Node.js service (`http://192.168.19.35:5001/gerar-vale-pedagio`)
3. Service gera PDF e envia por WhatsApp/Email

**Exemplo de uso:**
```bash
curl -X POST http://localhost:8002/api/semparar/gerar-recibo \
  -H "Content-Type: application/json" \
  -d '{
    "cod_viagem": "68470838",
    "telefone": "5531988892076",
    "email": "usuario@example.com",
    "flg_imprime": true
  }'
```

**Observações:**
- ⚠️ Requer serviço Node.js rodando em 192.168.19.35:5001
- 📱 WhatsApp recebe PDF automaticamente
- 📧 Email opcional (se fornecido, também envia por email)
- ⏱️ Rate limit: 20 req/min (protege contra spam)

### 🧪 Teste Completo (FASE 1A → 1B → 2A → 2B → 2C)
**Interface HTML:** `public/test-semparar-fase1b.html`

**Acesso:** http://localhost:8002/test-semparar-fase1b.html

**Workflow de teste:**
1. **Teste 1:** Roteirizar municípios (FASE 1B)
2. **Teste 2:** Cadastrar rota temporária (FASE 1B)
3. **Teste 3:** Obter custo da rota (FASE 1B)
4. **Teste 4:** Comprar viagem (FASE 2A + 2B)
5. **Teste 5:** Baixar recibo PDF (FASE 2C) ← NOVO!
6. **Verificar Progress:** Query `PUB.sPararViagem` (FASE 2B)

**Scripts de teste:**
- `test-fase2b-completo.ps1` - PowerShell test script (Windows)
- `test-roteirizar.json` - Simple route test data
- `test-roteirizar-completo.json` - Complete route test data (4 municipalities)

### 📋 Próximas Fases (Planejadas)
- **FASE 3A:** Validação e pesquisa de viagens
- **FASE 3B:** Frontend Vue.js integration (`resources/ts/pages/compra-viagem/`)

### 🔗 Documentação Adicional
- `SEMPARAR_IMPLEMENTATION_ROADMAP.md` - Complete implementation plan
- `COMPRA_VIAGEM_ANALISE.md` - Business analysis and requirements

---

## 🗺️ Sistema de Rotas SemParar (Módulo Completo)

**Visão Geral:**
Sistema de gestão de rotas pré-cadastradas no Progress Database com visualização em mapa interativo (Leaflet + OpenStreetMap) e capacidade de simular entregas reais de pacotes sobre essas rotas.

### Arquitetura

```
Frontend (Vue)                Backend (Laravel)              Database (Progress)
┌──────────────┐             ┌──────────────────┐          ┌──────────────────┐
│ index.vue    │────────────▶│ SemPararRota     │─────────▶│ PUB.semPararRot  │
│ (Listagem)   │             │ Controller       │          │ (Rotas)          │
└──────────────┘             └──────────────────┘          └──────────────────┘
                                      │                              │
┌──────────────┐                      │                              │
│ mapa/[id].vue│                      ▼                              ▼
│ (Visualizar/ │             ┌──────────────────┐          ┌──────────────────┐
│  Editar)     │────────────▶│ ProgressService  │─────────▶│ PUB.semPararRotMu│
└──────────────┘             │ (JDBC Connector) │          │ (Municípios)     │
       │                     └──────────────────┘          └──────────────────┘
       │
       ▼
┌──────────────────┐
│ usePackage       │
│ Simulation       │
│ (Composable)     │
└──────────────────┘
       │
       ▼
┌──────────────────┐
│ Leaflet +        │
│ OpenStreetMap +  │
│ OSRM Routing     │
└──────────────────┘
```

### Componentes Principais

#### 1. **index.vue** - Listagem de Rotas
**Path:** `resources/ts/pages/rotas-semparar/index.vue`

**Features:**
- ✅ VDataTableServer com paginação server-side
- ✅ Filtros tri-state (Tipo: All/CD/Rota, Retorno: All/Sim/Não)
- ✅ Busca por nome com debounce (500ms)
- ✅ Estatísticas (total, CDs, rotas com retorno)
- ✅ Ações: Visualizar, Editar, Deletar

**Endpoints usados:**
- `GET /api/semparar-rotas?page=1&per_page=10&flg_cd=true`

#### 2. **mapa/[id].vue** - Visualização + Edição + Simulação
**Path:** `resources/ts/pages/rotas-semparar/mapa/[id].vue`

**Features:**
- ✅ Mapa interativo Leaflet + OpenStreetMap (100% gratuito)
- ✅ Marcadores numerados customizados (L.divIcon)
- ✅ Roteamento real via OSRM (routing.openstreetmap.de)
- ✅ Geocoding automático (Google API + cache SQLite)
- ✅ Drag & drop para reordenar municípios (vuedraggable)
- ✅ Adicionar/remover municípios via autocomplete
- ✅ Simulação de pacotes sobre a rota
- ✅ Debug panel com logs e métricas

**Endpoints usados:**
- `GET /api/semparar-rotas/{id}/municipios`
- `PUT /api/semparar-rotas/{id}`
- `PUT /api/semparar-rotas/{id}/municipios`
- `POST /api/geocoding/lote`
- `POST /api/pacotes/itinerario`

**Tecnologias de Mapa:**
```typescript
// Inicialização
map = L.map(container).setView([-14.2350, -51.9253], 4)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map)

// Routing GRATUITO
const osrmRouter = L.Routing.osrmv1({
  serviceUrl: 'https://routing.openstreetmap.de/routed-car/route/v1',
  profile: 'driving'
})

// Marcadores customizados
const icon = L.divIcon({
  html: `<div style="background: #2196F3; ...">1</div>`
})
```

#### 3. **usePackageSimulation.ts** - Composable de Simulação
**Path:** `resources/ts/composables/usePackageSimulation.ts`

**Responsabilidades:**
- Autocomplete de pacotes
- Carregar itinerário de pacote
- Processar coordenadas GPS do Progress ("230876543" → -23.0876543)
- Gerenciar estado da simulação
- Criar marcadores e waypoints combinados (rota + entregas)

**Exemplo de uso:**
```typescript
const {
  selectedPacote,
  entregas,
  simulationActive,
  startSimulation,
  stopSimulation
} = usePackageSimulation()

// Iniciar simulação
await startSimulation()
// entregas = [{lat: -23.08, lon: -46.01, razcli: "Cliente A", ...}, ...]

// Parar simulação
stopSimulation()
```

### Tabelas Progress

#### PUB.semPararRot (Rotas)
```sql
CREATE TABLE PUB.semPararRot (
  sPararRotID INTEGER PRIMARY KEY,
  desSPararRot VARCHAR(60),     -- Nome da rota
  tempoViagem INTEGER,          -- Dias de viagem
  flgCD LOGICAL,                -- É Centro de Distribuição?
  flgRetorno LOGICAL,           -- Tem retorno?
  datAtu DATE,                  -- Data última atualização
  resAtu VARCHAR(15)            -- Responsável atualização
)
```

#### PUB.semPararRotMu (Municípios da Rota)
```sql
CREATE TABLE PUB.semPararRotMu (
  sPararRotID INTEGER,          -- FK para semPararRot
  sPararMuSeq INTEGER,          -- Sequência do município (1, 2, 3...)
  codMun INTEGER,               -- Código do município
  codEst INTEGER,               -- Código do estado
  desMun VARCHAR(60),           -- Nome do município
  desEst VARCHAR(60),           -- Nome do estado
  cdibge INTEGER                -- Código IBGE (para geocoding)
)
```

### Fluxo de Simulação

```
1. Usuário seleciona pacote no autocomplete
   └─▶ POST /api/pacotes/itinerario {codPac: 3043368}

2. Backend retorna pedidos com GPS
   └─▶ {pedidos: [{gps_lat: "230876543", gps_lon: "460123456", ...}]}

3. Composable processa coordenadas
   └─▶ processGpsCoordinate("230876543") → -23.0876543

4. Entregas filtradas (apenas com GPS válido)
   └─▶ entregas: [{lat: -23.08, lon: -46.01, ...}]

5. Mapa atualizado com marcadores combinados
   └─▶ Azul: Municípios da rota SemParar
   └─▶ Verde: Primeira entrega
   └─▶ Laranja: Entregas intermediárias
   └─▶ Vermelho: Última entrega

6. OSRM calcula rota combinada
   └─▶ waypoints: [rota1, rota2, ..., entrega1, entrega2, ...]
   └─▶ Polyline desenhada em rosa (#E91E63)
```

### Problemas Conhecidos e Soluções

#### ⚠️ CRÍTICO: `updateSemPararRotaMunicipios()` Pode Perder Dados

**Problema:**
```php
// Progress JDBC NÃO suporta transações
DELETE FROM PUB.semPararRotMu WHERE sPararRotID = 204;  // ✅ OK
// Se falhar aqui, municípios são perdidos!
INSERT INTO PUB.semPararRotMu VALUES (...);  // ❌ Falha
```

**Mitigação Atual:**
- Validação prévia de dados
- Logging detalhado

**Solução Futura:**
- Strategy pattern (UPDATE/INSERT/DELETE granular)
- Validação completa antes de DELETE

#### ⚠️ OSRM Público Pode Falhar

**Problema:** Servidor público pode ter downtime

**Mitigação:**
```typescript
.on('routingerror', (e) => {
  // Fallback: desenhar linha reta tracejada
  L.polyline(waypoints, {
    dashArray: '10, 10',
    opacity: 0.5
  }).addTo(map)
})
```

**Solução Futura:**
- Hospedar OSRM próprio via Docker
- Cache de rotas no banco

### URLs Importantes

- **Listagem:** http://localhost:8002/rotas-semparar
- **Mapa (Rota 204):** http://localhost:8002/rotas-semparar/mapa/204
- **Teste Pacote:** http://localhost:8002/test-leaflet-pacote

### Documentação Adicional

- **Análise Completa:** `ANALISE_ROTAS_SEMPARAR.md` (arquitetura, problemas, melhorias)
- **Sistema de Debug:** `DEBUG_MAPA_ROTAS.md` (como usar debug panel)

---

## Important Notes

- **Repository:** https://github.com/Psykhepathos/ndd-vuexy.git
- **Old systems (deprecated):** ndd-laravel, ndd-flutter repos
- **Key features:**
  - Dashboard NDD: http://localhost:8002/ndd-dashboard
  - Transportes: http://localhost:8002/transportes (transporter management)
  - Pacotes: http://localhost:8002/pacotes (package tracking)
  - Vale Pedágio: http://localhost:8002/vale-pedagio (toll pass calculator)
  - Rotas Padrão: http://localhost:8002/rotas-padrao (CRUD + interactive map with Leaflet/OSM)
  - Compra Viagem: http://localhost:8002/compra-viagem (SemParar trip purchase - in development)
- **Progress JDBC:** Located in `c:/Progress/OpenEdge/java/openedge.jar`
- **Java Connector:** Auto-compiled on first use in `storage/app/java/`
- **Pagination:** Progress lacks OFFSET - use subquery pattern in ProgressService
- **Always test functionality before committing**
- **Use Progress API endpoints for schema exploration, not tinker**

## Google Maps Integration

**Cache Strategy:**
- **Geocoding cache**: SQLite table `municipio_coordenadas` (persistent, no expiration)
- **Routing cache**: SQLite table `route_segments` (30 days TTL, ~100m tolerance)
- **Rate limiting**: 200ms delay between new Google API requests
- **Cache hit rate**: 80%+ after first visualization of routes

**Services:**
- `GeocodingService` - Converts IBGE codes → lat/lon coordinates
- `RoutingService` - Calculates real road routes between points
- Both services use local cache to minimize API calls

**Quota monitoring:**
- Monitor usage: `GET /api/google-maps/quota`
- Reset counters: `POST /api/google-maps/reset-counters`

## Debugging Tips

**Progress connection issues:**
```bash
# Test connection
curl http://localhost:8002/api/progress/test-connection

# Check Java is installed
java -version

# Check Progress driver exists
dir "c:\Progress\OpenEdge\java\openedge.jar"

# View Laravel logs
php artisan pail
```

**Frontend issues:**
```bash
# Check TypeScript errors
pnpm run typecheck

# Check for linting issues
pnpm run lint

# Clear Vite cache
rm -rf node_modules/.vite && pnpm run dev
```

**Database queries:**
```bash
# Test custom SQL via API
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 5 * FROM PUB.transporte"}'
```
