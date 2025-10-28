# ANÁLISE CRÍTICA: Erros na Tela de Compra de Viagem Laravel vs Progress

**Data:** 2025-10-27
**Referência:** `SEMPARAR_AI_REFERENCE.md` (Progress compraRota.p)
**Status:** 🔴 IMPLEMENTAÇÃO INCOMPLETA E COM ERROS CRÍTICOS

---

## ❌ ERROS CRÍTICOS - FLUXO QUEBRADO

### 1. **ERRO FATAL: Verificação de Preço NÃO Cria Rota Temporária**

**Arquivo:** `CompraViagemController.php` linha 511-577
**Severity:** 🔴 CRÍTICO - Quebra fluxo completo

#### O que o Progress faz (compraRota.p linha 592-696):
```progress
1. roterizaCa() → Cria rota temporária no SemParar
   - Build t-entrega from semPararRotMu
   - Apply regional logic (Pará, Acre, Amazonas)
   - Add pedido deliveries if pacote exists
   - Build pontosParadaDset XML
   - SOAP roteirizarPracasPedagio() → GET pracas
   - SOAP cadastrarRotaTemporaria() → GET codRotaSemParar
   - Export rotas.csv + email

2. verificaPreco() → Calcula preço da rota temporária
   - SOAP obterCustoRota(nomRotSemParar, placa, eixos)
   - Return preco

3. Display f_Preco modal

4. compraViagem() → Usa rota temporária já criada
   - SOAP comprarViagem(nomRotSemParar...)
```

#### O que o Laravel faz (ERRADO):
```php
verificarPreco():
  verifyTripPriceSemParar(
    cod_rota,        // ❌ ERRADO: Passa ID da rota local (204)
    codpac,
    qtd_eixos,
    placa,
    data_inicio,
    data_fim
  )

  // ❌ NÃO chama roterizaCa()
  // ❌ NÃO cria rota temporária no SemParar
  // ❌ NÃO retorna nomRotSemParar (ex: "RJ-204-123456")
  // ❌ NÃO retorna codRotaSemParar (ex: "123456")
```

**Consequência:**
- ❌ API SemParar obterCustoRota() recebe ID interno (204) ao invés do nome da rota temporária
- ❌ Preço calculado está ERRADO ou API retorna erro
- ❌ Compra de viagem IMPOSSÍVEL (não tem rota temporária criada)

**Correção Necessária:**
```php
verificarPreco():
  // PASSO 1: Criar rota temporária
  $rotaTemp = roterizaCa($cod_rota, $codpac, $flgretorno)
  // Retorna: nomRotSemParar, codRotaSemParar

  // PASSO 2: Calcular preço da rota temporária
  $preco = verificaPreco($rotaTemp['nomRotSemParar'], $placa, $eixos)

  return [
    'valor' => $preco,
    'nome_rota' => $rotaTemp['nomRotSemParar'],    // "RJ-204-123456"
    'cod_rota' => $rotaTemp['codRotaSemParar'],    // "123456"
    'numero_viagem' => null  // Ainda não comprou
  ]
```

---

### 2. **ERRO FATAL: Falta Lógica de Roteirização (roterizaCa)**

**Arquivo:** `ProgressService.php`
**Severity:** 🔴 CRÍTICO - Funcionalidade core ausente

#### O que está faltando:
```progress
// Progress: Rota.cls método roterizaCa() (SEMPARAR_AI_REFERENCE.md linha 125-212)

roterizaCa(flgretorno, sPararRotID, codpac, OUT nomeRota, OUT codRotaSemParar, OUT erro):
  STEP 1: Build t-entrega from semPararRotMu
  STEP 2: If pacote exists AND NOT CD:
    - Add pedido deliveries from arqrdnt (GPS coordinates)
    - Filter duplicates by lat/lon
    - Apply regional logic:
      * Pará (codest=16): Only 1 entry if not flgretorno
      * Acre/Amazonas (12/13) with "AC" in codrot: Only 1 entry
      * Specific IBGE codes: 5103379, 1501576 excluded
      * IBGE 1502509: excluded if not flgretorno
  STEP 3: If flgretorno AND NOT CD:
    - Remove intermediate points (keep seqped < counter2 and last)
    - Set last seqped = 0
  STEP 4: Export pontos.csv + email
  STEP 5: Build pontosParadaDset XML
  STEP 6: SOAP roteirizarPracasPedagio(pontosXML, opcoesXML, cToken, OUT xml)
  STEP 7: Parse pracaPedagio dataset
  STEP 8: Build pracas XML list
  STEP 9: SOAP cadastrarRotaTemporaria(pracasXML, nomeRota, cToken, OUT xml)
  STEP 10: Parse <id> → codRotaSemParar
```

**Laravel atual:** ❌ NÃO EXISTE

**Consequência:**
- ❌ Impossível criar rotas no SemParar
- ❌ Preço não pode ser calculado
- ❌ Compra não pode ser efetuada
- ❌ Lógica regional (Pará, Acre, Amazonas) perdida
- ❌ Entregas de pacotes não são incluídas

**Correção Necessária:**
Criar `SemPararService::roterizaCa()` com TODA a lógica documentada no `SEMPARAR_AI_REFERENCE.md` linhas 125-212.

---

### 3. **ERRO FATAL: Falta Método retornoCa (Personalização de Pontos)**

**Arquivo:** `ProgressService.php`
**Severity:** 🔴 CRÍTICO - Feature ausente

#### O que o Progress faz (compraRota.p linha 604-632):
```progress
if flgPersonalizado then:
  1. conexao:retornoCa() → Returns t-entrega with municipalities
  2. Display CadastroEntrega frame
  3. User edits t-entrega (F1-F7 drag/drop)
  4. conexao:retornoTemp(INPUT TABLE t-entrega) → Create temp route with custom points
```

**Laravel atual:** ❌ NÃO EXISTE

**Consequência:**
- ❌ Usuário NÃO pode customizar pontos de entrega
- ❌ Feature "Personalizar Pontos" quebrada

**Correção Necessária:**
```php
// SemPararService.php
retornoCa($flgretorno, $sPararRotID, $codpac): array
  // Returns t-entrega table for editing

retornoTemp($tentrega, $sPararRotID, $codpac): array
  // Creates temp route with custom points
```

---

### 4. **ERRO CRÍTICO: Lógica Regional Ausente**

**Arquivo:** `ProgressService.php`
**Severity:** 🔴 CRÍTICO - Rotas incorretas

#### Lógica documentada (SEMPARAR_AI_REFERENCE.md linha 536-606):
```progress
// Pará state handling (codest=16)
IF estado.codest = 16 THEN DO:
  IF countpara = 1 AND NOT flgretorno THEN
    DELETE t-entrega.  // Only 1 Pará entry allowed
  FIND estado WHERE codest = 15.  // Switch to Pará main
  FIND municipio WHERE codmun = 140 AND codest = 15.
  ASSIGN countPara = 1.
END.

// Acre/Amazonas handling (codest=12/13)
IF (estado.codest = 12 OR estado.codest = 13) AND ACAM THEN DO:
  FIND estado WHERE codest = 12.  // Acre
  FIND municipio WHERE codmun = 40.
  IF countACAM = 1 AND NOT flgretorno THEN
    DELETE t-entrega.  // Only 1 AC/AM entry
  ASSIGN countACAM = 1.
END.

// IBGE code exclusions
IF cdibge = 5103379 → SKIP  // Always
IF cdibge = 1501576 → SKIP  // Always
IF cdibge = 1502509 AND NOT flgretorno → SKIP
```

**Laravel atual:** ❌ NÃO IMPLEMENTADO

**Consequência:**
- ❌ Rotas para Pará/Amapá ERRADAS
- ❌ Rotas para Acre/Amazonas ERRADAS
- ❌ Códigos IBGE especiais não são excluídos
- ❌ API SemParar pode rejeitar rotas mal formadas

---

### 5. **ERRO CRÍTICO: Falta Processamento de Coordenadas GPS**

**Arquivo:** `ProgressService.php`
**Severity:** 🔴 CRÍTICO - Entregas não mapeadas

#### O que o Progress faz (Rota.cls linha 147-189):
```progress
// Load deliveries from arqrdnt (GPS coordinates)
FOR EACH arqrdnt WHERE arqrdnt.codpac = codpac NO-LOCK:
  // Convert Progress GPS format to decimal
  latitude = formatCoordinates(arqrdnt.lat)   // "230876543" → "-23,0876543"
  longitude = formatCoordinates(arqrdnt.long)

  // Check duplicate by lat/lon
  FIND FIRST t-entrega WHERE
    (t-entrega.latitude = latitude AND t-entrega.longitude = longitude) OR
    t-entrega.desCidade = arqrdnt.cidade NO-ERROR.

  IF NOT AVAIL t-entrega:
    CREATE t-entrega.
    ASSIGN t-entrega.latitude = latitude
           t-entrega.longitude = longitude
           t-entrega.end_destinatario = arqrdnt.end
           t-entrega.codibge = municipio.cdibge
           t-entrega.seqped = counter++.
END.
```

**Função formatCoordinates (SEMPARAR_AI_REFERENCE.md linha 636-648):**
```progress
INPUT: "230876543" (Progress arqrdnt.lat format)
PROCESS:
  1. Remove: "W", "N", "E", "S", "-", ".", ","
  2. Format: "-XX,XXXXXXXX"
OUTPUT: "-23,0876543"
```

**Laravel atual:** ❌ NÃO IMPLEMENTADO

**Consequência:**
- ❌ Entregas de pacotes NÃO são incluídas na rota
- ❌ Rota criada contém APENAS municípios da semPararRotMu
- ❌ Cliente não recebe rota real do pacote

---

### 6. **ERRO CRÍTICO: Falta SOAP Client SemParar**

**Arquivo:** `SemPararService.php` (NÃO EXISTE)
**Severity:** 🔴 CRÍTICO - Core feature ausente

#### O que precisa existir (SEMPARAR_AI_REFERENCE.md linha 52-108):
```php
class SemPararService {
  private $soapClient;
  private $cToken;  // Session token

  // AUTHENTICATION
  autenticarUsuario(): string
    // SOAP autenticarUsuario("2024209702", "CORPORATIVO", "Tambasa20")
    // Parse <sessao>VALUE</sessao>
    // Store cToken

  // VEHICLE STATUS
  obterStatusVeiculo($placa): array
    // SOAP obterStatusVeiculo($placa, $cToken)
    // Parse <descricao>, <eixos>, <proprietario>, <tag>

  // ROUTING
  roteirizarPracasPedagio($pontosXML, $opcoesXML): array
    // SOAP roteirizarPracasPedagio($pontosXML, $opcoesXML, $cToken)
    // Parse pracaPedagio dataset

  cadastrarRotaTemporaria($pracasXML, $nomeRota): string
    // SOAP cadastrarRotaTemporaria($pracasXML, $nomeRota, $cToken)
    // Parse <id> → codRotaSemParar

  // PRICING
  obterCustoRota($nomeRota, $placa, $eixos, $inicio, $fim): float
    // SOAP obterCustoRota($nomeRota, $placa, $eixos, $inicio, $fim, $cToken)
    // Parse <valor>

  // PURCHASE
  comprarViagem($nomeRota, $placa, $eixos, $inicio, $fim, $itemFin1): string
    // SOAP comprarViagem($nomeRota, $placa, $eixos, $inicio, $fim, $itemFin1, "", "", $cToken)
    // Parse <numero> → numeroViagem

  // RECEIPT
  obterReciboViagem($numeroViagem): array
    // SOAP obterReciboViagem($numeroViagem, $cToken)
    // Parse obterReciboViagemReturnDset

  // EXTRACT
  obterExtratoCreditos($inicio, $fim): array
    // SOAP obterExtratoCreditos($inicio, $fim, $cToken)
    // Upsert sParargetExtra
}
```

**WSDL Endpoint (SEMPARAR_AI_REFERENCE.md linha 608-630):**
```
Production:
  WSDL: https://app.viafacil.com.br/wsvp/ValePedagio?wsdl
  Service: ValePedagioService
  Port: ValePedagio
  TLS: 1.3 + TLS_AES_128_GCM_SHA256
```

**Laravel atual:** ❌ NÃO EXISTE

**Consequência:**
- ❌ NENHUMA chamada SOAP real funciona
- ❌ Sistema 100% simulado
- ❌ Impossível comprar viagens reais

---

## ⚠️ ERROS GRAVES - LÓGICA INCORRETA

### 7. **ERRO: Validação de Rota Sugerida Incompleta**

**Arquivo:** `CompraViagemController.php` linha 169-190
**Severity:** ⚠️ GRAVE - Rota sugerida pode estar errada

#### O que o Progress faz (compraRota.p linha 432-475):
```progress
// STEP 1: Try pacsoc first
find first pacsoc where pacsoc.codpacsoc = pacote.codpac.
if avail pacsoc:
  find first b_pacote where b_pacote.codpac = pacsoc.codpac.
  find first introt of b_pacote.

// STEP 2: If no pacsoc, use introt of pacote
if not avail introt:
  find first introt of pacote.

// STEP 3: Find semPararIntrot matching
if flgretorno = false:
  for each semPararIntrot where semPararIntrot.codrot = introt.codrot,
    first sempararrot where sempararrot.sPararRotID = semPararIntrot.sPararRotID
                      and index(sempararrot.desSPararRot,"RETORNO") = 0:
    // Found normal route

if flgretorno = true:
  for each sempararintrot where sempararintrot.codrot = introt.codrot,
    first sempararrot where sempararrot.spararrotid = sempararintrot.spararrotid
                      and index(sempararrot.desspararrot,"RETORNO")> 0:
    // Found return route
```

**Laravel atual (linha 172-186):**
```php
// STEP 1: Try pacsoc
$rotaPacsoc = getRotaSugeridaPorPacsoc($codpac);  // ✅ OK

// STEP 2: Try introt
$rotaIntrot = getRotaSugeridaPorIntrot($codpac, false);  // ❌ ERRADO

// PROBLEMA: $flgretorno é false HARD-CODED
// Deveria ser: getRotaSugeridaPorIntrot($codpac, $flgretorno)
```

**Consequência:**
- ❌ Se usuário ativa "Retorno", rota sugerida NÃO considera flag
- ❌ Rota sugerida pode ser de IDA quando deveria ser RETORNO

**Correção:**
```php
// linha 181
$rotaIntrot = $this->progressService->getRotaSugeridaPorIntrot(
  $validated['codpac'],
  $request->input('flgretorno', false)  // ✅ CORRETO: Passa flag de retorno
);
```

---

### 8. **ERRO: Falta Toggle F3 de Retorno**

**Arquivo:** `index.vue` linha 561-567
**Severity:** ⚠️ GRAVE - UX quebrada

#### O que o Progress faz (compraRota.p linha 176-196):
```progress
on f3 of vCodPac in frame cadastroRota do:
  if not flgcd and not flgretorno then do:
    assign flgRetorno = true.
    frame cadastroRota:title = titleCD + "- RETORNO ".
    return no-apply.
  end.
  if not flgcd and flgretorno then do:
    assign flgretorno = false.
    frame CadastroRota:title = " COMPRA DE VIAGENS SEMPARAR - OUTROS ".
    return no-apply.
  end.
  // ... (CD logic similar)
end.
```

**Laravel atual:**
```vue
<VSwitch
  v-model="modoRetorno"
  label="Retorno"
  :disabled="verificaPacote"  <!-- ❌ ERRADO: Desabilita após validar pacote -->
/>
```

**Problema:**
- ❌ Switch é desabilitado após validar pacote
- ❌ Progress permite toggle F3 DURANTE processo
- ❌ Usuário não pode mudar modo de IDA→RETORNO sem resetar tudo

**Correção:**
```vue
<VSwitch
  v-model="modoRetorno"
  label="Retorno"
  :disabled="verificaRota"  <!-- ✅ CORRETO: Desabilita só após selecionar rota -->
  @update:model-value="onToggleRetorno"
/>

// Script:
const onToggleRetorno = () => {
  if (verificaPacote.value) {
    // Recarrega rota sugerida considerando novo flag
    rotaId.value = null
    carregarTodasRotas()
  }
}
```

---

### 9. **ERRO: Falta Frame f_Placa Modal com Edição de Eixos**

**Arquivo:** `index.vue` linha 1000-1138
**Severity:** ⚠️ GRAVE - UX diferente do Progress

#### O que o Progress faz (compraRota.p linha 71-82):
```progress
define frame f_Placa
    vDescricaoVei label "Desc" format "x(31)" colon 6
    skip
    vEixos label "Eixos" format "x(31)" colon 6  <!-- EDITÁVEL -->
    skip
    vProprietario label "Dono" format "x(31)" colon 6
    skip
    vTag label "Tag" format "x(31)" colon 6
    skip(1)
    btConfirma at 8
    btCancela at 22
    with overlay size 40 by 10 at row 10 col 4.

// Validação eixos (linha 400-419)
on return of vEixos in frame f_Placa do:
    if vEixos:screen-value = "" or integer(vEixos:screen-value) < 2 or integer(vEixos) > 10 then do:
      message "Eixos invalidos (minimo 2, maximo 10)".
      return no-apply.
    end.
    enable btConfirma btCancela.
end.
```

**Laravel atual (linha 1080-1112):**
```vue
<!-- ✅ Eixos editável existe -->
<VTextField
  v-model.number="eixos"
  type="number"
  min="2"
  max="10"
/>

<!-- ❌ MAS: Falta validação de ENTER no campo eixos -->
<!-- ❌ Progress: User edita eixos → ENTER → Valida → Habilita btConfirma -->
<!-- ❌ Laravel: User edita eixos → Clica direto em "Confirmar" -->
```

**Problema:**
- ⚠️ UX diferente, mas não quebra funcionalidade
- ⚠️ Progress exige ENTER em vEixos antes de habilitar btConfirma
- ⚠️ Laravel permite editar e confirmar direto

**Sugestão:**
Manter comportamento Laravel (mais simples e intuitivo).

---

### 10. **ERRO: Falta Email com CSV de Pontos**

**Arquivo:** `ProgressService.php`
**Severity:** ⚠️ GRAVE - Feature ausente

#### O que o Progress faz (compraRota.p linha 1000-1018):
```progress
procedure envia_email_anexo:
  nom_arq = os-getenv("_RELATO") + "/rotas.csv".

  run ftp/crr/enviaemail_com_anexo.p(
    /* De      */ "corporativo@tambasa.com.br",
    /* Copia   */ "",
    /* Para    */ usuario.e-mail,
    /* Assunto */ "Pracas da rota gerada",
    /* Corpo   */ "Em anexo rota",
    /* Arquivo */ nom_arq
  ) no-error.

  os-delete value(nom_arq).
end procedure.
```

**Chamado em:** linha 663 (após roterizaCa)

**Laravel atual:** ❌ NÃO IMPLEMENTADO

**Consequência:**
- ❌ Usuário NÃO recebe CSV com praças de pedágio
- ❌ Auditoria reduzida

---

## ⚠️ ERROS MÉDIOS - Features Ausentes

### 11. **ERRO: Falta Modo "Personalizar Pontos"**

**Arquivo:** `index.vue`
**Severity:** ⚠️ MÉDIO - Feature completa ausente

#### O que o Progress faz (compraRota.p linha 593-632):
```progress
// ASK: "DESEJA PERSONALIZAR PONTOS?"
run rt/rtedmsg.p("DESEJA PERSONALIZAR PONTOS?").

if return-value = "S":
  // STEP 1: Load default points
  conexao:retornoCa(flgRetorno, sPararRotID, codpac, OUTPUT TABLE t-entrega, output erro).

  // STEP 2: Display CadastroEntrega frame
  enable vMunicipio with frame cadastroEntrega.
  wait-for end-error of vMunicipio in frame CadastroEntrega.

  // STEP 3: User edits points (F1-F7 drag/drop/add/remove)

  // STEP 4: Create temp route with custom points
  conexao:retornoTemp(INPUT TABLE t-entrega, sPararRotID, codpacString,
                      OUT nomRotSemParar, OUT codRotaSemParar, OUT erro).
```

**Laravel atual:** ❌ NÃO IMPLEMENTADO

**Features Progress que faltam:**
- ❌ Dialog "Deseja personalizar pontos?"
- ❌ Frame CadastroEntrega (browse + add/remove municípios)
- ❌ Drag & drop para reordenar
- ❌ F5: Excluir município
- ❌ F6: Mover para baixo
- ❌ F7: Mover para cima
- ❌ F1: Salvar e continuar

**Sugestão:**
Feature complexa - pode ser adiada para v2.0. Por enquanto, usar apenas rota padrão.

---

### 12. **ERRO: Falta Validação de Viagem Duplicada com Exceção de Usuários**

**Arquivo:** `CompraViagemController.php` linha 438-456
**Severity:** ⚠️ MÉDIO - Regra de negócio incompleta

#### O que o Progress faz (compraRota.p linha 555-581):
```progress
for each spararviagem
  where spararviagem.CodPac = pacote.codpac
  and   spararviagem.sPararRotID = semPararRot.sPararRotID
  and   spararviagem.flgCancelado = false:

  // EXCEÇÃO: Usuário diogodias pode pular validação
  if userid("dictdb") = "diogodias" then next.

  // AVISO: Usuários tenreiro/cici recebem warning mas podem continuar
  if userid("dictdb") = "tenreiro" or userid("dictdb") = "cici" then do:
    run rt/rtedmsg.p("ATENCAO", "VIAGEM JA COMPRADA UMA VEZ PARA ESTA ROTA").
    if return-value = "E" then next.  // Permite continuar
  end.

  // ERRO: Outros usuários são bloqueados
  run rt/rtedmsg.p("JA FOI COMPRADO VIAGEM PARA ESSA PACOTE E ESSA ROTA").
  return no-apply.
end.
```

**Laravel atual:**
```php
// linha 439
$viagemCheck = viagemJaComprada($codpac, $rotaId);

if ($viagemCheck['duplicada']) {
  return response()->json([
    'error' => 'Já existe viagem comprada...',
    'code' => 'VIAGEM_DUPLICADA'
  ], 400);
}

// ❌ SEM EXCEÇÕES PARA USUÁRIOS ESPECÍFICOS
```

**Consequência:**
- ❌ Usuários `tenreiro`, `cici`, `diogodias` não têm privilégios especiais
- ❌ Impossível recomprar viagem mesmo com autorização

**Correção:**
```php
// Adicionar verificação de usuário
$user = auth()->user();

if ($viagemCheck['duplicada']) {
  // Exceção total: diogodias
  if ($user && $user->username === 'diogodias') {
    Log::info('Usuário diogodias: permitindo viagem duplicada');
    // Continua normalmente
  }
  // Warning: tenreiro/cici
  elseif ($user && in_array($user->username, ['tenreiro', 'cici'])) {
    return response()->json([
      'warning' => true,
      'message' => 'ATENÇÃO: Viagem já comprada uma vez para esta rota',
      'viagem_existente' => $viagem,
      'allow_override' => true  // Frontend mostra confirmação
    ], 200);
  }
  // Erro: Outros usuários
  else {
    return response()->json([
      'success' => false,
      'error' => 'Já foi comprado viagem para essa pacote e essa rota',
      'code' => 'VIAGEM_DUPLICADA'
    ], 400);
  }
}
```

---

### 13. **ERRO: Falta Geração de Recibo**

**Arquivo:** `CompraViagemController.php` linha 694
**Severity:** ⚠️ MÉDIO - Feature ausente

#### O que o Progress faz (compraRota.p linha 890-916):
```progress
// ASK: "DESEJA IMPRIMIR O RECIBO?"
run rt/rtedmsg.p("DESEJA IMPRIMIR O RECIBO?").

if return-value = "S":
  assign flgImprime = true.

conexao = new Rota("prd").
conexao:get().
conexao:criaRecibo(
  input sPararViagem.codViagem,
  input spararViagem.codtrn,
  input usuario.e-mail,
  input flgImprime,  <!-- true = imprime PDF -->
  output erro
).
```

**Método criaRecibo (Rota.cls linha 281-293):**
```progress
// STEP 1: Get receipt data from SemParar
SOAP obterReciboViagem(codViagem, cToken, OUT xml)
DATASET obterReciboViagemReturnDset:READ-XML

// STEP 2: Convert to JSON
JsonObject:Write(dataset)

// STEP 3: Call Python API
HTTP POST http://192.168.19.35:5001/gerar-vale-pedagio
  BODY: {data: dataset, telefone: formataCelular(codtrn), email, flgImprime}
```

**Laravel atual:** ❌ TODO comentado (linha 694)

**Consequência:**
- ❌ Usuário NÃO recebe PDF do recibo
- ❌ Email NÃO é enviado

**Correção:**
Implementar `SemPararService::criaRecibo()` chamando:
1. SOAP obterReciboViagem
2. HTTP POST Python API (192.168.19.35:5001)

---

### 14. **ERRO: Falta Salvamento de semPararRotMuLog Personalizado**

**Arquivo:** `CompraViagemController.php` linha 676-692
**Severity:** ⚠️ MÉDIO - Auditoria incompleta

#### O que o Progress faz (compraRota.p linha 868-888):
```progress
if flgPersonalizado then do:
  // Save CUSTOM municipalities from t-entrega
  for each t-entrega no-lock:
    create semPararRotMuLog.
    assign semPararRotMuLog.cdibge      = integer(t-entrega.codibge)
           semPararRotMuLog.DesEst      = t-entrega.desEstado
           semPararRotMuLog.DesMun      = t-entrega.desCidade
           semPararRotMuLog.sPararMuSeq = t-entrega.seqped
           semPararRotMuLog.codViagem   = numeroViagem
           semPararRotMuLog.datAtu      = semPararRot.datAtu
           semPararRotMuLog.resAtu      = semPararRot.resAtu.
  end.
end.
else do:
  // Save DEFAULT municipalities from semPararRotMu
  for each semPararRotMu where semPararRotMu.sPararRotID = semPararRot.sPararRotID no-lock:
    create semPararRotMuLog.
    buffer-copy semPararRotMu to semPararRotMuLog.
    assign semPararRotMuLog.codViagem = numeroViagem
           semPararRotMuLog.datAtu    = semPararRot.datAtu
           semPararRotMuLog.resAtu    = semPararRot.resAtu.
  end.
end.
```

**Laravel atual (linha 676-692):**
```php
$rota = getSemPararRota($cod_rota);
$municipios = $rota['data']['municipios'];

salvarSemPararRotMuLog($numeroViagem, $cod_rota, $municipios);

// ❌ SEMPRE salva semPararRotMu padrão
// ❌ NÃO verifica se foi personalizado
// ❌ NÃO salva t-entrega customizada
```

**Consequência:**
- ⚠️ Se personalização for implementada, auditoria estará errada
- ⚠️ Log não reflete pontos customizados

**Correção:**
```php
if (isset($validated['municipios_personalizados'])) {
  // Save custom municipalities
  salvarSemPararRotMuLog($numeroViagem, $cod_rota, $validated['municipios_personalizados']);
} else {
  // Save default municipalities
  salvarSemPararRotMuLog($numeroViagem, $cod_rota, $municipios);
}
```

---

## ⚠️ ERROS MENORES - Detalhes de Implementação

### 15. **ERRO: Falta Validação de Tempo de Viagem**

**Arquivo:** `CompraViagemController.php` linha 460
**Severity:** ⚠️ MENOR - Default incorreto

#### O que o Progress usa (compraRota.p linha 584-588):
```progress
assign datInicio = today
       datFim = today + if semPararRot.tempoViagem = 0 then 5 else semPararRot.tempoViagem.
```

**Laravel atual:**
```php
// linha 460
$tempoViagem = $rotaData['tempoviagem'] ?? 5;
$dataFim = now()->addDays($tempoViagem)->format('Y-m-d');

// ✅ CORRETO: Usa default 5 se NULL
// ✅ Mas: Progress verifica se = 0, não NULL
```

**Problema:**
- ⚠️ Se `tempoviagem` = 0 no banco, Laravel adiciona 0 dias (dataFim = dataInicio)
- ⚠️ Progress adiciona 5 dias nesse caso

**Correção:**
```php
$tempoViagem = ($rotaData['tempoviagem'] ?? 0) ?: 5;  // 0 ou NULL → 5
```

---

### 16. **ERRO: Falta Controle de Disabled nos Switches de Modo**

**Arquivo:** `index.vue` linha 551-568
**Severity:** ⚠️ MENOR - UX inconsistente

#### O que o Progress faz (compraRota.p):
- F3: Alterna flgRetorno DURANTE o processo (mesmo após validar pacote)
- Modo CD: Fixo ao iniciar (par_in = "CD" ou "OTHER"), não muda

**Laravel atual:**
```vue
<VSwitch v-model="modoCD" :disabled="verificaPacote" />
<VSwitch v-model="modoRetorno" :disabled="verificaPacote" />

<!-- ❌ Ambos desabilitados após validar pacote -->
<!-- ✅ Progress: modoCD fixo, modoRetorno mutável -->
```

**Correção:**
```vue
<VSwitch
  v-model="modoCD"
  :disabled="true"  <!-- Fixo ao abrir tela -->
  readonly
/>

<VSwitch
  v-model="modoRetorno"
  :disabled="verificaRota"  <!-- Pode mudar até selecionar rota -->
  @update:model-value="onToggleRetorno"
/>
```

---

### 17. **ERRO: Falta Botão "Limpar" no Meio do Processo**

**Arquivo:** `index.vue` linha 872-878
**Severity:** ⚠️ MENOR - UX melhor que Progress

#### Laravel atual:
```vue
<VBtn @click="resetar">Limpar</VBtn>

// ✅ Tem botão Limpar
// ✅ Mas não existe método resetar() no script
```

**Progress:** ❌ F4 em cada campo para voltar, não tem "Limpar tudo"

**Problema:**
- ❌ Método `resetar()` não está definido no script
- ✅ Mas `resetarCompleto()` existe (linha 491)

**Correção:**
```vue
<VBtn @click="resetarCompleto">Limpar</VBtn>
```

---

## 🟡 WARNINGS - Boas Práticas

### 18. **WARNING: Hardcoded "SYSTEM" User**

**Arquivo:** `CompraViagemController.php` linha 658
**Severity:** 🟡 WARNING - Auditoria incorreta

```php
$dadosViagem = [
  'usuario' => 'SYSTEM' // TODO: Pegar usuário autenticado
];
```

**Progress:** `userid("dictdb")` (usuário real)

**Correção:**
```php
'usuario' => auth()->user()->username ?? 'SYSTEM'
```

---

### 19. **WARNING: Falta Logging Completo**

**Arquivo:** `CompraViagemController.php`
**Severity:** 🟡 WARNING - Debug reduzido

**Progress:** Exporta CSV de rotas + emails para auditoria

**Laravel:** Log::info() básico

**Sugestão:**
Adicionar logs estruturados para cada etapa crítica:
- Antes/depois de cada chamada SOAP
- Antes/depois de salvar no banco
- Erros detalhados com stack trace

---

### 20. **WARNING: Falta Teste de Conexão SOAP Inicial**

**Arquivo:** `CompraViagemController.php` linha 34-73
**Severity:** 🟡 WARNING - UX pode quebrar sem aviso

**Progress (Connect.cls linha 39-45):**
```progress
GET():
  connectToServer()
  loadWebServicePort()
  executeWebServiceProcedure(OUTPUT c-str-xml-retorno)
  cToken = extractContentFromXml(c-str-xml-retorno)

// Testa conexão ANTES de qualquer operação
```

**Laravel atual:**
```php
initialize():
  return [
    'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
    'warning' => '...'
  ];

// ❌ NÃO testa conexão SOAP
```

**Sugestão:**
```php
initialize():
  if ($this->ALLOW_SOAP_QUERIES) {
    try {
      $semparar = new SemPararService();
      $semparar->autenticarUsuario();  // Testa conexão
    } catch (\Exception $e) {
      return ['error' => 'Erro ao conectar SemParar: ' . $e->getMessage()];
    }
  }
```

---

## 📊 RESUMO DE ERROS

| Categoria | Quantidade | Severity | Status |
|-----------|------------|----------|--------|
| 🔴 CRÍTICOS (Fluxo Quebrado) | 6 | BLOCKER | ❌ NÃO IMPLEMENTADO |
| ⚠️ GRAVES (Lógica Incorreta) | 4 | MAJOR | ⚠️ PARCIALMENTE IMPLEMENTADO |
| ⚠️ MÉDIOS (Features Ausentes) | 4 | MEDIUM | ❌ NÃO IMPLEMENTADO |
| ⚠️ MENORES (Detalhes) | 3 | MINOR | ⚠️ BUGS PEQUENOS |
| 🟡 WARNINGS (Boas Práticas) | 3 | INFO | ⚠️ MELHORIAS |
| **TOTAL** | **20** | | |

---

## 🔥 TOP 5 PRIORIDADES PARA CORRIGIR

### 1. 🔴 **IMPLEMENTAR roterizaCa() - SEM ISSO, NADA FUNCIONA**
- Arquivo: `SemPararService.php` (criar)
- Linhas: ~500 linhas de código
- Referência: `SEMPARAR_AI_REFERENCE.md` linha 125-212
- Inclui:
  - Build t-entrega from semPararRotMu
  - Add pedido deliveries (GPS format conversion)
  - Apply regional logic (Pará, Acre, Amazonas)
  - SOAP roteirizarPracasPedagio
  - SOAP cadastrarRotaTemporaria
  - Export CSV + email

### 2. 🔴 **CRIAR SemPararService com SOAP Client**
- Arquivo: `app/Services/SemPararService.php` (criar)
- Linhas: ~800 linhas de código
- Referência: `SEMPARAR_AI_REFERENCE.md` linha 52-108
- Métodos:
  - autenticarUsuario()
  - obterStatusVeiculo()
  - roteirizarPracasPedagio()
  - cadastrarRotaTemporaria()
  - obterCustoRota()
  - comprarViagem()
  - obterReciboViagem()
  - cancelarViagem()
  - reemitirViagem()

### 3. 🔴 **CORRIGIR verificarPreco() para chamar roterizaCa()**
- Arquivo: `CompraViagemController.php` linha 511-577
- Mudança:
  ```php
  verificarPreco():
    // ANTES: verifyTripPriceSemParar(cod_rota, codpac, ...)

    // DEPOIS:
    $rotaTemp = $this->semPararService->roterizaCa(
      $cod_rota, $codpac, $flgretorno
    );
    $preco = $this->semPararService->obterCustoRota(
      $rotaTemp['nome_rota'], $placa, $eixos, $inicio, $fim
    );
  ```

### 4. ⚠️ **IMPLEMENTAR retornoCa() e retornoTemp()**
- Arquivo: `SemPararService.php`
- Linhas: ~200 linhas de código
- Referência: `SEMPARAR_AI_REFERENCE.md` linha 213-247
- Para: Feature "Personalizar Pontos"

### 5. ⚠️ **ADICIONAR Lógica Regional (Pará, Acre, Amazonas)**
- Arquivo: `SemPararService.php` dentro de roterizaCa()
- Linhas: ~100 linhas de código
- Referência: `SEMPARAR_AI_REFERENCE.md` linha 536-606
- Crítico para: Rotas Norte/Nordeste

---

## 🎯 PLANO DE AÇÃO

### Fase 1: Core SOAP (1-2 semanas)
- [ ] Criar `SemPararService.php`
- [ ] Implementar autenticarUsuario()
- [ ] Implementar obterStatusVeiculo()
- [ ] Testar conexão WSDL production

### Fase 2: Roteirização (2-3 semanas)
- [ ] Implementar roterizaCa() básico
- [ ] Adicionar lógica regional
- [ ] Implementar GPS coordinate conversion
- [ ] Implementar duplicate detection
- [ ] Implementar XML dataset building
- [ ] Testar roteirizarPracasPedagio()
- [ ] Testar cadastrarRotaTemporaria()

### Fase 3: Pricing & Purchase (1 semana)
- [ ] Implementar obterCustoRota()
- [ ] Corrigir verificarPreco()
- [ ] Implementar comprarViagem()
- [ ] Testar fluxo completo

### Fase 4: Receipt & Logs (1 semana)
- [ ] Implementar obterReciboViagem()
- [ ] Integrar Python API (port 5001)
- [ ] Implementar CSV export + email
- [ ] Testar geração de recibo

### Fase 5: Personalização (1 semana)
- [ ] Implementar retornoCa()
- [ ] Implementar retornoTemp()
- [ ] Criar UI para editar pontos

### Fase 6: Polimento (1 semana)
- [ ] Corrigir exceções de usuários (tenreiro, cici, diogodias)
- [ ] Adicionar logging completo
- [ ] Testar todos os fluxos
- [ ] Documentar API

**TEMPO TOTAL ESTIMADO:** 7-9 semanas

---

## 📝 CONCLUSÃO

A implementação Laravel atual está **~30% completa**. Os erros críticos impedem o sistema de funcionar:

1. ❌ **Roteirização não existe** → Impossível criar rotas temporárias
2. ❌ **SOAP Client não existe** → Impossível chamar API SemParar
3. ❌ **Verificação de preço quebrada** → Usa ID interno ao invés de rota temporária
4. ❌ **Lógica regional ausente** → Rotas Norte/Nordeste incorretas
5. ❌ **GPS conversion ausente** → Entregas não mapeadas

**Recomendação:** Seguir o plano de ação acima, priorizando **Fases 1, 2 e 3** antes de qualquer teste end-to-end.

---

**Documento gerado em:** 2025-10-27
**Baseado em:** `SEMPARAR_AI_REFERENCE.md` (análise Progress completa)
**Status:** 🔴 CRÍTICO - Sistema não funcional sem correções
