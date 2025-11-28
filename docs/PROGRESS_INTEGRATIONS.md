# Progress Database Integrations

## Overview

This document provides comprehensive mapping of all screens/pages that integrate with the Progress OpenEdge database, including their table relationships, data flow, and implementation details.

The NDD Vuexy system uses a **direct JDBC connection** to Progress OpenEdge database, bypassing traditional Laravel Eloquent ORM for Progress tables. The architecture follows this pattern:

```
Vue/Vuexy Frontend → REST API → Laravel Controllers → ProgressService → JDBC Connector → Progress Database
```

**Critical Design Decisions:**
- Progress tables use JDBC/raw SQL (NOT Eloquent)
- Laravel tables (cache, users) use Eloquent ORM
- No transactions supported on Progress JDBC
- Single-line SQL required for Progress queries
- Keyset pagination for large datasets

---

## Module 1: Transportes (Transporters Management)

### Frontend
**Page:** `resources/ts/pages/transportes/index.vue`

**Features:**
- Server-side pagination with keyset cursor support
- Advanced filtering (type, nature, status, search)
- Real-time statistics dashboard
- List view with detailed transporter information
- Drill-down to transporter details (drivers, vehicles)

**Key Components:**
- `VDataTableServer` - Server-side paginated table
- Keyset pagination (cursor-based) for efficient large dataset handling
- Debounced search (500ms) and filtered updates (300ms)
- Statistics cards showing totals, autonomous/companies breakdown

**API Calls:**
```javascript
// List transporters (paginated)
GET /api/transportes?page=1&per_page=10&search=joao&tipo=autonomo&status_ativo=true

// Get statistics
GET /api/transportes/statistics

// View details
GET /api/transportes/{id}
```

### Backend
**Controller:** `app/Http/Controllers/Api/TransporteController.php`

**Endpoints:**
| Endpoint | Method | Description | Rate Limit |
|----------|--------|-------------|------------|
| `/api/transportes` | GET | List transporters (paginated) | 60/min |
| `/api/transportes/{id}` | GET | Get specific transporter with relationships | 60/min |
| `/api/transportes/statistics` | GET | Aggregate statistics | 10/min |
| `/api/transportes/schema` | GET | Table schema inspection | 10/min |
| `/api/transportes/test-connection` | GET | JDBC connection test | 10/min |
| `/api/transportes/query` | POST | Custom SQL (admin-only) | 5/min |

**ProgressService Methods:**
- `getTransportesPaginated($filters)` - Main listing with keyset pagination
- `getTransporteById($id)` - Fetch single transporter
- `getMotoristasPorTransportador($id)` - Get drivers for transporter
- `getVeiculosPorTransportador($id)` - Get vehicles for transporter

### Progress Tables

**Main Table: PUB.transporte**
```sql
-- Structure inspection
SELECT * FROM PUB.transporte WHERE 1=0

-- Key columns:
codtrn        INTEGER      Primary key, transporter code
nomtrn        VARCHAR(60)  Transporter name
flgautonomo   BOOLEAN      1=Autonomous driver, 0=Company
natcam        CHAR(1)      Nature: T=Transport, A=Agregado
tipcam        INTEGER      Type code
codcnpjcpf    VARCHAR(20)  CNPJ/CPF document
numpla        VARCHAR(10)  Vehicle plate
numtel        INTEGER      Phone number
dddtel        INTEGER      Phone area code
flgati        BOOLEAN      Active status (1=active, 0=inactive)
indcd         CHAR(1)      CD indicator
```

**Related Tables:**
- `PUB.motorista` - Drivers linked to transporters (via codtrn foreign key)
- `PUB.veiculo` - Vehicles linked to transporters (via codtrn foreign key)

### Relationships

```
PUB.transporte (1) ──┐
                      ├──> PUB.motorista (N) - Drivers
                      └──> PUB.veiculo (N) - Vehicles

Foreign Key Pattern:
  transporte.codtrn = motorista.codtrn
  transporte.codtrn = veiculo.codtrn
```

### Data Flow

```
1. Frontend (index.vue)
   └─> Filters changed (tipo, natureza, status, search)
       └─> Debounced API call (300-500ms)

2. API Endpoint (TransporteController@index)
   └─> Validates filters (regex, bounds, allowed values)
       └─> Calls ProgressService

3. ProgressService (getTransportesPaginated)
   └─> Builds WHERE clause from filters
       └─> Keyset pagination SQL (codtrn > last_id)
           └─> executeCustomQuery(SQL)

4. JDBC Connector (ProgressJDBCConnector.java)
   └─> Opens Progress JDBC connection
       └─> Executes SQL
           └─> Returns JSON results

5. Response Flow (reverse direction)
   └─> ProgressService formats pagination metadata
       └─> Controller returns JSON response
           └─> Frontend updates table + statistics
```

### Sample Queries

**Basic listing (first page):**
```sql
SELECT TOP 10 codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd FROM PUB.transporte ORDER BY codtrn
```

**Filtered (autonomous, active only):**
```sql
SELECT TOP 10 codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd FROM PUB.transporte WHERE flgautonomo = 1 AND flgati = 1 ORDER BY codtrn
```

**Keyset pagination (next page after codtrn=5000):**
```sql
SELECT TOP 10 codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd FROM PUB.transporte WHERE codtrn > 5000 ORDER BY codtrn
```

**Statistics (single aggregated query):**
```sql
SELECT COUNT(*) as total, SUM(CASE WHEN flgautonomo = 1 THEN 1 ELSE 0 END) as autonomos, SUM(CASE WHEN flgautonomo = 0 THEN 1 ELSE 0 END) as empresas, SUM(CASE WHEN flgati = 1 THEN 1 ELSE 0 END) as ativos, SUM(CASE WHEN flgati = 0 THEN 1 ELSE 0 END) as inativos, SUM(CASE WHEN numpla IS NOT NULL AND numpla <> '' THEN 1 ELSE 0 END) as com_placa, SUM(CASE WHEN numtel IS NOT NULL AND numtel <> '' THEN 1 ELSE 0 END) as com_telefone FROM PUB.transporte
```

**Motoristas por transportador:**
```sql
SELECT codmot, nommot, cpfmot, dddcel, numcel, flgati FROM PUB.motorista WHERE codtrn = 12345 ORDER BY nommot
```

---

## Module 2: Pacotes (Package Tracking)

### Frontend
**Page:** `resources/ts/pages/pacotes/index.vue`

**Features:**
- Advanced multi-criteria filtering (search, code, transporter, route, status, date range)
- Autocomplete for transporters and routes
- Package itinerary viewer
- TCD (Troca CD) indicator
- Server-side pagination

**Key Components:**
- Multiple `VAutocomplete` for transporters/routes (200ms debounce)
- Date range filters (data_inicio, data_fim)
- Situacao filters (U=Urgent, M=Marked, S=Separating, A=Waiting, F=Finished)
- Custom pagination controls

**API Calls:**
```javascript
// List packages
GET /api/pacotes?page=1&per_page=15&codigo=3048790&rota=AC&situacao=U&apenas_recentes=1

// Autocomplete transporters
GET /api/transportes?search=joao&per_page=20

// Autocomplete routes
GET /api/rotas?search=AC

// Get itinerary
POST /api/pacotes/itinerario
Body: { "Pacote.codPac": 3048790 }
```

### Backend
**Controller:** `app/Http/Controllers/Api/PacoteController.php`

**Endpoints:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/pacotes` | GET | List packages (paginated with filters) |
| `/api/pacotes/{id}` | GET | Get specific package details |
| `/api/pacotes/itinerario` | POST | Get full itinerary with deliveries |
| `/api/pacotes/autocomplete` | GET | Package autocomplete search |
| `/api/pacotes/statistics` | GET | Package statistics |

**ProgressService Methods:**
- `getPacotesPaginated($filters)` - Main listing with complex filters
- `getPacoteById($id)` - Fetch single package
- `getItinerarioPacote($codPac)` - Complete itinerary with deliveries

### Progress Tables

**Main Table: PUB.pacote**
```sql
-- Structure:
codpac        INTEGER       Primary key, package code
datforpac     DATE          Formation date
horforpac     INTEGER       Formation time (HHMM format)
codtrn        INTEGER       Transporter code (FK → transporte)
codmot        INTEGER       Driver code (FK → motorista)
numpla        VARCHAR(10)   Vehicle plate
valpac        DECIMAL       Package value
volpac        DECIMAL       Package volume
pespac        DECIMAL       Package weight
sitpac        CHAR(1)       Status (U/M/S/A/F)
codrot        VARCHAR(10)   Route code (FK → introt)
nroped        INTEGER       Number of deliveries
flg_tcd       INTEGER       TCD flag (1=yes, 0=no)
```

**Related Tables:**
- `PUB.transporte` - Transporter information (via codtrn)
- `PUB.motorista` - Driver information (via codmot)
- `PUB.carga` - Loads related to package (via codpac)
- `PUB.pedido` - Delivery orders (via codcar from carga)
- `PUB.introt` - Route information (via codrot)

### Relationships

```
PUB.pacote (1)
    ├──> PUB.transporte (1) - Transporter info
    ├──> PUB.motorista (0..1) - Driver (if codmot > 0)
    ├──> PUB.introt (1) - Route
    └──> PUB.carga (N) - Loads
            └──> PUB.pedido (N) - Delivery orders
                    └──> PUB.cliente (1) - Customer

Foreign Key Pattern:
  pacote.codtrn = transporte.codtrn
  pacote.codmot = motorista.codmot
  pacote.codrot = introt.codrot
  pacote.codpac = carga.codpac
  carga.codcar = pedido.codcar
  pedido.codcli = cliente.codcli
```

### Data Flow

```
1. Frontend (index.vue)
   └─> Multiple filters + autocomplete selections
       └─> API call with all filter params

2. API Endpoint (PacoteController@index)
   └─> Validates 11+ filter parameters
       └─> Calls ProgressService

3. ProgressService (getPacotesPaginated)
   └─> Builds complex WHERE clause:
       - Search (codpac or nomtrn LIKE)
       - Codigo filter (codpac range)
       - Transportador (nomtrn LIKE)
       - Rota (codrot =)
       - Situacao (sitpac =)
       - Apenas recentes (codpac >= 800000)
       - Date range (datforpac BETWEEN)
   └─> Joins with transporte for nomtrn
   └─> executeCustomQuery(SQL)

4. For Itinerary (special flow):
   └─> Frontend calls POST /api/pacotes/itinerario
       └─> ProgressService.getItinerarioPacote()
           └─> Multiple queries:
               1. Get package details
               2. Get loads (carga)
               3. Get deliveries (pedido) with customers
               4. Assemble hierarchical structure
```

### Sample Queries

**Package listing with transporter:**
```sql
SELECT TOP 15 p.codpac, p.datforpac, p.horforpac, p.codtrn, p.codmot, p.numpla, p.valpac, p.volpac, p.pespac, p.sitpac, p.codrot, p.nroped, p.flg_tcd, t.nomtrn FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE p.codpac >= 800000 ORDER BY p.datforpac DESC, p.codpac DESC
```

**Package itinerary (multi-query):**
```sql
-- 1. Package info
SELECT * FROM PUB.pacote WHERE codpac = 3048790

-- 2. Loads
SELECT * FROM PUB.carga WHERE codpac = 3048790

-- 3. Deliveries with customer
SELECT ped.*, cli.nomcli FROM PUB.pedido ped LEFT JOIN PUB.cliente cli ON ped.codcli = cli.codcli WHERE ped.codcar IN (SELECT codcar FROM PUB.carga WHERE codpac = 3048790) ORDER BY ped.numseqped
```

**Autocomplete pacotes:**
```sql
SELECT TOP 20 p.codpac, p.codrot, p.datforpac, p.sitpac, p.nroped, t.nomtrn FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE p.codpac >= 3040000 AND p.codpac < 3050000 ORDER BY p.datforpac DESC, p.codpac DESC
```

---

## Module 3: Rotas SemParar (SemParar Routes)

### Frontend
**Pages:**
- `resources/ts/pages/rotas-semparar/index.vue` - List/CRUD interface
- `resources/ts/pages/rotas-semparar/mapa/[id].vue` - Interactive map viewer

**Features (List Page):**
- CRUD operations (Create, Read, Update, Delete)
- Three-state toggle filters (Tipo: all/cd/rota, Retorno: all/com/sem)
- Search by name or code
- Server-side pagination

**Features (Map Page):**
- Google Maps integration with route visualization
- Draggable municipality sequence editor
- Real-time geocoding with cache
- Route calculation with Google Directions API
- Debug panel with metrics and logs
- Package simulation overlay (GPS deliveries)

**Key Components:**
- `VDataTableServer` for route listing
- `draggable` (vuedraggable) for municipality ordering
- Google Maps JavaScript API
- Debug system with 4 log levels and 6 categories
- Geocoding queue with anti-concurrency lock

**API Calls:**
```javascript
// List routes
GET /api/semparar-rotas?page=1&per_page=10&flg_cd=true&flg_retorno=false

// Get route with municipalities
GET /api/semparar-rotas/{id}/municipios

// Create route
POST /api/semparar-rotas
Body: {
  nome: "Rota Teste",
  tempo_viagem: 5,
  flg_cd: true,
  flg_retorno: false,
  municipios: [...]
}

// Update route
PUT /api/semparar-rotas/{id}

// Delete route
DELETE /api/semparar-rotas/{id}

// Update municipalities
PUT /api/semparar-rotas/{id}/municipios
Body: { municipios: [...] }

// Autocomplete
GET /api/semparar-rotas/municipios?search=sao&estado_id=25
GET /api/semparar-rotas/estados
```

### Backend
**Controller:** `app/Http/Controllers/Api/SemPararRotaController.php`

**Endpoints:**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/semparar-rotas` | GET | List routes (paginated) |
| `/api/semparar-rotas` | POST | Create new route |
| `/api/semparar-rotas/{id}` | GET | Get specific route |
| `/api/semparar-rotas/{id}` | PUT | Update route |
| `/api/semparar-rotas/{id}` | DELETE | Delete route |
| `/api/semparar-rotas/{id}/municipios` | GET | Get route with municipalities |
| `/api/semparar-rotas/{id}/municipios` | PUT | Update municipalities sequence |
| `/api/semparar-rotas/municipios` | GET | Municipality autocomplete |
| `/api/semparar-rotas/estados` | GET | States list |

**ProgressService Methods:**
- `getSemPararRotas($filters)` - List routes with pagination
- `getSemPararRota($id)` - Get single route
- `getSemPararRotaWithMunicipios($id)` - Get route with municipalities
- `createSemPararRota($data)` - Create route (INSERT)
- `updateSemPararRota($id, $data)` - Update route (UPDATE)
- `deleteSemPararRota($id)` - Delete route (DELETE)
- `updateSemPararRotaMunicipios($id, $municipios)` - Update municipalities
- `getMunicipiosForAutocomplete($search, $estadoId)` - Autocomplete
- `getEstadosForAutocomplete()` - States list

### Progress Tables

**Main Table: PUB.semPararRot**
```sql
-- Structure:
sPararRotID    INTEGER       Primary key, route ID
desSPararRot   VARCHAR(60)   Route description/name
tempoViagem    INTEGER       Travel time in days (1-15)
flgCD          BOOLEAN       1=CD route, 0=Regular route
flgRetorno     BOOLEAN       1=Return route, 0=One-way
datAtu         DATE          Last update date
resAtu         VARCHAR(20)   Last update user
```

**Junction Table: PUB.semPararRotMu**
```sql
-- Structure:
sPararRotID    INTEGER       Route ID (FK → semPararRot)
codMun         INTEGER       Municipality code (FK → municipio)
codEst         INTEGER       State code (FK → estado)
ordMun         INTEGER       Sequence order (1, 2, 3...)

-- Composite Primary Key: (sPararRotID, ordMun)
```

**Reference Tables:**
- `PUB.municipio` - Municipality master data
- `PUB.estado` - State master data

### Relationships

```
PUB.semPararRot (1)
    └──> PUB.semPararRotMu (N) - Ordered municipalities
            ├──> PUB.municipio (1) - Municipality details
            └──> PUB.estado (1) - State details

Foreign Key Pattern:
  semPararRot.sPararRotID = semPararRotMu.sPararRotID
  semPararRotMu.codMun = municipio.codmun
  semPararRotMu.codEst = estado.codest
  municipio.codest = estado.codest (redundant FK)

Order Constraint:
  semPararRotMu.ordMun UNIQUE within same sPararRotID
```

### Geocoding & Routing Integration

**Geocoding Service** (converts IBGE → lat/lon):
- **API:** `POST /api/geocoding/ibge` and `POST /api/geocoding/lote`
- **Service:** `GeocodingService.php`
- **Model:** `MunicipioCoordenada.php` (Laravel Eloquent - cache table)
- **Cache:** Table `municipio_coordenadas` (SQLite, permanent)
- **External:** Google Geocoding API

**Routing Service** (calculates routes with roads):
- **API:** `POST /api/routing/calculate`
- **Service:** `RoutingService.php`
- **Model:** `RouteSegment.php` (Laravel Eloquent - cache table)
- **Cache:** Table `route_segments` (SQLite, 30 days TTL)
- **External:** Google Directions API
- **Rate Limit:** 200ms between new Google API calls

**Cache Strategy:**
```
1. Frontend requests route with municipalities
2. For each municipality:
   a. Check municipio_coordenadas cache (SQLite)
   b. If miss → Google Geocoding API → save to cache
3. For route segments (origin → destination):
   a. Check route_segments cache with tolerance (~100m)
   b. If miss → Google Directions API → save to cache
   c. Extract polyline and distance
4. Render on Google Maps
```

### Data Flow

```
LIST FLOW:
1. Frontend (index.vue)
   └─> Toggle filters (tipo, retorno) + search
       └─> GET /api/semparar-rotas

2. SemPararRotaController@index
   └─> Validates filters
       └─> ProgressService.getSemPararRotas()

3. ProgressService
   └─> Builds WHERE clause (flgCD, flgRetorno, search)
   └─> JOIN with municipio count (totalmunicipios)
   └─> executeCustomQuery(SQL)

MAP FLOW:
1. Frontend (mapa/[id].vue)
   └─> Load route: GET /api/semparar-rotas/{id}/municipios
   └─> Initialize Google Maps

2. For each municipality in sequence:
   └─> POST /api/geocoding/ibge { codigo_ibge: 3550308 }
       └─> GeocodingService checks cache
           └─> If miss → Google Geocoding API
               └─> Save to municipio_coordenadas
       └─> Return { lat, lon }

3. For each route segment (A → B):
   └─> POST /api/routing/calculate { origin, destination }
       └─> RoutingService checks cache
           └─> If miss → Google Directions API
               └─> Save to route_segments
       └─> Return { polyline, distance, duration }

4. Render on map:
   └─> Markers for each municipality (numbered, colored by status)
   └─> Polyline for route segments (blue line)
   └─> InfoWindow on marker click

CRUD FLOW:
1. Create: POST /api/semparar-rotas
   └─> INSERT INTO PUB.semPararRot (single-line SQL)
   └─> Get new sPararRotID
   └─> INSERT INTO PUB.semPararRotMu (for each municipality)

2. Update: PUT /api/semparar-rotas/{id}
   └─> UPDATE PUB.semPararRot SET ... WHERE sPararRotID = ?
   └─> DELETE FROM PUB.semPararRotMu WHERE sPararRotID = ?
   └─> INSERT INTO PUB.semPararRotMu (new municipalities)

3. Delete: DELETE /api/semparar-rotas/{id}
   └─> DELETE FROM PUB.semPararRotMu WHERE sPararRotID = ?
   └─> DELETE FROM PUB.semPararRot WHERE sPararRotID = ?
```

### Sample Queries

**List routes with municipality count:**
```sql
SELECT r.sPararRotID, r.desSPararRot, r.tempoViagem, r.flgCD, r.flgRetorno, r.datAtu, r.resAtu, COUNT(m.codMun) as totalMunicipios FROM PUB.semPararRot r LEFT JOIN PUB.semPararRotMu m ON r.sPararRotID = m.sPararRotID GROUP BY r.sPararRotID, r.desSPararRot, r.tempoViagem, r.flgCD, r.flgRetorno, r.datAtu, r.resAtu ORDER BY r.sPararRotID DESC
```

**Get route with municipalities (ordered):**
```sql
SELECT m.sPararRotID, m.codMun, m.codEst, m.ordMun, mun.desmun, mun.cdibge, est.nomest, est.siglaest FROM PUB.semPararRotMu m LEFT JOIN PUB.municipio mun ON m.codMun = mun.codmun LEFT JOIN PUB.estado est ON m.codEst = est.codest WHERE m.sPararRotID = 204 ORDER BY m.ordMun
```

**Create route (single-line):**
```sql
INSERT INTO PUB.semPararRot (desSPararRot, tempoViagem, flgCD, flgRetorno, datAtu, resAtu) VALUES ('Rota Teste ABC', 5, 1, 0, '2025-10-02', 'ADMIN')
```

**Get last inserted ID:**
```sql
SELECT MAX(sPararRotID) as last_id FROM PUB.semPararRot
```

**Insert municipality (single-line):**
```sql
INSERT INTO PUB.semPararRotMu (sPararRotID, codMun, codEst, ordMun) VALUES (204, 5436, 25, 1)
```

**Update route (single-line):**
```sql
UPDATE PUB.semPararRot SET desSPararRot = 'Rota Atualizada', tempoViagem = 7, flgCD = 0, flgRetorno = 1, datAtu = '2025-10-02', resAtu = 'ADMIN' WHERE sPararRotID = 204
```

**Delete route:**
```sql
DELETE FROM PUB.semPararRotMu WHERE sPararRotID = 204
DELETE FROM PUB.semPararRot WHERE sPararRotID = 204
```

**Municipality autocomplete:**
```sql
SELECT TOP 50 m.codmun, m.desmun, m.cdibge, m.codest, e.nomest, e.siglaest FROM PUB.municipio m LEFT JOIN PUB.estado e ON m.codest = e.codest WHERE UPPER(m.desmun) LIKE '%SAO PAULO%' AND m.codest = 25 ORDER BY m.desmun
```

---

## Module 4: Vale Pedágio (Toll Calculator)

### Frontend
**Page:** `resources/ts/pages/vale-pedagio/index.vue`

**Features:**
- Google Maps integration with route visualization
- Draggable route points (waypoints)
- Route type selector (fastest/shortest)
- Vehicle configuration (fuel consumption, axles)
- Real-time calculations (fuel cost, time, tolls)
- Paginated route points display

**Key Components:**
- Google Maps Directions API
- `draggable` for route point reordering
- Custom calculator sidebar
- Results dashboard with icons

**API Calls:**
```javascript
// This module primarily uses client-side Google APIs
// No direct Progress database integration

// Potential future endpoints:
// - Save route history
// - Load saved routes
// - Get transporter vehicle data
```

**Progress Integration:**
This module does NOT directly query Progress database. It's a standalone calculator using Google Maps APIs.

**Potential Future Integration:**
- Load transporter vehicle data from `PUB.transporte` (numpla, tipcam)
- Load motorista data from `PUB.motorista`
- Save calculation history to a new table
- Link calculations to pacotes for cost estimation

---

## Database Schema Summary

### Core Entity Relationship Diagram

```
┌──────────────────┐
│  PUB.transporte  │ (Transporters)
│  PK: codtrn      │
└────────┬─────────┘
         │
         ├───────────────┐
         │               │
         ▼               ▼
┌─────────────────┐  ┌────────────────┐
│ PUB.motorista   │  │  PUB.veiculo   │
│ PK: codmot      │  │  PK: codvei    │
│ FK: codtrn      │  │  FK: codtrn    │
└─────────────────┘  └────────────────┘
         │
         │
         ▼
┌──────────────────┐
│   PUB.pacote     │ (Packages)
│   PK: codpac     │
│   FK: codtrn     │────────┐
│   FK: codmot     │        │
│   FK: codrot     │        │
└────────┬─────────┘        │
         │                  │
         ▼                  ▼
┌──────────────────┐  ┌──────────────────┐
│    PUB.carga     │  │   PUB.introt     │ (Routes)
│    PK: codcar    │  │   PK: codrot     │
│    FK: codpac    │  └──────────────────┘
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   PUB.pedido     │ (Deliveries)
│   PK: numseqped  │
│   FK: codcar     │
│   FK: codcli     │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   PUB.cliente    │ (Customers)
│   PK: codcli     │
└──────────────────┘

┌──────────────────────┐
│  PUB.semPararRot     │ (SemParar Routes)
│  PK: sPararRotID     │
└───────────┬──────────┘
            │
            ▼
┌──────────────────────┐
│  PUB.semPararRotMu   │ (Route Municipalities)
│  PK: (sPararRotID,   │
│       ordMun)        │
│  FK: sPararRotID     │
│  FK: codMun          │
│  FK: codEst          │
└───────────┬──────────┘
            │
            ├────────────────┐
            ▼                ▼
┌──────────────────┐  ┌──────────────────┐
│ PUB.municipio    │  │   PUB.estado     │
│ PK: codmun       │  │   PK: codest     │
│ FK: codest       │  └──────────────────┘
└──────────────────┘
```

### Cache Tables (Laravel Eloquent - SQLite)

```
┌─────────────────────────┐
│ municipio_coordenadas   │ (Geocoding Cache)
│ PK: id                  │
│ UK: cdibge              │
│ Fields: lat, lon        │
└─────────────────────────┘

┌─────────────────────────┐
│ route_segments          │ (Routing Cache)
│ PK: id                  │
│ Fields: origin_lat,     │
│         origin_lon,     │
│         destination_lat,│
│         destination_lon,│
│         polyline,       │
│         distance_meters,│
│         duration_seconds│
│ TTL: 30 days            │
└─────────────────────────┘
```

---

## Progress SQL Constraints & Best Practices

### 1. Single-Line SQL Requirement
Progress JDBC has issues with multi-line queries. Always write SQL on a single line.

```sql
-- ❌ WRONG - Multi-line
UPDATE PUB.semPararRot
SET desSPararRot = 'Test',
    tempoViagem = 5
WHERE sPararRotID = 204

-- ✅ CORRECT - Single-line
UPDATE PUB.semPararRot SET desSPararRot = 'Test', tempoViagem = 5 WHERE sPararRotID = 204
```

### 2. No Transaction Support
Progress JDBC does NOT support transactions. Never use `beginTransaction()`, `commit()`, or `rollback()`.

```php
// ❌ WRONG
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql);
DB::connection('progress')->commit();

// ✅ CORRECT - Execute individual queries
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
$this->executeUpdate($sql3);
```

### 3. TOP Instead of LIMIT
Progress uses `SELECT TOP N` syntax, not `LIMIT N`.

```sql
-- ❌ WRONG
SELECT * FROM PUB.transporte LIMIT 10

-- ✅ CORRECT
SELECT TOP 10 * FROM PUB.transporte
```

### 4. No OFFSET Support
Progress lacks native OFFSET. Simulate with keyset pagination or subqueries.

```sql
-- ❌ WRONG
SELECT * FROM PUB.transporte LIMIT 10 OFFSET 20

-- ✅ CORRECT - Keyset pagination
SELECT TOP 10 * FROM PUB.transporte WHERE codtrn > 12345 ORDER BY codtrn

-- ✅ ALTERNATIVE - Subquery (inefficient)
SELECT * FROM PUB.transporte WHERE codtrn NOT IN (SELECT TOP 20 codtrn FROM PUB.transporte ORDER BY codtrn) ORDER BY codtrn
```

### 5. Schema Prefix Required
Always use `PUB.tablename` format.

```sql
-- ❌ WRONG
SELECT * FROM transporte

-- ✅ CORRECT
SELECT * FROM PUB.transporte
```

### 6. Case-Sensitive Names
Table and column names are case-sensitive in Progress.

```sql
-- ❌ WRONG
SELECT CODTRN FROM pub.Transporte

-- ✅ CORRECT
SELECT codtrn FROM PUB.transporte
```

### 7. String Literals
Use single quotes for string values, not double quotes.

```sql
-- ❌ WRONG
WHERE nomtrn = "JOAO"

-- ✅ CORRECT
WHERE nomtrn = 'JOAO'
```

### 8. SQL Injection Prevention
Always use parameterized queries or escape values.

```php
// ❌ WRONG - SQL injection risk
$sql = "SELECT * FROM PUB.transporte WHERE nomtrn = '$name'";

// ✅ CORRECT - Escaped
$sql = "SELECT * FROM PUB.transporte WHERE nomtrn = " . $this->escapeSqlString($name);

// ✅ BETTER - Parameterized (if connector supports)
$sql = "SELECT * FROM PUB.transporte WHERE codtrn = ?";
DB::connection('progress')->select($sql, [$id]);
```

---

## Pagination Patterns

### Keyset Pagination (Recommended)
Best for large datasets with stable ordering.

```php
// First page
SELECT TOP 10 codtrn, nomtrn FROM PUB.transporte ORDER BY codtrn

// Next page (after codtrn=5000)
SELECT TOP 10 codtrn, nomtrn FROM PUB.transporte WHERE codtrn > 5000 ORDER BY codtrn

// Previous page (before codtrn=5000)
SELECT TOP 10 codtrn, nomtrn FROM PUB.transporte WHERE codtrn < 5000 ORDER BY codtrn DESC
```

**Advantages:**
- Constant performance regardless of page number
- No data skipping when new records inserted
- Efficient for deep pagination

**Implementation in ProgressService:**
```php
if ($direction === 'next' && $lastId) {
    $sql = "SELECT TOP $perPage * FROM PUB.transporte WHERE codtrn > $lastId ORDER BY codtrn";
} elseif ($direction === 'prev' && $lastId) {
    $sql = "SELECT TOP $perPage * FROM PUB.transporte WHERE codtrn < $lastId ORDER BY codtrn DESC";
    // Reverse results after fetch
}
```

### Legacy Offset Pagination
Fallback for compatibility (inefficient).

```php
// Page 3, 10 per page (skip 20 records)
$offset = ($page - 1) * $perPage; // 20

// Skip query
$skipSql = "SELECT TOP $offset codtrn FROM PUB.transporte ORDER BY codtrn";
$skipResult = executeQuery($skipSql);
$lastSkipId = end($skipResult)['codtrn'];

// Fetch query
$sql = "SELECT TOP $perPage * FROM PUB.transporte WHERE codtrn > $lastSkipId ORDER BY codtrn";
```

**Disadvantages:**
- Performance degrades with higher page numbers
- Two queries required (skip + fetch)
- Data inconsistency if records change between queries

---

## Security Considerations

### 1. Input Validation
All controllers implement strict validation.

```php
$validated = $request->validate([
    'page' => 'integer|min:1|max:1000',
    'per_page' => 'integer|min:5|max:50',
    'search' => [
        'nullable',
        'string',
        'max:100',
        'regex:/^[a-zA-Z0-9\s\-._@]+$/'
    ],
    'codigo' => 'nullable|integer|min:1|max:999999999'
]);
```

### 2. Rate Limiting
Sensitive endpoints have aggressive rate limits.

```php
Route::get('transportes/statistics', [TransporteController::class, 'statistics'])
    ->middleware('throttle:10,1');  // 10 requests per minute

Route::post('transportes/query', [TransporteController::class, 'query'])
    ->middleware('throttle:5,1');   // 5 requests per minute (admin-only)
```

### 3. SQL Injection Prevention
Custom queries blocked with keyword detection.

```php
$dangerousPatterns = [
    'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE',
    'INSERT', 'UPDATE', 'EXEC', 'EXECUTE', '--', '/*', '*/',
    'UNION', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE'
];

foreach ($dangerousPatterns as $pattern) {
    if (strpos(strtoupper($sql), $pattern) !== false) {
        return error("Forbidden keyword: {$pattern}");
    }
}
```

### 4. Admin-Only Endpoints
Custom query execution restricted to admin users.

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('transportes/query', [TransporteController::class, 'query']);
});

// In controller:
if (!$user || !$user->hasRole('admin')) {
    return response()->json(['error' => 'Access denied'], 403);
}
```

---

## Performance Optimization

### 1. Use Aggregated Queries
Fetch multiple statistics in a single query instead of N queries.

```sql
-- ✅ GOOD - Single query
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN flgautonomo = 1 THEN 1 ELSE 0 END) as autonomos,
    SUM(CASE WHEN flgautonomo = 0 THEN 1 ELSE 0 END) as empresas,
    SUM(CASE WHEN flgati = 1 THEN 1 ELSE 0 END) as ativos
FROM PUB.transporte

-- ❌ BAD - Multiple queries
SELECT COUNT(*) FROM PUB.transporte
SELECT COUNT(*) FROM PUB.transporte WHERE flgautonomo = 1
SELECT COUNT(*) FROM PUB.transporte WHERE flgautonomo = 0
```

### 2. Limit Result Sets
Always use TOP clause to prevent overwhelming the system.

```sql
-- ✅ GOOD
SELECT TOP 50 * FROM PUB.transporte

-- ❌ BAD
SELECT * FROM PUB.transporte -- Could return millions of rows
```

### 3. Use JOINs Over Subqueries
JOINs are generally more efficient than nested subqueries.

```sql
-- ✅ GOOD
SELECT p.*, t.nomtrn
FROM PUB.pacote p
LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn

-- ❌ BAD
SELECT p.*, (SELECT nomtrn FROM PUB.transporte WHERE codtrn = p.codtrn) as nomtrn
FROM PUB.pacote p
```

### 4. Index-Friendly WHERE Clauses
Use primary keys and indexed columns in WHERE clauses.

```sql
-- ✅ GOOD - Uses primary key
WHERE codtrn = 12345

-- ⚠️ SLOW - Full table scan
WHERE nomtrn LIKE '%JOAO%'
```

### 5. Cache External API Calls
Geocoding and routing results are cached to avoid repeated API calls.

```php
// Check cache first
$cached = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();
if ($cached) {
    return ['lat' => $cached->latitude, 'lon' => $cached->longitude];
}

// If miss, call Google API and cache
$result = $this->googleGeocodingApi->geocode($codigoIBGE);
MunicipioCoordenada::create([
    'cdibge' => $codigoIBGE,
    'latitude' => $result['lat'],
    'longitude' => $result['lon']
]);
```

**Cache Hit Rate:** 80%+ after initial population

---

## Testing Endpoints

### cURL Examples

**Test Progress connection:**
```bash
curl http://localhost:8002/api/progress/test-connection
```

**List transporters:**
```bash
curl "http://localhost:8002/api/transportes?page=1&per_page=10&search=joao"
```

**Get transporter details:**
```bash
curl http://localhost:8002/api/transportes/12345
```

**Get statistics:**
```bash
curl http://localhost:8002/api/transportes/statistics
```

**List packages:**
```bash
curl "http://localhost:8002/api/pacotes?page=1&per_page=15&codigo=3048790&rota=AC"
```

**Get package itinerary:**
```bash
curl -X POST http://localhost:8002/api/pacotes/itinerario \
  -H "Content-Type: application/json" \
  -d '{"Pacote.codPac": 3048790}'
```

**List SemParar routes:**
```bash
curl "http://localhost:8002/api/semparar-rotas?page=1&per_page=10&flg_cd=true"
```

**Get route with municipalities:**
```bash
curl http://localhost:8002/api/semparar-rotas/204/municipios
```

**Create SemParar route:**
```bash
curl -X POST http://localhost:8002/api/semparar-rotas \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Rota Teste ABC",
    "tempo_viagem": 5,
    "flg_cd": true,
    "flg_retorno": false,
    "municipios": [
      {
        "cod_est": 25,
        "cod_mun": 5436,
        "des_est": "SÃO PAULO",
        "des_mun": "SÃO PAULO",
        "cdibge": 3550308
      }
    ]
  }'
```

**Geocode by IBGE:**
```bash
curl -X POST http://localhost:8002/api/geocoding/ibge \
  -H "Content-Type: application/json" \
  -d '{"codigo_ibge": 3550308}'
```

**Calculate route:**
```bash
curl -X POST http://localhost:8002/api/routing/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "origin": {"lat": -23.5505, "lng": -46.6333},
    "destination": {"lat": -22.9099, "lng": -43.1729}
  }'
```

---

## Troubleshooting Guide

### Progress Connection Issues

**Problem:** `Connection refused` or `No suitable driver`

**Solutions:**
1. Check Progress JDBC driver exists:
   ```bash
   dir "c:\Progress\OpenEdge\java\openedge.jar"
   ```

2. Test Java installation:
   ```bash
   java -version
   ```

3. Test connection via API:
   ```bash
   curl http://localhost:8002/api/progress/test-connection
   ```

4. Check Laravel logs:
   ```bash
   php artisan pail
   ```

### Query Syntax Errors

**Problem:** `Syntax error in SQL statement`

**Common Causes:**
- Multi-line SQL (compress to single line)
- Missing `PUB.` prefix
- Using `LIMIT` instead of `TOP`
- Double quotes instead of single quotes

**Example Fix:**
```sql
-- ❌ WRONG
SELECT * FROM transporte
WHERE nomtrn = "JOAO"
LIMIT 10

-- ✅ CORRECT
SELECT TOP 10 * FROM PUB.transporte WHERE nomtrn = 'JOAO'
```

### Pagination Not Working

**Problem:** Empty results on page 2+

**Possible Causes:**
1. Keyset pagination cursor mismatch
2. Legacy pagination with deleted records
3. Incorrect `has_next`/`has_prev` logic

**Debug:**
```javascript
console.log('Cursor info:', {
  next_cursor: pagination.next_cursor,
  prev_cursor: pagination.prev_cursor,
  has_next: pagination.has_next,
  has_prev: pagination.has_prev
});
```

### Geocoding Failures

**Problem:** Municipalities not showing on map

**Debug Steps:**
1. Check debug panel (click "Debug" button on map page)
2. Look for geocoding errors in logs
3. Verify IBGE code is correct
4. Check Google API quota

**Console Logs:**
```javascript
// Enable debug panel
showDebugPanel.value = true

// Check logs
console.log('Geocoding stats:', debugStats.value)
console.log('Failed geocodes:', debugLogs.value.filter(l => l.level === 'error'))
```

### Transaction Errors

**Problem:** `Transactions not supported`

**Cause:** Progress JDBC doesn't support transactions.

**Fix:** Remove all transaction calls:
```php
// ❌ WRONG
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
DB::connection('progress')->commit();

// ✅ CORRECT
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
// No transactions - hope for the best!
```

---

## Future Improvements

### 1. Batch Operations
Implement batch INSERT/UPDATE for municipalities to reduce query count.

### 2. WebSocket Real-Time Updates
Push notifications when packages or routes change in Progress database.

### 3. Full-Text Search
Implement proper search indexing for transporter/package names.

### 4. Export Functionality
Add CSV/Excel export for reports (currently placeholder buttons).

### 5. Route History
Save Vale Pedágio calculations for auditing and reuse.

### 6. GPS Integration
Link package deliveries with real GPS coordinates from drivers' apps.

### 7. Analytics Dashboard
Aggregate statistics across all modules with charts and trends.

---

## References

- **Progress SQL Reference:** OpenEdge SQL Development Guide
- **Laravel Documentation:** https://laravel.com/docs/12.x
- **Vue 3 Composition API:** https://vuejs.org/guide/
- **Vuexy Template:** https://demos.themeselection.com/materio-vuetify-vuejs-admin-template/
- **Google Maps API:** https://developers.google.com/maps/documentation
- **Vuetify 3:** https://vuetifyjs.com/en/

---

**Document Version:** 1.0
**Last Updated:** 2025-10-02
**Author:** System Architecture Team
**Status:** Complete
