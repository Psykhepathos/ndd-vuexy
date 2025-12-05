# Plano de Implementação: Sistema de Importação de Praças de Pedágio ANTT

**Branch:** `feature/praca-pedagio-import`
**Data:** 2025-11-21
**Objetivo:** Criar sistema completo para importar, armazenar e visualizar praças de pedágio da ANTT nos mapas do sistema

---

## 📋 Análise do CSV

**Arquivo fonte:** `dados_das_pracas_de_pedagio.csv` (ANTT)

**Estrutura:**
```csv
concessionaria;praca_de_pedagio;ano_do_pnv_snv;rodovia;uf;km_m;municipio;tipo_pista;sentido;situacao;data_da_inativacao;latitude;longitude
```

**Campos importantes:**
- `concessionaria` - Nome da concessionária (ex: "AUTOPISTA FERNÃO DIAS")
- `praca_de_pedagio` - Nome da praça (ex: "1 Norte (Mairiporã)")
- `rodovia` - BR-XXX (ex: "BR-381")
- `uf` - Sigla do estado (ex: "SP", "MG")
- `km_m` - Quilômetro (ex: "67,800")
- `municipio` - Nome do município
- `tipo_pista` - Tipo (ex: "Principal")
- `sentido` - Direção (ex: "Crescente/Decrescente")
- `situacao` - Status (ex: "Ativo", "Inativo")
- `latitude` - Coordenada (ex: "-23,341210")
- `longitude` - Coordenada (ex: "-46,573664")

**Problemas encontrados:**
- ✅ Delimitador: ponto-e-vírgula (`;`)
- ⚠️ Encoding: Windows-1252/ISO-8859-1 (acentos aparecem como `�`)
- ⚠️ Decimal: vírgula (`,`) precisa ser convertida para ponto (`.`)

---

## 🏗️ FASE 1: Backend - Database & Models

### 1.1 Migration - Tabela `pracas_pedagio`

**Arquivo:** `database/migrations/YYYY_MM_DD_create_pracas_pedagio_table.php`

```php
Schema::create('pracas_pedagio', function (Blueprint $table) {
    $table->id();

    // Dados da praça
    $table->string('concessionaria', 100);
    $table->string('praca', 100);  // Nome da praça
    $table->string('rodovia', 20);  // BR-XXX
    $table->string('uf', 2);
    $table->decimal('km', 8, 3);  // Quilômetro (999999.999)
    $table->string('municipio', 100);

    // Classificação
    $table->integer('ano_pnv')->nullable();
    $table->string('tipo_pista', 50)->nullable();
    $table->string('sentido', 50)->nullable();

    // Status
    $table->enum('situacao', ['Ativo', 'Inativo'])->default('Ativo');
    $table->date('data_inativacao')->nullable();

    // Coordenadas (CRÍTICO para mapas)
    $table->decimal('latitude', 10, 7);   // -99.9999999
    $table->decimal('longitude', 10, 7);  // -999.9999999

    // Metadados
    $table->string('fonte', 50)->default('ANTT');  // Origem do dado
    $table->date('data_importacao')->nullable();

    // Índices para performance
    $table->index('situacao');
    $table->index('rodovia');
    $table->index('uf');
    $table->index(['latitude', 'longitude']);  // Busca geográfica

    $table->timestamps();
});
```

**Comando:**
```bash
php artisan make:migration create_pracas_pedagio_table
```

### 1.2 Model - PracaPedagio

**Arquivo:** `app/Models/PracaPedagio.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracaPedagio extends Model
{
    protected $table = 'pracas_pedagio';

    protected $fillable = [
        'concessionaria',
        'praca',
        'rodovia',
        'uf',
        'km',
        'municipio',
        'ano_pnv',
        'tipo_pista',
        'sentido',
        'situacao',
        'data_inativacao',
        'latitude',
        'longitude',
        'fonte',
        'data_importacao'
    ];

    protected $casts = [
        'km' => 'decimal:3',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'data_inativacao' => 'date',
        'data_importacao' => 'date',
        'ano_pnv' => 'integer'
    ];

    // Scopes
    public function scopeAtivas($query)
    {
        return $query->where('situacao', 'Ativo');
    }

    public function scopePorRodovia($query, $rodovia)
    {
        return $query->where('rodovia', $rodovia);
    }

    public function scopePorUf($query, $uf)
    {
        return $query->where('uf', $uf);
    }

    public function scopeProximasDe($query, $lat, $lon, $raioKm = 50)
    {
        // Busca praças próximas usando Haversine formula
        // 1 grau ≈ 111km
        $delta = $raioKm / 111;

        return $query->whereBetween('latitude', [$lat - $delta, $lat + $delta])
                     ->whereBetween('longitude', [$lon - $delta, $lon + $delta]);
    }
}
```

**Comando:**
```bash
php artisan make:model PracaPedagio
```

---

## 🔧 FASE 2: Backend - Service de Importação

### 2.1 Service - PracaPedagioImportService

**Arquivo:** `app/Services/PracaPedagioImportService.php`

```php
<?php

namespace App\Services;

use App\Models\PracaPedagio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PracaPedagioImportService
{
    /**
     * Importar CSV da ANTT
     */
    public function importarCSV(string $filePath): array
    {
        $imported = 0;
        $errors = [];
        $startTime = microtime(true);

        try {
            // Detectar encoding (Windows-1252 -> UTF-8)
            $content = file_get_contents($filePath);
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');

            // Salvar temporariamente
            $tempPath = sys_get_temp_dir() . '/pracas_utf8.csv';
            file_put_contents($tempPath, $content);

            // Abrir CSV
            $handle = fopen($tempPath, 'r');
            if (!$handle) {
                throw new \Exception('Não foi possível abrir o arquivo CSV');
            }

            // Ler header
            $header = fgetcsv($handle, 0, ';');

            // Processar linhas
            DB::beginTransaction();

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                try {
                    $data = array_combine($header, $row);

                    PracaPedagio::updateOrCreate(
                        [
                            'praca' => $data['praca_de_pedagio'],
                            'rodovia' => $data['rodovia'],
                            'km' => $this->parseDecimal($data['km_m'])
                        ],
                        [
                            'concessionaria' => $data['concessionaria'],
                            'uf' => $data['uf'],
                            'municipio' => $data['municipio'],
                            'ano_pnv' => $data['ano_do_pnv_snv'] ?: null,
                            'tipo_pista' => $data['tipo_pista'],
                            'sentido' => $data['sentido'],
                            'situacao' => $data['situacao'],
                            'data_inativacao' => $data['data_da_inativacao'] ?: null,
                            'latitude' => $this->parseDecimal($data['latitude']),
                            'longitude' => $this->parseDecimal($data['longitude']),
                            'fonte' => 'ANTT',
                            'data_importacao' => now()
                        ]
                    );

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'line' => $imported + 2, // +2 = header + 1-indexed
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                    Log::warning('Erro ao importar praça', [
                        'line' => $imported + 2,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            fclose($handle);
            unlink($tempPath);

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Importação de praças concluída', [
                'imported' => $imported,
                'errors' => count($errors),
                'duration' => $duration . 's'
            ]);

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'duration' => $duration
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erro fatal na importação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'imported' => $imported,
                'errors' => $errors
            ];
        }
    }

    /**
     * Converter string com vírgula para decimal
     */
    private function parseDecimal(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    /**
     * Limpar todas as praças (CUIDADO!)
     */
    public function limparTudo(): bool
    {
        try {
            PracaPedagio::truncate();
            Log::warning('Todas as praças foram removidas do banco');
            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao limpar praças', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Estatísticas do banco
     */
    public function getEstatisticas(): array
    {
        return [
            'total' => PracaPedagio::count(),
            'ativas' => PracaPedagio::ativas()->count(),
            'inativas' => PracaPedagio::where('situacao', 'Inativo')->count(),
            'por_uf' => PracaPedagio::select('uf', DB::raw('count(*) as total'))
                ->groupBy('uf')
                ->orderBy('total', 'desc')
                ->get(),
            'por_concessionaria' => PracaPedagio::select('concessionaria', DB::raw('count(*) as total'))
                ->groupBy('concessionaria')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
        ];
    }
}
```

**Comando:**
```bash
php artisan make:service PracaPedagioImportService
```

---

## 🌐 FASE 3: Backend - API Controller

### 3.1 Controller - PracaPedagioController

**Arquivo:** `app/Http/Controllers/Api/PracaPedagioController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracaPedagio;
use App\Services\PracaPedagioImportService;
use Illuminate\Http\Request;

class PracaPedagioController extends Controller
{
    protected $importService;

    public function __construct(PracaPedagioImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * POST /api/pracas-pedagio/importar
     */
    public function importar(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240' // 10MB
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $result = $this->importService->importarCSV($path);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => "Importação concluída: {$result['imported']} praças importadas",
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro na importação',
            'error' => $result['error'] ?? 'Erro desconhecido',
            'data' => $result
        ], 500);
    }

    /**
     * GET /api/pracas-pedagio/estatisticas
     */
    public function estatisticas()
    {
        $stats = $this->importService->getEstatisticas();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * GET /api/pracas-pedagio
     */
    public function index(Request $request)
    {
        $query = PracaPedagio::query();

        // Filtros
        if ($request->has('rodovia')) {
            $query->porRodovia($request->rodovia);
        }

        if ($request->has('uf')) {
            $query->porUf($request->uf);
        }

        if ($request->has('situacao')) {
            $query->where('situacao', $request->situacao);
        } else {
            $query->ativas(); // Default: apenas ativas
        }

        // Busca por proximidade
        if ($request->has('lat') && $request->has('lon')) {
            $raio = $request->get('raio_km', 50);
            $query->proximasDe($request->lat, $request->lon, $raio);
        }

        $pracas = $query->orderBy('rodovia')->orderBy('km')->get();

        return response()->json([
            'success' => true,
            'data' => $pracas
        ]);
    }

    /**
     * GET /api/pracas-pedagio/{id}
     */
    public function show($id)
    {
        $praca = PracaPedagio::find($id);

        if (!$praca) {
            return response()->json([
                'success' => false,
                'message' => 'Praça não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $praca
        ]);
    }

    /**
     * DELETE /api/pracas-pedagio/limpar (ADMIN ONLY)
     */
    public function limpar()
    {
        $result = $this->importService->limparTudo();

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Todas as praças foram removidas'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro ao limpar praças'
        ], 500);
    }
}
```

**Comando:**
```bash
php artisan make:controller Api/PracaPedagioController
```

### 3.2 Rotas API

**Arquivo:** `routes/api.php` (adicionar)

```php
// Praças de Pedágio
Route::prefix('pracas-pedagio')->group(function () {
    Route::get('/', [PracaPedagioController::class, 'index']);
    Route::get('/estatisticas', [PracaPedagioController::class, 'estatisticas']);
    Route::get('/{id}', [PracaPedagioController::class, 'show']);

    // Admin only
    Route::post('/importar', [PracaPedagioController::class, 'importar']);
    Route::delete('/limpar', [PracaPedagioController::class, 'limpar']);
});
```

---

## 🎨 FASE 4: Frontend - Tela de Importação

### 4.1 Página de Importação

**Arquivo:** `resources/ts/pages/pracas-pedagio/importar.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue'

const file = ref<File | null>(null)
const loading = ref(false)
const success = ref(false)
const error = ref<string | null>(null)
const result = ref<any>(null)

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  file.value = target.files?.[0] || null
}

const importar = async () => {
  if (!file.value) return

  loading.value = true
  success.value = false
  error.value = null

  try {
    const formData = new FormData()
    formData.append('file', file.value)

    const response = await fetch('http://localhost:8002/api/pracas-pedagio/importar', {
      method: 'POST',
      body: formData
    })

    const data = await response.json()

    if (!data.success) {
      throw new Error(data.message || 'Erro ao importar')
    }

    result.value = data.data
    success.value = true

  } catch (err: any) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const limpar = async () => {
  if (!confirm('ATENÇÃO: Isso irá deletar TODAS as praças do banco. Confirma?')) return

  loading.value = true
  try {
    const response = await fetch('http://localhost:8002/api/pracas-pedagio/limpar', {
      method: 'DELETE'
    })

    const data = await response.json()
    if (data.success) {
      alert('Todas as praças foram removidas')
    }
  } catch (err) {
    alert('Erro ao limpar praças')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle>Importar Praças de Pedágio (ANTT)</VCardTitle>
          <VCardText>
            <VFileInput
              label="Selecione o arquivo CSV"
              accept=".csv"
              @change="handleFileChange"
            />

            <VBtn
              color="primary"
              :loading="loading"
              :disabled="!file"
              @click="importar"
            >
              Importar
            </VBtn>

            <VBtn
              color="error"
              variant="outlined"
              class="ms-2"
              @click="limpar"
            >
              Limpar Tudo
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol v-if="success" cols="12">
        <VAlert type="success">
          <VAlertTitle>Importação Concluída</VAlertTitle>
          <div>Importadas: {{ result.imported }}</div>
          <div>Erros: {{ result.errors.length }}</div>
          <div>Duração: {{ result.duration }}s</div>
        </VAlert>
      </VCol>

      <VCol v-if="error" cols="12">
        <VAlert type="error">{{ error }}</VAlert>
      </VCol>
    </VRow>
  </div>
</template>
```

---

## 🗺️ FASE 5: Integração com Mapas

### 5.1 Atualizar MapService para incluir praças

**Arquivo:** `app/Services/MapService.php` (adicionar método)

```php
public function getPracasPedagioProximas(array $waypoints, float $raioKm = 10): array
{
    $pracas = [];

    foreach ($waypoints as $waypoint) {
        [$lat, $lon] = $waypoint;

        $pracasProximas = PracaPedagio::ativas()
            ->proximasDe($lat, $lon, $raioKm)
            ->get();

        foreach ($pracasProximas as $praca) {
            $pracas[] = [
                'id' => $praca->id,
                'nome' => $praca->praca,
                'rodovia' => $praca->rodovia,
                'km' => $praca->km,
                'concessionaria' => $praca->concessionaria,
                'latitude' => (float) $praca->latitude,
                'longitude' => (float) $praca->longitude
            ];
        }
    }

    // Remover duplicatas
    return array_values(array_unique($pracas, SORT_REGULAR));
}
```

### 5.2 Atualizar CompraViagemMapaFixo para exibir praças

**Arquivo:** `resources/ts/pages/compra-viagem/components/CompraViagemMapaFixo.vue`

```typescript
// Adicionar praças de pedágio ANTT ao mapa
const carregarPracasANTT = async () => {
  if (!props.formData.rota.municipios.length) return

  try {
    // Buscar praças próximas da rota
    const lats = props.formData.rota.municipios.map(m => m.lat).filter(Boolean)
    const lons = props.formData.rota.municipios.map(m => m.lon).filter(Boolean)

    if (!lats.length) return

    const minLat = Math.min(...lats)
    const maxLat = Math.max(...lats)
    const minLon = Math.min(...lons)
    const maxLon = Math.max(...lons)

    const response = await fetch(
      `http://localhost:8002/api/pracas-pedagio?lat=${(minLat + maxLat) / 2}&lon=${(minLon + maxLon) / 2}&raio_km=100`
    )

    const data = await response.json()

    if (data.success && data.data.length > 0) {
      data.data.forEach((praca: any) => {
        const icon = L.divIcon({
          html: `<div style="background: #FFC107; color: #000; border: 2px solid #FF6F00; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px;">🛑</div>`,
          className: 'praca-antt-marker',
          iconSize: [20, 20]
        })

        const marker = L.marker([praca.latitude, praca.longitude], { icon })
          .bindPopup(`
            <strong>🛑 ${praca.nome}</strong><br>
            ${praca.rodovia} - KM ${praca.km}<br>
            ${praca.concessionaria}
          `)

        marker.addTo(markersLayer.value!)
      })

      console.log(`✅ ${data.data.length} praças ANTT adicionadas ao mapa`)
    }
  } catch (error) {
    console.error('Erro ao carregar praças ANTT:', error)
  }
}

// Chamar após desenhar rota
onMounted(async () => {
  await initMap()
  await carregarPracasANTT()
})
```

---

## 📝 Checklist de Implementação

### Backend
- [ ] Criar migration `create_pracas_pedagio_table`
- [ ] Criar model `PracaPedagio` com scopes
- [ ] Criar service `PracaPedagioImportService`
- [ ] Criar controller `PracaPedagioController`
- [ ] Adicionar rotas em `routes/api.php`
- [ ] Testar importação com CSV da ANTT
- [ ] Validar dados importados no banco

### Frontend
- [ ] Criar página `pracas-pedagio/importar.vue`
- [ ] Adicionar rota no router
- [ ] Adicionar item no menu lateral
- [ ] Testar upload e importação
- [ ] Criar página de listagem (opcional)

### Integração com Mapas
- [ ] Atualizar `MapService` para incluir praças
- [ ] Atualizar `CompraViagemMapaFixo` para exibir praças
- [ ] Atualizar `CompraViagemStep4Preco` para exibir praças ANTT
- [ ] Testar visualização em rotas reais

### Testes
- [ ] Importar CSV completo da ANTT
- [ ] Validar encoding de acentos
- [ ] Validar coordenadas
- [ ] Testar filtros (rodovia, UF, proximidade)
- [ ] Testar visualização no mapa

---

## 🚀 Ordem de Execução

1. **Criar database** (migration + model)
2. **Criar service** de importação
3. **Criar controller** e rotas API
4. **Testar importação** via Postman/cURL
5. **Criar frontend** de importação
6. **Integrar com mapas** existentes
7. **Testar end-to-end**

---

## 📊 Estimativa de Dados

**CSV da ANTT:**
- Estimativa: 500-1000 praças
- Tamanho: ~200KB
- Tempo de importação: 2-5 segundos

**Banco de dados:**
- Espaço: ~500KB-1MB
- Índices: ~100KB

---

## 🔒 Segurança

- ✅ Validação de arquivo (CSV, max 10MB)
- ✅ Conversão de encoding automática
- ✅ Transação de banco (rollback em erro)
- ✅ Rate limiting nos endpoints
- ⚠️ Endpoint de limpeza deve ter autenticação admin
- ⚠️ Endpoint de importação deve ter autenticação admin

---

## 📚 Documentação Adicional

- ANTT: https://dados.antt.gov.br/
- Haversine Formula: https://en.wikipedia.org/wiki/Haversine_formula
- Laravel File Upload: https://laravel.com/docs/11.x/filesystem
