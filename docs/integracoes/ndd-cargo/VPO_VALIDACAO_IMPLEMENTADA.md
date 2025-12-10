# VPO Emission - Validação de Campos Obrigatórios Implementada

**Data:** 2025-12-08
**Status:** ✅ IMPLEMENTADO E TESTADO
**Branch:** `feature/vpo-emissao-wizard`

---

## 🎯 Objetivo

Implementar validação completa de **todos os 19 campos obrigatórios** antes de tentar emitir VPO na NDD Cargo.

**Requisito do Usuário:**
> "tem que ter validação de todos os campos que são validados e caso não tenha eles no banco faça o pedido de cadastro na tela para o usuário"

---

## ✅ O Que Foi Implementado

### 1. Validação de Campos Obrigatórios

**Arquivo:** `app/Services/Vpo/VpoEmissaoService.php`

**Método Principal:** `validarCamposObrigatorios(VpoTransportadorCache $vpoCache): array`
**Linhas:** 503-573

**Campos Validados (19 campos):**

| Categoria | Campos (Total) | Campos Obrigatórios |
|-----------|----------------|---------------------|
| **Transportador** | 5 | cpf_cnpj, antt_rntrc, antt_nome, antt_validade, antt_status |
| **Veículo** | 3 | placa, veiculo_tipo, veiculo_modelo |
| **Condutor** | 5 | condutor_rg, condutor_nome, condutor_sexo, condutor_nome_mae, condutor_data_nascimento |
| **Endereço** | 4 | endereco_rua, endereco_bairro, endereco_cidade, endereco_estado |
| **Contato** | 2 | contato_celular, contato_email |

**Lógica de Validação:**
```php
foreach ($camposObrigatorios as $campo => $descricao) {
    $valor = $vpoData[$campo] ?? null;

    // Considera INVÁLIDO se:
    // - null
    // - string vazia ('')
    // - apenas espaços em branco ('   ')
    if ($valor === null || $valor === '' || trim((string) $valor) === '') {
        $camposFaltantes[] = [
            'campo' => $campo,
            'descricao' => $descricao,
            'categoria' => $this->getCategoriaCampo($campo)
        ];
    }
}
```

### 2. Agrupamento por Categoria

**Método:** `getCategoriaCampo(string $campo): string`
**Linhas:** 578-601

Agrupa campos em 5 categorias para facilitar a compreensão do usuário:
- Transportador
- Veículo
- Condutor
- Endereço
- Contato

### 3. Mensagem Amigável para o Usuário

**Método:** `construirMensagemValidacao(array $camposFaltantes, VpoTransportadorCache $vpoCache): string`
**Linhas:** 606-637

**Formato da Mensagem:**
```
Não é possível emitir Vale Pedágio (VPO). Faltam 4 campos obrigatórios (Score: 45/100).

Por favor, cadastre os seguintes dados:

• Transportador:
  - Código RNTRC (Registro ANTT)
  - Data de validade do RNTRC

• Endereço:
  - Estado (UF)

• Contato:
  - Email de contato

Após cadastrar os dados, sincronize novamente e tente a emissão.
```

### 4. Integração no Fluxo de Emissão

**Método Modificado:** `iniciarEmissao(int $codpac, int $rotaId): array`
**Linhas:** 70-86

**Fluxo:**
1. Carregar pacote do Progress ✅
2. Sincronizar dados VPO (VpoDataSyncService) ✅
3. Buscar rota SemParar ✅
4. **VALIDAR CAMPOS OBRIGATÓRIOS** ✅ ← NOVA ETAPA
5. Se validação falhar → retornar erro detalhado
6. Se validação passar → prosseguir com emissão

**Código:**
```php
// 2.5. VALIDAR campos obrigatórios (CRÍTICO!)
$validacao = $this->validarCamposObrigatorios($vpoCache);

if (!$validacao['valido']) {
    Log::warning("VPO Emissao: Validacao falhou", [
        'codtrn' => $codtrn,
        'score' => $vpoCache->score_qualidade,
        'campos_faltantes' => $validacao['campos_faltantes']
    ]);

    return [
        'success' => false,
        'data' => null,
        'error' => $validacao['mensagem'],
        'validation_errors' => $validacao['campos_faltantes'],
        'score_qualidade' => $vpoCache->score_qualidade
    ];
}
```

---

## 🧪 Testes Realizados

### Teste 1: Validação com Campos Faltantes (codtrn 3247)

**Comando:**
```bash
curl -X POST http://localhost:8002/api/vpo/emissao/iniciar \
  -H "Content-Type: application/json" \
  -d '{"codpac": 3048790, "rota_id": 204}'
```

**Resultado:** ✅ SUCESSO (Validação bloqueou emissão)

**Response (HTTP 200):**
```json
{
  "success": false,
  "message": "Não é possível emitir Vale Pedágio (VPO). Faltam 4 campos obrigatórios (Score: 45/100).\n\nPor favor, cadastre os seguintes dados:\n\n• Transportador:\n  - Código RNTRC (Registro ANTT)\n  - Data de validade do RNTRC\n\n• Endereço:\n  - Estado (UF)\n\n• Contato:\n  - Email de contato\n\nApós cadastrar os dados, sincronize novamente e tente a emissão.",
  "validation_errors": [
    {
      "campo": "antt_rntrc",
      "descricao": "Código RNTRC (Registro ANTT)",
      "categoria": "Transportador"
    },
    {
      "campo": "antt_validade",
      "descricao": "Data de validade do RNTRC",
      "categoria": "Transportador"
    },
    {
      "campo": "endereco_estado",
      "descricao": "Estado (UF)",
      "categoria": "Endereço"
    },
    {
      "campo": "contato_email",
      "descricao": "Email de contato",
      "categoria": "Contato"
    }
  ],
  "score_qualidade": 45
}
```

**Log (Laravel):**
```
[2025-12-08 15:42:11] local.WARNING: VPO Emissao: Validacao falhou
{
  "codtrn": 3247,
  "score": 45,
  "campos_faltantes": [
    {"campo":"antt_rntrc","descricao":"Código RNTRC (Registro ANTT)","categoria":"Transportador"},
    {"campo":"antt_validade","descricao":"Data de validade do RNTRC","categoria":"Transportador"},
    {"campo":"endereco_estado","descricao":"Estado (UF)","categoria":"Endereço"},
    {"campo":"contato_email","descricao":"Email de contato","categoria":"Contato"}
  ]
}
```

**Verificação:**
- ✅ Validação detectou os 4 campos faltantes
- ✅ Agrupou corretamente por categoria
- ✅ Retornou mensagem amigável
- ✅ Emissão foi bloqueada (não enviou SOAP)
- ✅ Score quality incluído (45/100)

---

## 📊 Estrutura da Resposta

### Validação Bem-Sucedida (Todos os Campos OK)
```json
{
  "success": true,
  "data": {
    "emissao_id": 123,
    "uuid": "7eb79c6e-f56f-4fa1-96ef-a00e7ca7c296",
    "status": "processing",
    "...": "..."
  }
}
```

### Validação Falhou (Campos Faltando)
```json
{
  "success": false,
  "message": "Não é possível emitir Vale Pedágio (VPO). Faltam N campos obrigatórios (Score: XX/100).\n\n...",
  "validation_errors": [
    {
      "campo": "antt_rntrc",
      "descricao": "Código RNTRC (Registro ANTT)",
      "categoria": "Transportador"
    }
  ],
  "score_qualidade": 45
}
```

**Campos Retornados:**
- `success` (boolean): `false` quando validação falha
- `message` (string): Mensagem formatada para exibição ao usuário
- `validation_errors` (array): Lista de campos faltantes com detalhes
- `score_qualidade` (int): Score 0-100 indicando completude dos dados

---

## 🎨 Interface de Teste

**Arquivo:** `public/test-vpo-validacao.html`

**URL:** http://localhost:8002/test-vpo-validacao.html

**Funcionalidades:**
- Input para codpac e rota_id
- Botão "Testar Validação"
- Exibe resultado formatado com:
  - Badge de score (vermelho/amarelo/verde)
  - Lista de campos faltantes agrupados por categoria
  - Mensagem completa para o usuário
  - JSON completo (collapse)

**Screenshot do Resultado:**
```
🚫 Campos Obrigatórios Faltando
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Score de Qualidade: [45/100]
Total de Campos Faltantes: 4

Mensagem para o Usuário:
┌────────────────────────────────────┐
│ Não é possível emitir Vale Pedágio │
│ (VPO). Faltam 4 campos obrigatórios│
│ (Score: 45/100).                   │
│                                     │
│ Por favor, cadastre os seguintes   │
│ dados:                              │
│                                     │
│ • Transportador:                   │
│   - Código RNTRC (Registro ANTT)  │
│   - Data de validade do RNTRC     │
│                                     │
│ • Endereço:                        │
│   - Estado (UF)                    │
│                                     │
│ • Contato:                         │
│   - Email de contato               │
└────────────────────────────────────┘

📊 Campos Agrupados por Categoria:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Transportador:
  • antt_rntrc - Código RNTRC (Registro ANTT)
  • antt_validade - Data de validade do RNTRC

Endereço:
  • endereco_estado - Estado (UF)

Contato:
  • contato_email - Email de contato
```

---

## 📝 Próximos Passos

### ETAPA 1: Resolver Dados Faltantes (CRÍTICO)

#### Problema 1: `antt_rntrc` e `antt_validade` (Progress Database)

**Status:** ❌ DADOS NÃO EXISTEM NO PROGRESS

**Investigação Necessária:**
```sql
-- Verificar se campos existem na tabela transporte
SELECT TOP 10 codtrn, nomtrn, cdantt, datvldantt
FROM PUB.transporte
WHERE cdantt IS NOT NULL AND cdantt <> ''
```

**Ações:**
1. Se campos existirem mas estiverem vazios → **Cadastro obrigatório pelo usuário**
2. Se campos NÃO existirem → **Impossível emitir VPO sem integração com API ANTT**

**Alternativa (última opção):**
- Integrar com API ANTT em tempo real para buscar RNTRC e validade
- Problema: Lento e depende de API externa

#### Problema 2: `endereco_estado` (JOIN não implementado)

**Status:** ⚠️ POSSÍVEL IMPLEMENTAR

**Solução:**
Modificar `VpoDataSyncService.php` para fazer JOIN com tabela `estado`:

```php
// Linha ~120 - VpoDataSyncService::syncTransportador()
$sql = "SELECT
    t.codtrn,
    t.nomtrn,
    t.codest,
    e.siglaest,  -- ← ADICIONAR
    ...
FROM PUB.transporte t
LEFT JOIN PUB.estado e ON t.codest = e.codest  -- ← ADICIONAR JOIN
WHERE t.codtrn = {$codtrn}";

// Mapear
$vpoData['endereco_estado'] = $transporteData['siglaest'] ?? null;
```

**Prioridade:** ALTA (implementação rápida)

#### Problema 3: `contato_email` (Vazio no Progress)

**Status:** ⚠️ CAMPO EXISTE MAS ESTÁ VAZIO

**Soluções:**
1. **Cadastro obrigatório:** Pedir ao usuário para cadastrar email (RECOMENDADO)
2. **Fallback:** Usar email genérico `naotem@tambasa.com.br` (NÃO RECOMENDADO)

**Prioridade:** MÉDIA

#### Problema 4: Waypoints Vazios

**Status:** ❌ `pontosRota` VAZIO NO XML

**Causa:** `getRotaWithWaypoints()` retorna array vazio

**Localização:** [VpoEmissaoService.php:318](app/Services/Vpo/VpoEmissaoService.php#L318)

**Investigação:**
```php
// Verificar se dados estão retornando do método
$rotaMunicipios = $this->progressService->getSemPararRotaWithMunicipios($rotaId);
Log::debug('Rota Municipios', ['data' => $rotaMunicipios]);
```

**Prioridade:** ALTA

---

### ETAPA 2: Frontend (Vue.js)

**Após resolver dados faltantes**, implementar frontend:

1. **Wizard de Emissão VPO** (`resources/ts/pages/vpo-emissao/nova.vue`)
   - Step 1: Selecionar pacote
   - Step 2: Validar transportador
   - Step 3: **Exibir erros de validação** ← USAR `validation_errors`
   - Step 4: Selecionar rota
   - Step 5: Mapa interativo
   - Step 6: Confirmação
   - Step 7: Polling de status
   - Step 8: Resultado final

2. **Componente de Validação** (`VpoValidationErrors.vue`)
   ```vue
   <template>
     <v-alert type="error" v-if="errors.length > 0">
       <h3>Campos Obrigatórios Faltando (Score: {{ score }}/100)</h3>
       <div v-for="categoria in groupedErrors" :key="categoria.nome">
         <strong>{{ categoria.nome }}:</strong>
         <ul>
           <li v-for="campo in categoria.campos" :key="campo.campo">
             {{ campo.descricao }}
           </li>
         </ul>
       </div>
       <p>Por favor, cadastre os dados e tente novamente.</p>
     </v-alert>
   </template>
   ```

3. **Lista de Emissões** (`resources/ts/pages/vpo-emissao/index.vue`)
   - Histórico de emissões
   - Status: pending/processing/completed/failed/cancelled
   - Download de recibos

---

## 🎯 Resumo do Estado Atual

### ✅ Implementado e Funcionando
1. **Validação completa de 19 campos obrigatórios** ✅
2. **Agrupamento por categoria** ✅
3. **Mensagem amigável para o usuário** ✅
4. **Bloqueio de emissão quando campos faltam** ✅
5. **Estrutura de resposta padronizada** ✅
6. **Interface de teste HTML** ✅
7. **Logs detalhados** ✅

### ❌ Pendente (Bloqueadores)
1. **Dados faltantes no Progress** (antt_rntrc, antt_validade)
2. **JOIN de estado** (endereco_estado) - FÁCIL DE IMPLEMENTAR
3. **Waypoints vazios** (pontosRota)
4. **Email vazio** (contato_email) - Precisa cadastro

### ⏸️ Aguardando (Próximas Sprints)
1. Frontend Vue.js
2. Integração com VPO list/detail pages
3. Download de recibos
4. Histórico de emissões

---

## 📚 Referências

- **Código:** `app/Services/Vpo/VpoEmissaoService.php` (linhas 70-86, 497-637)
- **Teste:** `public/test-vpo-validacao.html`
- **Problemas:** `docs/integracoes/ndd-cargo/VPO_PROBLEMAS_ENCONTRADOS.md`
- **Sync:** `docs/integracoes/ndd-cargo/VPO_DATA_SYNC_COMPLETO.md`

---

**Autor:** Claude Code
**Data:** 2025-12-08 16:00
**Status:** ✅ VALIDAÇÃO IMPLEMENTADA E TESTADA
**Próximo Passo:** Investigar dados faltantes no Progress (antt_rntrc, antt_validade, endereco_estado)
