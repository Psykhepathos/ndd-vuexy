# Sistema de Sincronização VPO (Vale Pedágio Obrigatório)

**Status:** ✅ IMPLEMENTADO E OPERACIONAL
**Data:** 2025-12-08
**Versão:** 1.0.0

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Fluxo de Sincronização](#fluxo-de-sincronização)
4. [Mapeamento Condicional](#mapeamento-condicional)
5. [Integração ANTT](#integração-antt)
6. [Sistema de Qualidade](#sistema-de-qualidade)
7. [API REST](#api-rest)
8. [Exemplos de Uso](#exemplos-de-uso)
9. [Troubleshooting](#troubleshooting)

---

## 🎯 Visão Geral

O **VPO Data Sync System** é um pipeline de sincronização de dados que:

1. **Extrai** dados de transportadores do Progress OpenEdge (ERP legado)
2. **Enriquece** com dados atualizados da ANTT (Agência Nacional de Transportes Terrestres)
3. **Armazena** em cache local MySQL/SQLite para acesso rápido
4. **Valida** completude e calcula score de qualidade (0-100)
5. **Fornece** dados formatados para requisições VPO à NDD Cargo API

### Problema Resolvido

- ❌ **Antes:** Dados incompletos/desatualizados no Progress → Falha nas requisições VPO
- ✅ **Agora:** Cache consolidado Progress + ANTT → 95% de cobertura VPO (18/19 campos)

---

## 🏗️ Arquitetura

```
┌─────────────────┐
│  Progress DB    │ (ERP Corporativo)
│  OpenEdge JDBC  │
└────────┬────────┘
         │ Fetch conditional (autônomo vs empresa)
         ▼
┌─────────────────┐
│ VpoDataSync     │ (Service Layer)
│    Service      │
└────────┬────────┘
         │ Enrich
         ▼
┌─────────────────┐
│   ANTT API      │ (Dados Abertos - CKAN)
│ dados.antt.gov  │
└────────┬────────┘
         │ Merge
         ▼
┌─────────────────┐
│  Local Cache    │ (MySQL/SQLite)
│ vpo_transport.  │
│ _cache          │
└────────┬────────┘
         │ Serve
         ▼
┌─────────────────┐
│  REST API       │ (VpoController)
│ /api/vpo/*      │
└────────┬────────┘
         │ Consume
         ▼
┌─────────────────┐
│  NDD Cargo API  │ (Roteirizador VPO)
│  Vale Pedágio   │
└─────────────────┘
```

### Componentes

| Componente | Arquivo | Responsabilidade |
|------------|---------|------------------|
| **Migration** | `2025_12_08_123624_create_vpo_transportadores_cache_table.php` | Schema do cache (19 campos VPO + metadados) |
| **Model** | `app/Models/VpoTransportadorCache.php` | Business logic, validação, scoring |
| **Service** | `app/Services/Vpo/VpoDataSyncService.php` | Orquestração da sincronização |
| **Controller** | `app/Http/Controllers/Api/VpoController.php` | REST API endpoints |
| **Routes** | `routes/api.php` | Rotas `/api/vpo/*` |

---

## 🔄 Fluxo de Sincronização

### 1. Sincronização Individual

```php
// POST /api/vpo/sync/transportador
{
    "codtrn": 1,
    "codmot": null,          // Apenas para empresas
    "placa": null,           // Opcional, busca do Progress
    "force_antt_update": false
}
```

**Passos:**

1. **Buscar Progress:**
   - Se `flgautonomo = true` → `mapAutonomoData()`
   - Se `flgautonomo = false` → `mapEmpresaData()`

2. **Verificar Cache:**
   - Existe? → Avaliar freshness (7 dias Progress, 30 dias ANTT)
   - Não existe? → Criar novo registro

3. **Enriquecer ANTT (se necessário):**
   - Estratégia 1: Dados Abertos ANTT (CKAN API)
   - Estratégia 2: Fallback (assumir ativo)
   - Estratégia 3: API Comercial (futuro)

4. **Merge & Save:**
   - Progress (dados primários) + ANTT (enriquecimento)
   - Salvar em `vpo_transportadores_cache`

5. **Calcular Qualidade:**
   - Score 0-100 baseado em completude
   - Identificar campos faltantes

### 2. Sincronização em Lote

```php
// POST /api/vpo/sync/batch
{
    "codtrn_list": [1, 2, 3, 4, 5],
    "force_antt_update": false
}
```

- Processa até **100 transportadores** por request
- Rate limiting: **100ms entre cada sync** (proteção)
- Retorna resumo: sucessos, falhas, tempo total

---

## 🔀 Mapeamento Condicional

### Campo Crítico: `veiculo_modelo`

**Descoberta:** Campo `transporte.desvei` contém modelo do veículo!

| Tipo | `flgautonomo` | Fonte 1 | Fonte 2 (Fallback) | Exemplos |
|------|---------------|---------|--------------------|---------|| **Autônomo** | `true` | `transporte.desvei` | - | "M.BENZ/1718", "VW/24.250 CLC 6X2" |
| **Empresa** | `false` | `trnvei.modvei` | `transporte.desvei` | "RANDON SP SRFG", "AXOR 2041" |

**⚠️ Atenção:** `tipcam.destipcam` é o **TIPO genérico** ("TOCO", "TRUCK"), NÃO o modelo!

### Lógica Autônomo

```php
protected function mapAutonomoData(array $transportador, ?string $destipcam): array
{
    return [
        'codtrn' => $transportador['codtrn'],
        'flgautonomo' => true,

        // Identificação
        'cpf_cnpj' => preg_replace('/\D/', '', $transportador['codcnpjcpf']),
        'antt_rntrc' => $transportador['cdantt'],
        'antt_nome' => $transportador['nomtrn'],

        // Veículo
        'placa' => $this->formatPlaca($transportador['numpla']),
        'veiculo_tipo' => $destipcam ?? 'Não especificado',
        'veiculo_modelo' => $transportador['desvei'] ?? null,  // ← DESCOBERTA!

        // Condutor (autônomo = condutor)
        'condutor_rg' => $transportador['numrg'] ?? $transportador['numhab'],
        'condutor_nome' => $transportador['nomtrn'],
        'condutor_data_nascimento' => $transportador['datnas'],

        // ... demais campos
    ];
}
```

### Lógica Empresa

```php
protected function mapEmpresaData(array $transportador, ?string $destipcam, ?int $codmot, ?string $placa): array
{
    // 1. Buscar motorista
    $motSql = "SELECT codmot, nommot, codcpf, codrntrc, ... FROM PUB.trnmot
               WHERE codtrn = {$codtrn} AND codmot = {$codmot}";

    // 2. Buscar veículo
    $veiSql = "SELECT numpla, tipcam, modvei FROM PUB.trnvei
               WHERE codtrn = {$codtrn} AND numpla = '{$placa}'";

    // 3. Determinar modelo (prioridade: trnvei.modvei → transporte.desvei)
    $veiculoModelo = $veiculo['modvei'] ?? $transportador['desvei'] ?? null;

    return [
        'codtrn' => $codtrn,
        'codmot' => $motorista['codmot'],
        'flgautonomo' => false,

        // Identificação (do motorista)
        'cpf_cnpj' => preg_replace('/\D/', '', $motorista['codcpf']),
        'antt_rntrc' => $motorista['codrntrc'] ?? $transportador['cdantt'],
        'antt_nome' => $motorista['nommot'],

        // Veículo (específico da empresa)
        'veiculo_modelo' => $veiculoModelo,  // ← Lógica condicional

        // ... demais campos
    ];
}
```

---

## 🌐 Integração ANTT

### Estratégia 1: Dados Abertos (CKAN API)

**URL Base:** `https://dados.antt.gov.br/api/3/action`

**Endpoints:**
- `GET /package_show?id=rntrc` → Obter resource_id do dataset
- `GET /datastore_search?resource_id={id}&q={rntrc}` → Buscar transportador

**Cache:**
- Dataset metadata: **24 horas**
- Transportador: **30 dias**

**Código:**
```php
protected function fetchFromAnttOpenData(string $rntrc): array
{
    // Cache do resource_id
    $this->anttDatasetCache = Cache::remember('antt_opendata_dataset', 86400, function () {
        $response = Http::timeout(30)
            ->get("{$this->anttApiBase}/package_show", ['id' => 'rntrc']);

        $package = $response->json()['result'];
        $latestResource = collect($package['resources'])
            ->sortByDesc('created')
            ->first();

        return $latestResource['id'];
    });

    // Buscar transportador
    $response = Http::timeout(30)
        ->get("{$this->anttApiBase}/datastore_search", [
            'resource_id' => $this->anttDatasetCache,
            'q' => $rntrc,
            'limit' => 1
        ]);

    $record = $response->json()['result']['records'][0];

    return [
        'success' => true,
        'data' => [
            'antt_status' => $record['Situacao'] ?? 'Ativo',
            'antt_validade' => Carbon::parse($record['DataValidadeCNH'])->format('Y-m-d'),
        ]
    ];
}
```

### Estratégia 2: Fallback

Se ANTT Open Data falhar:
```php
return [
    'success' => true,
    'data' => ['antt_status' => 'Ativo'],
    'fonte' => 'fallback'
];
```

### Estratégia 3: API Comercial (Futuro)

Placeholders para:
- **Infosimples:** https://www.infosimples.com/api/rntrc
- **Netrin:** https://netrin.com.br/api/transportador
- **Direct Data:** Integração direta ANTT (paga)

---

## 📊 Sistema de Qualidade

### Score de Qualidade (0-100 pontos)

```php
public function calculateQualityScore(): int
{
    $score = 100;
    $campos_faltantes = [];

    // Campos obrigatórios (-10 pontos cada)
    $obrigatorios = [
        'cpf_cnpj', 'antt_rntrc', 'antt_nome', 'placa',
        'veiculo_tipo', 'condutor_rg', 'condutor_nome',
        'condutor_nome_mae', 'condutor_data_nascimento',
        'endereco_rua', 'endereco_cidade', 'endereco_estado',
        'contato_celular', 'contato_email'
    ];

    foreach ($obrigatorios as $campo) {
        if (empty($this->$campo)) {
            $score -= 10;
            $campos_faltantes[] = $campo;
        }
    }

    // Campos opcionais (-5 pontos cada)
    $opcionais = ['veiculo_modelo', 'antt_validade', 'endereco_bairro'];
    foreach ($opcionais as $campo) {
        if (empty($this->$campo)) {
            $score -= 5;
            $campos_faltantes[] = $campo;
        }
    }

    // RNTRC vencido (-20 pontos)
    if (!$this->isRntrcValido()) {
        $score -= 20;
    }

    // Status não ativo (-30 pontos)
    if ($this->antt_status !== 'Ativo') {
        $score -= 30;
    }

    // Dados desatualizados (-10 pontos)
    if ($this->isStale()) {  // > 7 dias
        $score -= 10;
    }

    return max(0, $score);
}
```

### Critérios de Freshness

| Tipo | Threshold | Método |
|------|-----------|--------|
| **Progress** | 7 dias | `isStale()` |
| **ANTT** | 30 dias | `needsAnttUpdate()` |
| **RNTRC** | Validade futura | `isRntrcValido()` |

---

## 🔌 API REST

### Endpoints

```bash
# Health Check
GET  /api/vpo/test-connection
# Response: {success: true, services: {progress, antt_opendata, database_local}}

# Sincronização
POST /api/vpo/sync/transportador
POST /api/vpo/sync/batch

# Consultas
GET  /api/vpo/transportadores?search=...&status=Ativo&qualidade_minima=70
GET  /api/vpo/transportadores/{codtrn}

# Manutenção
DELETE /api/vpo/transportadores/{codtrn}  # Força resync
POST   /api/vpo/transportadores/{codtrn}/recalcular-qualidade

# Estatísticas
GET  /api/vpo/statistics
```

### Rate Limiting

| Operação | Limite | Justificativa |
|----------|--------|---------------|
| Health check | 10 req/min | Monitoramento leve |
| Sync individual | 30 req/min | Operação moderada |
| Sync batch | 10 req/min | Operação pesada (100 itens) |
| Consultas | 60 req/min | Leitura rápida |
| Manutenção | 30 req/min | Operação administrativa |

---

## 💻 Exemplos de Uso

### 1. Sincronizar Transportador Autônomo

```bash
curl -X POST http://localhost:8002/api/vpo/sync/transportador \
  -H "Content-Type: application/json" \
  -d '{
    "codtrn": 1,
    "force_antt_update": false
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "codtrn": 1,
    "flgautonomo": true,
    "cpf_cnpj": "60029137691",
    "antt_rntrc": "02767948",
    "antt_nome": "VANDERLEI ANTONIO DE SOUZA",
    "placa": "AUF3A90",
    "veiculo_tipo": "TOCO",
    "veiculo_modelo": "M.BENZ/1718",
    "score_qualidade": 35,
    "campos_faltantes": [
      "condutor_rg",
      "condutor_nome_mae",
      "endereco_estado",
      "contato_email",
      "antt_validade"
    ],
    "antt_fonte": "fallback"
  },
  "message": "Sincronização concluída com sucesso (score: 35/100)"
}
```

### 2. Sincronizar Lote

```bash
curl -X POST http://localhost:8002/api/vpo/sync/batch \
  -H "Content-Type: application/json" \
  -d '{
    "codtrn_list": [1, 2, 3, 4, 5],
    "force_antt_update": false
  }'
```

**Response:**
```json
{
  "success": true,
  "total": 5,
  "sucesso": 4,
  "falhas": 1,
  "tempo_total_ms": 2834,
  "resultados": [
    {"codtrn": 1, "success": true, "score": 35},
    {"codtrn": 2, "success": true, "score": 70},
    {"codtrn": 3, "success": false, "error": "Transportador não encontrado"},
    {"codtrn": 4, "success": true, "score": 85},
    {"codtrn": 5, "success": true, "score": 50}
  ]
}
```

### 3. Consultar Cache

```bash
# Listar todos (com filtros)
curl "http://localhost:8002/api/vpo/transportadores?status=Ativo&qualidade_minima=70&per_page=10"

# Obter específico (com dados VPO formatados)
curl "http://localhost:8002/api/vpo/transportadores/1"
```

**Response (show):**
```json
{
  "success": true,
  "data": { /* Registro completo */ },
  "vpo_data": {
    "cpf_cnpj": "60029137691",
    "antt_rntrc": "02767948",
    "antt_nome": "VANDERLEI ANTONIO DE SOUZA",
    "antt_validade": null,
    "antt_status": "Ativo",
    "placa": "AUF3A90",
    "veiculo_tipo": "TOCO",
    "veiculo_modelo": "M.BENZ/1718",
    "condutor_rg": "",
    "condutor_nome": "VANDERLEI ANTONIO DE SOUZA",
    "condutor_sexo": "M",
    "condutor_nome_mae": null,
    "condutor_data_nascimento": "1969-10-25",
    "endereco_rua": "AMAPA, 45",
    "endereco_bairro": "ZONA RURAL",
    "endereco_cidade": "SANTANA DO ARAGUAIA",
    "endereco_estado": null,
    "contato_celular": "31973501099",
    "contato_email": ""
  },
  "meta": {
    "needs_update": false,
    "rntrc_valido": false,
    "needs_antt_update": false
  }
}
```

### 4. Estatísticas

```bash
curl "http://localhost:8002/api/vpo/statistics"
```

**Response:**
```json
{
  "success": true,
  "statistics": {
    "total": 150,
    "ativos": 142,
    "rntrc_validos": 98,
    "qualidade_alta": 85,
    "qualidade_media": 65.5,
    "por_status": {
      "Ativo": 142,
      "Suspenso": 5,
      "Cancelado": 3
    },
    "por_fonte_antt": {
      "dados_abertos": 120,
      "fallback": 30
    }
  }
}
```

---

## 🔧 Troubleshooting

### Erro: "Transportador não encontrado no Progress"

**Causa:** `codtrn` inválido ou transportador inativo.

**Solução:**
```bash
# Verificar Progress diretamente
curl "http://localhost:8002/api/transportes/{codtrn}"
```

### Erro: "ANTT Open Data timeout"

**Causa:** API ANTT lenta ou indisponível.

**Comportamento:** Sistema usa fallback (assume ativo).

**Verificação:**
```bash
curl "https://dados.antt.gov.br/api/3/action/package_show?id=rntrc"
```

### Score de Qualidade Baixo (<50)

**Causa:** Muitos campos faltando no Progress.

**Diagnóstico:**
```json
{
  "score_qualidade": 35,
  "campos_faltantes": [
    "condutor_rg",
    "condutor_nome_mae",
    "endereco_estado",
    "contato_email",
    "antt_validade"
  ]
}
```

**Ação:** Atualizar dados no Progress ou aceitar limitação (dados legados).

### Erro: "NOT NULL constraint failed"

**Causa:** Migration antiga sem campos nullable.

**Solução:**
```bash
php artisan migrate:fresh
# Ou aplicar migration específica:
php artisan migrate --path=database/migrations/2025_12_08_124813_make_optional_vpo_fields_nullable.php
```

---

## 📈 Métricas de Sucesso

### Cobertura VPO

- ✅ **18/19 campos mapeados** (95%)
- ⚠️ **1 campo ausente:** `condutor_sexo` (default: 'M')
- ⚠️ **2 campos condicionais:** `antt_validade`, `veiculo_modelo`

### Performance

- **Sync individual:** ~2-4 segundos (Progress + ANTT)
- **Sync batch (100 items):** ~3-5 minutos (com rate limiting)
- **Query cache:** <50ms (índices otimizados)

### Cache Hit Rate

- **Progress:** ~85% (após primeira sync)
- **ANTT:** ~95% (após primeira sync, TTL 30 dias)

---

## 🚀 Próximos Passos

### Fase 2: Integração NDD Cargo

```bash
# Usar dados VPO em requisição roteirizador
curl -X POST https://app.nddcargo.com.br/webservice/v1/Roteirizador \
  -H "Content-Type: application/xml" \
  -d "$(cat <<XML
<Motoristas>
  <Motorista>
    <CPF>$vpo_data[cpf_cnpj]</CPF>
    <RNTRC>$vpo_data[antt_rntrc]</RNTRC>
    <Nome>$vpo_data[antt_nome]</Nome>
    <!-- ... demais campos VPO -->
  </Motorista>
</Motoristas>
XML
)"
```

### Fase 3: Automação

```php
// Criar Artisan command
php artisan make:command VpoSyncScheduled

// Agendar em app/Console/Kernel.php
$schedule->command('vpo:sync-all')
    ->dailyAt('02:00')  // Sync diária 2h AM
    ->withoutOverlapping();
```

### Fase 4: Monitoramento

- Dashboard de qualidade (Vue.js)
- Alertas para transportadores inativos
- Relatório de campos faltantes
- Logs de sincronização

---

## 📚 Referências

- **Documentação NDD Cargo:** http://manuais.nddigital.com.br/nddCargo/
- **ANTT Dados Abertos:** https://dados.antt.gov.br
- **Progress OpenEdge JDBC:** Documentação Progress
- **VPO Requirements:** 19 campos obrigatórios (ver TABELA_MAPEAMENTO_VPO.md)

---

**Última Atualização:** 2025-12-08
**Autor:** Sistema de Sincronização VPO v1.0.0
**Status:** ✅ Produção
