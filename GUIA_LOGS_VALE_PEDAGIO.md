# 📋 Guia Completo de Logs - Sistema Vale Pedágio

## 📁 Arquivos de Log

### **vale_pedagio_detalhado.log**
Arquivo principal de log localizado no mesmo diretório do `app.py`.

**Características:**
- ✅ **Rotação automática**: Quando atinge 10MB, cria novo arquivo
- ✅ **Backup**: Mantém até 5 arquivos históricos (`.log.1`, `.log.2`, etc)
- ✅ **Codificação**: UTF-8 (suporta caracteres especiais)
- ✅ **Níveis**: DEBUG, INFO, WARNING, ERROR, CRITICAL

---

## 🔍 Níveis de Log

| Nível | Quando Usar | Visível em |
|-------|-------------|------------|
| **DEBUG** | Detalhes técnicos (payloads, traceback completo) | Apenas arquivo |
| **INFO** | Operações normais (PDF gerado, email enviado) | Arquivo + Console |
| **WARNING** | Situações suspeitas (fila saturada, telefone vazio) | Arquivo + Console |
| **ERROR** | Falhas recuperáveis (email falhou, impressão falhou) | Arquivo + Console |
| **CRITICAL** | Falhas fatais (servidor não inicia, erro geral) | Arquivo + Console |

---

## 📊 Formato dos Logs

```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [funcao:linha] mensagem
```

**Exemplo:**
```
[2025-11-06 08:30:45] [INFO] [api_vale_pedagio:658] [PDF] Gerando PDF... (request_id=a1b2c3d4)
[2025-11-06 08:30:47] [INFO] [api_vale_pedagio:663] [PDF] [OK] PDF gerado com sucesso: Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf
[2025-11-06 08:30:47] [DEBUG] [api_vale_pedagio:667] [DEBUG] Path completo do PDF: /var/www/html/SemPararQA/Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf
```

---

## 🔍 Como Acompanhar Erros

### 1. **Monitorar em Tempo Real**

#### **Todos os logs (INFO + DEBUG):**
```bash
tail -f vale_pedagio_detalhado.log
```

#### **Apenas erros (WARNING/ERROR/CRITICAL):**
```bash
tail -f vale_pedagio_detalhado.log | grep -E "WARNING|ERROR|CRITICAL"
```

#### **Apenas impressão:**
```bash
tail -f vale_pedagio_detalhado.log | grep IMPRESSAO
```

#### **Apenas uma requisição específica:**
```bash
tail -f vale_pedagio_detalhado.log | grep "a1b2c3d4"  # Substituir pelo request_id
```

---

### 2. **Buscar Erros Passados**

#### **Últimos 100 erros:**
```bash
grep -E "ERROR|CRITICAL" vale_pedagio_detalhado.log | tail -100
```

#### **Erros de impressão nas últimas 24h:**
```bash
grep "IMPRESSAO.*ERROR" vale_pedagio_detalhado.log | tail -50
```

#### **Todas as requisições que falharam:**
```bash
grep "ERRO CRITICO" vale_pedagio_detalhado.log
```

#### **Buscar por código de viagem específico:**
```bash
grep "91734800" vale_pedagio_detalhado.log
```

---

### 3. **Estatísticas**

#### **Contar requisições bem-sucedidas vs falhas:**
```bash
echo "Sucessos: $(grep -c 'Recibo gerado e enviado com sucesso' vale_pedagio_detalhado.log)"
echo "Falhas: $(grep -c 'ERRO CRITICO' vale_pedagio_detalhado.log)"
```

#### **Contar falhas por tipo:**
```bash
echo "Falhas de email: $(grep -c 'EMAIL.*ERROR' vale_pedagio_detalhado.log)"
echo "Falhas de WhatsApp: $(grep -c 'WHATSAPP.*ERROR' vale_pedagio_detalhado.log)"
echo "Falhas de impressao: $(grep -c 'IMPRESSAO.*ERROR' vale_pedagio_detalhado.log)"
```

---

## 🧪 Estrutura de Uma Requisição Completa

### **Exemplo de requisição bem-sucedida:**

```
================================================================================
[2025-11-06 08:30:45] [INFO] [INICIO] Nova requisicao de vale pedagio - ID: a1b2c3d4
[2025-11-06 08:30:45] [DEBUG] IP origem: 192.168.19.100
[2025-11-06 08:30:45] [DEBUG] Headers: {'Content-Type': 'application/json', ...}
[2025-11-06 08:30:45] [DEBUG] Payload recebido (primeiros 500 chars): {"data":{"obterReciboViagemReturnDset":{...}}}
[2025-11-06 08:30:45] [INFO] Parametros recebidos: telefone=5531993233194, email=usuario@tambasa.com.br, flgImprime=True

[2025-11-06 08:30:45] [INFO] Codigo da viagem extraido: 91734800
[2025-11-06 08:30:45] [DEBUG] Dados da viagem: nomeRota=ROTA_SP_RJ, catVeiculo=02 EIXOS, total=131.46

[2025-11-06 08:30:45] [INFO] [PDF] Gerando PDF... (request_id=a1b2c3d4)
[2025-11-06 08:30:47] [INFO] [PDF] [OK] PDF gerado com sucesso: Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf
[2025-11-06 08:30:47] [INFO] [PDF] Tamanho: 7574 bytes | Tempo geracao: 1.82s
[2025-11-06 08:30:47] [DEBUG] Path completo do PDF: /var/www/html/SemPararQA/Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf

[2025-11-06 08:30:47] [INFO] [EMAIL] Iniciando envio para: usuario@tambasa.com.br
[2025-11-06 08:30:49] [INFO] [EMAIL] [OK] Email enviado com sucesso | Tempo: 2.13s

[2025-11-06 08:30:49] [INFO] [WHATSAPP] Iniciando envio para: 5531993233194
[2025-11-06 08:30:51] [INFO] [WHATSAPP] [OK] WhatsApp enviado com sucesso | Tempo: 1.95s
[2025-11-06 08:30:51] [DEBUG] Resposta Z-API: {"success":true,"message":"Message sent"}

[2025-11-06 08:30:51] [INFO] [IMPRESSAO] ========== INICIO IMPRESSAO ==========
[2025-11-06 08:30:51] [INFO] [IMPRESSAO] Solicitada impressao na impressora transp4
[2025-11-06 08:30:51] [DEBUG] PDF existe antes impressao? True
[2025-11-06 08:30:51] [INFO] [IMPRESSAO] Tentando adquirir lock de impressao...
[2025-11-06 08:30:51] [INFO] [IMPRESSAO] Lock adquirido! Iniciando processo de impressao...
[2025-11-06 08:30:51] [INFO] [IMPRESSAO] Verificando se impressora 'transp4' existe...
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] [OK] Impressora 'transp4' encontrada!
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Status da impressora: printer transp4 is idle. enabled since...
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Aceitando trabalhos: transp4 accepting requests since...
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Trabalhos na fila antes da impressao: 2
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Enviando PDF 'Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf' para impressora 'transp4'...
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Saida do comando lp: 'request id is transp4-1234 (1 file(s))'
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] [OK] Trabalho enviado para fila: transp4-1234
[2025-11-06 08:30:52] [INFO] [IMPRESSAO] Aguardando 10s para spooler copiar o PDF...
[2025-11-06 08:31:02] [INFO] [IMPRESSAO] Spooler teve tempo suficiente para copiar o arquivo
[2025-11-06 08:31:02] [INFO] [IMPRESSAO] Job transp4-1234 ainda em processamento na fila
[2025-11-06 08:31:02] [INFO] [IMPRESSAO] Lock de impressao sera liberado agora
[2025-11-06 08:31:02] [INFO] [IMPRESSAO] [OK] PDF enviado para impressao com sucesso | Tempo total: 11.23s
[2025-11-06 08:31:02] [INFO] [IMPRESSAO] ========== FIM IMPRESSAO ==========

[2025-11-06 08:31:02] [INFO] [CLEANUP] ========== INICIO CLEANUP ==========
[2025-11-06 08:31:02] [INFO] [CLEANUP] Aguardando 5s para garantir que spooler terminou...
[2025-11-06 08:31:02] [DEBUG] PDF existe antes cleanup? True
[2025-11-06 08:31:07] [INFO] [CLEANUP] [OK] PDF removido com sucesso: Vale_Pedagio_91734800_20251106_083045_a1b2c3d4.pdf (7574 bytes)
[2025-11-06 08:31:07] [INFO] [CLEANUP] ========== FIM CLEANUP ==========

[2025-11-06 08:31:07] [INFO] [RESUMO] ========================================
[2025-11-06 08:31:07] [INFO] [RESUMO] Request ID: a1b2c3d4
[2025-11-06 08:31:07] [INFO] [RESUMO] Codigo Viagem: 91734800
[2025-11-06 08:31:07] [INFO] [RESUMO] Email: [OK] | WhatsApp: [OK] | Impressao: [OK]
[2025-11-06 08:31:07] [INFO] [RESUMO] ========================================
================================================================================
```

---

### **Exemplo de requisição com erro:**

```
================================================================================
[2025-11-06 09:15:23] [INFO] [INICIO] Nova requisicao de vale pedagio - ID: x7y8z9w0
[2025-11-06 09:15:23] [INFO] Parametros recebidos: telefone=5531993233194, email=usuario@tambasa.com.br, flgImprime=True
[2025-11-06 09:15:23] [INFO] Codigo da viagem extraido: 91734800

[2025-11-06 09:15:23] [INFO] [PDF] Gerando PDF... (request_id=x7y8z9w0)
[2025-11-06 09:15:25] [INFO] [PDF] [OK] PDF gerado com sucesso: Vale_Pedagio_91734800_20251106_091523_x7y8z9w0.pdf

[2025-11-06 09:15:25] [INFO] [EMAIL] Iniciando envio para: usuario@tambasa.com.br
[2025-11-06 09:15:27] [INFO] [EMAIL] [OK] Email enviado com sucesso | Tempo: 2.01s

[2025-11-06 09:15:27] [INFO] [WHATSAPP] Iniciando envio para: 5531993233194
[2025-11-06 09:15:29] [ERROR] [WHATSAPP] [ERROR] Falha ao enviar: Connection timeout
[2025-11-06 09:15:29] [DEBUG] Traceback WhatsApp: Traceback (most recent call last):
  File "app.py", line 690, in api_vale_pedagio
    response = enviar_whatsapp(pdf_file, telefone)
  ...
  requests.exceptions.Timeout: Connection timeout

[2025-11-06 09:15:29] [INFO] [IMPRESSAO] ========== INICIO IMPRESSAO ==========
[2025-11-06 09:15:29] [INFO] [IMPRESSAO] Solicitada impressao na impressora transp4
[2025-11-06 09:15:29] [WARNING] [IMPRESSAO] [AVISO] Fila saturada (12 trabalhos)! Limpando trabalhos antigos...
[2025-11-06 09:15:32] [INFO] [IMPRESSAO] Trabalhos removidos: 7
[2025-11-06 09:15:35] [INFO] [IMPRESSAO] Trabalhos na fila APOS limpeza: 5
[2025-11-06 09:15:35] [INFO] [IMPRESSAO] Enviando PDF 'Vale_Pedagio_91734800_20251106_091523_x7y8z9w0.pdf' para impressora 'transp4'...
[2025-11-06 09:15:35] [INFO] [IMPRESSAO] [OK] Trabalho enviado para fila: transp4-1235
[2025-11-06 09:15:45] [INFO] [IMPRESSAO] [OK] PDF enviado para impressao com sucesso | Tempo total: 16.12s
[2025-11-06 09:15:45] [INFO] [IMPRESSAO] ========== FIM IMPRESSAO ==========

[2025-11-06 09:15:50] [INFO] [RESUMO] Request ID: x7y8z9w0
[2025-11-06 09:15:50] [INFO] [RESUMO] Email: [OK] | WhatsApp: [FALHOU] | Impressao: [OK]
================================================================================
```

---

## 🚨 Indicadores de Problemas

### **Problema: Impressão não funciona**

#### **Buscar:**
```bash
grep "IMPRESSAO.*ERROR\|IMPRESSAO.*ERRO" vale_pedagio_detalhado.log | tail -20
```

#### **Sintomas comuns:**

| Log | Problema | Solução |
|-----|----------|---------|
| `Impressora 'transp4' nao encontrada` | CUPS não reconhece impressora | `lpstat -p transp4` |
| `Fila saturada (X trabalhos)` | Muitos trabalhos travados | `cancel -a transp4` |
| `Fila nao foi limpa! Trabalhos podem estar travados!` | CUPS travado | `sudo systemctl restart cups` |
| `Timeout ao enviar impressao` | CUPS não responde | Verificar logs CUPS |
| `PDF nao existe` | Arquivo deletado muito cedo | Aumentar `wait_time` |

---

### **Problema: WhatsApp não envia**

#### **Buscar:**
```bash
grep "WHATSAPP.*ERROR" vale_pedagio_detalhado.log | tail -20
```

#### **Sintomas comuns:**

| Log | Problema | Solução |
|-----|----------|---------|
| `Connection timeout` | Z-API fora do ar | Verificar https://api.z-api.io |
| `401 Unauthorized` | Token inválido | Verificar `client_token` e `api_token` |
| `Invalid phone number` | Formato de telefone errado | Verificar formatação (DDI+DDD+Numero) |

---

### **Problema: Email não envia**

#### **Buscar:**
```bash
grep "EMAIL.*ERROR" vale_pedagio_detalhado.log | tail -20
```

#### **Sintomas comuns:**

| Log | Problema | Solução |
|-----|----------|---------|
| `Connection refused` | SMTP bloqueado | Verificar firewall |
| `550 Relay denied` | Email não autorizado | Usar `naoresponda@tambasa.com.br` |
| `Timeout` | SMTP lento | Aumentar timeout |

---

## 📈 Análise de Performance

### **Tempo médio de processamento:**
```bash
grep "Tempo geracao:" vale_pedagio_detalhado.log | awk -F': ' '{print $NF}' | awk '{sum+=$1; count++} END {print "Media PDF: " sum/count "s"}'
```

### **Requisições mais lentas:**
```bash
grep "Tempo total:" vale_pedagio_detalhado.log | sort -t':' -k4 -rn | head -10
```

---

## 🔧 Manutenção

### **Limpar logs antigos manualmente:**
```bash
rm vale_pedagio_detalhado.log.5  # Remove arquivo mais antigo
```

### **Forçar rotação:**
```bash
# Após 10MB, rotação é automática
ls -lh vale_pedagio_detalhado.log*
```

### **Compactar logs para backup:**
```bash
tar -czf logs_backup_$(date +%Y%m%d).tar.gz vale_pedagio_detalhado.log*
```

---

## 📌 Dicas Rápidas

### **Ver últimas 50 linhas:**
```bash
tail -50 vale_pedagio_detalhado.log
```

### **Ver todo o arquivo:**
```bash
less vale_pedagio_detalhado.log
```

### **Buscar padrão específico:**
```bash
grep -i "erro" vale_pedagio_detalhado.log
```

### **Contar ocorrências:**
```bash
grep -c "PDF gerado com sucesso" vale_pedagio_detalhado.log
```

### **Ver logs entre datas:**
```bash
sed -n '/2025-11-06 08:30/,/2025-11-06 09:00/p' vale_pedagio_detalhado.log
```

---

## ✅ Checklist de Depuração

Ao investigar um problema:

- [ ] Identificar `request_id` da requisição problemática
- [ ] Buscar todos os logs dessa requisição: `grep "request_id" vale_pedagio_detalhado.log`
- [ ] Verificar se há logs `ERROR` ou `CRITICAL`
- [ ] Verificar seção `[RESUMO]` para ver o que falhou
- [ ] Se impressão falhou, verificar logs `[IMPRESSAO]`
- [ ] Se WhatsApp/Email falhou, verificar traceback em `[DEBUG]`
- [ ] Verificar tempo de processamento (pode indicar timeout)
- [ ] Comparar com requisição bem-sucedida similar

---

## 📞 Informações Importantes

- **Arquivo principal**: `vale_pedagio_detalhado.log`
- **Localização**: Mesmo diretório do `app.py` (`/var/www/html/SemPararQA/`)
- **Tamanho máximo**: 10MB por arquivo
- **Histórico**: 5 arquivos (total ~50MB)
- **Codificação**: UTF-8
- **Formato timestamp**: `YYYY-MM-DD HH:MM:SS`

---

**Última atualização:** 2025-11-06
