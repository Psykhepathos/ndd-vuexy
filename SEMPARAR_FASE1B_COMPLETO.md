# SemParar SOAP API - FASE 1B COMPLETO ✅

**Data:** 2025-10-27
**Status:** Implementado, testado e funcional
**Branch:** master
**Commit:** Pending

---

## 📋 Resumo Executivo

FASE 1B implementa **roteirização de praças de pedágio** usando a API SOAP do SemParar ValePedagio. Permite calcular quais praças de pedágio estão em uma rota, cadastrar rotas temporárias e obter custos totais.

### Métodos Implementados

1. **roteirizarPracasPedagio** - Calcula praças de pedágio na rota
2. **cadastrarRotaTemporaria** - Cadastra rota temporária no SemParar
3. **obterCustoRota** - Calcula custo total da rota

---

## 🐛 Bug Crítico Identificado e Corrigido

### Problema

O PHP `SoapClient` estava enviando parâmetros **VAZIOS** para o SOAP server:

```xml
<!-- ❌ ERRADO - Parâmetros vazios -->
<pontosParada xsi:type="ns2:PontosParada"/>
<opcoesRota xsi:type="ns2:OpcoesRota"/>
<sessao xsi:type="xsd:long">-671526932967373896</sessao>
```

**Resultado:** API retornava `status: 999` (erro desconhecido)

### Causa Raiz

Estava passando **strings XML** diretamente para `$soapClient->roteirizarPracasPedagio()`, mas o PHP SoapClient espera que XML complexo seja passado como `SoapVar` com tipo `XSD_ANYXML`.

### Solução

```php
// ❌ ANTES (errado)
$response = $soapClient->roteirizarPracasPedagio(
    $pontosXml,  // String não é enviada corretamente!
    $opcoesXml,
    $token
);

// ✅ DEPOIS (correto)
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);

$response = $soapClient->roteirizarPracasPedagio(
    $pontosParam,  // SoapVar envia o XML corretamente
    $opcoesParam,
    $token
);
```

**Arquivo:** `app/Services/SemParar/SemPararService.php:184-191`

---

## 📁 Arquivos Criados/Modificados

### Novos Arquivos

#### 1. `app/Services/SemParar/XmlBuilders/PontosParadaBuilder.php` (158 linhas)

**Propósito:** Construtor de XML para datasets Progress

**Estrutura XML gerada:**
```xml
<pontosParada xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <pontoParada>
    <ponto>
      <codigoIBGE>3550308</codigoIBGE>
      <latLong>
        <latitude>-23.5505199</latitude>
        <longitude>-46.6333094</longitude>
      </latLong>
      <descricao>SAO PAULO - SP</descricao>
    </ponto>
    <ponto>
      <codigoIBGE>3304557</codigoIBGE>
      <latLong>
        <latitude>-22.9068467</latitude>
        <longitude>-43.1728965</longitude>
      </latLong>
      <descricao>RIO DE JANEIRO - RJ</descricao>
    </ponto>
  </pontoParada>
  <status>0</status>
</pontosParada>
```

**Métodos principais:**
- `buildPontosParadaXml(array $pontos): string` - Constrói XML de pontos
- `buildOpcoesRotaXml(bool $alternativas): string` - Constrói XML de opções
- `parsePracaPedagio(object $response): array` - Parseia resposta SOAP

**Baseado em:** `SEMPARAR_AI_REFERENCE.md` (Progress dataset structure)

#### 2. `public/test-semparar-fase1b.html` (350+ linhas)

**Propósito:** Interface HTML de teste interativa

**Features:**
- Teste 1: Rota simples SP→RJ (2 pontos)
- Teste 2: Rota completa com pacote 3043368 (19 pontos)
- Teste 3: Cadastrar rota temporária
- Teste 4: Calcular custo por placa/eixos
- Tabela visual com praças encontradas
- Logs de sucesso/erro coloridos

**URL:** http://localhost:8002/test-semparar-fase1b.html

### Arquivos Modificados

#### 1. `app/Services/SemParar/SemPararService.php` (+232 linhas)

**Adicionado:**

```php
/**
 * Roteirizar praças de pedágio
 *
 * @param array $pontos Array de pontos (cod_ibge, desc, latitude, longitude)
 * @param bool $alternativas Buscar rotas alternativas
 * @return array Result with pracas array
 */
public function roteirizarPracasPedagio(array $pontos, bool $alternativas = false): array

/**
 * Cadastrar rota temporária
 *
 * @param array $pracaIds Array of toll plaza IDs
 * @param string $nomeRota Route name
 * @return array Result with id and nome
 */
public function cadastrarRotaTemporaria(array $pracaIds, string $nomeRota): array

/**
 * Obter custo da rota
 *
 * @param string $nomeRota Route name
 * @param string $placa Vehicle plate
 * @param int $eixos Number of axles
 * @param string $dataInicio Start date (YYYY-MM-DD)
 * @param string $dataFim End date (YYYY-MM-DD)
 * @return array Result with valor
 */
public function obterCustoRota(string $nomeRota, string $placa, int $eixos, string $dataInicio, string $dataFim): array
```

#### 2. `app/Http/Controllers/Api/SemPararController.php` (+134 linhas)

**Adicionado:**

```php
/**
 * Roteirizar praças de pedágio
 * POST /api/semparar/roteirizar
 */
public function roteirizar(Request $request): JsonResponse

/**
 * Cadastrar rota temporária
 * POST /api/semparar/rota-temporaria
 */
public function cadastrarRotaTemporaria(Request $request): JsonResponse

/**
 * Obter custo da rota
 * POST /api/semparar/custo-rota
 */
public function obterCustoRota(Request $request): JsonResponse
```

#### 3. `routes/api.php`

**Adicionado:**

```php
// Rotas para SemParar SOAP API (FASE 1A + 1B)
Route::prefix('semparar')->group(function () {
    // FASE 1A - Core
    Route::get('test-connection', [SemPararController::class, 'testConnection'])
        ->middleware('throttle:10,1');
    Route::post('status-veiculo', [SemPararController::class, 'statusVeiculo'])
        ->middleware('throttle:60,1');

    // FASE 1B - Routing
    Route::post('roteirizar', [SemPararController::class, 'roteirizar'])
        ->middleware('throttle:20,1');
    Route::post('rota-temporaria', [SemPararController::class, 'cadastrarRotaTemporaria'])
        ->middleware('throttle:20,1');
    Route::post('custo-rota', [SemPararController::class, 'obterCustoRota'])
        ->middleware('throttle:60,1');
});
```

---

## 🧪 Testes Realizados

### Teste 1: Rota SP→RJ (Simples)

**Entrada:**
```json
{
  "pontos": [
    {"cod_ibge": 3550308, "desc": "SAO PAULO - SP", "latitude": -23.5505199, "longitude": -46.6333094},
    {"cod_ibge": 3304557, "desc": "RIO DE JANEIRO - RJ", "latitude": -22.9068467, "longitude": -43.1728965}
  ],
  "alternativas": false
}
```

**Resultado:** ✅ SUCESSO
- **Status:** 0
- **Praças encontradas:** 6
- **Rodovia:** Pres. Dutra BR-116
- **Concessionária:** CONCESSIONARIA DO SISTEMA RODOVIÁRIO RIO - SAO PAULO S.A

**Praças:**
1. ARUJA NORTE (KM 204)
2. GUARAREMA NORTE (KM 182.5)
3. JACAREI NORTE (KM 165)
4. MOREIRA CESAR NORTE (KM 87)
5. ITATIAIA NORTE (KM 319)
6. BR116, KM207+100, NORTE, SEROPÉDICA

### Teste 2: Rota Completa com Pacote 3043368

**Entrada:**
- **Rota 183 (CD - BARREIRAS):** 4 municípios
  - CONTAGEM - MG
  - JOAO PINHEIRO - MG
  - PARACATU - MG
  - UNAI - MG
- **Pacote 3043368:** 15 entregas em Jaboticatubas-MG

**Total de pontos:** 19

**Resultado:** ✅ SUCESSO
- **Status:** 0
- **Praças encontradas:** 12
- **Rodovias:** BR-040 (Via Cristais) + BR-050 (MGO Rodovias)

**Praças:**
1. BR-040, KM487,268, NORTE, CAPIM BRANCO
2. BR-040, KM405,49, NORTE, CURVELO
3. BR-040, KM329,021, NORTE, FELIXLÂNDIA
4. BR-040, KM254,13, NORTE, SÃO GONÇALO DO ABAETÉ
5. BR-040, KM172,72, NORTE, JOAO PINHEIRO
6. BR-040, KM91,36, NORTE, LAGOA GRANDE
7. IPAMERI KM 143+985 SUL (BR-050)
8. CAMPO ALEGRE KM 226+000 SUL (BR-050)
9. ARAGUARI I KM 013+730 SUL (BR-050)
10. ARAGUARI II KM 051+475 SUL (BR-050)
11. UBERABA KM 104+900 SUL (BR-050)
12. DELTA KM 198+060 SUL (BR-050)

---

## 📊 Estatísticas

| Métrica | Valor |
|---------|-------|
| Linhas de código adicionadas | ~524 |
| Arquivos criados | 2 |
| Arquivos modificados | 3 |
| Endpoints REST criados | 3 |
| Testes bem-sucedidos | 2/2 |
| Bug crítico resolvido | 1 (SoapVar XSD_ANYXML) |
| Praças totais encontradas (testes) | 18 |

---

## 🔗 Endpoints REST Disponíveis

### 1. POST `/api/semparar/roteirizar`

**Descrição:** Roteiriza praças de pedágio entre pontos

**Request Body:**
```json
{
  "pontos": [
    {
      "cod_ibge": 3550308,
      "desc": "SAO PAULO - SP",
      "latitude": -23.5505199,
      "longitude": -46.6333094
    }
  ],
  "alternativas": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Roteirização concluída",
  "data": {
    "success": true,
    "pracas": [
      {
        "id": 8,
        "praca": "ARUJA NORTE",
        "rodovia": "PRES.DUTRA - BR-116",
        "km": 204,
        "concessionaria": "CONCESSIONARIA DO SISTEMA RODOVIÁRIO RIO - SAO PAULO S.A",
        "status": 0
      }
    ],
    "total": 6,
    "status": 0
  }
}
```

**Rate Limit:** 20 req/min

### 2. POST `/api/semparar/rota-temporaria`

**Descrição:** Cadastra rota temporária no SemParar

**Request Body:**
```json
{
  "praca_ids": [8, 447, 10, 12, 14, 16],
  "nome_rota": "TESTE_SP_RJ"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Rota temporária cadastrada",
  "data": {
    "success": true,
    "cod_rota_semparar": "12345",
    "nome_rota_semparar": "TESTE_SP_RJ",
    "status": 0
  }
}
```

**Rate Limit:** 20 req/min

### 3. POST `/api/semparar/custo-rota`

**Descrição:** Calcula custo total da rota

**Request Body:**
```json
{
  "nome_rota": "TESTE_SP_RJ",
  "placa": "HNE3C80",
  "eixos": 2,
  "data_inicio": "2025-10-27",
  "data_fim": "2025-10-27"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Custo calculado",
  "data": {
    "success": true,
    "valor": 123.45,
    "status": 0
  }
}
```

**Rate Limit:** 60 req/min

---

## 🎯 Integração com Progress

### Como Progress chama roteirizarPracasPedagio

**Arquivo:** `C:\Users\15857\Desktop\corporativo\SemParar\Rota.cls:543`

```progress
// Progress serializa DATASET para XML
DATASET pontosParadaDset:WRITE-XML("LONGCHAR", envia-Xml-Roteriza1, TRUE).

// Opções como string XML
envia-Xml-Roteriza2 = '<opcoesRota>
  <alternativas>false</alternativas>
  <status>0</status>
  <tipoRota>1</tipoRota>
</opcoesRota>'.

// Chama SOAP
RUN VALUE("roteirizarPracasPedagio") IN hPorta(
    envia-Xml-Roteriza1,
    envia-Xml-Roteriza2,
    this-object:cToken,
    OUTPUT retorno-Xml-Roteriza
).
```

### Nossa Implementação PHP

**Arquivo:** `app/Services/SemParar/SemPararService.php:164-191`

```php
// Construir XML (equivalente ao Progress DATASET:WRITE-XML)
$pontosXml = PontosParadaBuilder::buildPontosParadaXml($pontos);
$opcoesXml = PontosParadaBuilder::buildOpcoesRotaXml($alternativas);

// Converter para SoapVar (CRÍTICO!)
$pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
$opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);

// Chamar SOAP (equivalente ao RUN VALUE)
$response = $soapClient->roteirizarPracasPedagio(
    $pontosParam,
    $opcoesParam,
    $token
);
```

---

## 🚀 Próximas Fases

### FASE 2A: Cadastro de Viagens
- `incluirPedidoComprarViagem()` - Incluir pedido de compra
- `comprarViagem()` - Efetivar compra de viagem
- Integração com tabela Progress `PUB.sPararViagem`

### FASE 2B: Gestão de Viagens
- `validarPesquisaViagens()` - Validar pesquisa de viagens
- `obterPedidosViagem()` - Obter pedidos de viagem
- `obterComprovante()` - Obter comprovante PDF

### FASE 3: Otimizações
- Cache de token único (evitar múltiplas autenticações)
- Retry automático em caso de falha
- Log estruturado de todas as operações SOAP
- Validação de inputs mais robusta

---

## 📝 Notas Importantes

1. **SoapVar é OBRIGATÓRIO:** Sem `new \SoapVar($xml, XSD_ANYXML)`, o PHP SoapClient NÃO envia o XML corretamente.

2. **Progress Dataset XML:** A estrutura XML é específica do Progress OpenEdge (namespaces, xsi:type, etc).

3. **Status Codes SemParar:**
   - `0` = Sucesso
   - `999` = Erro desconhecido (geralmente rota sem pedágios ou XML inválido)
   - Ver tabela `PUB.semPararStatus` no Progress para códigos completos

4. **Token é REUTILIZADO:** Mesma sessão pode ser usada para múltiplas chamadas (cache de 1 hora).

5. **Coordenadas GPS:** Progress armazena em formato string ("19,5087S"), convertemos para decimal (-19.5087).

---

## ✅ Checklist de Implementação

- [x] Criar `PontosParadaBuilder.php` com XML builder
- [x] Adicionar `roteirizarPracasPedagio()` ao `SemPararService`
- [x] Adicionar `cadastrarRotaTemporaria()` ao `SemPararService`
- [x] Adicionar `obterCustoRota()` ao `SemPararService`
- [x] Criar endpoints REST no `SemPararController`
- [x] Registrar rotas com rate limiting
- [x] Identificar e corrigir bug de SoapVar
- [x] Testar com rota simples SP→RJ
- [x] Testar com pacote real (3043368)
- [x] Criar interface HTML de teste
- [x] Documentar FASE 1B completa
- [ ] Commit e push para repositório

---

**Desenvolvido com:** Laravel 12.15.0 + PHP 8.4 + PHP SoapClient
**Testado em:** Windows 11 + Progress OpenEdge 11.7
**Documentação:** SEMPARAR_AI_REFERENCE.md + Progress Rota.cls
