# Mapeamento VPO → Progress Database
**Data:** 2025-12-08
**Última Atualização:** 2025-12-08 23:00 (Versão 2.0 - Lógica Condicional Completa)
**Objetivo:** Mapear campos validados do VPO para campos do banco Progress

**🚨 ATUALIZAÇÃO CRÍTICA:** Este documento foi completamente reescrito para refletir a **lógica condicional baseada em `flgautonomo`** descoberta via queries JDBC.

**✅ Descobertas Confirmadas via Queries Reais:**
1. **Lógica Condicional Principal:** 79% dos campos dependem de `flgautonomo`
2. Campo `modvei` **EXISTE** em `trnvei` (NÃO está ausente!)
3. Campos `cdantt` e `datvldantt` confirmados em `transporte`
4. Tabela `trnvei` disponível para empresas (veículos completos)
5. JOINs com `tipcam`, `bairro`, `municipio`, `estado` funcionais

**Documento Complementar:** [CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md](./CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md)

---

## 📊 RESUMO EXECUTIVO

### Cobertura de Dados

| Categoria | Quantidade | % |
|-----------|------------|---|
| ✅ Campos mapeáveis | 18 | 95% |
| ⚠️ Campos condicionais (flgautonomo) | 15 | 79% |
| ✅ Campos com JOIN | 4 | 21% |
| ❌ Campos ausentes | 1 | 5% |
| **TOTAL** | **19** | **100%** |

**Único campo ausente:** `condutor_sexo` (usar padrão 'M')

**Cobertura:** 18/19 campos (95%) podem ser mapeados com dados existentes no Progress.

### Tabelas Progress Envolvidas

| Tabela | Quando Usar | Descrição |
|--------|-------------|-----------|
| `PUB.transporte` | **SEMPRE** | Autônomos (todos os dados) e Empresas (dados gerais) |
| `PUB.trnmot` | Empresas apenas | Dados dos motoristas (`flgautonomo = false`) |
| `PUB.trnvei` | Empresas apenas | Dados dos veículos (`flgautonomo = false`) |
| `PUB.tipcam` | **SEMPRE** | Tipos de caminhão (via JOIN) |
| `PUB.bairro` | Conforme disponível | Endereços (via JOIN) |
| `PUB.municipio` | Conforme disponível | Endereços (via JOIN) |
| `PUB.estado` | Conforme disponível | Endereços (via JOIN) |
| `PUB.pacote` | Opcional | Melhor fonte para placa (viagem específica) |

---

## 🔀 LÓGICA CONDICIONAL PRINCIPAL

**DECISOR:** Campo `flgautonomo` da tabela `PUB.transporte`

```sql
SELECT flgautonomo FROM PUB.transporte WHERE codtrn = ?
```

### Se `flgautonomo = true` (AUTÔNOMO):
- ✅ Pessoa física trabalhando para si mesma
- ✅ **TODOS os dados** em `PUB.transporte`
- ❌ **NÃO usar** `trnmot` (não tem motoristas separados)
- ❌ **NÃO usar** `trnvei` (não tem cadastro de veículos)
- ⚠️ Alguns campos podem estar vazios (ex: `numrg`)

### Se `flgautonomo = false` (EMPRESA):
- ✅ Pessoa jurídica com múltiplos motoristas
- ✅ Dados gerais em `PUB.transporte`
- ✅ **Dados do motorista** em `PUB.trnmot` (OBRIGATÓRIO!)
- ✅ **Dados do veículo** em `PUB.trnvei` (OPCIONAL, mas recomendado)
- ⚠️ Campos de `transporte` podem estar vazios (ex: `cdantt`, `codcnpjcpf`)

---

## 🟢 CAMPOS VALIDADOS (9 campos - API valida)

### 1. CPF/CNPJ (`cpf_cnpj`)

**Validação VPO:** CPF (11 dígitos) ou CNPJ (14 dígitos) sem pontuação
**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Tipo | Observação |
|----------|-------|-------|------|------------|
| **Autônomo** | `transporte` | `codcnpjcpf` | CPF | ✅ Exemplo: "60029137691" |
| **Empresa** | `transporte` | `codcnpjcpf` | CNPJ | ⚠️ Pode estar vazio |
| **Motorista (empresa)** | `trnmot` | `codcpf` | CPF | ✅ Exemplo: "11623232724" |

**Query Autônomo:**
```sql
SELECT codcnpjcpf FROM PUB.transporte WHERE codtrn = ? AND flgautonomo = 1
```

**Query Empresa + Motorista:**
```sql
-- Para VPO: usar CPF do MOTORISTA (não CNPJ da empresa)
SELECT m.codcpf FROM PUB.trnmot m WHERE m.codtrn = ? AND m.codmot = ?
```

**PHP:**
```php
if ($transportador->flgautonomo) {
    $cpfCnpj = $transportador->codcnpjcpf;  // CPF autônomo
} else {
    $cpfCnpj = $motorista->codcpf;  // CPF do motorista
}

// Remover pontuação
$cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
```

---

### 2. RNTRC (`antt_rntrc`)

**Validação VPO:** Código RNTRC válido
**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `cdantt` | ✅ Confirmado: "02767948" |
| **Empresa** | `transporte` | `cdantt` | ⚠️ Pode estar vazio |
| **Motorista (empresa)** | `trnmot` | `codrntrc` | ⚠️ Pode estar vazio |

**⚠️ AÇÃO NECESSÁRIA:** Campo `cdantt` **NÃO está** na query atual do `ProgressService::getTransporteById()` linha 288!

**Query Autônomo:**
```sql
SELECT cdantt FROM PUB.transporte WHERE codtrn = ? AND flgautonomo = 1
```

**Query Empresa + Motorista:**
```sql
-- Priorizar RNTRC do motorista
SELECT m.codrntrc, t.cdantt
FROM PUB.trnmot m
INNER JOIN PUB.transporte t ON t.codtrn = m.codtrn
WHERE m.codtrn = ? AND m.codmot = ?
```

**PHP:**
```php
if ($transportador->flgautonomo) {
    $anttRntrc = $transportador->cdantt;
} else {
    // Priorizar RNTRC do motorista, fallback empresa
    $anttRntrc = $motorista->codrntrc ?: ($transportador->cdantt ?: null);
}
```

---

### 3. Nome/Razão Social (`antt_nome`)

**Validação VPO:** Nome completo ou razão social
**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `nomtrn` | Nome do autônomo (pessoa física) |
| **Empresa** | `transporte` | `nomtrn` | Razão social da empresa |
| **Motorista (empresa)** | `trnmot` | `nommot` | ✅ **Usar este para VPO!** |

**PHP:**
```php
// Para VPO: usar nome do CONDUTOR (não da empresa)
if ($transportador->flgautonomo) {
    $anttNome = $transportador->nomtrn;  // Nome autônomo
} else {
    $anttNome = $motorista->nommot;  // Nome motorista
}
```

---

### 4. Validade RNTRC (`antt_validade`)

**Validação VPO:** Data no formato YYYY-MM-DD
**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `datvldantt` | ✅ Confirmado (pode ser NULL) |
| **Motorista (empresa)** | `trnmot` | `datvldrntrc` | ✅ Confirmado (pode ser NULL) |

**⚠️ AÇÃO NECESSÁRIA:** Campo `datvldantt` **NÃO está** na query atual do `ProgressService::getTransporteById()`!

**PHP:**
```php
if ($transportador->flgautonomo) {
    $anttValidade = $transportador->datvldantt;
} else {
    $anttValidade = $motorista->datvldrntrc;
}

// Formatar data
$anttValidadeFormatada = $anttValidade ? date('Y-m-d', strtotime($anttValidade)) : null;
```

---

### 5. Status RNTRC (`antt_status`)

**Validação VPO:** "Ativo" ou "Vencido"
**Status:** ⚠️ **PROXY** (calculado por data de validade)

**PHP:**
```php
$status = 'Desconhecido';
if ($anttValidade) {
    $status = strtotime($anttValidade) > time() ? 'Ativo' : 'Vencido';
}

// Alternativa: Consultar API ANTT em tempo real (mais confiável)
// $status = $this->consultarStatusRNTRC($anttRntrc);
```

---

### 6. Placa (`placa`)

**Validação VPO:** Placa formato Mercosul (ABC1D23)
**Status:** ⚠️ **CONDICIONAL + PRIORIDADE**

**Prioridade de Fontes:**
1. 🎯 `PUB.pacote.numpla` (placa da viagem específica) - **MELHOR**
2. `PUB.trnvei.numpla` (veículo específico - empresas)
3. `PUB.transporte.numpla` (placa genérica - autônomos ou empresa)

**PHP:**
```php
// Prioridade: pacote > trnvei > transporte
if (isset($codpac)) {
    $pacote = DB::connection('progress')
        ->selectOne('SELECT numpla FROM PUB.pacote WHERE codpac = ?', [$codpac]);
    $placa = $pacote->numpla ?? null;
}

if (!$placa && !$transportador->flgautonomo && isset($numpla)) {
    $veiculo = DB::connection('progress')
        ->selectOne('SELECT numpla FROM PUB.trnvei WHERE codtrn = ? AND numpla = ?',
                    [$codtrn, $numpla]);
    $placa = $veiculo->numpla ?? null;
}

if (!$placa) {
    $placa = $transportador->numpla;
}
```

---

### 7. Tipo de Veículo (`veiculo_tipo`)

**Validação VPO:** Descrição do tipo de veículo
**Status:** ⚠️ **CONDICIONAL + JOIN**

| Condição | Fonte | JOIN | Observação |
|----------|-------|------|------------|
| **Autônomo** | `transporte.tipcam` | `tipcam.destipcam` | ✅ Ex: "TOCO" (código 2) |
| **Empresa** | `trnvei.tipcam` | `tipcam.destipcam` | ✅ Ex: "CARRETA 3 EIXOS" (código 99) |

**Query Autônomo:**
```sql
SELECT tc.destipcam
FROM PUB.transporte t
LEFT JOIN PUB.tipcam tc ON tc.tipcam = t.tipcam
WHERE t.codtrn = ?
```

**Query Empresa:**
```sql
SELECT tc.destipcam
FROM PUB.trnvei v
LEFT JOIN PUB.tipcam tc ON tc.tipcam = v.tipcam
WHERE v.codtrn = ? AND v.numpla = ?
```

---

### 8. Modelo de Veículo (`veiculo_modelo`)

**Validação VPO:** Descrição do modelo
**Status:** ⚠️ **CONDICIONAL** (✅ EXISTE para empresas, ❌ AUSENTE para autônomos)

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | ❌ N/A | ❌ | Usar genérico baseado em `tipcam` |
| **Empresa** | `trnvei` | `modvei` | ✅ **EXISTE!** Ex: "RANDON SP SRFG", "AXOR 2041" |

**⚠️ CORREÇÃO CRÍTICA:** Campo `modvei` **NÃO está ausente** no Progress! Existe em `PUB.trnvei` para empresas.

**Query Empresa:**
```sql
SELECT modvei, marvei FROM PUB.trnvei WHERE codtrn = ? AND numpla = ?
```

**PHP:**
```php
if ($transportador->flgautonomo) {
    // Autônomos: modelo genérico
    $veiculoModelo = $this->getModeloGenerico($transportador->tipcam);
} else {
    // Empresas: modelo real de trnvei
    $veiculo = DB::connection('progress')
        ->selectOne('SELECT modvei FROM PUB.trnvei WHERE codtrn = ? AND numpla = ?',
                    [$codtrn, $placa]);
    $veiculoModelo = $veiculo->modvei ?: $this->getModeloGenerico($veiculo->tipcam);
}

private function getModeloGenerico($tipcam) {
    $modelos = [
        1 => 'Caminhão 3/4 Padrão',
        2 => 'Caminhão Toco Padrão',
        3 => 'Caminhão Truck Padrão',
        97 => 'Cavalo Simples Padrão',
        99 => 'Carreta 3 Eixos Padrão',
    ];
    return $modelos[$tipcam] ?? 'Modelo Não Especificado';
}
```

---

### 9. RG do Condutor (`condutor_rg`)

**Validação VPO:** RG sem pontuação
**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `numrg` | ⚠️ Pode estar vazio - fallback `numhab`? |
| **Motorista (empresa)** | `trnmot` | `numrg` | ✅ Confirmado: "11623232724" |

**PHP:**
```php
if ($transportador->flgautonomo) {
    // Autônomo: numrg pode estar vazio, usar numhab como fallback?
    $condutorRg = $transportador->numrg ?: ($transportador->numhab ?: null);
} else {
    $condutorRg = $motorista->numrg;
}

// Remover pontuação
$condutorRg = preg_replace('/[^0-9]/', '', $condutorRg);
```

---

## 🔴 CAMPOS OBRIGATÓRIOS (10 campos - NÃO validados pela API)

### 10. Nome do Condutor (`condutor_nome`)

**Status:** ⚠️ **CONDICIONAL** (mesmo que `antt_nome`)

**PHP:**
```php
// Mesmo mapeamento de antt_nome
if ($transportador->flgautonomo) {
    $condutorNome = $transportador->nomtrn;
} else {
    $condutorNome = $motorista->nommot;
}
```

---

### 11. Sexo do Condutor (`condutor_sexo`)

**Status:** ❌ **AUSENTE** (único campo sem mapeamento)

**PHP:**
```php
// Campo não existe em nenhuma tabela - usar padrão
$condutorSexo = 'M';  // Masculino (padrão - API não valida)
```

---

### 12. Nome da Mãe (`condutor_nome_mae`)

**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `nommae`? | ⚠️ Precisa confirmar se existe |
| **Motorista (empresa)** | `trnmot` | `nommae` | ✅ Confirmado: "MARIA CATARUNA..." |

**PHP:**
```php
if ($transportador->flgautonomo) {
    // TODO: Verificar se transporte.nommae existe
    $condutorNomeMae = $transportador->nommae ?? null;
} else {
    $condutorNomeMae = $motorista->nommae;
}
```

---

### 13. Data de Nascimento (`condutor_data_nascimento`)

**Status:** ⚠️ **CONDICIONAL**

| Condição | Fonte | Campo | Observação |
|----------|-------|-------|------------|
| **Autônomo** | `transporte` | `datnas` | ✅ Confirmado: "1969-10-25" |
| **Motorista (empresa)** | `trnmot` | `datnas` | ✅ Confirmado: "1987-04-25" |

**PHP:**
```php
if ($transportador->flgautonomo) {
    $condutorDataNascimento = $transportador->datnas;
} else {
    $condutorDataNascimento = $motorista->datnas;
}

// Formatar data
$condutorDataNascimento = date('Y-m-d', strtotime($condutorDataNascimento));
```

---

### 14-17. Endereço (`endereco_rua`, `endereco_bairro`, `endereco_cidade`, `endereco_estado`)

**Status:** ⚠️ **CONDICIONAL + JOIN**

| Condição | Fonte Principal | Fallback | JOINs |
|----------|----------------|----------|-------|
| **Autônomo** | `transporte` | - | `bairro`, `municipio`, `estado` |
| **Motorista (empresa)** | `trnmot` | `transporte` | `bairro`, `municipio`, `estado` |

**Query Completa (Empresa):**
```sql
SELECT
  m.desend,                 -- endereco_rua
  m.numend,                 -- número
  bai.desbai,               -- endereco_bairro
  mun.desmun,               -- endereco_cidade
  est.siglaest              -- endereco_estado (UF)
FROM PUB.trnmot m
LEFT JOIN PUB.bairro bai ON bai.codbai = m.codbai
LEFT JOIN PUB.municipio mun ON mun.codmun = m.codmun
LEFT JOIN PUB.estado est ON est.codest = m.codest
WHERE m.codtrn = ? AND m.codmot = ?
```

**PHP:**
```php
if ($transportador->flgautonomo) {
    $enderecoRua = $transportador->desend;
    $codest = $transportador->codest;
    $codmun = $transportador->codmun;
    $codbai = $transportador->codbai;
} else {
    $enderecoRua = $motorista->desend ?: $transportador->desend;
    $codest = $motorista->codest ?: $transportador->codest;
    $codmun = $motorista->codmun ?: $transportador->codmun;
    $codbai = $motorista->codbai ?: $transportador->codbai;
}

// Buscar descrições via JOIN
$endereco = $this->getEndereco($codest, $codmun, $codbai);
```

---

### 18. Celular (`contato_celular`)

**Status:** ⚠️ **CONDICIONAL**

**PHP:**
```php
if ($transportador->flgautonomo) {
    $contatoCelular = $transportador->dddcel . $transportador->numcel;
} else {
    $celularMot = $motorista->dddtel . $motorista->numtel;
    $celularEmp = $transportador->dddcel . $transportador->numcel;
    $contatoCelular = $celularMot ?: $celularEmp;
}

// Formatar: 11 dígitos (DDD + número)
$contatoCelular = preg_replace('/[^0-9]/', '', $contatoCelular);
```

---

### 19. Email (`contato_email`)

**Status:** ⚠️ **CONDICIONAL**

**PHP:**
```php
if ($transportador->flgautonomo) {
    $contatoEmail = $transportador->{'e-mail'};
} else {
    $contatoEmail = $motorista->email ?: $transportador->{'e-mail'};
}
```

---

## 📝 QUERIES SQL COMPLETAS

### Query 1: Autônomo (Single Query)

```sql
SELECT
  t.codtrn,
  t.nomtrn,                    -- antt_nome, condutor_nome
  t.codcnpjcpf,                -- cpf_cnpj
  t.cdantt,                    -- antt_rntrc
  t.datvldantt,                -- antt_validade
  t.numrg,                     -- condutor_rg
  t.numhab,                    -- condutor_rg (fallback)
  t.datnas,                    -- condutor_data_nascimento
  t.numpla,                    -- placa
  t.tipcam,                    -- veiculo_tipo (código)
  tc.destipcam,                -- veiculo_tipo (descrição)
  t.desend,                    -- endereco_rua
  t.numend,                    -- número
  t.dddcel,                    -- contato_celular (DDD)
  t.numcel,                    -- contato_celular (número)
  t."e-mail",                  -- contato_email
  est.siglaest,                -- endereco_estado (UF)
  mun.desmun,                  -- endereco_cidade
  bai.desbai                   -- endereco_bairro
FROM PUB.transporte t
LEFT JOIN PUB.tipcam tc ON tc.tipcam = t.tipcam
LEFT JOIN PUB.estado est ON est.codest = t.codest
LEFT JOIN PUB.municipio mun ON mun.codmun = t.codmun
LEFT JOIN PUB.bairro bai ON bai.codbai = t.codbai
WHERE t.codtrn = ? AND t.flgautonomo = 1
```

---

### Query 2: Empresa (3 Queries Separadas)

**2a. Dados do Transportador (Empresa):**
```sql
SELECT codtrn, nomtrn, codcnpjcpf, cdantt, numpla
FROM PUB.transporte
WHERE codtrn = ? AND flgautonomo = 0
```

**2b. Dados do Motorista (COMPLETO):**
```sql
SELECT
  m.codtrn,
  m.codmot,
  m.nommot,                    -- antt_nome, condutor_nome
  m.codcpf,                    -- cpf_cnpj
  m.codrntrc,                  -- antt_rntrc
  m.datvldrntrc,               -- antt_validade
  m.numrg,                     -- condutor_rg
  m.datnas,                    -- condutor_data_nascimento
  m.nommae,                    -- condutor_nome_mae
  m.desend,                    -- endereco_rua
  m.numend,                    -- número
  m.dddtel,                    -- contato_celular (DDD)
  m.numtel,                    -- contato_celular (número)
  m.email,                     -- contato_email
  est.siglaest,                -- endereco_estado (UF)
  mun.desmun,                  -- endereco_cidade
  bai.desbai                   -- endereco_bairro
FROM PUB.trnmot m
LEFT JOIN PUB.estado est ON est.codest = m.codest
LEFT JOIN PUB.municipio mun ON mun.codmun = m.codmun
LEFT JOIN PUB.bairro bai ON bai.codbai = m.codbai
WHERE m.codtrn = ? AND m.codmot = ?
```

**2c. Dados do Veículo (OPCIONAL):**
```sql
SELECT
  v.numpla,                    -- placa
  v.tipcam,                    -- veiculo_tipo (código)
  v.modvei,                    -- veiculo_modelo ✅ EXISTE!
  v.marvei,                    -- marca
  tc.destipcam                 -- veiculo_tipo (descrição)
FROM PUB.trnvei v
LEFT JOIN PUB.tipcam tc ON tc.tipcam = v.tipcam
WHERE v.codtrn = ? AND v.numpla = ?
```

---

## 🎯 IMPLEMENTAÇÃO RECOMENDADA

### Classe VPOProgressMapper

**Arquivo:** `app/Services/NddCargo/Mappers/VPOProgressMapper.php`

**Referência Completa:** [CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md](./CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md)

**Métodos Principais:**
```php
public function mapearDadosVPO(int $codtrn, ?int $codmot, ?string $numpla, ?int $codpac): array

private function mapearAutonomo($transportador, ?int $codpac): array

private function mapearEmpresa($transportador, $motorista, $veiculo, ?int $codpac): array
```

---

## ⚠️ AÇÕES NECESSÁRIAS

### 1. Atualizar ProgressService.php

**Arquivo:** `app/Services/ProgressService.php`
**Linha:** 288

**Adicionar campos:** `cdantt`, `datvldantt`

```php
$sql = "SELECT codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla,
               numtel, dddtel, numcel, dddcel, flgati, indcd, desend, numend,
               cplend, numceptrn, \"e-mail\", numhab, venhab, cathab, datnas,
               cdantt,        -- ✅ ADICIONAR
               datvldantt     -- ✅ ADICIONAR
        FROM PUB.transporte WHERE codtrn = $id";
```

### 2. Criar Métodos no ProgressService

```php
/**
 * Busca dados completos para emissão VPO
 *
 * @param int $codtrn Código transportador
 * @param int|null $codmot Código motorista (obrigatório para empresas)
 * @param string|null $numpla Placa veículo (opcional)
 * @param int|null $codpac Código pacote (opcional - melhor fonte placa)
 * @return array Dados mapeados para VPO
 */
public function getDadosVPO(int $codtrn, ?int $codmot = null, ?string $numpla = null, ?int $codpac = null): array
```

### 3. Testar Lógica Condicional

```bash
# Autônomo
curl http://localhost:8002/api/transportes/1

# Empresa
curl http://localhost:8002/api/transportes/3695
```

---

## 📊 ESTATÍSTICAS ATUALIZADAS

| Categoria | Quantidade | % | Observações |
|-----------|------------|---|-------------|
| ✅ Campos OK (direto ou condicional) | 18 | 95% | Mapeamento completo disponível |
| ⚠️ Campos CONDICIONAIS (flgautonomo) | 15 | 79% | **MAIORIA dos campos!** |
| ✅ Campos com JOIN | 4 | 21% | tipcam, bairro, municipio, estado |
| ❌ Campos ausentes no Progress | 1 | 5% | condutor_sexo (usar 'M' padrão) |
| **TOTAL** | **19** | **100%** |  |

**Descoberta Crítica:** Campo `modvei` **EXISTE** em `trnvei` (cobertura 95%, não 84%)!

---

## 🔗 REFERÊNCIAS

### Documentos Relacionados
- [CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md](./CORRECAO_MAPEAMENTO_COMPLETO_FLGAUTONOMO.md) - **Leitura OBRIGATÓRIA!**
- [TABELA_MAPEAMENTO_VPO.md](./TABELA_MAPEAMENTO_VPO.md) - Tabela resumo visual
- [MODELO_EMISSAO_VPO.md](./MODELO_EMISSAO_VPO.md) - Modelo de dados VPO

### Validação
- ✅ Confirmado via queries JDBC em banco real
- ✅ Testado com autônomo (codtrn=1)
- ✅ Testado com empresa (codtrn=3695, codmot=1)
- ✅ Campo `modvei` confirmado em `trnvei`
- ✅ Campos `cdantt` e `datvldantt` confirmados em `transporte`

---

**Documento criado por:** Claude Code
**Data:** 2025-12-08
**Versão:** 2.0.0 (Lógica Condicional Completa)
**Status:** ✅ VALIDADO com queries reais no banco Progress
