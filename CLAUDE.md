# CLAUDE.md

Guia de desenvolvimento para o Sistema NDD - Gestao de Transporte.

**Ultima Atualizacao:** 2025-12-10

## Quick Start

```bash
# Iniciar servidores
php artisan serve --port=8002  # Backend (OBRIGATORIO porta 8002!)
pnpm run dev                   # Frontend

# Acessar
http://localhost:8002          # SEMPRE usar esta URL!
# Login: admin@ndd.com / Admin@123

# Testes
pnpm run typecheck             # TypeScript
pnpm run lint                  # ESLint
php artisan test               # Backend

# Testar conexoes
curl http://localhost:8002/api/progress/test-connection
curl http://localhost:8002/api/semparar/test-connection
curl http://localhost:8002/api/ndd-cargo/test-connection
```

---

## Regras Criticas

### 1. Progress Database - SEM TRANSACOES!

```php
// NUNCA fazer isso - Progress JDBC nao suporta transacoes
DB::connection('progress')->beginTransaction();
$this->executeUpdate($sql);
DB::connection('progress')->commit();  // FALHA!

// SEMPRE fazer assim - execucao direta
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);

// SQL SEMPRE em linha unica (Progress tem problemas com multi-linha)
$sql = "UPDATE PUB.semPararRot SET desSPararRot = 'Test' WHERE sPararRotID = 204";
```

### 2. OSRM Routing - SEMPRE usar proxy Laravel

```typescript
// NUNCA usar leaflet-routing-machine direto (CORS/timeout)
import 'leaflet-routing-machine'
L.Routing.control({ ... })  // FALHA!

// SEMPRE usar proxy Laravel
const response = await fetch('/api/routing/route', {
  method: 'POST',
  body: JSON.stringify({
    start: [lng, lat],  // [lng, lat] - ATENCAO ordem!
    end: [lng2, lat2]
  })
})
```

### 3. SemParar SOAP - Parametros posicionais

```php
// ERRADO - causa "Array to string conversion"
$client->autenticarUsuario(['cnpj' => $x, 'login' => $y]);

// CERTO - parametros posicionais
$client->autenticarUsuario($cnpj, $user, $password);

// ERRADO - XML como string (envia vazio!)
$client->roteirizarPracasPedagio($pontosXml, $opcoesXml, $token);

// CERTO - usar SoapVar
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$client->roteirizarPracasPedagio($pontosParam, $opcoesParam, $token);
```

### 4. VPO Autonomo vs Empresa

```php
// Dados vem de tabelas DIFERENTES baseado no tipo!
$transportador = $this->progressService->getTransporteById($codtrn);

if ($transportador['flgautonomo']) {
    // AUTONOMO: tudo em PUB.transporte
    $condutor_nome = $transportador['nomtrn'];
    $condutor_cpf = $transportador['codcnpjcpf'];
} else {
    // EMPRESA: motorista em PUB.trnmot, veiculo em PUB.trnvei
    $motorista = $this->getMotoristaByCode($codmot);
    $condutor_nome = $motorista['nommot'];
    $condutor_cpf = $motorista['codcpf'];
}
```

### 5. Progress vs Eloquent

```php
// Progress tables (PUB.*) -> Raw JDBC via ProgressService
DB::connection('progress')->select('SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]);

// Laravel tables (users, cache) -> Eloquent ORM
$user = User::find($userId);
$coords = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();
```

### 6. Vuexy Template

NUNCA criar UI do zero. SEMPRE copiar de templates existentes:
- Listas: `resources/ts/pages/apps/user/list/index.vue`
- Forms: `resources/ts/pages/apps/user/view/UserBioPanel.vue`
- Dashboards: `resources/ts/pages/apps/logistics/dashboard.vue`

### 7. Git Commits

```bash
# Estilo de commit
git commit -m "Fix: Corrigir timeout no proxy OSRM"
git commit -m "Add: Endpoint validacao de veiculo"
git commit -m "Update: Melhorar cache de geocoding"

# NUNCA mencionar AI/Claude nos commits
# NUNCA adicionar Co-Authored-By do Claude
# Commits devem parecer feitos por humano
```

---

## Arquitetura

```
Vue/Vuexy Frontend (5173/4/6)
        |
        v HTTP API
Laravel Backend (8002)
        |
        v JDBC
Progress OpenEdge (192.168.80.113)

APIs Externas:
- Google Geocoding (IBGE -> coordenadas)
- OSRM Public (roteamento gratuito)
- SemParar SOAP (pedagio)
- NDD Cargo SOAP (VPO)
- Python Flask (PDF + WhatsApp)
```

### Stack
- **Frontend:** Vue 3.5 + TypeScript 5.8 + Vuexy + Vuetify 3.8
- **Backend:** Laravel 12 + Sanctum
- **Database:** Progress OpenEdge (JDBC) + SQLite (cache)
- **Maps:** Leaflet + OpenStreetMap + OSRM (100% gratuito)

---

## Controllers Principais

### Transportes
```
GET  /api/transportes              - Lista paginada
GET  /api/transportes/{id}         - Detalhes + motoristas + veiculos
GET  /api/transportes/statistics   - Estatisticas
```

### Pacotes
```
GET  /api/pacotes                  - Lista com filtros
GET  /api/pacotes/{id}             - Detalhes
POST /api/pacotes/itinerario       - Itinerario com GPS (para mapa)
```

### Rotas SemParar
```
GET  /api/semparar-rotas           - Lista rotas
GET  /api/semparar-rotas/{id}/municipios  - Rota + municipios
PUT  /api/semparar-rotas/{id}/municipios  - Atualizar ordem (drag&drop)
POST /api/semparar-rotas           - Criar rota
DELETE /api/semparar-rotas/{id}    - Deletar
```

### SemParar SOAP
```
GET  /api/semparar/test-connection - Testar conexao
POST /api/semparar/roteirizar      - Encontrar pracas pedagio
POST /api/semparar/comprar-viagem  - COMPRAR VIAGEM ($$$ REAL!)
POST /api/semparar/gerar-recibo    - Gerar PDF
POST /api/semparar/cancelar-viagem - Cancelar (IRREVERSIVEL!)
```

### VPO / NDD Cargo
```
POST /api/vpo/sync/transportador   - Sincronizar transportador
GET  /api/vpo/transportadores      - Listar cache
POST /api/vpo/emissao/validate     - Validar para emissao
POST /api/vpo/emissao/emit         - Emitir VPO (NDD Cargo)

POST /api/ndd-cargo/roteirizador/consultar  - Consultar pracas
GET  /api/ndd-cargo/resultado/{guid}        - Resultado assincrono
```

### Geocoding & Routing
```
POST /api/geocoding/ibge           - Coordenadas por IBGE
POST /api/geocoding/lote           - Batch geocoding
POST /api/routing/route            - Proxy OSRM (gratuito)
```

### Pracas Pedagio
```
GET  /api/pracas-pedagio           - Lista com filtros
POST /api/pracas-pedagio/importar  - Import CSV ANTT
POST /api/pracas-pedagio/proximidade - Buscar por coordenadas
```

---

## Services Principais

### ProgressService (2574 linhas)
Interface JDBC com Progress OpenEdge.

```php
// Conexao
$this->testConnection();
$this->executeCustomQuery($sql);
$this->executeUpdate($sql);  // SEM TRANSACOES!

// Transportadores
$this->getTransportesPaginated($filters);
$this->getTransporteById($id);
$this->getMotoristasPorTransportador($id);
$this->getVeiculosPorTransportador($id);

// Pacotes
$this->getPacotesPaginated($filters);
$this->getItinerarioPacote($codPac);  // GPS para mapa

// SemParar Rotas
$this->getSemPararRotas($filters);
$this->getSemPararRotaWithMunicipios($id);
$this->updateSemPararRotaMunicipios($id, $municipios);  // DELETE + INSERT!
```

### GeocodingService
```php
$this->getCoordenadasByIbge($codigoIbge, $nomeMunicipio, $uf);
// Cache: municipio_coordenadas (SQLite, permanente)
// Rate limit: 200ms entre chamadas Google
// Cache hit: 80%+
```

### VpoDataSyncService (660 linhas)
```php
$this->syncTransportador($codtrn, $codmot, $placa);
$this->syncBatch([$codtrn1, $codtrn2, ...]);
// Fontes: Progress -> ANTT -> Cache
// Quality score: 0-100
```

### NddCargoService
```php
$this->consultarRoteirizador($pontos);
$this->consultarResultado($guid);
// Protocolo: CrossTalk SOAP 1.1 + RSA-SHA1
```

---

## Frontend Pages

### Transportes
`resources/ts/pages/transportes/index.vue`
- VDataTableServer com paginacao
- Filtros: tipo, status, natureza, busca

### Pacotes
`resources/ts/pages/pacotes/index.vue`
- Filtros: data, situacao, rota, TCD
- Itinerario para simulacao no mapa

### Rotas Padrao (Mapa Interativo)
`resources/ts/pages/rotas-padrao/mapa/[id].vue`
- Leaflet + OpenStreetMap
- Drag & drop municipios (vuedraggable)
- Roteamento OSRM via proxy
- Simulacao de pacotes

### Compra Viagem (Wizard 5 etapas)
`resources/ts/pages/compra-viagem/nova.vue`
1. Pacote - validacao
2. Veiculo - verificar SemParar
3. Rota - sugestao automatica
4. Preco - custo via SOAP
5. Confirmacao - compra

### VPO Emissao (Wizard)
`resources/ts/pages/vpo-emissao/nova.vue`
- 5 etapas: Pacote -> Motorista -> Veiculo -> Rota -> Confirmacao
- Suporte autonomo (CPF) e empresa (CNPJ)
- Mapa com pracas de pedagio
- Processamento assincrono

---

## Database

### Progress OpenEdge (JDBC)

**Caracteristicas:**
- SEM TRANSACOES
- SEM OFFSET (usar keyset pagination)
- Case-sensitive
- Schema: PUB.tablename

**Tabelas principais:**
- `PUB.transporte` - Transportadores
- `PUB.trnmot` - Motoristas (empresa)
- `PUB.trnvei` - Veiculos (empresa)
- `PUB.pacote` - Pacotes
- `PUB.pedido` - Pedidos com GPS
- `PUB.semPararRot` - Rotas SemParar
- `PUB.semPararRotMu` - Municipios das rotas
- `PUB.sPararViagem` - Viagens compradas

### SQLite (Cache Laravel)
- `users` - Usuarios Sanctum
- `municipio_coordenadas` - Cache geocoding (permanente)
- `pracas_pedagio` - Dados ANTT
- `vpo_transportadores_cache` - Cache VPO
- `motorista_empresa_cache` - Cache motoristas

---

## Troubleshooting

### Progress Connection
```bash
curl http://localhost:8002/api/progress/test-connection
# Verificar: PROGRESS_HOST no .env, driver JDBC, Java instalado
```

### OSRM Routing
```bash
curl -X POST http://localhost:8002/api/routing/route \
  -H "Content-Type: application/json" \
  -d '{"start":[-46.63,-23.55],"end":[-43.17,-22.91]}'
# Se falhar: OSRM publico pode estar fora, fallback para linha reta
```

### SemParar SOAP
```bash
curl http://localhost:8002/api/semparar/test-connection
# Limpar cache: curl -X POST http://localhost:8002/api/semparar/debug/clear-cache
```

### Mapa nao carrega
1. Verificar OSRM proxy
2. Console do browser para erros JS
3. Verificar coordenadas validas
4. CSS do Leaflet carregado

### Frontend 404
```bash
rm -rf node_modules/.vite
pnpm run dev
```

---

## Estrutura do Projeto

```
app/
  Http/Controllers/Api/
    TransporteController.php
    PacoteController.php
    SemPararRotaController.php
    SemPararController.php
    CompraViagemController.php
    VpoController.php
    VpoEmissaoController.php
    NddCargoController.php
    GeocodingController.php
    RoutingController.php
    MapController.php
    PracaPedagioController.php
  Services/
    ProgressService.php           # 2574 linhas!
    GeocodingService.php
    SemParar/
    Vpo/
    NddCargo/

resources/ts/pages/
    transportes/
    pacotes/
    rotas-padrao/
    compra-viagem/
    vpo-emissao/
    pracas-pedagio/

docs/
    integracoes/ndd-cargo/        # 15 docs VPO
    audits/                       # Auditorias seguranca
    bug-fixes/                    # Correcoes
```

---

## Variaveis de Ambiente

```env
# Aplicacao
APP_URL=http://localhost:8002

# Progress OpenEdge
PROGRESS_HOST=192.168.80.113
PROGRESS_PORT=2574
PROGRESS_DATABASE=tambasa

# Google Maps (apenas geocoding)
GOOGLE_MAPS_API_KEY=...

# SemParar SOAP
SEMPARAR_CNPJ=...
SEMPARAR_USER=...
SEMPARAR_PASSWORD=...

# Seguranca compra viagem
ALLOW_SOAP_QUERIES=true       # Habilita validacoes
ALLOW_SOAP_PURCHASE=false     # BLOQUEIA COMPRAS REAIS!

# Python Flask (PDF)
PYTHON_FLASK_URL=http://192.168.19.35:5001
```

---

## Documentacao

- `docs/INDEX.md` - Indice completo
- `docs/integracoes/ndd-cargo/` - VPO/NDD Cargo (15 docs)
- `docs/audits/` - Auditorias de seguranca
- `docs/bug-fixes/` - Correcoes aplicadas

---

## Estatisticas

- 21 Controllers
- 14 Services
- 60+ API Endpoints
- 21 Tabelas Progress
- 13 Tabelas Laravel
- 6.913+ Transportadores
- 800.000+ Pacotes

---

**Desenvolvido por:** Psykhepathos
**Repositorio:** https://github.com/Psykhepathos/ndd-vuexy
