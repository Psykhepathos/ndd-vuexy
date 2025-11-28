# 🖨️ Solução para Problema de Impressão - transp4

## 🚨 Problema
O Flask envia o PDF para impressão com sucesso (sem erros), mas a impressora física não recebe o documento.

## 🔍 Causa Raiz (Provável)

### 1. **PDF sendo deletado muito cedo** (90% de chance)
- Código aguardava apenas **5 segundos** (3s + 2s) antes de deletar o PDF
- Com 6 trabalhos na fila, o spooler CUPS pode estar ocupado
- Se o PDF for deletado antes do spooler copiar, a impressão falha silenciosamente

### 2. **Fila de impressão travada** (10% de chance)
- 6 trabalhos na fila pode indicar trabalhos travados/pendentes
- Novos trabalhos entram na fila mas não são processados

## ✅ Soluções Aplicadas no Código

Já apliquei as seguintes correções no `app.py`:

1. ✅ **Aumentado tempo de espera de 5s → 15s**
   - 10s após enviar para fila
   - 5s antes de deletar o PDF

2. ✅ **Verificação de status da impressora**
   - Verifica se está online E aceitando trabalhos
   - Log do status completo

3. ✅ **Captura melhorada de Job ID**
   - Múltiplos padrões de regex (inglês + português)
   - Log da saída completa do comando `lp`

4. ✅ **Limpeza mais agressiva da fila**
   - Limite reduzido de 10 → 5 trabalhos
   - Verifica se limpeza funcionou
   - Alerta se fila continuar travada

## 🔧 Passos para Resolver

### Passo 1: Copiar novo `app.py` para o servidor

```bash
# No Windows (este PC), arquivo atualizado está em:
C:\Users\15857\AppData\Local\Temp\fz3temp-2\app.py

# Copiar para o servidor Linux (192.168.19.35):
scp "C:\Users\15857\AppData\Local\Temp\fz3temp-2\app.py" usuario@192.168.19.35:/var/www/html/SemPararQA/app.py
```

### Passo 2: Executar diagnóstico no servidor

```bash
# Conectar ao servidor
ssh usuario@192.168.19.35

# Copiar script de diagnóstico (ou executar comandos manualmente)
cd /var/www/html/SemPararQA

# Tornar executável
chmod +x diagnostico_impressora.sh

# Executar
./diagnostico_impressora.sh > diagnostico_resultado.txt 2>&1

# Ver resultado
cat diagnostico_resultado.txt
```

### Passo 3: Analisar resultado e aplicar correções

#### Se impressora estiver PAUSADA:
```bash
cupsenable transp4
cupsaccept transp4
```

#### Se fila estiver TRAVADA (6+ trabalhos antigos):
```bash
# Limpar TODOS os trabalhos
cancel -a transp4

# Ou limpar trabalhos específicos
cancel transp4-123 transp4-124 transp4-125
```

#### Se CUPS estiver com problemas:
```bash
sudo systemctl restart cups

# Verificar se subiu corretamente
systemctl status cups
```

### Passo 4: Reiniciar Flask com novo código

```bash
# Parar Flask atual (se rodando com systemd)
sudo systemctl restart flask-semparar

# OU se rodando manualmente:
pkill -f "python.*app.py"
cd /var/www/html/SemPararQA
python3 app.py > log.txt 2>&1 &
```

### Passo 5: Testar impressão

```bash
# Testar impressão direta (sem Flask)
echo "TESTE DIRETO - $(date)" > teste.txt
lp -d transp4 teste.txt

# Aguardar 5 segundos
sleep 5

# Verificar se imprimiu
lpstat -o transp4

# Se imprimiu, o problema é no código Python
# Se NÃO imprimiu, o problema é na impressora/CUPS
```

### Passo 6: Testar via API

```bash
# Fazer requisição de teste
curl -X POST http://192.168.19.35:5001/gerar-vale-pedagio \
  -H "Content-Type: application/json" \
  -d @payload_teste.json

# Monitorar logs em tempo real
tail -f /var/www/html/SemPararQA/log.txt
```

## 📊 Novos Logs para Monitorar

Com o código atualizado, você verá estes novos logs:

```
[IMPRESSAO] Status da impressora: printer transp4 is idle. enabled since...
[IMPRESSAO] Aceitando trabalhos: transp4 accepting requests since...
[IMPRESSAO] Saida do comando lp: 'request id is transp4-789 (1 file(s))'
[IMPRESSAO] [OK] Trabalho enviado para fila: transp4-789
[IMPRESSAO] Aguardando 10s para spooler copiar o PDF...
[IMPRESSAO] Trabalhos na fila APOS limpeza: 2
[CLEANUP] Aguardando 5s para garantir que spooler terminou...
```

## 🚨 Se AINDA não funcionar

### Opção 1: Desabilitar deleção do PDF (temporário)
```python
# No app.py, comentar estas linhas (593-604):
# if os.path.exists(pdf_file):
#     try:
#         os.remove(pdf_file)
#         log("[CLEANUP] [OK] PDF removido com sucesso: {}".format(pdf_file))
#     except Exception as e:
#         log("[CLEANUP] [ERRO] ao remover PDF: {}".format(e))
```

Isso deixará os PDFs acumularem no servidor, mas confirmará se o problema é o timing de deleção.

### Opção 2: Aumentar ainda mais o tempo de espera
```python
# Linha 514: Mudar de 10s para 20s
wait_time = 20

# Linha 619: Mudar de 5s para 10s
cleanup_wait = 10
```

### Opção 3: Verificar permissões
```bash
# Verificar quem está rodando o Flask
ps aux | grep app.py

# Verificar permissões do spooler
ls -la /var/spool/cups/

# Se necessário, adicionar usuário ao grupo de impressão
sudo usermod -a -G lp <usuario_flask>
```

## 📝 Checklist de Verificação

- [ ] Novo `app.py` copiado para servidor
- [ ] Flask reiniciado
- [ ] Diagnóstico executado (`diagnostico_impressora.sh`)
- [ ] Fila de impressão limpa (se necessário)
- [ ] CUPS reiniciado (se necessário)
- [ ] Teste direto com `lp` funcionou
- [ ] Teste via API funcionou
- [ ] PDF imprimiu fisicamente

## 📞 Comandos Úteis

### Monitorar fila em tempo real:
```bash
watch -n 2 'lpstat -o transp4'
```

### Ver logs do CUPS em tempo real:
```bash
tail -f /var/log/cups/error_log
```

### Verificar se spooler está processando:
```bash
ls -lh /var/spool/cups/
```

### Testar conectividade com impressora:
```bash
lpinfo -v  # Lista todas as impressoras disponíveis
```

## ⏱️ Timeline Esperado

Com as correções aplicadas:
1. Envio para fila: **imediato**
2. Spooler copia PDF: **até 10s**
3. Impressora processa: **5-30s** (depende da fila)
4. Impressão física: **10-60s** (depende do hardware)

**Total esperado: 25-100 segundos** do envio até sair na impressora.

## 🎯 Resultado Esperado

Após aplicar as correções, os logs devem mostrar:

```
[IMPRESSAO] [OK] Trabalho enviado para fila: transp4-789
[IMPRESSAO] Aguardando 10s para spooler copiar o PDF...
[IMPRESSAO] Job transp4-789 ainda em processamento na fila
[CLEANUP] Aguardando 5s para garantir que spooler terminou...
[CLEANUP] [OK] PDF removido com sucesso: Vale_Pedagio_91734800_...pdf
[RESUMO] Email: [OK] | WhatsApp: [OK] | Impressao: [OK]
```

E a impressora física deve receber o documento **dentro de 1-2 minutos**.
