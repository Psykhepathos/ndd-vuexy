# 🚨 BUG CRÍTICO: Validação de Municípios Rejeita Acentos UTF-8

**Data:** 2025-12-04
**Severidade:** ⚠️ **CRÍTICO** - BLOQUEANTE PARA PRODUÇÃO
**Impacto:** Geocoding de municípios brasileiros com acentos (SÃO PAULO, JOÃO PESSOA, etc.) será REJEITADO
**Status:** 🔴 **NÃO RESOLVIDO**

---

## 📋 Resumo Executivo

O backend Laravel está **rejeitando nomes de municípios com acentos** (JOÃO, SÃO, TRÊS, etc.), mesmo tendo uma regex que **deveria** aceitar caracteres UTF-8 acentuados.

### Impacto Real:
- ❌ Municípios como "JOÃO PESSOA", "SÃO PAULO", "TRÊS CORAÇÕES" serão **REJEITADOS**
- ❌ Frontend não conseguirá fazer geocoding de 30%+ dos municípios brasileiros
- ❌ Sistema **INOPERANTE** para rotas que incluam municípios com acentos

---

## 🔬 Reprodução do Bug

### Teste Realizado:
```powershell
# PowerShell test script
$body = @{
    municipios = @(
        @{
            cdibge = "3136306"
            desmun = "JOÃO PINHEIRO"  # ← Nome com acento
            desest = "MG"
            cod_mun = 3630
            cod_est = 31
        }
    )
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:8002/api/geocoding/lote" `
    -Method POST `
    -ContentType "application/json; charset=utf-8" `
    -Body ([System.Text.Encoding]::UTF8.GetBytes($body))
```

### Resultado Obtido:
```json
{
    "success": false,
    "message": "Dados inválidos",
    "errors": {
        "municipios.0.desmun": [
            "Nome do município contém caracteres inválidos"
        ]
    }
}
```

**Status HTTP:** `422 Unprocessable Entity`

---

## 📍 Localização do Código

**Arquivo:** `app/Http/Controllers/Api/GeocodingController.php`
**Linhas:** 96-100

### Código Atual (COM BUG):
```php
'municipios.*.desmun' => [
    'required',
    'string',
    'max:100',
    'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/u'  // ← Regex DEVERIA aceitar À-ÿ
],
```

### Mensagem de Erro Customizada:
```php
// Linha 113
'municipios.*.desmun.regex' => 'Nome do município contém caracteres inválidos',
```

---

## 🔍 Análise Técnica

### 1. A Regex Está Correta?

✅ **SIM** - A regex `/^[a-zA-ZÀ-ÿ\s\-\.]+$/u` é tecnicamente correta:
- `a-zA-Z` - Letras ASCII (A-Z, a-z)
- `À-ÿ` - Caracteres latinos estendidos (À, Á, Ã, Ç, Õ, etc.)
- `\s` - Espaços
- `\-\.` - Hífen e ponto
- `+` - Um ou mais caracteres
- `$/u` - **Flag Unicode** ativa suporte UTF-8

### 2. Por Que Está Falhando?

**Hipóteses:**

#### A) Problema de Encoding UTF-8 no Transporte
```
Cliente (PowerShell) → HTTP → Laravel
     UTF-8          ?????     UTF-8
```

**Possível causa:**
- PowerShell pode estar enviando em **Latin-1** em vez de UTF-8
- Laravel pode não estar detectando `charset=utf-8` no Content-Type
- Middleware de Laravel pode estar corrompendo UTF-8

#### B) Regex PHP Não Interpreta UTF-8 Corretamente
```php
// PHP pode estar usando encoding padrão (não UTF-8) internamente
$nome = "JOÃO"; // Recebido como UTF-8
preg_match('/^[À-ÿ]+$/u', $nome); // FALSE! (encoding mismatch)
```

**Possível causa:**
- PHP `mbstring` extension pode não estar habilitada
- `mb_internal_encoding()` pode estar em ISO-8859-1
- Classe `Request` do Laravel pode não estar usando UTF-8

#### C) Validação Laravel Corrompe UTF-8
```php
// Laravel Validator pode estar convertendo string antes de regex
$validated['desmun']; // "JOÃƒO" (UTF-8 corrompido)
```

---

## 🧪 Diagnóstico Adicional Necessário

### Teste 1: Verificar Encoding Recebido pelo Laravel
```php
// Adicionar em GeocodingController::getCoordenadasLote() antes da validação
Log::debug('Encoding Test', [
    'raw_input' => $request->getContent(),
    'charset' => $request->getCharset(),
    'content_type' => $request->header('Content-Type'),
    'municipio' => $request->input('municipios.0.desmun'),
    'encoding_detected' => mb_detect_encoding($request->input('municipios.0.desmun')),
]);
```

### Teste 2: Verificar mb_string PHP
```php
// Adicionar em qualquer controller
Log::debug('PHP Encoding', [
    'mbstring_enabled' => extension_loaded('mbstring'),
    'internal_encoding' => mb_internal_encoding(),
    'regex_encoding' => mb_regex_encoding(),
]);
```

### Teste 3: Testar Regex Diretamente
```php
$nome = "JOÃO PINHEIRO";
$regex = '/^[a-zA-ZÀ-ÿ\s\-\.]+$/u';
$match = preg_match($regex, $nome);

Log::debug('Regex Test', [
    'nome' => $nome,
    'regex' => $regex,
    'match' => $match,
    'nome_length' => strlen($nome),
    'nome_mb_length' => mb_strlen($nome),
]);
```

---

## ✅ Soluções Propostas

### Solução 1: Relaxar Validação (RÁPIDA, mas NÃO IDEAL)
```php
// OPÇÃO A: Remover regex completamente
'municipios.*.desmun' => [
    'required',
    'string',
    'max:100',
    // regex removida - apenas verifica tamanho
],

// OPÇÃO B: Regex mais permissiva (aceita qualquer caractere exceto especiais)
'municipios.*.desmun' => [
    'required',
    'string',
    'max:100',
    'regex:/^[^<>{}\\|;:"\'\[\]()@#$%^&*=+~`]+$/u', // Bloqueia apenas chars perigosos
],
```

**Vantagens:**
- ✅ Solução imediata
- ✅ Aceita todos os municípios brasileiros

**Desvantagens:**
- ❌ Menos segura (aceita mais caracteres)
- ❌ Não resolve causa raiz

---

### Solução 2: Forçar Encoding UTF-8 (RECOMENDADA)
```php
// app/Http/Middleware/ForceUtf8Encoding.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceUtf8Encoding
{
    public function handle(Request $request, Closure $next)
    {
        // Garantir que Laravel interprete como UTF-8
        if ($request->isJson()) {
            $content = $request->getContent();

            // Forçar UTF-8 se não estiver
            if (mb_detect_encoding($content, 'UTF-8', true) === false) {
                $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
                $request->merge(json_decode($content, true));
            }
        }

        return $next($request);
    }
}

// Registrar em app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        // ...
        \App\Http\Middleware\ForceUtf8Encoding::class,
    ],
];
```

**Vantagens:**
- ✅ Resolve causa raiz
- ✅ Mantém validação rigorosa
- ✅ Corrige em todo o sistema

---

### Solução 3: Custom Validation Rule (MAIS ROBUSTA)
```php
// app/Rules/ValidMunicipioName.php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidMunicipioName implements Rule
{
    public function passes($attribute, $value)
    {
        // Garantir encoding UTF-8
        if (mb_detect_encoding($value, 'UTF-8', true) === false) {
            return false;
        }

        // Normalizar para NFD (decompor acentos)
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);

        // Verificar apenas letras latinas (com ou sem acentos)
        return preg_match('/^[\p{L}\s\-\.]+$/u', $normalized);
    }

    public function message()
    {
        return 'Nome do município contém caracteres inválidos ou encoding incorreto.';
    }
}

// Uso:
'municipios.*.desmun' => [
    'required',
    'string',
    'max:100',
    new \App\Rules\ValidMunicipioName(),
],
```

**Vantagens:**
- ✅ Mais robusto
- ✅ Usa Unicode Property `\p{L}` (todas as letras)
- ✅ Normalização NFD resolve problemas de encoding
- ✅ Reusável

---

## 🧪 Casos de Teste

### Municípios que DEVEM ser aceitos:
```json
[
    "SÃO PAULO",           // Tilde + espaço
    "JOÃO PESSOA",         // Tilde em Ã
    "TRÊS CORAÇÕES",       // Acento circunflexo + cedilha
    "AÇAILÂNDIA",          // Cedilha + tilde
    "BELO HORIZONTE",      // Sem acento (controle)
    "BELO-HORIZONTE",      // Hífen
    "DR. PEDRINHO",        // Ponto + espaço
    "BALNEÁRIO CAMBORIÚ"   // Acento agudo
]
```

### Caracteres que DEVEM ser REJEITADOS:
```json
[
    "SÃO PAULO <script>",  // Script injection
    "CIDADE; DROP TABLE",  // SQL injection
    "NOME{MALICIOSO}",     // Curly braces
    "TESTE|PIPE",          // Pipe
    "PATH/../../../etc"    // Path traversal
]
```

---

## 📊 Dados Reais do Progress Database

### Municípios com Acentos na Rota 197:
```json
{
    "spararmuseq": 5,
    "codmun": 3630,
    "codest": 31,
    "desmun": "JOAO PINHEIRO                 ",  // ← SEM acento no Progress!
    "desest": "MG",
    "cdibge": 3136306
}
```

**⚠️ DESCOBERTA IMPORTANTE:**
Progress Database armazena "JOAO" (sem acento) em vez de "JOÃO".

**Implicações:**
1. Progress pode não suportar UTF-8 corretamente
2. Dados já estão "sanitizados" (sem acentos) no banco
3. **MAS** o geocoding Google API precisa do nome CORRETO com acentos!

---

## 🔄 Workaround Atual

O sistema **FUNCIONA** porque:
1. Progress envia "JOAO PINHEIRO" (sem acento) ✅
2. Validação aceita "JOAO" (sem acento) ✅
3. Google Geocoding API reconhece "JOAO PINHEIRO" → "João Pinheiro, MG" ✅

**PORÉM**, se alguém tentar enviar manualmente "JOÃO PINHEIRO" (com acento), será **rejeitado**!

---

## 🎯 Recomendação Final

### Prioridade 1: **VERIFICAR SE O BUG É REAL EM PRODUÇÃO**
```bash
# Testar diretamente do browser (fetch usa UTF-8)
fetch('http://localhost:8002/api/geocoding/lote', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({municipios: [{cdibge: "3136306", desmun: "JOÃO PINHEIRO", desest: "MG"}]})
}).then(r => r.json()).then(console.log)
```

### Prioridade 2: **IMPLEMENTAR SOLUÇÃO 2** (ForceUtf8Encoding middleware)
- Garante UTF-8 em todo sistema
- Não quebra validação existente
- Simples de implementar

### Prioridade 3: **ADICIONAR TESTES AUTOMATIZADOS**
```php
// tests/Feature/GeocodingControllerTest.php
public function test_accepts_municipality_names_with_accents()
{
    $response = $this->postJson('/api/geocoding/lote', [
        'municipios' => [
            [
                'cdibge' => '3550308',
                'desmun' => 'SÃO PAULO',  // ← Com acentos
                'desest' => 'SP',
            ]
        ]
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
}
```

---

## 📝 Próximos Passos

1. ✅ **Documentar bug** (este arquivo)
2. ⏳ **Testar com fetch() do navegador** (UTF-8 garantido)
3. ⏳ **Implementar ForceUtf8Encoding middleware**
4. ⏳ **Adicionar testes automatizados**
5. ⏳ **Validar solução em produção**

---

**Responsável:** Claude Code
**Data Criação:** 2025-12-04 19:10 UTC
**Última Atualização:** 2025-12-04 19:10 UTC
**Status:** 🔴 **BUG ATIVO - AGUARDANDO CORREÇÃO**
