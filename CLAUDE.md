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
**ALWAYS use JDBC direct connection, NOT Eloquent:**
```php
// CORRECT - Direct JDBC
DB::connection('progress')->select('SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]);
$this->progressService->executeCustomQuery($sql);

// WRONG - Never use Eloquent models
Pacote::find(123);  // ❌
```

### 3. Git Commits
- **NEVER** mention Claude, AI, or use emojis in commits
- Use technical, descriptive messages
- Configure: `git config --global user.name "Psykhepathos"`

## Key Services & APIs

### ProgressService Methods
**Core Connection:**
- `testConnection()` - Test JDBC connection
- `executeCustomQuery($sql)` - Run custom SQL (SELECT only)
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

**Routing & Maps:**
- `GET /api/routing/test` - Test routing service
- `POST /api/routing/route` - Calculate route
- `POST /api/route-cache/find` - Find cached route
- `POST /api/route-cache/save` - Save route to cache
- `GET /api/route-cache/stats` - Cache statistics

### Progress SQL Conventions
- **Schema:** Always use `PUB.tablename` (e.g., `PUB.transporte`, `PUB.pacote`)
- **Limit:** Use `SELECT TOP 10` (not LIMIT)
- **Offset:** Progress lacks native OFFSET - simulate with subqueries or fetch all + array_slice in PHP
- **Case:** Progress is case-sensitive for table/column names
- **Strings:** Use single quotes `'value'`
- **Joins:** Use `LEFT JOIN` syntax, not nested subqueries
- **Transactions:** Wrap INSERTs/UPDATEs in `DB::connection('progress')->beginTransaction()`

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

## Project Structure

```
ndd-vuexy/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php           # Authentication
│   │   ├── TransporteController.php     # Transporters
│   │   ├── PacoteController.php         # Packages
│   │   ├── RotaController.php           # Routes autocomplete
│   │   ├── SemPararRotaController.php   # SemParar routes CRUD
│   │   ├── RoutingController.php        # Route calculation proxy
│   │   └── ProgressController.php       # Raw Progress queries
│   └── Services/
│       └── ProgressService.php          # Main Progress DB service (1500+ lines)
├── resources/ts/
│   ├── pages/
│   │   ├── transportes/                 # Transporters module
│   │   ├── pacotes/                     # Packages module
│   │   ├── vale-pedagio/                # Toll pass calculator
│   │   ├── rotas-semparar/              # SemParar routes with map
│   │   └── apps/                        # Vuexy example pages (reference templates)
│   ├── @layouts/                        # Layout components
│   ├── navigation/vertical/ndd.ts       # Left sidebar menu
│   └── plugins/                         # Vue plugins (router, vuetify, etc)
├── routes/api.php                       # API routes
├── storage/app/java/
│   ├── ProgressJDBCConnector.java       # JDBC connector for Progress
│   └── gson-2.8.9.jar                   # JSON library for Java
└── database/migrations/                 # SQLite migrations (NOT Progress)

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

# API URLs
LARAVEL_API=http://localhost:8002
VUE_FRONTEND=http://localhost:5174
```

## Important Notes

- **Repository:** https://github.com/Psykhepathos/ndd-vuexy.git
- **Old systems (deprecated):** ndd-laravel, ndd-flutter repos
- **Key features:**
  - Vale Pedágio: http://localhost:8002/vale-pedagio
  - Rotas SemParar: http://localhost:8002/rotas-semparar (CRUD + interactive map)
  - Pacotes: http://localhost:8002/pacotes (package tracking)
  - Transportes: http://localhost:8002/transportes (transporter management)
- **Progress JDBC:** Located in `c:/Progress/OpenEdge/java/openedge.jar`
- **Java Connector:** Auto-compiled on first use in `storage/app/java/`
- **Pagination:** Progress lacks OFFSET - use subquery pattern in ProgressService
- **Always test functionality before committing**
- **Use Progress API endpoints for schema exploration, not tinker**

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