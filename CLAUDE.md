# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 📚 Navigation

- [Quick Start](#-quick-start) - Commands to get started
- [Recent Changes](#-recent-changes-dec-2025) - Latest security updates
- [Critical Rules](#-critical-rules) - MUST READ before coding
- [Architecture](#-architecture) - System overview
- [Backend Controllers](#-backend-controllers-21-controllers) - Complete API reference
- [Services Layer](#-services-layer) - Business logic
- [Frontend Modules](#-frontend-modules) - Vue pages and components
- [Database](#-database-architecture) - Progress + SQLite/MySQL
- [Implementation Patterns](#-critical-implementation-patterns) - Code examples
- [Troubleshooting](#-troubleshooting) - Common issues

---

## 🔄 Recent Changes (Dec 2025)

### VPO Emission Wizard & NDD Cargo Integration (Dec 8-9, 2025)

**Current Branch:** `feature/vpo-emissao-wizard`

Complete VPO (Vale Pedágio Obrigatório) emission system integrated with NDD Cargo:

**New Components:**
- ✅ **VpoDataSyncService** - Progress → ANTT → Cache synchronization (660 lines)
- ✅ **VpoEmissaoService** - VPO emission business logic
- ✅ **MotoristaEmpresaCacheService** - Driver cache for companies (trnmot)
- ✅ **NDD Cargo Integration** - CrossTalk SOAP + RSA-SHA1 digital signature

**Key Features:**
- 100% VPO field coverage (19/19 campos mapeados)
- Conditional logic for autônomo vs empresa transporters
- ANTT Open Data integration (CKAN API)
- Quality score system (0-100)

**Endpoints:**
```
POST /api/vpo/sync/transportador    - Sync single transporter
POST /api/vpo/sync/batch            - Batch sync
GET  /api/vpo/transportadores       - List cached transporters
GET  /api/vpo/statistics            - Sync statistics
POST /api/vpo/emissao/validate      - Validate for emission
POST /api/vpo/emissao/emit          - Emit VPO (NDD Cargo)
```

**Documentation:** `docs/integracoes/ndd-cargo/INDEX.md` (15 docs, 8000+ lines)

---

### Major Security Audit & Bug Fixes (Dec 4-5, 2025)

**Previous Branch:** `refactor/controller-bug-audit` (merged)

A comprehensive security audit identified and fixed **81 bugs** across all controllers:

**By Severity:**
- 🔴 **23 CRITICAL** - SQL injection, DoS vulnerabilities, unprotected financial endpoints
- 🟡 **32 IMPORTANT** - LGPD compliance, authentication gaps, data validation
- 🟢 **26 MODERATE** - Code quality, documentation, configuration issues

**Key Improvements:**
- ✅ **Rate Limiting**: Comprehensive protection against brute force (5-60 req/min by endpoint)
- ✅ **Authentication**: Admin-only access for sensitive operations (quota reset, data deletion, etc.)
- ✅ **SQL Injection**: 8 critical vulnerabilities eliminated with prepared statements
- ✅ **LGPD Compliance**: Full audit trail logging (user_id, IP, timestamp) in 22 locations
- ✅ **DoS Protection**: Array limits (max 100 items), timeout optimization (60s), input validation
- ✅ **Data Protection**: Strategy pattern for updates, confirmation codes for destructive operations

**Documentation:**
- 📁 `docs/audits/` - 8 controller-by-controller security audits
- 📁 `docs/bug-fixes/` - 15 detailed bug fix documents
- 📁 `docs/security/` - Critical security alerts
- 📁 `docs/analysis/` - Technical analysis and flow documentation
- 📁 `docs/summaries/` - Progress summaries and consolidated reports

**See:** `docs/summaries/RESUMO_CONSOLIDADO_FINAL_2025-12-05.md` for complete details.

---

## ⚡ Quick Start

```bash
# Start development servers
php artisan serve --port=8002  # Backend API (REQUIRED PORT!)
pnpm run dev                   # Frontend (Vite)

# Access system
http://localhost:8002          # ✅ ALWAYS use this URL!
# Login: admin@ndd.com / Admin@123 (or user@ndd.com / User@123)

# Testing & validation
pnpm run typecheck            # TypeScript check
pnpm run lint                 # ESLint
php artisan test              # Backend tests
composer test                 # Clear cache + tests

# Test connections
curl http://localhost:8002/api/progress/test-connection  # Progress JDBC
curl http://localhost:8002/api/semparar/test-connection  # SemParar SOAP

# Build for production
pnpm run build
```

**⚠️ CRITICAL:** ALWAYS use `http://localhost:8002` to access the system! Vite dev server (port 5173/5174/5176) is for hot-reload only, NOT for viewing the app.

---

## 🚨 CRITICAL RULES

### 1. Progress Database - NO TRANSACTIONS!

```php
// ❌ NEVER DO THIS - Progress JDBC doesn't support transactions
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql);
DB::connection('progress')->commit();

// ✅ ALWAYS DO THIS - Direct execution
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
// If either fails, catch exception and handle manually

// ✅ SQL must be SINGLE-LINE (Progress JDBC has multi-line issues)
$sql = "UPDATE PUB.semPararRot SET desSPararRot = 'Test', tempoViagem = 5 WHERE sPararRotID = 204";

// ❌ NEVER multi-line
$sql = "UPDATE PUB.semPararRot SET
  desSPararRot = 'Test',
  tempoViagem = 5
  WHERE sPararRotID = 204";
```

**Why:** Progress JDBC driver doesn't implement transaction methods. Any attempt to use transactions will fail silently or throw errors.

### 2. OSRM Routing - ALWAYS Use Laravel Proxy!

```typescript
// ❌ NEVER use leaflet-routing-machine directly (CORS/timeout issues)
import 'leaflet-routing-machine'
const osrmRouter = L.Routing.osrmv1({
  serviceUrl: 'https://routing.openstreetmap.de/...'
})
L.Routing.control({ router: osrmRouter, ... })  // FAILS!

// ✅ ALWAYS use Laravel proxy (segment-by-segment)
for (let i = 0; i < waypoints.length - 1; i++) {
  const response = await fetch('http://localhost:8002/api/routing/route', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      start: [waypoints[i].lng, waypoints[i].lat],  // [lng, lat]
      end: [waypoints[i+1].lng, waypoints[i+1].lat]
    })
  })

  const data = await response.json()
  if (data.success) {
    allCoordinates.push(...data.coordinates)  // [[lat, lng], ...]
  }
}

L.polyline(allCoordinates, { color: '#E91E63' }).addTo(map)
```

**Why:** Public OSRM servers block direct browser requests. Laravel proxy handles retry logic, fallbacks, and CORS.

**Reference:** `resources/ts/pages/rotas-padrao/mapa/[id].vue` (lines 449-610)

### 3. SemParar SOAP - Positional Parameters + SoapVar

```php
// ❌ WRONG - Named params cause "Array to string conversion"
$client->autenticarUsuario(['cnpj' => $x, 'login' => $y, 'senha' => $z]);

// ✅ CORRECT - Positional params
$client->autenticarUsuario($cnpj, $user, $password);

// ❌ WRONG - XML as string (sends EMPTY XML in SOAP body!)
$client->roteirizarPracasPedagio($pontosXml, $opcoesXml, $token);

// ✅ CORRECT - Use SoapVar with XSD_ANYXML
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);
$client->roteirizarPracasPedagio($pontosParam, $opcoesParam, $token);
```

**Why:** PHP SoapClient has quirks with Progress SOAP services. These patterns are battle-tested.

### 4. Vuexy Template Usage (MANDATORY)

**NEVER create UI from scratch. ALWAYS copy from existing Vuexy templates:**

```
Lists:       resources/ts/pages/apps/user/list/index.vue
Forms:       resources/ts/pages/apps/user/view/UserBioPanel.vue
Dashboards:  resources/ts/pages/apps/logistics/dashboard.vue
```

**Use Vuexy components:**
- `AppTextField` instead of `VTextField`
- `AppSelect` instead of `VSelect`
- `VDataTableServer` for paginated tables
- Theme classes: `text-high-emphasis`, `text-medium-emphasis`

### 5. Progress vs. Eloquent

```php
// ✅ CORRECT - Progress tables (PUB.*) → Raw JDBC
DB::connection('progress')->select('SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]);
$this->progressService->executeCustomQuery($sql);

// ❌ WRONG - Never use Eloquent for Progress tables
Pacote::find(123);  // Won't work with JDBC!

// ✅ CORRECT - Laravel tables → Eloquent ORM
$coords = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();  // Cache table
$user = User::find($userId);  // Laravel users
```

**Summary:**
- **Progress tables (PUB.*)** → Raw JDBC via ProgressService ✅
- **Laravel tables (users, cache, etc.)** → Eloquent ORM ✅

### 6. VPO Autônomo vs Empresa Logic

```php
// VPO data comes from DIFFERENT tables based on transporter type!

// ✅ CORRECT - Check flgautonomo FIRST, then fetch from appropriate table
$transportador = $this->progressService->getTransporteById($codtrn);

if ($transportador['flgautonomo']) {
    // AUTÔNOMO: All data from PUB.transporte
    $condutor_nome = $transportador['nomtrn'];
    $condutor_cpf = $transportador['codcnpjcpf'];
    $condutor_rg = $transportador['numrg'];
    $condutor_nome_mae = $transportador['NomMae'];
    $veiculo_modelo = $transportador['desvei'];  // desvei = modelo!
    $veiculo_placa = $transportador['numpla'];
} else {
    // EMPRESA: Driver from PUB.trnmot, vehicle from PUB.trnvei
    $motorista = $this->progressService->getMotoristaByCode($codmot);
    $condutor_nome = $motorista['nommot'];
    $condutor_cpf = $motorista['codcpf'];
    $condutor_rg = $motorista['numrg'];
    $condutor_nome_mae = $motorista['nommae'];

    $veiculo = $this->progressService->getVeiculoByPlaca($placa);
    $veiculo_modelo = $veiculo['modvei'];
    $veiculo_placa = $veiculo['numpla'];
}

// ❌ WRONG - Using same field for both types
$condutor_nome = $transportador['nomtrn'];  // Only works for autônomo!
```

**Key Tables:**
- **Autônomo:** `PUB.transporte` (driver IS the transporter)
- **Empresa:** `PUB.transporte` + `PUB.trnmot` (drivers) + `PUB.trnvei` (vehicles)

**Documentation:** `docs/integracoes/ndd-cargo/TABELA_MAPEAMENTO_VPO.md`

### 7. Git Commits

```bash
# Configure user
git config --global user.name "Psykhepathos"
git config --global user.email "your-email@example.com"

# Commit style
git commit -m "Fix: Correct OSRM routing proxy timeout handling"
git commit -m "Add: Vehicle validation endpoint for trip purchase"
git commit -m "Update: Enhance geocoding cache hit rate"

# NEVER mention AI/Claude in commits
# NEVER use emojis in commits
```

---

## 🏗️ Architecture

```
Vue/Vuexy Frontend (Port 5173/4/6)
        ↓ HTTP API
Laravel Backend (Port 8002)
        ↓ JDBC Direct
Progress OpenEdge Database (192.168.80.113)

External APIs:
- Google Geocoding (IBGE → coordinates, cached 80%+)
- OSRM Public Servers (free routing, 3 servers with retry)
- SemParar SOAP (toll management, 2 WSDLs)
- Python Flask (PDF generation + WhatsApp/Email)
```

**Stack:**
- **Frontend:** Vue 3.5.14 + TypeScript 5.8.3 + Vuexy + Vuetify 3.8.5
- **Backend:** Laravel 12.15.0 + Sanctum authentication
- **Database:** Progress OpenEdge (JDBC) + SQLite (cache)
- **Maps:** Leaflet + OpenStreetMap + OSRM (100% FREE!)
- **Build:** Vite 6.3.5 + PNPM

**Key Metrics:**
- 18 Controllers
- 11 Services (ProgressService: 2574 lines!)
- 21 Progress Tables (via JDBC, NO transactions)
- 11 Laravel Tables (SQLite/MySQL)
- 50+ API Endpoints
- 15+ Vue Pages

---

## 🎯 Backend Controllers (21 Controllers)

### Authentication & Core

#### **AuthController**
```
POST   /api/auth/login      - Sanctum token auth
POST   /api/auth/register   - User registration
POST   /api/auth/logout     - Invalidate token (protected)
GET    /api/auth/user       - Current user (protected)
```
**Response:** `{accessToken, userData, userAbilityRules}`
**Tables:** `users` (SQLite/MySQL)

#### **ProgressController**
```
GET    /api/progress/test-connection       - JDBC health check
GET    /api/progress/transportes           - Raw transporter list
GET    /api/progress/transportes/{id}      - Specific transporter
POST   /api/progress/query                 - Custom SQL (protected, admin, 5 req/min)
```
**Tables:** All via ProgressService
**Rate Limit:** 10 req/min (test), 5 req/min (custom queries)

---

### Transport Management

#### **TransporteController** (PRIMARY CRUD)
```
GET    /api/transportes?page=1&per_page=10&search=...&tipo=autonomo
       &natureza=T&status_ativo=true        - Paginated list (60 req/min)
GET    /api/transportes/{id}                - Detailed view + drivers + vehicles
GET    /api/transportes/test-connection     - JDBC test (10 req/min)
GET    /api/transportes/statistics          - Aggregated stats (10 req/min)
GET    /api/transportes/schema              - Table schema (10 req/min)
POST   /api/transportes/query               - Custom SQL (protected, admin, 5 req/min)
```

**ProgressService Methods:**
- `getTransportesPaginated($filters)` - Keyset pagination
- `getTransporteById($id)` - Full data
- `getMotoristasPorTransportador($id)` - Associated drivers
- `getVeiculosPorTransportador($id)` - Associated vehicles

**Progress Tables:** `PUB.transporte`, `PUB.trnmot`
**Frontend:** `resources/ts/pages/transportes/index.vue`

#### **MotoristaController**
```
GET    /api/motoristas                      - List drivers
GET    /api/motoristas/{id}                 - Driver details
POST   /api/motoristas                      - Create driver
PUT    /api/motoristas/{id}                 - Update driver
DELETE /api/motoristas/{id}                 - Delete driver
GET    /api/motoristas/progress/{codigo}    - Find by Progress code
```
**Tables:** `motoristas` (SQLite/MySQL - local cache)

---

### Package Management

#### **PacoteController**
```
GET    /api/pacotes?page=1&per_page=15&search=...&codigo=...
       &transportador=...&situacao=...&apenas_recentes=1
       &data_inicio=...&data_fim=...        - Paginated packages (60 req/min)
GET    /api/pacotes/{id}                    - Package details
POST   /api/pacotes/itinerario              - Full itinerary with GPS (for map simulation)
GET    /api/pacotes/autocomplete?search=304 - Quick search
GET    /api/pacotes/statistics              - Stats
```

**ProgressService Methods:**
- `getPacotesPaginated($filters)` - Includes TCD flag (`flg_tcd`)
- `getPacoteById($id)` - Full package data
- `getItinerarioPacote($codPac)` - **CRITICAL for map** - Returns deliveries with GPS

**Progress Tables:** `PUB.pacote`, `PUB.transporte`, `PUB.paccd`, `PUB.carga`, `PUB.pedido`
**Frontend:** `resources/ts/pages/pacotes/index.vue`

**Itinerário Response:**
```json
{
  "pedidos": [
    {"razcli": "Cliente A", "gps_lat": "230876543", "gps_lon": "460123456", ...}
  ]
}
```
**GPS Processing:** "230876543" → -23.0876543 (divide by 10^7)

---

### Geographic & Routing Services

#### **RotaController**
```
GET    /api/rotas?search=SP                 - Autocomplete for routes
```
**Progress Tables:** `PUB.introt`
**ProgressService:** `getRotas($search)`

#### **GeocodingController** (CRITICAL for Maps)
```
POST   /api/geocoding/ibge                  - Single municipality coordinates
POST   /api/geocoding/lote                  - Batch geocoding (multiple municipalities)
```

**Request:**
```json
{
  "codigo_ibge": "3550308",
  "nome_municipio": "SÃO PAULO",
  "uf": "SP"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "lat": -23.5505,
    "lon": -46.6333,
    "fonte": "google_geocoding",
    "cached": false
  }
}
```

**Service:** `GeocodingService`
**Cache:** `municipio_coordenadas` (SQLite, PERSISTENT, no expiration)
**Rate Limit:** 200ms between Google API calls
**Cache Hit Rate:** 80%+ after first use
**Frontend:** Used by ALL map pages

#### **RoutingController** (OSRM Proxy - 100% FREE!)
```
POST   /api/routing/route                   - OSRM proxy (2 points) ✅ USE THIS
GET    /api/routing/test                    - Service test

❌ REMOVED: POST /api/routing/calculate (Google Directions API - deprecated 2025-12-03)
```

**OSRM Request:**
```json
{
  "start": [-46.6333, -23.5505],  // [lng, lat]
  "end": [-43.1729, -22.9068]
}
```

**OSRM Response:**
```json
{
  "success": true,
  "coordinates": [[-23.5505, -46.6333], ...],  // [[lat, lng], ...] - INVERTED!
  "distance_km": 356.7,
  "duration_minutes": 280
}
```

**Servers Tried (in order):**
1. `https://router.project-osrm.org`
2. `https://routing.openstreetmap.de/routed-car`
3. `http://router.project-osrm.org` (HTTP fallback)

**Retry:** 2 retries/server, 15s timeout, 1s delay
**Fallback:** `{success: false, fallback: "usar_linha_reta"}`
**Frontend Reference:** `resources/ts/pages/rotas-padrao/mapa/[id].vue:449-610`

#### **MapController** (Unified Service)
```
POST   /api/map/route                       - Calculate route (100 req/min)
POST   /api/map/geocode-batch               - Batch geocoding (60 req/min)
POST   /api/map/cluster-points              - Proximity clustering (60 req/min)
GET    /api/map/cache-stats                 - Cache stats (30 req/min)
POST   /api/map/clear-expired-cache         - Admin cleanup (5 req/min)
GET    /api/map/providers                   - Available providers
```
**Service:** `MapService` (abstraction over OSRM/Google)

---

### SemParar Integration (Toll Management)

#### **SemPararRotaController** (Route Management)
```
GET    /api/semparar-rotas?page=1&per_page=10&search=...
       &flg_cd=true&flg_retorno=true
       &tempo_minimo=1&tempo_maximo=5       - List routes (60 req/min)
GET    /api/semparar-rotas/{id}             - Route details
GET    /api/semparar-rotas/{id}/municipios  - Route + municipalities
POST   /api/semparar-rotas                  - Create route
PUT    /api/semparar-rotas/{id}             - Update route
PUT    /api/semparar-rotas/{id}/municipios  - Update municipalities (drag & drop)
DELETE /api/semparar-rotas/{id}             - Delete route
GET    /api/semparar-rotas/municipios?search=...&estado_id=... - Municipality autocomplete
GET    /api/semparar-rotas/estados          - State list
```

**ProgressService Methods:**
- `getSemPararRotas($filters)` - Paginated routes
- `getSemPararRotaWithMunicipios($id)` - Route + ordered municipalities
- `createSemPararRota($data)` - Create with municipalities
- `updateSemPararRota($id, $data)` - Update metadata
- `updateSemPararRotaMunicipios($id, $municipios)` - **⚠️ CRITICAL: DELETE + INSERT (data loss risk!)**
- `deleteSemPararRota($id)` - Cascading delete

**Progress Tables:** `PUB.semPararRot`, `PUB.semPararRotMu`, `PUB.municipio`, `PUB.estado`

**Frontend:**
- `resources/ts/pages/rotas-padrao/index.vue` - List view
- `resources/ts/pages/rotas-padrao/mapa/[id].vue` - Interactive map (Leaflet + OSRM)

**Known Issue:** `updateSemPararRotaMunicipios` can lose data if INSERT fails after DELETE (no transactions!)

#### **SemPararController** (SOAP API Integration)
```
FASE 1A - Core:
GET    /api/semparar/test-connection        - Auth test (10 req/min)
POST   /api/semparar/status-veiculo         - Vehicle status (60 req/min)
GET    /api/semparar/debug/token            - Get cached token
POST   /api/semparar/debug/clear-cache      - Force re-auth

FASE 1B - Routing:
POST   /api/semparar/roteirizar             - Find toll plazas (20 req/min)
POST   /api/semparar/rota-temporaria        - Create temp route (20 req/min)
POST   /api/semparar/custo-rota             - Get route cost (60 req/min)

FASE 2A - Purchase:
POST   /api/semparar/comprar-viagem         - ⚠️ PURCHASE TRIP! REAL MONEY! (10 req/min)

FASE 2C - Receipt:
POST   /api/semparar/obter-recibo           - Get receipt data (60 req/min)
POST   /api/semparar/gerar-recibo           - Generate PDF + WhatsApp/Email (20 req/min)

FASE 3A - Management:
POST   /api/semparar/consultar-viagens      - List trips by period (60 req/min)
POST   /api/semparar/cancelar-viagem        - ⚠️ CANCEL TRIP! IRREVERSIBLE! (20 req/min)
POST   /api/semparar/reemitir-viagem        - Reissue with new plate (20 req/min)
```

**Service:** `SemPararService` → `SemPararSoapClient`

**SOAP Methods:**
- `autenticarUsuario()` - Get session token (cached 1 hour)
- `statusVeiculo($placa)` - Check vehicle registration
- `roteirizarPracasPedagio($pontos, $alternativas)` - Find toll plazas
- `cadastrarRotaTemporaria($pracaIds, $nomeRota)` - Register temp route
- `obterCustoRota($nomeRota, $placa, $eixos, $dataInicio, $dataFim)` - Get cost
- `comprarViagem(...)` - **Purchase trip (REAL MONEY!)**
- `obterRecibo($codViagem)` - Get receipt data (SOAP only, NOT PDF!)
- `gerarRecibo($codViagem, $telefone, $email)` - Generate PDF + send
- `consultarViagens($dataInicio, $dataFim)` - Query trips (uses `vpextrato` WSDL)
- `cancelarViagem($codViagem)` - Cancel trip
- `reemitirViagem($codViagem, $placa)` - Reissue trip

**WSDL URLs:**
- Main: `https://app.viafacil.com.br/wsvp/ValePedagio?wsdl` (purchase, cancel, reissue, receipt)
- Extrato: `https://app.viafacil.com.br/vpextrato/ValePedagio?wsdl` (query trips)

**Progress Tables:** `PUB.sPararViagem` (trip purchase records)

**External Services:**
- Python Flask: `http://192.168.19.35:5001/gerar-vale-pedagio` (PDF generation)
- Z-API: WhatsApp messaging
- SMTP: Email (always uses `naoresponda@tambasa.com.br`)

**Test Interfaces:**
- `http://localhost:8002/test-semparar-fase1b.html` - Complete workflow
- `http://localhost:8002/test-semparar-fase3a.html` - Trip management

#### **CompraViagemController** (Trip Purchase Wizard)
```
GET    /api/compra-viagem/initialize        - Get config
GET    /api/compra-viagem/statistics        - Stats
GET    /api/compra-viagem/health            - Health check
POST   /api/compra-viagem/viagens           - List purchased trips
POST   /api/compra-viagem/validar-pacote    - STEP 1: Validate package
POST   /api/compra-viagem/validar-placa     - STEP 2: Validate vehicle
GET    /api/compra-viagem/rotas             - STEP 3: List routes
POST   /api/compra-viagem/validar-rota      - STEP 3: Validate route
POST   /api/compra-viagem/verificar-preco   - STEP 4: Check price
POST   /api/compra-viagem/comprar           - STEP 5: Purchase trip
```

**Environment Variables (CRITICAL):**
```env
ALLOW_SOAP_QUERIES=true      # Enable validations/queries (safe)
ALLOW_SOAP_PURCHASE=false    # ⚠️ BLOCK REAL PURCHASES (default: false!)
```

**ProgressService Methods:**
- `isPacoteTCD($codPac)` - Check TCD status
- `validatePackageForCompraViagem($codPac, $flgcd)` - Validate package
- `getRotaSugeridaPorPacsoc($codPac)` - Suggest route (method 1)
- `getRotaSugeridaPorIntrot($codPac, $flgcd)` - Suggest route (method 2)
- `salvarViagemSemParar(...)` - Save trip to Progress (109 lines!)

**Progress Tables:** `PUB.pacote`, `PUB.paccd`, `PUB.pacsoc`, `PUB.introt`, `PUB.sPararViagem`

**Frontend:**
- `resources/ts/pages/compra-viagem/index.vue` - Trip list
- `resources/ts/pages/compra-viagem/nova.vue` - Purchase wizard (5 steps)
- `resources/ts/pages/compra-viagem/components/CompraViagemStep*.vue` - Step components

**Workflow:** Package → Vehicle → Route → Price → Confirmation → Purchase → Progress Save

---

### Toll Plaza Management

#### **PracaPedagioController**
```
GET    /api/pracas-pedagio?situacao=Ativo&rodovia=BR-116
       &uf=SP&lat=-23.5&lon=-46.6&raio_km=50
       &sort_by=rodovia&per_page=15          - List with filters (60 req/min)
GET    /api/pracas-pedagio/{id}             - Specific plaza
GET    /api/pracas-pedagio/estatisticas     - Statistics (30 req/min)
POST   /api/pracas-pedagio/proximidade      - Find near coordinates (60 req/min)
POST   /api/pracas-pedagio/importar         - Import ANTT CSV (5 req/min)
DELETE /api/pracas-pedagio/limpar           - ⚠️ Clear all! (2 req/min)
```

**Service:** `PracaPedagioImportService`
**Tables:** `pracas_pedagio` (SQLite/MySQL - ANTT official data)
**CSV Format:** ANTT official (18+ columns)
**Frontend:** `resources/ts/pages/pracas-pedagio/index.vue`

---

### VPO & NDD Cargo Integration (NEW - Dec 2025)

#### **VpoController**
```
GET    /api/vpo/test-connection                    - Test Progress + ANTT
POST   /api/vpo/sync/transportador                 - Sync single transporter
POST   /api/vpo/sync/batch                         - Batch sync (max 50)
GET    /api/vpo/transportadores                    - List cached transporters
GET    /api/vpo/transportadores/{codtrn}           - Get cached data
DELETE /api/vpo/transportadores/{codtrn}           - Remove from cache
POST   /api/vpo/transportadores/{codtrn}/recalcular-qualidade  - Recalc score
GET    /api/vpo/statistics                         - Sync statistics
```

**Service:** `VpoDataSyncService`
**Tables:** `vpo_transportadores_cache` (Laravel/SQLite)
**Documentation:** `docs/integracoes/ndd-cargo/VPO_DATA_SYNC.md`

#### **VpoEmissaoController**
```
POST   /api/vpo/emissao/validate                   - Validate for emission
POST   /api/vpo/emissao/preview                    - Preview XML (no emit)
POST   /api/vpo/emissao/emit                       - ⚠️ EMIT VPO (real!)
GET    /api/vpo/emissao/motoristas/{codtrn}        - Get drivers (empresa)
```

**Service:** `VpoEmissaoService`, `MotoristaEmpresaCacheService`
**Documentation:** `docs/integracoes/ndd-cargo/VPO_EMISSAO_WIZARD.md`

#### **NddCargoController**
```
GET    /api/nddcargo/test-connection               - Test NDD Cargo SOAP
POST   /api/nddcargo/roteirizador/consultar        - Query toll plazas
GET    /api/nddcargo/roteirizador/resultado/{guid} - Get async result
```

**Service:** `NddCargoService`, `DigitalSignature`, `NddCargoSoapClient`
**Protocol:** CrossTalk over SOAP 1.1 + RSA-SHA1 digital signature
**Documentation:** `docs/integracoes/ndd-cargo/IMPLEMENTACAO_BACKEND.md`

---

## 🔧 Services Layer

### ProgressService (MASSIVE - 2574 lines!)

**Path:** `app/Services/ProgressService.php`
**Role:** PRIMARY interface to Progress OpenEdge via JDBC
**Java Connector:** `storage/app/java/ProgressJDBCConnector.java`

**Connection:**
- Host: `192.168.80.113`
- Database: `tambasa`
- Driver: `c:/Progress/OpenEdge/java/openedge.jar`
- **NO TRANSACTIONS SUPPORT!**

**Core Methods:**
- `testConnection()` - JDBC health check
- `executeJavaConnector($action, ...$params)` - Raw JDBC
- `executeCustomQuery($sql)` - SELECT only (security)
- `executeUpdate($sql)` - UPDATE/INSERT/DELETE (NO TRANSACTIONS!)
- `escapeSqlString($value)` - SQL injection protection

**Transporters:**
- `getTransportesPaginated($filters)` - Keyset pagination
- `getTransporteById($id)` - Full data + drivers + vehicles
- `getMotoristasPorTransportador($id)` - Drivers
- `getVeiculosPorTransportador($id)` - Vehicles

**Packages:**
- `getPacotesPaginated($filters)` - With TCD flag
- `getPacoteById($id)` - Details
- `getItinerarioPacote($codPac)` - **CRITICAL** - Returns GPS deliveries

**SemParar Routes:**
- `getSemPararRotas($filters)` - List routes
- `getSemPararRotaWithMunicipios($id)` - Route + municipalities
- `createSemPararRota($data)` - Create
- `updateSemPararRota($id, $data)` - Update metadata
- `updateSemPararRotaMunicipios($id, $municipios)` - **⚠️ DELETE + INSERT (data loss risk!)**
- `deleteSemPararRota($id)` - Cascading delete

**Trip Purchase:**
- `isPacoteTCD($codPac)` - Check TCD status
- `validatePackageForCompraViagem($codPac, $flgcd)` - Validation
- `getRotaSugeridaPorPacsoc($codPac)` - Route suggestion
- `getRotaSugeridaPorIntrot($codPac, $flgcd)` - Route suggestion (alt)
- `salvarViagemSemParar(...)` - Save trip (109 lines!)

**Geographic:**
- `getMunicipiosForAutocomplete($search, $estadoId)` - Cities
- `getEstadosForAutocomplete()` - States

**Progress SQL Conventions:**
```sql
-- ✅ CORRECT
SELECT TOP 10 codtrn, nomtrn FROM PUB.transporte WHERE flgati = 1

-- Schema: ALWAYS PUB.tablename
-- Limit: TOP N (not LIMIT)
-- Strings: Single quotes 'value'
-- Case-sensitive: codtrn, NOT CodTrn
-- Single-line preferred (multi-line causes issues)

-- ❌ WRONG - No OFFSET support
SELECT * FROM PUB.transporte LIMIT 10 OFFSET 20

-- ❌ NEVER use transactions
DB::connection('progress')->beginTransaction();
```

### GeocodingService

**Path:** `app/Services/GeocodingService.php`

**Methods:**
- `getCoordenadasByIbge($codigoIbge, $nomeMunicipio, $uf)` - Single geocoding
- `geocodeByGoogle($nomeMunicipio, $uf)` - Direct Google API
- `getCoordenadasLote($municipios)` - Batch processing

**Cache Strategy:**
1. Check `municipio_coordenadas` (SQLite, persistent)
2. If miss → Google Geocoding API
3. Save to `municipio_coordenadas` AND `progress_municipios_gps`
4. Rate limit: 200ms between API calls

**Cache Hit Rate:** 80%+ after first use

### VPO Services (NEW - Dec 2025)

**Path:** `app/Services/Vpo/`

#### VpoDataSyncService (660 lines)
```php
// Sync transporter data: Progress → ANTT → Cache
$result = $vpoDataSyncService->syncTransportador($codtrn, $codmot, $placa);

// Batch sync multiple transporters
$results = $vpoDataSyncService->syncBatch([1809, 6826, 6269]);

// Key methods:
- fetchFromProgress($codtrn, $codmot, $placa)  // Get Progress data
- fetchFromAntt($rntrc)                         // Enrich with ANTT
- mergeData($progressData, $anttData)           // Combine sources
- calculateQualityScore($data)                  // Score 0-100
```

#### VpoEmissaoService
```php
// Validate transporter for VPO emission
$validation = $vpoEmissaoService->validateForEmission($codtrn);

// Emit VPO via NDD Cargo
$result = $vpoEmissaoService->emit($codtrn, $codmot, $placa, $rotaId);
```

#### MotoristaEmpresaCacheService
```php
// Cache drivers for empresa transporters (from PUB.trnmot)
$motoristas = $motoristaService->getMotoristasByTransportador($codtrn);
$motorista = $motoristaService->getMotoristaByCode($codmot);
```

**Tables:**
- `vpo_transportadores_cache` - Synced transporter data (Laravel/SQLite)
- `motorista_empresa_cache` - Driver cache for empresas (Laravel/SQLite)

### NDD Cargo Services

**Path:** `app/Services/NddCargo/`

#### NddCargoService (278 lines)
```php
// Query roteirizador (toll plazas for route)
$result = $nddCargoService->consultarRoteirizador($pontos);

// Get async result by GUID
$result = $nddCargoService->consultarResultado($guid);
```

#### DigitalSignature (322 lines)
```php
// Sign XML with RSA-SHA1 (required for NDD Cargo)
$signedXml = $digitalSignature->signXml($xmlContent);
```

#### NddCargoSoapClient (374 lines)
```php
// CrossTalk SOAP 1.1 protocol
$response = $soapClient->call($method, $signedPayload);
```

**Configuration:** `config/nddcargo.php` (certificate paths, endpoints)

**Documentation:** `docs/integracoes/ndd-cargo/IMPLEMENTACAO_BACKEND.md`

---

### SemPararService (Business Logic)

**Path:** `app/Services/SemParar/SemPararService.php`
**Dependencies:** `SemPararSoapClient`, `PontosParadaBuilder`

**FASE 1A - Core:**
- `testConnection()` - SOAP health
- `statusVeiculo($placa)` - Vehicle status
- `getToken()` - Get cached token (1 hour TTL)

**FASE 1B - Routing:**
- `roteirizarPracasPedagio($pontos, $alternativas)` - Find toll plazas
- `cadastrarRotaTemporaria($pracaIds, $nomeRota)` - Register temp route
- `obterCustoRota($nomeRota, $placa, $eixos, $dataInicio, $dataFim)` - Get cost

**FASE 2A - Purchase:**
- `comprarViagem(...)` - **REAL PURCHASE!**

**FASE 2C - Receipt:**
- `obterRecibo($codViagem)` - Get data (NOT PDF!)
- `gerarRecibo($codViagem, $telefone, $email, $flgImprime)` - Generate PDF + send

**FASE 3A - Management:**
- `consultarViagens($dataInicio, $dataFim)` - Query trips
- `cancelarViagem($codViagem)` - Cancel (IRREVERSIBLE)
- `reemitirViagem($codViagem, $placa)` - Reissue

### SemPararSoapClient (Low-level SOAP)

**Path:** `app/Services/SemParar/SemPararSoapClient.php`

**Methods:**
- `getSoapClient()` - Main WSDL (lazy-loaded)
- `getSoapExtratoClient()` - Extrato WSDL (lazy-loaded)
- `autenticarUsuario()` - Get token (cached 1h)

**SOAP Options:**
- TLS 1.2/1.3
- 60s timeout
- Exceptions enabled
- Trace enabled

### MapService (Unified Abstraction)

**Path:** `app/Services/Map/MapService.php`

**Providers:**
- `OsrmProvider` - OSRM routing (FREE)
- `GoogleMapsProvider` - Geocoding only (routing deprecated)

**Methods:**
- `calculateRoute($waypoints, $options)` - Auto provider
- `geocodeBatch($municipalities, $options)` - Batch geocoding
- `clusterPoints($points, $options)` - Proximity clustering
- `getCacheStats()` - Cache statistics

**Utilities:**
- `CoordinateConverter` - Transformations
- `DistanceCalculator` - Haversine distance
- `CacheManager` - Unified cache

---

## 🎨 Frontend Modules

### Transport Management

#### Transportes (Transporters)
**Path:** `resources/ts/pages/transportes/index.vue`

**API Endpoints:**
- `GET /api/transportes` - Paginated list
- `GET /api/transportes/statistics` - Stats

**Features:**
- Multi-filter search (code, name, type, status)
- Tri-state filters (All/Autônomo/Empresa)
- VDataTableServer (server-side pagination)

#### Pacotes (Packages)
**Path:** `resources/ts/pages/pacotes/index.vue`

**API Endpoints:**
- `GET /api/pacotes` - List with filters
- `GET /api/pacotes/statistics` - Stats
- `GET /api/pacotes/autocomplete` - Search

**Features:**
- Date range filters
- Status filters
- "Apenas recentes" toggle (codpac > 800000)
- TCD badge display

---

### Route Management (COMPLEX!)

#### Rotas Padrão - Index
**Path:** `resources/ts/pages/rotas-padrao/index.vue`

**API:** `GET /api/semparar-rotas`

**Features:**
- Tri-state filters (Type: All/CD/Route, Return: All/Yes/No)
- Statistics cards
- Actions: View map, Edit, Delete

#### Rotas Padrão - Interactive Map (CRITICAL MODULE!)
**Path:** `resources/ts/pages/rotas-padrao/mapa/[id].vue`

**API Endpoints:**
- `GET /api/semparar-rotas/{id}/municipios` - Load route
- `PUT /api/semparar-rotas/{id}` - Save metadata
- `PUT /api/semparar-rotas/{id}/municipios` - Save municipalities
- `POST /api/geocoding/lote` - Batch geocoding
- `POST /api/routing/route` - OSRM routing (segmented)
- `POST /api/pacotes/itinerario` - Load package deliveries
- `GET /api/pacotes/autocomplete` - Package search

**Technologies:**
- **Leaflet** - Map rendering
- **OpenStreetMap** - Free tiles
- **OSRM** - Free routing (via Laravel proxy!)
- **vuedraggable** - Drag & drop

**Features:**
1. **Route Display:**
   - Custom numbered markers (`L.divIcon`)
   - Polyline routing (OSRM via proxy)
   - Popups with municipality info

2. **Editing:**
   - Drag & drop to reorder
   - Add/remove municipalities
   - Save changes to Progress

3. **Package Simulation:**
   - Load real package itinerary
   - Overlay deliveries on map
   - Combined routing (route + deliveries)
   - Color-coded markers:
     - Blue: Route municipalities
     - Green: First delivery
     - Orange: Middle deliveries
     - Red: Last delivery

4. **Debug System:**
   - Toggle debug panel (button in header)
   - Logs & metrics
   - Geocoding stats
   - Map update tracking

**Routing Implementation (CRITICAL!):**
```typescript
// Segment-by-segment to avoid OSRM limits
const allCoordinates = []
for (let i = 0; i < waypoints.length - 1; i++) {
  const response = await fetch('/api/routing/route', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      start: [waypoints[i].lng, waypoints[i].lat],  // [lng, lat]
      end: [waypoints[i+1].lng, waypoints[i+1].lat]
    })
  })

  const data = await response.json()

  if (data.success) {
    allCoordinates.push(...data.coordinates)  // [[lat, lng], ...]
  } else {
    // Fallback: dashed line
    L.polyline(
      [[waypoints[i].lat, waypoints[i].lng], [waypoints[i+1].lat, waypoints[i+1].lng]],
      {color: '#999', dashArray: '10, 10', opacity: 0.5}
    ).addTo(map)
  }
}

// Draw final polyline
L.polyline(allCoordinates, {color: '#E91E63', weight: 4}).addTo(map)
```

**Composables:** `resources/ts/composables/usePackageSimulation.ts`

**Known Issues:**
- OSRM public servers can have downtime (fallback implemented)
- ~25-50 waypoint limit per route (public server limit)

---

### Trip Purchase System

#### Compra Viagem - Index (Trip List)
**Path:** `resources/ts/pages/compra-viagem/index.vue`

**API:** `POST /api/compra-viagem/viagens`

**Features:**
- Trip history
- Status badges
- Receipt download

#### Compra Viagem - Nova (Purchase Wizard)
**Path:** `resources/ts/pages/compra-viagem/nova.vue`

**Workflow (5 Steps):**

**Step 1 - Package:**
- API: `POST /api/compra-viagem/validar-pacote`
- Validate package, check TCD status, load transporter

**Step 2 - Vehicle:**
- API: `POST /api/compra-viagem/validar-placa`
- Select vehicle, verify SemParar registration

**Step 3 - Route:**
- API: `GET /api/compra-viagem/rotas` + `POST /api/compra-viagem/validar-rota`
- Choose suggested route or select manually

**Step 4 - Price:**
- API: `POST /api/compra-viagem/verificar-preco`
- Set date range, verify cost via SemParar SOAP

**Step 5 - Confirmation:**
- API: `POST /api/compra-viagem/comprar`
- Review all data, confirm purchase, save to Progress

**Components:**
- `CompraViagemStep1Pacote.vue`
- `CompraViagemStep2Placa.vue`
- `CompraViagemStep3Rota.vue`
- `CompraViagemStep4Preco.vue`
- `CompraViagemStep5Confirmacao.vue`
- `CompraViagemMapaFixo.vue` - Static map preview

**Safety:**
- `ALLOW_SOAP_PURCHASE` flag (default: false)
- Double confirmation modal
- Test mode warnings

---

### Other Modules

#### Vale Pedágio (Toll Pass Calculator)
**Path:** `resources/ts/pages/vale-pedagio/index.vue`

**API:**
- `GET /api/pracas-pedagio`
- `POST /api/pracas-pedagio/proximidade`

**Features:**
- Calculate toll costs
- Filter by highway, state
- Proximity search

#### Praças Pedágio (Toll Plaza Management)
**Path:** `resources/ts/pages/pracas-pedagio/index.vue`

**API:**
- `GET /api/pracas-pedagio` - List
- `POST /api/pracas-pedagio/importar` - Import CSV
- `DELETE /api/pracas-pedagio/limpar` - Clear all
- `GET /api/pracas-pedagio/estatisticas` - Stats

**Features:**
- CRUD operations
- ANTT CSV import
- Map preview
- Proximity search

---

## 💾 Database Architecture

### Progress OpenEdge (Main Database)

**Connection:** JDBC via Java connector
**Host:** 192.168.80.113
**Database:** tambasa

**Characteristics:**
- ⚠️ **NO TRANSACTIONS** - JDBC driver doesn't support `beginTransaction()/commit()/rollBack()`
- ⚠️ **NO OFFSET** - Must use keyset/cursor pagination
- ⚠️ **Case-sensitive** - Table/column names
- ⚠️ **Single-line SQL preferred** - Multi-line queries cause issues
- ✅ **Schema prefix required** - Always `PUB.tablename`

**Tables (21 tables):**

**Transport:**
- `PUB.transporte` - Transporters (codtrn, nomtrn, flgautonomo, codcnpjcpf, numpla, flgati)
- `PUB.trnmot` - Transporter-Driver relationship

**Packages:**
- `PUB.pacote` - Packages (codpac, codtrn, codmot, sitpac, datforpac, codrot)
- `PUB.paccd` - TCD Packages (codpaccd)
- `PUB.pacsoc` - Package associations
- `PUB.carga` - Loads (codcar, codpac)
- `PUB.pedido` - Deliveries (numseqped, codcar, codcli, **gps_lat, gps_lon**)

**Routes:**
- `PUB.introt` - Routes (codrot, desrot)
- `PUB.semPararRot` - SemParar Routes (sPararRotID, desSPararRot, flgCD, flgRetorno, tempoViagem)
- `PUB.semPararRotMu` - Route Municipalities (sPararRotID, sPararMuSeq, codMun, codEst, cdibge)
- `PUB.semPararRotMuLog` - Municipality logs
- `PUB.semPararIntrot` - Route integrations
- `PUB.semPararStatus` - Status tracking

**Geographic:**
- `PUB.municipio` - Cities (codmun, desmun, **cdibge**)
- `PUB.estado` - States (codest, nomest, siglaest)

**SemParar:**
- `PUB.sPararViagem` - Purchased trips (codviagem, codpac, numpla, nomrotsemparar, valviagem, datacompra, flgcancelado)

**Other:**
- `PUB.cliente` - Customers
- `PUB.bairro` - Neighborhoods
- `PUB.arqrdnt` - Archives
- `PUB.caixafech` - Cash close
- `PUB.cxapacote` - Cash package

### SQLite/MySQL (Laravel Tables)

**Migrations (11 tables):**
- `users` - Laravel users + Sanctum
- `cache` - Application cache
- `jobs` - Queue jobs
- `motoristas` - Drivers (local cache)
- `personal_access_tokens` - Sanctum tokens
- `municipio_coordenadas` - **Geocoding cache (PERSISTENT, no expiration)**
- `progress_municipios_gps` - Progress-compatible GPS cache
- `pracas_pedagio` - ANTT toll plaza data
- `route_segments` - Route segment cache (30 days, DEPRECATED)
- `route_cache` - Route cache (DEPRECATED)

---

## 🔐 Authentication & Security

### Authentication Flow

**System:** Laravel Sanctum (token-based)

```
1. POST /api/auth/login {email, password}
2. Server validates credentials
3. Returns: {accessToken, userData, userAbilityRules}
4. Client stores token in localStorage
5. Client sends: Authorization: Bearer {token}
```

**Protected Endpoints:**
- `POST /api/auth/logout`
- `GET /api/auth/user`
- `POST /api/transportes/query` (admin-only)

**Most endpoints are public** (for now)

### Role-Based Access Control (RBAC)

**System:** Two-tier role system with ENUM validation

**Available Roles:**
- `admin` - Full system access + custom SQL queries
- `user` - Standard access (default for new registrations)

**User Management:**
```bash
# Default users (after seeding)
admin@ndd.com / Admin@123  # Role: admin
user@ndd.com  / User@123   # Role: user

# Run seeder
php artisan db:seed --class=DefaultUserSeeder
```

**Role Validation Layers:**

1. **Database Level** (SQLite/MySQL):
   ```sql
   -- ENUM validation enforced at schema level
   ALTER TABLE users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'user';

   -- Index for performance
   CREATE INDEX users_role_index ON users(role);
   ```

2. **Model Level** (app/Models/User.php):
   ```php
   // Mutator validates before save
   public function setRoleAttribute($value): void {
       $validRoles = ['admin', 'user'];
       if (!in_array($value, $validRoles, true)) {
           throw new \InvalidArgumentException("Role inválido: '$value'");
       }
       $this->attributes['role'] = $value;
   }
   ```

3. **Controller Level** (app/Http/Controllers/Api/AuthController.php):
   ```php
   // Login: Validate role integrity (NO fallback)
   if (!$user->role || !in_array($user->role, ['admin', 'user'], true)) {
       \Log::error('Usuário com role inválido detectado', [...]);
       return response()->json([
           'success' => false,
           'message' => 'Erro de integridade de dados do usuário.'
       ], 500);
   }

   // Register: Explicit role assignment
   User::create([
       'name' => $request->name,
       'email' => $request->email,
       'password' => Hash::make($request->password),
       'role' => 'user',  // Always 'user' for new registrations
   ]);
   ```

**Password Requirements:**
- Minimum 8 characters
- At least one lowercase letter (a-z)
- At least one uppercase letter (A-Z)
- At least one number (0-9)
- At least one special character (@$!%*#?&)

**Frontend Integration:**
```typescript
// resources/ts/utils/api.ts
// 401 Handler with redirect guard
if (response.status === 401) {
  // Clear cookies
  accessTokenCookie.value = null
  userDataCookie.value = null
  userAbilityRulesCookie.value = null

  // Redirect to login (with guard to prevent infinite loop)
  if (!window.location.pathname.includes('/login')) {
    window.location.href = '/login'
  }
}
```

**⚠️ CRITICAL:**
- ❌ NEVER use silent fallbacks like `$user->role ?? 'user'`
- ✅ ALWAYS validate role integrity and log errors
- ✅ ALWAYS throw exceptions for invalid roles at model level
- ✅ New users ALWAYS get 'user' role (never 'admin')
- ✅ Admin role must be assigned manually via seeder or tinker

### Rate Limiting Tiers

```
Test/Debug:              10 req/min
Expensive Ops:           10 req/min  (stats, schema)
Admin Ops:               5 req/min   (custom SQL)
Standard CRUD:           60 req/min
Map Operations:          100 req/min (route), 60 req/min (geocode)

SemParar SOAP:
  Queries:               60 req/min
  Routing:               20 req/min
  Purchase:              10 req/min  (CRITICAL!)
  Management:            20 req/min  (cancel/reissue)

Toll Plaza:
  List:                  60 req/min
  Import:                5 req/min
  Clear:                 2 req/min   (CRITICAL!)
```

### Security Measures

**SQL Injection:**
- `escapeSqlString()` in ProgressService
- Regex validation for search terms
- Integer casting for IDs
- Whitelist for enum values

**SOAP Security:**
- Token caching (1 hour)
- TLS 1.2/1.3 enforcement
- Timeout controls (60s)
- Retry limits

**File Upload:**
- CSV only (10MB max)
- MIME type validation
- Temporary file cleanup

---

## 💡 Critical Implementation Patterns

### 1. Progress JDBC Transactions

```php
// ❌ NEVER DO THIS
try {
    DB::connection('progress')->beginTransaction();
    $this->executeUpdate("UPDATE PUB.semPararRot SET tempoViagem = 5 WHERE sPararRotID = 204");
    $this->executeUpdate("INSERT INTO PUB.semPararRotMu VALUES (...)");
    DB::connection('progress')->commit();
} catch (\Exception $e) {
    DB::connection('progress')->rollBack();  // DOESN'T WORK!
}

// ✅ ALWAYS DO THIS
try {
    // Execute sequentially, handle errors manually
    $this->executeUpdate("UPDATE PUB.semPararRot SET tempoViagem = 5 WHERE sPararRotID = 204");
    $this->executeUpdate("INSERT INTO PUB.semPararRotMu VALUES (...)");
} catch (\Exception $e) {
    Log::error('Progress update failed', ['error' => $e->getMessage()]);
    // Manual compensating actions if needed
}
```

**Why:** Progress JDBC driver doesn't implement `PDO::beginTransaction()`, `commit()`, or `rollBack()`. Any attempt will fail.

**Workarounds:**
- Validate ALL data before any UPDATE/INSERT/DELETE
- Log all operations extensively
- Consider using Progress ABL procedures for critical multi-step operations

### 2. OSRM Routing Proxy

```typescript
// ❌ NEVER use leaflet-routing-machine directly
import L from 'leaflet'
import 'leaflet-routing-machine'

const osrmRouter = L.Routing.osrmv1({
  serviceUrl: 'https://routing.openstreetmap.de/routed-car/route/v1'
})

L.Routing.control({
  router: osrmRouter,
  waypoints: [
    L.latLng(-23.5505, -46.6333),
    L.latLng(-22.9068, -43.1729)
  ]
}).addTo(map)
// ❌ FAILS with CORS errors or timeouts!

// ✅ ALWAYS use Laravel proxy (segment-by-segment)
async function drawRoute(waypoints) {
  const allCoordinates = []

  for (let i = 0; i < waypoints.length - 1; i++) {
    try {
      const response = await fetch('http://localhost:8002/api/routing/route', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          start: [waypoints[i].lng, waypoints[i].lat],
          end: [waypoints[i+1].lng, waypoints[i+1].lat]
        })
      })

      const data = await response.json()

      if (data.success) {
        allCoordinates.push(...data.coordinates)
      } else {
        // Fallback: straight dashed line
        allCoordinates.push(
          [waypoints[i].lat, waypoints[i].lng],
          [waypoints[i+1].lat, waypoints[i+1].lng]
        )
      }
    } catch (error) {
      console.error('Routing failed:', error)
      // Fallback
      allCoordinates.push(
        [waypoints[i].lat, waypoints[i].lng],
        [waypoints[i+1].lat, waypoints[i+1].lng]
      )
    }
  }

  // Draw polyline
  L.polyline(allCoordinates, {
    color: '#E91E63',
    weight: 4,
    opacity: 0.8
  }).addTo(map)
}
```

**Why:** Public OSRM servers block direct browser requests (CORS) and have rate limits. Laravel proxy:
- Tries 3 different OSRM servers
- Implements retry logic (2 retries/server, 15s timeout)
- Handles CORS properly
- Provides fallback mechanisms

**Reference:** `resources/ts/pages/rotas-padrao/mapa/[id].vue:449-610`

### 3. SemParar SOAP Parameters

```php
// ❌ WRONG - Named parameters cause "Array to string conversion"
$response = $this->soapClient->autenticarUsuario([
    'cnpj' => $cnpj,
    'login' => $user,
    'senha' => $password
]);
// PHP Fatal error: Uncaught Error: Array to string conversion

// ✅ CORRECT - Positional parameters
$response = $this->soapClient->autenticarUsuario($cnpj, $user, $password);
// Returns: stdClass { sessao: "3642419762017373443", status: 0 }

// ❌ WRONG - XML as string (sends EMPTY XML in SOAP body!)
$pontosXml = '<PontosParada><PontoParada>...</PontoParada></PontosParada>';
$opcoesXml = '<OpcoesRota><alternativas>false</alternativas></OpcoesRota>';
$response = $this->soapClient->roteirizarPracasPedagio($pontosXml, $opcoesXml, $token);
// SOAP request contains empty <pontos/> and <opcoes/>!

// ✅ CORRECT - Use SoapVar with XSD_ANYXML
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);
$response = $this->soapClient->roteirizarPracasPedagio($pontosParam, $opcoesParam, $token);
// SOAP request contains full XML content
```

**Why:**
- PHP SoapClient expects positional params for Progress SOAP services
- XML parameters need `SoapVar` wrapper to prevent HTML encoding
- These patterns are battle-tested against actual SemParar SOAP API

**Documentation:** `CHECKPOINT_FASE_1A.md`, `SEMPARAR_FASE1B_COMPLETO.md`

### 4. Geocoding Cache Strategy

```php
public function getCoordenadasByIbge($codigoIbge, $nomeMunicipio, $uf) {
    // 1. Check persistent cache (SQLite)
    $cached = MunicipioCoordenada::where('cdibge', $codigoIbge)->first();

    if ($cached) {
        Log::info('Geocoding cache HIT', ['cdibge' => $codigoIbge]);
        return [
            'lat' => $cached->latitude,
            'lon' => $cached->longitude,
            'fonte' => $cached->fonte,
            'cached' => true
        ];
    }

    // 2. Cache MISS - Call Google Geocoding API
    Log::info('Geocoding cache MISS', ['cdibge' => $codigoIbge]);
    $coordenadas = $this->geocodeByGoogle($nomeMunicipio, $uf);

    if (!$coordenadas) {
        return null;
    }

    // 3. Save to BOTH caches (SQLite + Progress-compatible)
    MunicipioCoordenada::create([
        'cdibge' => $codigoIbge,
        'latitude' => $coordenadas['lat'],
        'longitude' => $coordenadas['lon'],
        'fonte' => 'google_geocoding',
        'nome_municipio' => $nomeMunicipio,
        'uf' => $uf
    ]);

    ProgressMunicipioGps::updateOrCreate(
        ['cod_mun' => $codMun, 'cod_est' => $codEst],
        ['gps_lat' => $coordenadas['lat'], 'gps_lon' => $coordenadas['lon']]
    );

    // 4. Rate limiting (200ms delay between API calls)
    usleep(200000);

    return [
        'lat' => $coordenadas['lat'],
        'lon' => $coordenadas['lon'],
        'fonte' => 'google_geocoding',
        'cached' => false
    ];
}
```

**Cache Performance:**
- First run: 100% API calls
- After first run: 80%+ cache hits
- No expiration (geographic data doesn't change)
- Dual cache: SQLite (fast) + Progress-compatible (legacy)

### 5. GPS Coordinate Processing

```typescript
// Progress stores GPS as INTEGER (lat/lon * 10^7)
// Example: -23.5505° → -235505000 (stored as string "230505000" with - implied)

function processGpsCoordinate(gpsString: string): number | null {
  if (!gpsString || gpsString === '0') return null

  // Parse as integer
  const value = parseInt(gpsString, 10)
  if (isNaN(value)) return null

  // Determine sign (if starts with 2 or 3, it's negative for latitude)
  // Brazil coordinates: lat -5 to -35, lon -35 to -75
  const isNegative = (gpsString[0] === '2' || gpsString[0] === '3')

  // Convert from integer to decimal
  const decimal = Math.abs(value) / 10_000_000

  return isNegative ? -decimal : decimal
}

// Usage in itinerary processing
const entregas = itinerarioData.pedidos
  .map(pedido => ({
    razcli: pedido.razcli,
    lat: processGpsCoordinate(pedido.gps_lat),
    lon: processGpsCoordinate(pedido.gps_lon)
  }))
  .filter(entrega => entrega.lat !== null && entrega.lon !== null)

// Examples:
// "230876543" → -23.0876543
// "460123456" → -46.0123456
```

### 6. Drag & Drop Municipality Reordering

```vue
<template>
  <draggable
    v-model="municipios"
    @end="onDragEnd"
    handle=".drag-handle"
    item-key="sPararMuSeq"
  >
    <template #item="{ element, index }">
      <div class="municipio-item">
        <v-icon class="drag-handle">mdi-drag</v-icon>
        <span>{{ index + 1 }}. {{ element.desMun }} - {{ element.desEst }}</span>
      </div>
    </template>
  </draggable>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import draggable from 'vuedraggable'

const municipios = ref([...])

async function onDragEnd() {
  // Update sequence numbers
  municipios.value = municipios.value.map((m, index) => ({
    ...m,
    sPararMuSeq: index + 1
  }))

  // Save to backend
  await fetch(`/api/semparar-rotas/${rotaId}/municipios`, {
    method: 'PUT',
    body: JSON.stringify({ municipios: municipios.value })
  })
}
</script>
```

**Backend Handler (ProgressService):**
```php
public function updateSemPararRotaMunicipios($id, array $municipios): bool
{
    try {
        // ⚠️ CRITICAL: DELETE + INSERT without transaction!
        // Validate EVERYTHING before DELETE

        // 1. Validate all municipalities exist
        foreach ($municipios as $mun) {
            if (!$mun['codMun'] || !$mun['codEst']) {
                throw new \Exception("Invalid municipality data");
            }
        }

        // 2. Delete existing municipalities
        $deleteSql = "DELETE FROM PUB.semPararRotMu WHERE sPararRotID = {$id}";
        $this->executeUpdate($deleteSql);

        // 3. Insert new municipalities with sequence
        foreach ($municipios as $index => $mun) {
            $seq = $index + 1;
            $insertSql = "INSERT INTO PUB.semPararRotMu (sPararRotID, sPararMuSeq, codMun, codEst, desMun, desEst, cdibge) VALUES ({$id}, {$seq}, {$mun['codMun']}, {$mun['codEst']}, '{$mun['desMun']}', '{$mun['desEst']}', {$mun['cdibge']})";
            $this->executeUpdate($insertSql);
        }

        return true;
    } catch (\Exception $e) {
        Log::error('Failed to update municipalities', [
            'rota_id' => $id,
            'error' => $e->getMessage()
        ]);

        // ⚠️ Data may be lost if INSERT fails after DELETE!
        // No way to rollback with Progress JDBC
        return false;
    }
}
```

**Known Issue:** If any INSERT fails after DELETE, municipalities are lost. No transactions available.

**Mitigation:**
- Extensive validation before DELETE
- Detailed error logging
- Consider implementing backup/restore mechanism

---

## 🐛 Troubleshooting

### Progress Connection Issues

```bash
# 1. Test JDBC connection
curl http://localhost:8002/api/progress/test-connection

# Expected response:
# {"success": true, "message": "Conexão bem-sucedida!", ...}

# 2. Check Java is installed
java -version
# Expected: Java Runtime Environment (build 1.8.0_xxx or higher)

# 3. Check Progress driver exists
dir "c:\Progress\OpenEdge\java\openedge.jar"
# OR on Linux/Mac:
ls -la /path/to/Progress/OpenEdge/java/openedge.jar

# 4. Test Java connector manually
cd storage/app/java
java -cp "c:/Progress/OpenEdge/java/openedge.jar;gson-2.8.9.jar;." ProgressJDBCConnector query "SELECT TOP 1 * FROM PUB.transporte"

# 5. View Laravel logs
php artisan pail
# OR
tail -f storage/logs/laravel.log
```

**Common Errors:**

**"Class not found" or "No driver found":**
- Check `PROGRESS_HOST` in `.env`
- Verify Progress driver path in `config/database.php`
- Ensure Java connector is compiled: `javac -cp ... ProgressJDBCConnector.java`

**"Connection refused":**
- Check Progress server is running
- Verify firewall allows connection to port 2574
- Test with telnet: `telnet 192.168.80.113 2574`

**"SQL syntax error":**
- Ensure single-line SQL (no newlines)
- Check case sensitivity (codtrn, NOT CodTrn)
- Always use `PUB.` schema prefix

### Frontend Issues

```bash
# TypeScript errors
pnpm run typecheck
# Fix errors before committing

# Linting issues
pnpm run lint
# Auto-fix most issues

# Clear Vite cache
rm -rf node_modules/.vite
pnpm run dev

# Frontend shows 404 for new route
rm -rf resources/ts/.temp
pnpm run dev

# Hot reload not working
# Check vite.config.ts port settings
# Restart both servers
```

**Common Errors:**

**"Cannot find module" in Vue:**
- Run `pnpm install`
- Check import paths (use `@/` for `resources/ts/`)
- Clear Vite cache

**"Unexpected token" in browser:**
- Check TypeScript compilation: `pnpm run typecheck`
- Ensure Vite is running: `pnpm run dev`

**"CORS error" when calling API:**
- ALWAYS use `http://localhost:8002` (NOT port 5173/4/6!)
- Check `config/cors.php` settings

### SOAP Errors

```bash
# 1. Test SemParar connection
curl http://localhost:8002/api/semparar/test-connection

# Expected:
# {"success": true, "token_length": 19, ...}

# 2. View cached token (debug)
curl http://localhost:8002/api/semparar/debug/token

# 3. Clear token cache (force re-auth)
curl -X POST http://localhost:8002/api/semparar/debug/clear-cache

# 4. View SOAP traces
tail -f storage/logs/laravel.log | grep -i soap
```

**Common SOAP Errors:**

**"Array to string conversion":**
- Using named params → Switch to positional params
- See [Critical Rule #3](#3-semparar-soap---positional-parameters--soapvar)

**"Empty XML in SOAP request":**
- Sending XML as string → Wrap with `SoapVar($xml, XSD_ANYXML)`
- See [Critical Rule #3](#3-semparar-soap---positional-parameters--soapvar)

**"Session expired" or "Invalid token":**
- Token cache expired → Will auto-refresh on next call
- Force refresh: `curl -X POST http://localhost:8002/api/semparar/debug/clear-cache`

**"consultarViagens returns empty":**
- Using wrong WSDL → Use `vpextrato` WSDL for trip queries
- Check `SemPararSoapClient::getSoapExtratoClient()`

### Map Issues

```bash
# 1. Test OSRM routing proxy
curl -X POST http://localhost:8002/api/routing/route \
  -H "Content-Type: application/json" \
  -d '{"start":[-46.63,-23.55],"end":[-43.17,-22.91]}'

# Expected:
# {"success": true, "coordinates": [[...]], "distance_km": 356.7}

# 2. Test geocoding
curl -X POST http://localhost:8002/api/geocoding/ibge \
  -H "Content-Type: application/json" \
  -d '{"codigo_ibge":"3550308","nome_municipio":"SÃO PAULO","uf":"SP"}'

# Expected:
# {"success": true, "data": {"lat": -23.5505, "lon": -46.6333, "cached": ...}}
```

**Common Map Errors:**

**"Map not loading / blank screen":**
1. Check OSRM proxy: `curl http://localhost:8002/api/routing/test`
2. Open browser console for JavaScript errors
3. Verify coordinates are valid: lat -90 to 90, lon -180 to 180
4. Check Leaflet CSS is loaded: `import 'leaflet/dist/leaflet.css'`

**"Routing failed / straight lines instead of roads":**
- OSRM public servers down → Fallback to dashed lines (expected behavior)
- Too many waypoints (>50) → Split route into segments
- Check network tab for 503/429 errors from OSRM

**"Markers not showing":**
- Leaflet default marker images missing → Use custom markers with `L.divIcon()`
- Check z-index CSS conflicts

**"CORS error from OSRM":**
- Using leaflet-routing-machine directly → ALWAYS use Laravel proxy!
- See [Critical Rule #2](#2-osrm-routing---always-use-laravel-proxy)

### Performance Issues

```bash
# 1. Check database query performance
# Enable query logging in .env:
DB_LOG_QUERIES=true

# 2. Monitor slow queries
tail -f storage/logs/laravel.log | grep -i "slow query"

# 3. Check cache hit rates
curl http://localhost:8002/api/map/cache-stats

# Expected:
# {"geocoding_hit_rate": "82%", "route_hit_rate": "45%"}

# 4. Clear expired caches
curl -X POST http://localhost:8002/api/map/clear-expired-cache
```

**Common Performance Issues:**

**"Progress queries very slow":**
- Check query uses TOP instead of LIMIT
- Verify indexes exist on PUB tables
- Use keyset pagination (NOT offset-based)
- Avoid SELECT * (specify columns)

**"Geocoding slow":**
- First run is slow (no cache) → Expected
- Subsequent runs should hit cache (80%+)
- Check `municipio_coordenadas` table has data

**"Map rendering slow":**
- Too many markers (>100) → Implement clustering
- Too many polyline points (>5000) → Simplify route
- Check network tab for slow API calls

### Development Workflow Issues

```bash
# "Class not found" after adding new service
composer dump-autoload

# "Route not found" after adding API endpoint
php artisan route:clear
php artisan route:cache

# "Config cached" preventing .env changes
php artisan config:clear

# "Permission denied" on storage/logs
chmod -R 775 storage
chown -R www-data:www-data storage  # Linux
# OR on Windows: Check folder permissions

# Git merge conflicts in auto-generated files
# These files can be safely regenerated:
git checkout --theirs auto-imports.d.ts
git checkout --theirs typed-router.d.ts
git checkout --theirs components.d.ts
pnpm run dev  # Regenerates files
```

### Port Conflicts

```bash
# Check what's using port 8002
netstat -ano | findstr :8002
# OR on Linux/Mac:
lsof -i :8002

# Kill process by PID (Windows)
taskkill /PID [PID] /F

# Kill process (Linux/Mac)
kill -9 [PID]

# Check Vite port (5173/5174/5176)
netstat -ano | findstr :5173
```

---

## 📁 Project Structure

```
ndd-vuexy/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php              # Authentication
│   │   ├── TransporteController.php        # Transporters (PRIMARY)
│   │   ├── PacoteController.php            # Packages
│   │   ├── MotoristaController.php         # Drivers
│   │   ├── RotaController.php              # Routes autocomplete
│   │   ├── SemPararRotaController.php      # SemParar routes CRUD
│   │   ├── SemPararController.php          # SemParar SOAP API
│   │   ├── CompraViagemController.php      # Trip purchase wizard
│   │   ├── GeocodingController.php         # IBGE → lat/lon
│   │   ├── RoutingController.php           # OSRM routing proxy (CRITICAL!)
│   │   ├── MapController.php               # Unified map service
│   │   ├── PracaPedagioController.php      # Toll plazas
│   │   ├── GoogleMapsQuotaController.php   # API quota tracking
│   │   ├── ProgressController.php          # Raw Progress queries
│   │   ├── VpoController.php               # VPO sync & cache (NEW!)
│   │   ├── VpoEmissaoController.php        # VPO emission (NEW!)
│   │   └── NddCargoController.php          # NDD Cargo SOAP (NEW!)
│   └── Services/
│       ├── ProgressService.php             # Progress JDBC (2574 lines!)
│       ├── GeocodingService.php            # Google Geocoding + cache
│       ├── RoutingService.php.deprecated   # ❌ REMOVED (Google Directions API)
│       ├── Map/
│       │   ├── MapService.php              # Unified map orchestrator
│       │   ├── CacheManager.php            # Cache management
│       │   ├── Providers/
│       │   │   ├── OsrmProvider.php        # OSRM routing (FREE)
│       │   │   └── GoogleMapsProvider.php  # Google (geocoding only)
│       │   └── Utils/
│       │       ├── CoordinateConverter.php # Coordinate transforms
│       │       └── DistanceCalculator.php  # Haversine distance
│       ├── SemParar/
│       │   ├── SemPararService.php         # Business logic
│       │   ├── SemPararSoapClient.php      # Low-level SOAP
│       ├── Vpo/                            # VPO Services (NEW!)
│       │   ├── VpoDataSyncService.php      # Progress → ANTT → Cache sync
│       │   ├── VpoEmissaoService.php       # VPO emission business logic
│       │   └── MotoristaEmpresaCacheService.php # Driver cache for empresas
│       ├── NddCargo/                       # NDD Cargo SOAP (NEW!)
│       │   ├── NddCargoService.php         # Roteirizador integration
│       │   ├── NddCargoSoapClient.php      # CrossTalk SOAP client
│       │   └── DigitalSignature.php        # RSA-SHA1 XML signing
│       │   └── XmlBuilders/
│       │       └── PontosParadaBuilder.php # Progress dataset XML
│       └── PracaPedagioImportService.php   # ANTT CSV import
├── resources/ts/
│   ├── pages/
│   │   ├── transportes/                    # Transporters module
│   │   │   └── index.vue                   # List page
│   │   ├── pacotes/                        # Packages module
│   │   │   └── index.vue                   # List + filters
│   │   ├── vale-pedagio/                   # Toll calculator
│   │   │   └── index.vue
│   │   ├── pracas-pedagio/                 # Toll plaza management
│   │   │   └── index.vue
│   │   ├── rotas-padrao/                   # SemParar routes (COMPLEX!)
│   │   │   ├── index.vue                   # List view
│   │   │   ├── nova.vue                    # Create route
│   │   │   └── mapa/
│   │   │       └── [id].vue                # Interactive map (Leaflet + OSRM)
│   │   ├── compra-viagem/                  # Trip purchase wizard
│   │   │   ├── index.vue                   # Trip list
│   │   │   ├── nova.vue                    # Purchase wizard (5 steps)
│   │   │   └── components/
│   │   │       ├── CompraViagemStep1Pacote.vue
│   │   │       ├── CompraViagemStep2Placa.vue
│   │   │       ├── CompraViagemStep3Rota.vue
│   │   │       ├── CompraViagemStep4Preco.vue
│   │   │       ├── CompraViagemStep5Confirmacao.vue
│   │   │       └── CompraViagemMapaFixo.vue
│   │   ├── vpo-emissao/                    # VPO Emission Wizard (NEW!)
│   │   │   └── index.vue                   # VPO emission wizard
│   │   └── apps/                           # Vuexy template examples (REFERENCE!)
│   │       ├── user/list/index.vue         # List template
│   │       └── user/view/UserBioPanel.vue  # Form template
│   ├── composables/
│   │   └── usePackageSimulation.ts         # Package simulation logic
│   ├── @layouts/                           # Layout components
│   ├── navigation/vertical/ndd.ts          # Left sidebar menu
│   └── plugins/                            # Vue plugins (router, vuetify, etc)
├── routes/
│   ├── api.php                             # API routes (50+ endpoints)
│   ├── web.php                             # Web routes (Vue SPA)
│   └── console.php                         # Artisan commands
├── storage/app/java/
│   ├── ProgressJDBCConnector.java          # JDBC connector (auto-compiled)
│   └── gson-2.8.9.jar                      # JSON library
├── database/
│   └── migrations/                         # SQLite/MySQL migrations (NOT Progress!)
├── config/
│   ├── database.php                        # Database connections
│   ├── cors.php                            # CORS settings
│   └── services.php                        # External services (Google, SemParar)
├── public/
│   ├── test-semparar-fase1b.html           # SemParar workflow test
│   └── test-semparar-fase3a.html           # Trip management test
├── docs/                                   # Additional documentation
│   ├── audits/                             # Security audits (8 files)
│   ├── bug-fixes/                          # Bug fix documentation (15 files)
│   ├── security/                           # Security alerts
│   ├── analysis/                           # Technical analysis (6 files)
│   ├── summaries/                          # Progress reports (3 files)
│   ├── semparar-phases/                    # SemParar phase docs
│   ├── migrations/                         # Map migration docs
│   ├── modules/                            # Module-specific docs
│   └── archive/                            # Historical analysis
├── .env                                    # Environment config (NOT in repo!)
├── .env.example                            # Environment template
├── composer.json                           # PHP dependencies
├── package.json                            # Node dependencies
├── vite.config.ts                          # Vite build config
├── tsconfig.json                           # TypeScript config
└── CLAUDE.md                               # This file!
```

---

## 📚 Additional Documentation

**Main Docs:**
- `README.md` - Project overview
- `DOCUMENTATION_INDEX.md` - Complete documentation map (40+ files)
- `docs/INDEX.md` - Detailed docs structure and organization

**Security & Quality (Dec 2025):**
- `docs/audits/` - 8 controller security audits
- `docs/bug-fixes/` - 15 detailed bug fix documents
- `docs/security/ALERTA_SEGURANCA_CRITICO_2025-12-04.md` - Critical security alert
- `docs/summaries/RESUMO_CONSOLIDADO_FINAL_2025-12-05.md` - Complete audit summary
- `docs/analysis/` - 6 technical analysis documents

**SemParar Integration:**
- `docs/semparar-phases/CHECKPOINT_FASE_1A.md` - SOAP core
- `docs/semparar-phases/SEMPARAR_FASE1B_COMPLETO.md` - Routing
- `SEMPARAR_IMPLEMENTATION_ROADMAP.md` - Complete roadmap

**Map & Performance:**
- `docs/MAP_SERVICE_FASE1_COMPLETO.md` - MapService unified implementation
- `docs/CACHE_OPTIMIZATION_AND_BUG_FIXES.md` - Cache optimization (80-85% improvement)
- `docs/migrations/` - Google Maps → OSRM migration docs

**Architecture & Modules:**
- `docs/PROGRESS_INTEGRATIONS.md` - Progress OpenEdge integration patterns
- `docs/modules/IMPLEMENTACAO_COMPLETA.md` - Trip purchase complete implementation
- `docs/archive/` - Historical analysis and debugging docs

---

## 🚀 Next Steps / Roadmap

### FASE 3B - Frontend Integration
- Complete trip purchase wizard UI
- Receipt download/print interface
- Trip history dashboard
- WhatsApp/Email notification UI

### FASE 4 - Advanced Management
- Bulk trip operations
- Reporting & analytics
- Advanced filters
- Export to Excel/PDF

### Performance Improvements
- Self-hosted OSRM instance (Docker)
- Redis for route caching
- Database query optimization
- Frontend lazy loading

### Security Enhancements
- ✅ **COMPLETED (Dec 2025)**: Comprehensive security audit (81 bugs fixed)
- ✅ **COMPLETED (Dec 2025)**: LGPD compliance logging (22 locations)
- ✅ **COMPLETED (Dec 2025)**: Rate limiting (all endpoints)
- ✅ **COMPLETED (Dec 2025)**: SQL injection protection
- Role-based access control (RBAC) - Enhanced
- API key rotation
- Two-factor authentication (2FA)

---

## 🔗 Important Links

**Repository:**
- GitHub: https://github.com/Psykhepathos/ndd-vuexy.git
- Main Branch: `master`
- Current Branch: `feature/vpo-emissao-wizard` (VPO emission wizard)
- Developer: Psykhepathos

**Deprecated Systems:**
- ndd-laravel (old backend)
- ndd-flutter (old mobile app)

**Key URLs:**
- **Dashboard:** http://localhost:8002/ndd-dashboard
- **Transporters:** http://localhost:8002/transportes
- **Packages:** http://localhost:8002/pacotes
- **Toll Calculator:** http://localhost:8002/vale-pedagio
- **Routes (with map):** http://localhost:8002/rotas-padrao
- **Trip Purchase:** http://localhost:8002/compra-viagem

**Test Interfaces:**
- SemParar Workflow: http://localhost:8002/test-semparar-fase1b.html
- SemParar Management: http://localhost:8002/test-semparar-fase3a.html

**External Services:**
- Progress Database: 192.168.80.113:2574
- Python Flask (PDF): http://192.168.19.35:5001
- OSRM Public: https://router.project-osrm.org

---

## ⚙️ Environment Configuration

```env
# Application
APP_NAME="NDD Transport"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8002

# Database - Progress OpenEdge
PROGRESS_HOST=192.168.80.113
PROGRESS_PORT=2574
PROGRESS_DATABASE=tambasa
PROGRESS_USERNAME=sysprogress
PROGRESS_PASSWORD=sysprogress

# Database - Laravel (SQLite/MySQL)
DB_CONNECTION=sqlite
# OR for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ndd_laravel
# DB_USERNAME=root
# DB_PASSWORD=

# Google Maps API (only geocoding now)
GOOGLE_MAPS_API_KEY=your_api_key_here

# SemParar SOAP
SEMPARAR_CNPJ=your_cnpj
SEMPARAR_USER=your_user
SEMPARAR_PASSWORD=your_password

# Trip Purchase Safety
ALLOW_SOAP_QUERIES=true       # Enable validations/queries (safe)
ALLOW_SOAP_PURCHASE=false     # ⚠️ BLOCK REAL PURCHASES! (default: false)

# External Services
PYTHON_FLASK_URL=http://192.168.19.35:5001
Z_API_TOKEN=your_whatsapp_token
SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USERNAME=your_smtp_user
SMTP_PASSWORD=your_smtp_password
SMTP_FROM_ADDRESS=naoresponda@tambasa.com.br
SMTP_FROM_NAME="NDD Transport"

# API URLs
LARAVEL_API=http://localhost:8002
VUE_FRONTEND=http://localhost:5174
VITE_API_BASE_URL=http://localhost:8002
```

---

## 📊 System Statistics

**Backend:**
- 21 Controllers (18 original + 3 VPO/NDD Cargo)
- 14 Services (ProgressService: 2574 lines, VpoDataSyncService: 660 lines)
- 60+ API Endpoints
- 21 Progress Tables (JDBC, no transactions)
- 13 Laravel Tables (SQLite/MySQL, +2 VPO cache tables)

**Frontend:**
- 15+ Vue Pages
- 20+ Components
- TypeScript 5.8.3
- Vuetify 3.8.5
- Vuexy Template

**Database:**
- Progress OpenEdge (main)
- SQLite (cache, 80%+ hit rate)
- 6,913+ Transporters (4,913 autônomos + 2,000 empresas)
- 800,000+ Packages

**External APIs:**
- Google Geocoding (IBGE → coordinates)
- OSRM Public (free routing, 3 servers)
- SemParar SOAP (toll management, 2 WSDLs)
- NDD Cargo SOAP (CrossTalk + RSA-SHA1 signature)
- ANTT Open Data (CKAN API - RNTRC validation)
- Python Flask (PDF generation)

**Performance:**
- Map queries: <500ms avg
- Geocoding: 80%+ cache hit rate
- Routing: 100% free (OSRM)
- Zero Google Maps tile costs!

---

## 🎓 Learning Resources

**Laravel:**
- Official Docs: https://laravel.com/docs
- Sanctum: https://laravel.com/docs/sanctum

**Vue.js:**
- Vue 3 Guide: https://vuejs.org/guide/
- TypeScript: https://vuejs.org/guide/typescript/overview.html

**Vuexy Template:**
- Documentation: https://pixinvent.com/vuexy-vuejs-admin-template/
- **ALWAYS reference template examples!**

**Leaflet & Maps:**
- Leaflet Docs: https://leafletjs.com/reference.html
- OSRM API: http://project-osrm.org/docs/v5.24.0/api/

**Progress OpenEdge:**
- JDBC Driver: Progress documentation
- SQL Reference: Progress ABL documentation

---

**Last Updated:** 2025-12-09
**Version:** 2.1.0
**Maintainer:** Psykhepathos

---

**🚛 NDD Transport Management System - Complete Architecture Reference**

_This document is the definitive guide for development. All developers must read the [Critical Rules](#-critical-rules) section before coding._
