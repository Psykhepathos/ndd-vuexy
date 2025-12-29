# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sistema NDD - Transport management system (Vale Pedagio Tambasa) integrating Progress OpenEdge database, SemParar SOAP, and NDD Cargo APIs.

## Development Commands

```bash
# Start development servers
php artisan serve --port=8002  # Backend (MUST use port 8002)
pnpm run dev                   # Frontend (Vite)

# Type checking and linting
pnpm run typecheck             # TypeScript check
pnpm run lint                  # ESLint with auto-fix

# Backend tests
php artisan test               # All tests
php artisan test --filter=TestName  # Single test

# Build for production
pnpm run build

# Test external connections
curl http://localhost:8002/api/progress/test-connection
curl http://localhost:8002/api/semparar/test-connection
```

## Architecture

```
Vue 3.5 + Vuexy + Vuetify (Frontend)
         |
         v HTTP API
Laravel 12 + Sanctum (Backend)
         |
         +--- SQLite (users, cache, RBAC)
         |
         v JDBC
Progress OpenEdge (Legacy ERP Database)

External APIs: Google Geocoding, OSRM (routing), SemParar SOAP, NDD Cargo SOAP
```

## Critical Rules

### 1. Progress Database - NO TRANSACTIONS

Progress JDBC does not support transactions. Never use beginTransaction/commit/rollBack.

```php
// WRONG - Will fail!
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql);
DB::connection('progress')->commit();

// CORRECT - Direct execution
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);

// SQL must be single-line (Progress has multi-line issues)
$sql = "UPDATE PUB.semPararRot SET desSPararRot = 'Test' WHERE sPararRotID = 204";
```

### 2. Progress vs Eloquent - Different Tables

```php
// Progress tables (PUB.*) -> Raw JDBC via ProgressService
$this->progressService->getTransporteById($id);
DB::connection('progress')->select('SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]);

// Laravel tables (users, roles, cache) -> Eloquent ORM
$user = User::find($userId);
$coords = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();
```

### 3. OSRM Routing - ALWAYS Use Laravel Proxy

Never use leaflet-routing-machine directly (CORS/timeout issues).

```typescript
// WRONG - CORS failure
import 'leaflet-routing-machine'
L.Routing.control({ ... })

// CORRECT - Use proxy endpoint
const response = await fetch('/api/routing/route', {
  method: 'POST',
  body: JSON.stringify({
    start: [lng, lat],  // Note: [lng, lat] order!
    end: [lng2, lat2]
  })
})
```

### 4. SemParar SOAP - Positional Parameters

```php
// WRONG - causes "Array to string conversion"
$client->autenticarUsuario(['cnpj' => $x, 'login' => $y]);

// CORRECT - positional parameters
$client->autenticarUsuario($cnpj, $user, $password);

// WRONG - XML as string sends empty!
$client->roteirizarPracasPedagio($pontosXml, $opcoesXml, $token);

// CORRECT - use SoapVar
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$client->roteirizarPracasPedagio($pontosParam, $opcoesParam, $token);
```

### 5. VPO: Autonomo vs Empresa

Data comes from DIFFERENT tables based on transporter type:

```php
$transportador = $this->progressService->getTransporteById($codtrn);

if ($transportador['flgautonomo']) {
    // AUTONOMO: everything in PUB.transporte
    $condutor_nome = $transportador['nomtrn'];
    $condutor_cpf = $transportador['codcnpjcpf'];
} else {
    // EMPRESA: driver in PUB.trnmot, vehicle in PUB.trnvei
    $motorista = $this->getMotoristaByCode($codmot);
    $condutor_nome = $motorista['nommot'];
    $condutor_cpf = $motorista['codcpf'];
}
```

### 6. Frontend API Calls

Always use the centralized API helper:

```typescript
import { $api } from '@/utils/api'
import { API_ENDPOINTS } from '@/config/api'

// CORRECT
const data = await $api(API_ENDPOINTS.pacotes)
const user = await $api(API_ENDPOINTS.user(123))

// WRONG - hardcoded URLs
const data = await fetch('/api/pacotes')
```

### 7. Vuexy UI Components

NEVER create UI from scratch. Always copy from existing templates:
- Lists: `resources/ts/pages/apps/user/list/index.vue`
- Forms: `resources/ts/pages/apps/user/view/UserBioPanel.vue`
- Dashboards: `resources/ts/pages/apps/logistics/dashboard.vue`
- Wizards: `resources/ts/pages/compra-viagem/nova.vue`

## Key Services

### ProgressService (`app/Services/ProgressService.php`)
Main JDBC interface to Progress OpenEdge. ~2500 lines.

```php
$this->testConnection();
$this->executeCustomQuery($sql);
$this->executeUpdate($sql);  // NO TRANSACTIONS!
$this->getTransportesPaginated($filters);
$this->getPacotesPaginated($filters);
$this->getSemPararRotaWithMunicipios($id);
```

### GeocodingService
Converts IBGE codes to coordinates. Uses SQLite cache (municipio_coordenadas table).

### SemParar Services (`app/Services/SemParar/`)
SOAP client for toll road operations. Handles authentication, route calculation, trip purchases.

### NddCargo Services (`app/Services/NddCargo/`)
VPO (Vale Pedagio Obrigatorio) integration. Uses CrossTalk SOAP 1.1 + RSA-SHA1 signatures.

## Database Schema

### Progress OpenEdge (PUB.*)
- `PUB.transporte` - Transporters
- `PUB.trnmot` - Drivers (company only)
- `PUB.trnvei` - Vehicles (company only)
- `PUB.pacote` - Packages
- `PUB.pedido` - Orders with GPS data
- `PUB.semPararRot` - SemParar routes
- `PUB.semPararRotMu` - Route municipalities

### SQLite (Laravel)
- `users`, `roles`, `permissions` - RBAC (Spatie)
- `municipio_coordenadas` - Geocoding cache (permanent)
- `pracas_pedagio` - ANTT toll plaza data
- `vpo_transportadores_cache` - VPO sync cache

## Key Frontend Pages

| Page | Path | Description |
|------|------|-------------|
| Transportes | `resources/ts/pages/transportes/index.vue` | Transporter list with filters |
| Pacotes | `resources/ts/pages/pacotes/index.vue` | Package management |
| Rotas Mapa | `resources/ts/pages/rotas-padrao/mapa/[id].vue` | Interactive Leaflet map |
| Compra Viagem | `resources/ts/pages/compra-viagem/nova.vue` | 5-step purchase wizard |
| VPO Emissao | `resources/ts/pages/vpo-emissao/nova.vue` | VPO emission wizard |
| Usuarios | `resources/ts/pages/usuarios/index.vue` | User management (RBAC) |

## Troubleshooting

### Progress connection fails
Check `PROGRESS_HOST`, `PROGRESS_PORT` in .env. Verify Java is installed and JDBC driver exists at `driver/openedge.jar`.

### Map not loading
1. Check OSRM proxy: `curl -X POST http://localhost:8002/api/routing/route -H "Content-Type: application/json" -d '{"start":[-46.63,-23.55],"end":[-43.17,-22.91]}'`
2. Check browser console for JS errors
3. Verify Leaflet CSS is loaded

### Frontend 404 / Vite cache
```bash
rm -rf node_modules/.vite
pnpm run dev
```

### Queue not processing (production)
```bash
sudo supervisorctl status ndd-queue
sudo supervisorctl restart ndd-queue
```

## Documentation

- `docs/INDEX.md` - Complete documentation index
- `docs/integracoes/ndd-cargo/` - NDD Cargo/VPO integration docs
- `docs/audits/` - Security audit reports