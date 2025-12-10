# Problemas Encontrados - VPO Emission NDD Cargo

**Data:** 2025-12-08
**Status:** ❌ BLOQUEADO - Dados obrigatórios faltando no Progress

---

## 🔍 Problema Principal

**Sintoma:** Requisições VPO com assinatura digital **NÃO aparecem** no painel NDD Cargo (HTTP 200, mas silenciosamente rejeitadas).

**Causa Raiz:** **Campos obrigatórios faltando** no XML VPO enviado.

---

## ❌ Campos Faltantes Identificados

### Análise do XML Enviado (UUID: 7eb79c6e-f56f-4fa1-96ef-a00e7ca7c296)

```xml
<emitirVPO_envio xmlns="http://www.nddigital.com.br/nddcargo" versao="4.2.12.0" token="...">
  <infEmitirVPO ID="7eb79c6e-f56f-4fa1-96ef-a00e7ca7c296">
    <transportador>
      <cpfCnpj>11604320000177</cpfCnpj>
      <!-- ❌ FALTA: anttRntrc -->
      <anttNome>TRANSPORTES MIGUELAO LTDA</anttNome>
      <!-- ❌ FALTA: anttValidade -->
      <anttStatus>Ativo</anttStatus>
    </transportador>
    <veiculo>
      <placa>RCP3C73</placa>
      <tipo>CARRETA</tipo>
      <modelo>IXQ0D30</modelo>
    </veiculo>
    <condutor>
      <rg>D</rg>
      <nome>TRANSPORTES MIGUELAO LTDA</nome>
      <sexo>M</sexo>
      <nomeMae>FD</nomeMae>
      <dataNascimento>1989-01-13</dataNascimento>
    </condutor>
    <endereco>
      <rua>A, QD 45</rua>
      <bairro>SAO JOSE DO PIRIA</bairro>
      <cidade>VITORIA DA CONQUISTA</cidade>
      <!-- ❌ FALTA: estado (UF) -->
    </endereco>
    <contato>
      <celular>77991865071</celular>
      <!-- ❌ FALTA: email -->
    </contato>
    <rota>
      <!-- ❌ VAZIO: pontosRota (sem waypoints!) -->
      <pontosRota/>
    </rota>
  </infEmitirVPO>
</emitirVPO_envio>
```

---

## 📊 Status VPO Sync (codtrn: 3247)

```json
{
  "cpf_cnpj": "11604320000177",           // ✅ OK
  "antt_rntrc": "",                       // ❌ VAZIO!
  "antt_nome": "TRANSPORTES MIGUELAO LTDA", // ✅ OK
  "antt_validade": null,                  // ❌ NULL!
  "antt_status": "Ativo",                 // ✅ OK (proxy)
  "placa": "RCP3C73",                     // ✅ OK
  "veiculo_tipo": "CARRETA",              // ✅ OK
  "veiculo_modelo": "IXQ0D30",            // ✅ OK
  "condutor_rg": "D",                     // ✅ OK
  "condutor_nome": "TRANSPORTES MIGUELAO LTDA", // ✅ OK
  "condutor_sexo": "M",                   // ✅ OK (padrão)
  "condutor_nome_mae": "FD",              // ✅ OK
  "condutor_data_nascimento": "1989-01-13", // ✅ OK
  "endereco_rua": "A, QD 45",             // ✅ OK
  "endereco_bairro": "SAO JOSE DO PIRIA", // ✅ OK
  "endereco_cidade": "VITORIA DA CONQUISTA", // ✅ OK
  "endereco_estado": null,                // ❌ NULL!
  "contato_celular": "77991865071",       // ✅ OK
  "contato_email": "",                    // ❌ VAZIO!

  "score_qualidade": 45,                  // ⚠️ BAIXO!
  "campos_faltantes": [
    "antt_rntrc",
    "endereco_estado",
    "contato_email",
    "antt_validade"
  ]
}
```

---

## 🔴 Campos Obrigatórios Faltando (4 campos)

| Campo | Status Progress | Mapeamento Esperado | Problema |
|-------|-----------------|---------------------|----------|
| **antt_rntrc** | ❌ NÃO EXISTE | `transporte.cdantt` | Campo `cdantt` não retorna dados |
| **antt_validade** | ❌ NÃO EXISTE | `transporte.datvldantt` | Campo `datvldantt` não retorna dados |
| **endereco_estado** | ❌ NÃO MAPEADO | `transporte.codest` → JOIN `estado.siglaest` | Sync não está fazendo JOIN |
| **contato_email** | ⚠️ VAZIO | `transporte."e-mail"` | Campo existe mas está vazio |

---

## 🐛 Problema Adicional: Waypoints Vazios

```xml
<rota>
  <pontosRota/>  <!-- ❌ VAZIO! -->
</rota>
```

**Causa:** Waypoints não estão sendo passados corretamente do `VpoEmissaoService` para o `VpoXmlBuilder`.

**Localização:** [VpoEmissaoService.php:318](app/Services/Vpo/VpoEmissaoService.php#L318)

```php
// Linha 318 - getRotaWithWaypoints()
$waypoints = [];  // Está retornando array vazio!
```

---

## ✅ Comparação: Roteirizador (FUNCIONA) vs VPO (NÃO FUNCIONA)

| Aspecto | Roteirizador | VPO |
|---------|--------------|-----|
| Assinatura Digital | ✅ RSA-SHA1 (4,748 bytes) | ✅ RSA-SHA1 (4,699 bytes) |
| Namespace | ✅ http://www.nddigital.com.br/nddcargo | ✅ http://www.nddigital.com.br/nddcargo |
| Versão | ✅ 4.2.12.0 | ✅ 4.2.12.0 |
| Token | ✅ Nos atributos | ✅ Nos atributos |
| ProcessCode | ✅ 2027 (Roteirizador) | ✅ 2028 (VPO) |
| ExchangePattern | ✅ 7 (Sync) | ✅ 9 (Async) |
| Campos Obrigatórios | ✅ Todos preenchidos | ❌ 4 campos faltando |
| **Aparece no painel NDD Cargo** | ✅ **SIM** | ❌ **NÃO** |

---

## 🔧 Soluções Propostas

### Solução 1: Adicionar JOIN para `endereco_estado` (RÁPIDO)

**Modificar:** `VpoDataSyncService.php`

```php
// Linha ~120 - Adicionar JOIN para pegar sigla do estado
$sql = "SELECT t.codtrn, ..., e.siglaest
        FROM PUB.transporte t
        LEFT JOIN PUB.estado e ON t.codest = e.codest
        WHERE t.codtrn = {$codtrn}";

// Mapear
$vpoData['endereco_estado'] = $transporteData['siglaest'] ?? null;
```

**Status:** ⚠️ Possível, mas precisa testar

---

### Solução 2: Verificar se `cdantt` e `datvldantt` existem no Progress (INVESTIGAÇÃO)

**Comandos para testar:**

```sql
-- Verificar se campos existem
SELECT TOP 1 cdantt, datvldantt FROM PUB.transporte WHERE cdantt IS NOT NULL

-- Se existirem, atualizar query sync
SELECT codtrn, cdantt, datvldantt, ... FROM PUB.transporte WHERE codtrn = ?
```

**Status:** 🔍 PRECISA INVESTIGAÇÃO no Progress Database

---

### Solução 3: Fixar waypoints no `VpoEmissaoService` (RÁPIDO)

**Problema:** `getRotaWithWaypoints()` está retornando array vazio.

**Localização:** [VpoEmissaoService.php:225-295](app/Services/Vpo/VpoEmissaoService.php#L225-L295)

**Verificar:**
- Se `$rotaMunicipios['data']['municipios']` está retornando dados
- Se coordenadas lat/lon estão presentes
- Se itinerário está sendo carregado corretamente

**Status:** ⚠️ PRECISA DEBUG

---

### Solução 4: Política de Fallback para Campos Faltantes (WORKAROUND)

**Para `contato_email`:**
```php
// Se vazio, usar email padrão
$email = $transporteData['e-mail'] ?: 'naotem@tambasa.com.br';
```

**Para `antt_rntrc` e `antt_validade`:**
- Se não existirem no Progress → **NÃO TEM COMO emitir VPO!**
- Alternativa: Consultar API ANTT em tempo real (lento, mas possível)

**Status:** ⚠️ ÚLTIMO RECURSO (pode não funcionar)

---

## 📝 Conclusão

### ✅ O que está funcionando:
1. Assinatura digital RSA-SHA1 implementada corretamente
2. Estrutura SOAP CrossTalk correta
3. Comunicação HTTP com NDD Cargo funcionando (HTTP 200)
4. Mapeamento de 15/19 campos VPO funcionando

### ❌ O que está bloqueando:
1. **Dados obrigatórios faltando no Progress** (cdantt, datvldantt)
2. **JOIN de estado não implementado** (endereco_estado)
3. **Waypoints vazios** (rota sem pontos)
4. **Email vazio** (contato_email)

### 🎯 Próximo Passo CRÍTICO:

**INVESTIGAR no Progress Database** se os campos `cdantt` e `datvldantt` existem e têm dados:

```bash
# Via ProgressService
curl -X POST http://localhost:8002/api/progress/query \
  -H "Content-Type: application/json" \
  -d '{"query": "SELECT TOP 10 codtrn, nomtrn, cdantt, datvldantt FROM PUB.transporte WHERE cdantt IS NOT NULL"}'
```

**Se os campos NÃO existirem ou estiverem todos NULL:**
→ **IMPOSSÍVEL emitir VPO** sem consultar API ANTT externa!

---

**Autor:** Claude Code
**Data:** 2025-12-08 15:40
**Status:** 🔴 BLOQUEADO - Aguardando validação de dados Progress
