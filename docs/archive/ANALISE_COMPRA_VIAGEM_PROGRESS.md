# Análise Completa: compraRota.p vs Implementação Laravel/Vue.js

**Data:** 2025-10-24
**Objetivo:** Identificar TODAS as funcionalidades do Progress que não estão implementadas no sistema Vue/Laravel

---

## 📋 RESUMO EXECUTIVO

### ✅ O que JÁ está implementado:
1. Validação de pacote (linha 198-264)
2. Auto-preenchimento da placa (linha 242)
3. Validação de placa via SemParar (linha 366-396)
4. Dialog de confirmação de eixos (linha 400-419)
5. Seleção de rota (linha 492-505)
6. Verificação de preço (linha 669-691)
7. Compra de viagem (linha 827-857)

### ❌ O que NÃO está implementado:

#### 1. **Sistema de Flags e Validações** (compraRota.p)
- ❌ **F3 para alternar Retorno** (linhas 176-196)
  - Progress permite F3 para toggle entre modo normal e RETORNO
  - Laravel: Não implementado

- ❌ **F4 para resetar campos** (linhas 266-291, 319-326, 351-354)
  - Progress permite F4 em vários campos para limpar e voltar
  - Laravel: Não implementado

- ❌ **F2 para Ajuda/Autocomplete** (linhas 329-349, 357-364, 479-485)
  - Progress tem help dialogs via F2
  - Laravel: Tem autocomplete mas não via hotkey F2

#### 2. **Validações de Negócio Complexas** (compraRota.p)
- ❌ **Verificação de Pacote TCD** (linhas 216-227, 331-342)
  - Progress verifica se pacote é TCD e bloqueia se flgcd = false
  - Laravel: Não implementado

- ❌ **Verificação de Rota CD vs Normal** (linhas 507-530)
  - Progress valida se rota é CD quando deveria ser normal e vice-versa
  - Laravel: Não implementado

- ❌ **Verificação de Rota Retorno** (linhas 531-554)
  - Progress valida flag de retorno da rota
  - Laravel: Não implementado

- ❌ **Verificação de Viagem Duplicada** (linhas 555-581)
  - Progress checa se já existe viagem comprada para mesmo pacote+rota
  - Laravel: Não implementado

#### 3. **Auto-sugestão de Rota** (compraRota.p linhas 432-475)
- ❌ **Busca rota por pacsoc** (linhas 433-440)
  - Se pacote tem pacsoc, busca rota do pacote pai
  - Laravel: Não implementado

- ❌ **Busca rota por semPararIntrot** (linhas 441-463)
  - Relaciona introt.codrot com semPararIntrot para sugerir rota
  - Laravel: Não implementado

- ❌ **Filtro de Retorno na sugestão** (linhas 442-460)
  - Filtra rotas com/sem "RETORNO" no nome baseado em flgRetorno
  - Laravel: Não implementado

#### 4. **Personalização de Pontos** (compraRota.p + Rota.cls)
- ❌ **Dialog "Deseja personalizar pontos?"** (linhas 594-602)
  - Progress permite customizar municípios antes de roteirizar
  - Laravel: Opção existe mas mostra toast "em desenvolvimento"

- ❌ **Frame CadastroEntrega completo** (linhas 148-159, 699-815)
  - Browse de municípios com drag & drop (F6/F7 para mover)
  - F5 para deletar município
  - F1 para salvar customizações
  - Laravel: Não implementado

- ❌ **retornoCa() vs retornoTemp()** (Rota.cls linhas 276-469, 471-606)
  - `retornoCa()`: Personalização ativa, carrega pontos editáveis
  - `retornoTemp()`: Cria rota temporária customizada
  - Laravel: Chama `roterizaCa()` direto, sem personalização

#### 5. **Lógica Complexa de Rotas** (Rota.cls)

##### `roterizaCa()` - PARCIALMENTE implementado (linhas 679-962)
Progress faz:
1. Loop municípios da rota → t-entrega com IBGE, lat=0, lon=0 ✅
2. Loop entregas do pacote → t-entrega com GPS real ✅
3. **❌ Regras especiais de rota:**
   - **AC/AM**: Se rota contém "AC", aplica regra ACAM (linhas 723, 773-783, 807-813)
   - **Pará (estado 16)**: Substituição por Maranhão (140) em certos casos (linhas 758-767, 799-805)
   - **Municípios blacklist**: Ignora IBGE 5103379, 1501576, 1502509 (linhas 768-772)
   - **Cliente específico**: Ignora "AVENIDA AEROPORTO,15" (linha 731)
4. **❌ Lógica de Retorno**: Se flgretorno=true, deleta entregas intermediárias, mantém só primeira e última (linhas 823-834)
5. **❌ Geocoding reverso**: Se achou município pelo nome, ZERA GPS e usa IBGE (linhas 787-791)
6. **❌ Export CSV + Email**: Exporta pontos.csv e envia por email (linhas 843-870)
7. DATASET pontosParadaDset criado e enviado ao SemParar ✅
8. Chama `roteirizarPracasPedagio` ✅
9. Chama `cadastrarRotaTemporaria` ✅

**Laravel implementa:**
- ✅ Passos 1, 2, 7, 8, 9
- ❌ Regras especiais de rota (AC/AM, Pará, blacklist, cliente específico)
- ❌ Lógica de retorno
- ❌ Geocoding reverso (zerar GPS quando tem IBGE)
- ❌ Export CSV + Email

#### 6. **Funcionalidades Pós-Compra** (compraRota.p)
- ❌ **Salvar sPararViagem no banco** (linhas 856-867)
  - Progress cria registro com todos os dados da viagem
  - Laravel: Não salva no banco Progress

- ❌ **Salvar semPararRotMuLog** (linhas 868-888)
  - Progress registra municípios usados na viagem (log)
  - Laravel: Não implementado

- ❌ **Dialog "Deseja imprimir recibo?"** (linhas 890-900)
  - Progress pergunta se quer imprimir
  - Laravel: Não implementado

- ❌ **criaRecibo()** (Rota.cls linhas 608-653)
  - Chama API Python (192.168.19.35:5001) para gerar PDF
  - Envia email com recibo
  - Laravel: Não implementado

- ❌ **Reset completo após compra** (linhas 925-956)
  - Progress limpa TODOS os campos e volta ao início
  - Laravel: Mantém dados no form

#### 7. **Outras Funcionalidades da Rota.cls**
- ❌ **cancelaViagem()** (linhas 99-105)
  - Cancela viagem já comprada

- ❌ **reemiteViagem()** (linhas 108-132)
  - Reemite viagem com nova placa

- ❌ **imprimeRecibo()** (linhas 31-63)
  - Imprime recibo via API Python

- ❌ **salvaRecibo()** (linhas 65-97)
  - Salva PDF do recibo

- ❌ **extratoRota()** (linhas 968-1017)
  - Obtém extrato de créditos SemParar
  - Salva na tabela `sParargetExtra`

#### 8. **Helpers e Funções** (Rota.cls)
- ✅ **formatCoordinates()** (linhas 1020-1033)
  - Formata coordenadas GPS
  - Laravel: Implementado em PHP

- ❌ **formataCelular()** (linhas 655-677)
  - Formata telefone com DDD do município
  - Laravel: Não implementado

- ❌ **verificaErro()** (linhas 134-160)
  - Parse XML do SemParar e traduz códigos de erro
  - Busca descrição na tabela `semPararStatus`
  - Laravel: Implementado mas sem lookup na tabela

---

## 🔍 ANÁLISE DETALHADA: compraRota.p LINHA POR LINHA

### **SEÇÃO 1: Declaração de Variáveis (linhas 1-49)**
```progress
using SemParar.Rota.
{SemParar/roteriza.i}
```
**Análise:**
- Progress usa include `roteriza.i` para definir DATASETs (t-entrega, pontosParadaDset, etc.)
- Laravel: Usa arrays PHP, não precisa de includes

**Status:** ✅ Equivalente implementado (arrays vs DATASET)

---

### **SEÇÃO 2: Frame Definitions (linhas 60-134)**
```progress
define frame f_Placa ...
define frame f_Preco ...
define frame CadastroRota ...
define frame CadastroEntrega ...
```
**Análise:**
- Progress usa frames 4GL para UI
- Laravel: Usa VDialog do Vuetify

**Status:** ✅ Equivalente implementado (VDialog vs Frame)

---

### **SEÇÃO 3: Validação de Usuário** (linhas 161-174)
```progress
find first Usuario where usuario.UsrIdt = userid("tambasa") no-lock no-error.
if not avail usuario then do:
  run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Usuario sem e-mail cadastrado no ERP ").
  ...
end.
```
**Análise:**
- Progress verifica se usuário tem email cadastrado
- Laravel: Não implementado

**Status:** ❌ **NÃO IMPLEMENTADO**

---

### **SEÇÃO 4: Toggle Retorno com F3** (linhas 176-196)
```progress
on f3 of vCodPac in frame cadastroRota do:
  if not flgcd and not flgretorno then do:
    assign flgRetorno = true.
    frame cadastroRota:title = titleCD + "- RETORNO ".
    ...
  end.
  ...
end.
```
**Análise:**
- F3 alterna entre modo normal e RETORNO
- Muda título da tela
- Laravel: Não tem toggle de retorno

**Status:** ❌ **NÃO IMPLEMENTADO**

---

### **SEÇÃO 5: Validação de Pacote** (linhas 198-264)
```progress
on return of vCodpac in frame CadastroRota do:
  find first pacote where pacote.codpac = integer(vCodpac:screen-value) no-lock no-error.
  if not available pacote then do:
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Nao existe pacote com este codigo").
    ...
  end.
  else do:
    if can-find(first paccd where paccd.codpaccd = integer(vCodpac:screen-value)) and flgcd = false then do:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Pacote e TCD ").
      ...
    end.
    ...
  end.
end.
```
**Análise:**
- ✅ Valida se pacote existe
- ❌ Verifica se pacote é TCD (tabela `paccd`)
- ✅ Preenche transporte
- ✅ Auto-preenche placa (linha 242)
- ✅ Habilita campo placa

**Status:** ⚠️ **PARCIALMENTE IMPLEMENTADO** (falta validação TCD)

---

### **SEÇÃO 6: F4 para Resetar** (linhas 266-291)
```progress
on f4 of vPlaca in frame CadastroRota do:
  assign vCodpac:screen-value = ""
         vCodpac = 0
         ...
         verificaPacote = false
         verificaTransporte = false
         verificaPlaca = false.
  ...
  apply "entry" to vCodpac in frame CadastroRota.
end.
```
**Análise:**
- F4 limpa TODOS os campos e volta ao início
- Laravel: Não tem tecla de atalho para reset

**Status:** ❌ **NÃO IMPLEMENTADO**

---

### **SEÇÃO 7: Validação de Placa** (linhas 366-396)
```progress
on return of vPlaca in frame cadastroRota do:
  find first trnvei where trnvei.NumPla = vPlaca:screen-value and trnvei.CodTrn = transporte.CodTrn no-lock no-error.
  if avail trnvei then assign vDesPlaca:screen-value = trnvei.modvei.

  message " Verificando placa...".
  conexao = new Rota("prd").
  conexao:get().
  conexao:loadWebServicePort().
  conexao:statusVei(input vPlaca:screen-value, output vDescricaoVei, output vEixos, output vProprietario, output vTag, output erro).
  conexao:disconnectfromServer().
  ...
end.
```
**Análise:**
- ✅ Busca modelo do veículo localmente (trnvei.modvei)
- ✅ Chama SemParar statusVei
- ✅ Mostra dialog com dados
- ✅ Permite editar eixos

**Status:** ✅ **IMPLEMENTADO** (mas falta busca local do modelo)

---

### **SEÇÃO 8: Confirmação de Eixos** (linhas 400-419, 422-476)
```progress
on return of vEixos in frame f_Placa do:
  if vEixos:screen-value = "" or integer(vEixos:screen-value) < 2 or integer(vEixos) > 10 then do:
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Eixos invalidos (minimo 2, maximo 10)  ").
    ...
  end.
  ...
end.

on return of btConfirma in frame f_Placa do:
  hide frame f_Placa.
  assign input vEixos.
  enable vRota with frame CadastroRota.
  disable vPlaca with frame CadastroRota.

  // Auto-sugestão de rota (linhas 432-475)
  if avail pacote then do:
    find first pacsoc where pacsoc.codpacsoc = pacote.codpac no-lock no-error.
    if avail pacsoc then do:
      find first b_pacote where b_pacote.codpac = pacsoc.codpac no-lock no-error.
      ...
    end.

    if avail introt then do:
      if flgretorno = false then do:
        for each semPararIntrot where semPararIntrot.codrot = introt.codrot no-lock,
            first sempararrot where sempararrot.sPararRotID = semPararIntrot.sPararRotID
                              and index(sempararrot.desSPararRot,"RETORNO") = 0 no-lock:
          assign vRota:screen-value = semPararRot.desSPararRot
                 vRota = sempararrot.desSPararRot.
        END.
      end.
      if flgretorno then do:
        for each sempararintrot where sempararintrot.codrot = introt.codrot no-lock,
            first sempararrot where sempararrot.spararrotid = sempararintrot.spararrotid
                              and index(sempararrot.desspararrot,"RETORNO")> 0 no-lock:
          assign vRota:screen-value = semPararRot.desSPararRot
                 vRota = sempararrot.desSPararRot.
        END.
      end.
    end.
  end.
  apply "entry" to vRota in frame CadastroRota.
end.
```
**Análise:**
- ✅ Validação de eixos (2-10)
- ❌ Auto-sugestão de rota por pacsoc
- ❌ Auto-sugestão de rota por semPararIntrot
- ❌ Filtro de retorno na sugestão

**Status:** ⚠️ **PARCIALMENTE IMPLEMENTADO** (falta auto-sugestão)

---

### **SEÇÃO 9: Seleção de Rota** (linhas 492-696)
```progress
on return of vRota in frame CadastroRota do:
  find first semPararRot where semPararRot.desSPararRot = vRota:screen-value no-lock no-error.
  if not avail semPararRot then do:
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Nao existe rota com este nome").
    ...
  end.
  else do:
    // Validação CD vs Normal (linhas 507-530)
    if flgcd and sempararrot.flgCD = false then do:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Rota nao e CD ").
      ...
    end.
    if not flgcd and sempararrot.flgCD = true then do:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Rota e CD ").
      ...
    end.

    // Validação Retorno (linhas 531-554)
    if flgRetorno and sempararrot.flgRetorno = false then do:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Rota nao e de retorno ").
      ...
    end.
    if not flgRetorno and sempararrot.flgRetorno = true then do:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Rota e de retorno ").
      ...
    end.

    // Verificação de viagem duplicada (linhas 555-581)
    for each spararviagem
         where spararviagem.CodPac = pacote.codpac
         and   spararviagem.sPararRotID = semPararRot.sPararRotID
         and   spararviagem.flgCancelado = false:
      run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " JA FOI COMPRADO VIAGEM PARA ESSA PACOTE E ESSA ROTA").
      ...
    end.

    // Calcular datas vigência (linhas 584-588)
    assign datInicio = today
           datFim = today + if semPararRot.tempoViagem = 0 then 5 else semPararRot.tempoViagem.
    display datInicio datFim with frame CadastroRota.

    // Personalização de pontos (linhas 594-662)
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <N>ao <S>im@N,n,S,s", " DESEJA PERSONALIZAR PONTOS? ").
    if return-value = "S" or return-value = "s" then do:
      assign flgPersonalizado = true.
      // Chama retornoCa() para edição
      conexao:retornoCa(input flgRetorno, input semPararRot.sPararRotID, input codpacString, output table t-entrega, output erro).
      // Frame de edição
      wait-for end-error of vMunicipio in frame CadastroEntrega.
      // Chama retornoTemp() para criar rota temporária
      conexao:retornoTemp(input table t-entrega, input sempararrot.sPararRotID, input codpacString, output nomRotSemParar, output codRotaSemParar, output erro).
    end.
    else do:
      // Sem personalização - chama roterizaCa() direto
      conexao:roterizaCa(input flgRetorno, input semPararRot.sPararRotID, input codpacString, output nomRotSemParar, output codRotaSemParar, output erro).
    end.

    // Verificar preço (linhas 669-691)
    message " Verificando preco...".
    conexao:verificaPreco(input nomRotSemParar, input vPlaca, input vEixos, input datInicio, input datFim, output preco, output erro).
    ...
  end.
end.
```
**Análise:**
- ✅ Validação de rota existe
- ❌ Validação CD vs Normal
- ❌ Validação Retorno
- ❌ Verificação de viagem duplicada
- ✅ Cálculo de datas vigência
- ❌ Personalização de pontos (mostra toast "em desenvolvimento")
- ✅ Verificação de preço

**Status:** ⚠️ **PARCIALMENTE IMPLEMENTADO** (60% implementado)

---

### **SEÇÃO 10: Compra de Viagem** (linhas 827-995)
```progress
on return of btConfirmaPreco in frame f_Preco do:
  assign verificaValor = true.
  display verificaValor with frame CadastroRota.

  message " Comprando viagem...".
  conexao:compraViagem(input nomRotSemParar, input vPlaca, input vEixos, input datInicio, input datFim, input string(vCodpac), output numeroViagem, output erro).

  if erro <> "OK" then do:
    run rt/rtedmsg.p(10, " ERRO ", "Tecle <E>ntedido@E,e", erro).
    ...
  end.
  else do:
    assign verificaCompra = true.
    display verificaCompra with frame CadastroRota.

    // Salvar no banco Progress (linhas 856-888)
    create sPararViagem.
    assign
      sPararViagem.CodPac         = vCodpac
      sPararViagem.codRotCreateSP = codRotaSemParar
      sPararViagem.codtrn         = vTransporte
      sPararViagem.codViagem      = numeroViagem
      sPararViagem.nomRotSemParar = nomRotSemParar
      sPararViagem.NumPla         = vPlaca
      sPararViagem.sPararRotID    = sempararrot.sPararRotID
      sPararViagem.valViagem      = valorViagem
      sPararViagem.resCompra      = userid("dictdb")
      sPararViagem.dataCompra     = today.

    // Salvar log de municípios (linhas 868-888)
    if flgPersonalizado then do:
      for each t-entrega no-lock:
        create semPararRotMuLog.
        assign semPararRotMuLog.cdibge      = integer(t-entrega.codibge)
               semPararRotMuLog.DesEst      = t-entrega.desEstado
               semPararRotMuLog.DesMun      = t-entrega.desCidade
               semPararRotMuLog.sPararMuSeq = t-entrega.seqped
               semPararRotMuLog.codViagem   = numeroViagem
               ...
      end.
    end.
    else do:
      for each semPararRotMu where semPararRotMu.sPararRotID = semPararRot.sPararRotID no-lock:
        create semPararRotMuLog.
        buffer-copy semPararRotMu to semPararRotMuLog.
        assign semPararRotMuLog.codViagem = numeroViagem
               ...
      end.
    end.

    // Perguntar impressão (linhas 890-916)
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <N>ao <S>im@N,n,S,s", " DESEJA IMPRIMIR O RECIBO? ").
    if return-value = "S" or return-value = "s" then do:
      assign flgImprime = true.
    end.

    message "Imprimindo recibo...".
    conexao:criaRecibo(input sPararViagem.codViagem, input spararViagem.codtrn, input usuario.e-mail, input flgImprime, output erro).

    // Mensagem sucesso + reset completo (linhas 919-956)
    run rt/rtedmsg.p(10, "ATENCAO", "Tecle <E>ntedido@E,e", " Viagem comprada com sucesso ").
    if return-value = "E" or return-value = "e" then do:
      // Zera todas as flags
      assign verificaCompra = false
             verificaPacote = false
             verificaTransporte = false
             verificaPlaca = false
             verificaRota = false
             verificaValor = false.

      // Limpa todas as variáveis
      assign vCodpac = 0
             vDesCodPac = ""
             vDescricaoVei = ""
             vDesPlaca = ""
             vDesTransporte = ""
             nomRotSemParar = ""
             codRotaSemParar = ""
             valorViagem = 0
             numeroViagem = ""
             datInicio = ?
             datFim = ?
             vTransporte = 0
             vPlaca = ""
             vRota = "".

      // Re-exibe formulário limpo
      display vCodpac vDesCodPac vDesPlaca vDesTransporte
           nomRotSemParar codRotaSemParar valorViagem numeroViagem
           datInicio datFim vTransporte vPlaca vRota with frame CadastroRota.

      // Volta ao início
      apply "entry" to vCodpac in frame CadastroRota.
    end.
  end.
end.
```
**Análise:**
- ✅ Chama compraViagem() do SemParar
- ❌ Salva sPararViagem no banco Progress
- ❌ Salva semPararRotMuLog (log de municípios)
- ❌ Pergunta se quer imprimir recibo
- ❌ Chama criaRecibo() (gera PDF via Python API)
- ❌ Reset completo do formulário após compra

**Status:** ⚠️ **PARCIALMENTE IMPLEMENTADO** (40% implementado)

---

## 🎯 PRIORIDADES DE IMPLEMENTAÇÃO

### 🔴 **CRÍTICO (impede funcionamento correto):**
1. **Validação de Pacote TCD** - Evita erros ao usar pacotes CD em modo normal
2. **Verificação de Viagem Duplicada** - Evita recomprar mesma viagem
3. **Salvar sPararViagem no banco** - Registro histórico da compra
4. **Regras especiais de rota** (AC/AM, Pará, blacklist) - Lógica de negócio crítica

### 🟠 **IMPORTANTE (funcionalidade esperada):**
5. **Auto-sugestão de rota** (pacsoc + semPararIntrot)
6. **Validações de CD vs Normal e Retorno**
7. **Salvar semPararRotMuLog** - Auditoria de municípios usados
8. **criaRecibo() + impressão** - Comprovante da compra
9. **Reset completo pós-compra** - UX esperada

### 🟢 **DESEJÁVEL (melhoria de UX):**
10. **F3 para toggle Retorno**
11. **F4 para reset rápido**
12. **F2 para help dialogs**
13. **Personalização de pontos completa** (Frame CadastroEntrega)
14. **Validação de email do usuário**
15. **Funções extras** (cancelaViagem, reemiteViagem, extratoRota)

---

## 📊 ESTATÍSTICAS

- **Total de linhas compraRota.p:** 1022
- **Total de métodos Rota.cls:** 14
- **Total de métodos Connect.cls:** 7

**Implementação atual:**
- ✅ **Fluxo básico:** 70% implementado
- ⚠️ **Validações de negócio:** 30% implementado
- ❌ **Pós-compra:** 20% implementado
- ❌ **Personalização:** 5% implementado
- ❌ **Funcionalidades extras:** 0% implementado

**Conclusão:** O sistema atual implementa o **fluxo feliz básico** (pacote válido → placa válida → rota válida → preço → compra), mas falta implementar **70% das validações de negócio, lógica especial de rotas, e funcionalidades pós-compra** que o Progress possui.
