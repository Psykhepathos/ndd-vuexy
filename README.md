# Sistema NDD - Gestao de Transporte

Sistema unificado para gestao de transportes com integracao Progress OpenEdge, SemParar SOAP e NDD Cargo.

## Stack Tecnologica

| Camada | Tecnologia |
|--------|------------|
| Frontend | Vue 3.5.14 + TypeScript 5.8.3 + Vuexy + Vuetify 3.8.5 |
| Backend | Laravel 12.15.0 + Sanctum |
| Database | Progress OpenEdge (JDBC) + SQLite (cache) |
| Mapas | Leaflet + OpenStreetMap + OSRM (100% gratuito) |
| Build | Vite 6.3.5 + PNPM |

## Quick Start

```bash
# Backend (SEMPRE usar porta 8002!)
php artisan serve --port=8002

# Frontend
pnpm run dev

# Acessar
http://localhost:8002
# Login: admin@ndd.com / Admin@123
```

## Modulos Implementados

### Gestao de Transportes
- Listagem paginada com 6.913+ transportadores
- Filtros por tipo (Autonomo/Empresa), status, natureza
- Detalhes com motoristas e veiculos associados

### Gestao de Pacotes
- Listagem com filtros avancados (data, situacao, rota)
- Flag TCD para pacotes especiais
- Itinerario com GPS para simulacao no mapa

### Rotas SemParar (Mapa Interativo)
- Mapa Leaflet com municipios ordenados
- Drag & drop para reordenar
- Roteamento OSRM (gratuito) via proxy Laravel
- Simulacao de pacotes com entregas reais

### Compra de Viagem SemParar
- Wizard 5 etapas (Pacote -> Veiculo -> Rota -> Preco -> Confirmacao)
- Integracao SOAP com SemParar
- Geracao de recibos PDF + WhatsApp/Email

### VPO Emission (Vale Pedagio Obrigatorio) - NOVO
- Wizard completo para emissao de VPO
- Integracao NDD Cargo com assinatura digital RSA-SHA1
- Suporte para Autonomo (CPF) e Empresa (CNPJ)
- Roteirizador com pracas de pedagio enriquecidas
- Processamento assincrono com polling de resultado

### Pracas de Pedagio
- Import CSV da ANTT
- Busca por proximidade geografica
- Enriquecimento com coordenadas para mapa

## Arquitetura

```
Vue/Vuexy Frontend (Port 5173/4/6)
        |
        v HTTP API
Laravel Backend (Port 8002)
        |
        v JDBC Direct
Progress OpenEdge Database (192.168.80.113)

APIs Externas:
- Google Geocoding (IBGE -> coordenadas, cache 80%+)
- OSRM Public (roteamento gratuito, 3 servers com retry)
- SemParar SOAP (pedagio, 2 WSDLs)
- NDD Cargo SOAP (VPO, CrossTalk + RSA-SHA1)
- Python Flask (PDF + WhatsApp/Email)
```

## Endpoints Principais

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
POST /api/pacotes/itinerario       - Itinerario com GPS
```

### Rotas SemParar
```
GET  /api/semparar-rotas           - Lista rotas
GET  /api/semparar-rotas/{id}/municipios - Rota + municipios
PUT  /api/semparar-rotas/{id}/municipios - Atualizar ordem
```

### SemParar SOAP
```
GET  /api/semparar/test-connection - Testar conexao
POST /api/semparar/roteirizar      - Encontrar pracas
POST /api/semparar/comprar-viagem  - Comprar viagem ($$)
POST /api/semparar/gerar-recibo    - Gerar PDF
```

### VPO / NDD Cargo
```
POST /api/vpo/sync/transportador   - Sincronizar transportador
GET  /api/vpo/transportadores      - Listar cache
POST /api/vpo/emissao/validate     - Validar para emissao
POST /api/vpo/emissao/emit         - Emitir VPO

POST /api/ndd-cargo/roteirizador/consultar  - Consultar pracas
GET  /api/ndd-cargo/resultado/{guid}        - Resultado assincrono
```

### Geocoding & Routing
```
POST /api/geocoding/ibge           - Coordenadas por IBGE
POST /api/geocoding/lote           - Batch geocoding
POST /api/routing/route            - Proxy OSRM (gratuito)
```

## Regras Criticas

### 1. Progress Database - SEM TRANSACOES!
```php
// NUNCA usar beginTransaction/commit/rollBack
// Progress JDBC nao suporta!
$this->executeUpdate($sql1);
$this->executeUpdate($sql2);
```

### 2. OSRM - SEMPRE usar proxy Laravel
```typescript
// NUNCA usar leaflet-routing-machine direto (CORS)
// SEMPRE usar /api/routing/route
```

### 3. SemParar SOAP - Parametros posicionais
```php
// ERRADO: $client->method(['param' => $value]);
// CERTO:  $client->method($param1, $param2, $param3);
```

### 4. VPO Autonomo vs Empresa
```php
// Autonomo: dados em PUB.transporte
// Empresa: motorista em PUB.trnmot, veiculo em PUB.trnvei
if ($transportador['flgautonomo']) {
    $condutor = $transportador['nomtrn'];
} else {
    $motorista = $this->getMotoristaByCode($codmot);
    $condutor = $motorista['nommot'];
}
```

## Estrutura do Projeto

```
app/
  Http/Controllers/Api/
    TransporteController.php      - Transportadores
    PacoteController.php          - Pacotes
    SemPararRotaController.php    - Rotas CRUD
    SemPararController.php        - SOAP API
    CompraViagemController.php    - Wizard compra
    VpoController.php             - VPO sync/cache
    VpoEmissaoController.php      - VPO emissao
    NddCargoController.php        - NDD Cargo SOAP
    GeocodingController.php       - Geocoding
    RoutingController.php         - OSRM proxy
  Services/
    ProgressService.php           - JDBC (2574 linhas!)
    GeocodingService.php          - Google + cache
    SemParar/                     - SOAP client
    Vpo/                          - VPO services
    NddCargo/                     - NDD Cargo services

resources/ts/pages/
    transportes/                  - Lista transportadores
    pacotes/                      - Lista pacotes
    rotas-padrao/                 - Rotas + mapa interativo
    compra-viagem/                - Wizard compra
    vpo-emissao/                  - Wizard VPO
    pracas-pedagio/               - Pracas ANTT
```

## Comandos Uteis

```bash
# Desenvolvimento
php artisan serve --port=8002     # Backend
pnpm run dev                      # Frontend

# Testes
pnpm run typecheck                # TypeScript
pnpm run lint                     # ESLint
php artisan test                  # PHPUnit

# Build
pnpm run build                    # Producao

# Testar conexoes
curl http://localhost:8002/api/progress/test-connection
curl http://localhost:8002/api/semparar/test-connection
curl http://localhost:8002/api/ndd-cargo/test-connection
```

## Documentacao Adicional

- `CLAUDE.md` - Guia completo de desenvolvimento (LEIA PRIMEIRO!)
- `docs/integracoes/ndd-cargo/` - Documentacao NDD Cargo (15 arquivos)
- `docs/audits/` - Auditorias de seguranca
- `docs/bug-fixes/` - Correcoes aplicadas

## Estatisticas

- 21 Controllers
- 14 Services
- 60+ API Endpoints
- 21 Tabelas Progress (JDBC)
- 13 Tabelas Laravel (SQLite)
- 6.913+ Transportadores
- 800.000+ Pacotes

---

**NDD Transport Management System**

Desenvolvido por Psykhepathos
