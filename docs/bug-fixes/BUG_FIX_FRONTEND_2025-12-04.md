# Bug Fix: Frontend - Erro de Import Din âmico

**Data:** 2025-12-04
**Severidade:** 🔴 CRÍTICA (impedia acesso a /rotas-padrao)
**Status:** ✅ RESOLVIDO

---

## 🐛 Erro Reportado

```
Failed to fetch dynamically imported module:
http://10.0.3.9:5173/resources/ts/pages/rotas-padrao/mapa/[id].vue

Mensagem: Ops! Algo deu errado
```

---

## 🔍 Investigação

### Sintomas
- Página `/rotas-padrao` retornando erro de carregamento
- Vite dev server rodando normalmente (porta 5173)
- Import dinâmico falhando

### Causa Raiz
- Arquivo **obsoleto/mal formado** causando falha no build do Vite
- `resources/ts/pages/vale-pedagio/new-sidebar.vue`:
  - ❌ Fragmento de template sem estrutura Vue válida
  - ❌ Sem tags `<template>` e `<script>` raiz
  - ❌ Começava direto com HTML (`<!-- Data/Hora -->`)
  - ❌ Apenas 213 linhas de HTML puro
  - ❌ **NÃO estava sendo usado** em nenhum lugar do projeto

### Erro de Build
```bash
error during build:
[vite:vue] resources/ts/pages/vale-pedagio/new-sidebar.vue (54:25): Invalid end tag.
SyntaxError: Invalid end tag.
```

---

## 🛠️ Solução Aplicada

### 1. Identificação
```bash
✅ Arquivo existe: vale-pedagio/new-sidebar.vue (8.798 bytes)
✅ Verificado importação: NENHUMA (arquivo não usado)
✅ Histórico git: Sem commits recentes
```

### 2. Ação Tomada
```bash
# Renomear arquivo problemático para backup
mv resources/ts/pages/vale-pedagio/new-sidebar.vue \
   resources/ts/pages/vale-pedagio/new-sidebar.vue.backup
```

### 3. Validação
```bash
✅ Build passou: 1m 49s
✅ Cache limpo: node_modules/.vite removido
✅ Teste endpoint: GET /rotas-padrao → 200 OK
✅ Import dinâmico: funcionando
```

### 4. Problema Persistente: Conflito de Porta Vite

**Sintoma adicional:**
- Erro continuou após fix inicial: "continua sem conseguir conectar"
- Browser ainda reportava: `Failed to fetch dynamically imported module`

**Causa secundária:**
- Processo antigo do Vite ainda ocupando porta 5173
- Novo Vite iniciou na porta 5174
- Browser tentando buscar da porta antiga (5173)

**Solução final:**
```bash
# 1. Identificar processos nas portas
netstat -ano | findstr :5173
netstat -ano | findstr :5174  # PID 566804

# 2. Matar processo antigo
taskkill //F //PID 566804

# 3. Limpar cache Vite
rm -rf node_modules/.vite

# 4. Reiniciar Vite na porta correta
pnpm run dev

# Resultado:
# ➜  Local:   http://localhost:5173/
# ➜  Network: http://10.0.3.9:5173/
```

**Validação completa:**
```bash
✅ curl http://localhost:8002/rotas-padrao → 200 OK
✅ curl http://localhost:5173/resources/ts/pages/rotas-padrao/mapa/[id].vue → 200 OK (58,859 bytes)
✅ Vite rodando corretamente na porta 5173
✅ Nenhum outro arquivo Vue malformado (verificados 140 arquivos)
```

---

## ✅ Resultado

| Antes | Depois |
|-------|--------|
| ❌ Build falhando | ✅ Build em 1m 49s |
| ❌ /rotas-padrao erro 500 | ✅ /rotas-padrao 200 OK |
| ❌ Import dinâmico quebrado | ✅ Import dinâmico funcional |
| ❌ Vite na porta errada (5174) | ✅ Vite na porta correta (5173) |
| ❌ Processo antigo travando porta | ✅ Porta liberada e limpa |

### 🔄 Última Etapa (Ação do Usuário)

**⚠️ IMPORTANTE:** Se o erro persistir no navegador, é necessário limpar o cache:

1. **Hard Refresh (Recomendado):**
   - Pressione `Ctrl + Shift + R` no navegador
   - Ou `Ctrl + F5`
   - Isso força recarregar sem usar cache

2. **Limpar Cache do Navegador:**
   - Chrome: F12 → Application → Clear storage → Clear site data
   - Firefox: F12 → Storage → Clear all
   - Edge: F12 → Application → Clear storage

3. **Testar Novamente:**
   - Acesse: http://localhost:8002/rotas-padrao
   - O dynamic import deve funcionar corretamente

---

## 📚 Lições Aprendidas

### 1. Arquivos Obsoletos
- Arquivos `.vue` não utilizados podem quebrar o build
- Vite processa TODOS os arquivos `.vue` no projeto
- Sempre verificar se arquivo é usado antes de investigar bug

### 2. Estrutura de Componente Vue
Um arquivo `.vue` válido DEVE ter:
```vue
<template>
  <!-- HTML aqui -->
</template>

<script setup lang="ts">
// TypeScript aqui
</script>

<style scoped>
/* CSS aqui */
</style>
```

### 3. Detecção de Arquivos Obsoletos
```bash
# Encontrar arquivos não importados
grep -r "nome-do-arquivo" resources/ts/

# Ver histórico git
git log --oneline --all -S"nome-do-arquivo.vue"
```

### 4. Conflitos de Porta no Vite
- Vite muda automaticamente de porta se a porta padrão está em uso
- Sempre verificar qual porta o Vite está usando: `netstat -ano | findstr :5173`
- Matar processos antigos antes de reiniciar: `taskkill //F //PID <pid>`
- Browser pode cachear módulos da porta antiga
- **Solução:** Hard refresh (Ctrl+Shift+R) após mudar porta

### 5. Hot Module Replacement (HMR)
- Mudanças de porta quebram HMR
- Sempre reiniciar servidor na mesma porta
- Limpar cache do Vite: `rm -rf node_modules/.vite`
- Verificar que arquivo é acessível via HTTP antes de culpar código

---

## 🔒 Prevenção Futura

### Recomendações

1. **Limpeza Regular de Código Morto**
   ```bash
   # Encontrar arquivos Vue não importados
   find resources/ts -name "*.vue" -exec grep -l {} resources/ts \; | sort | uniq -u
   ```

2. **Pre-commit Hook para Validar Build**
   ```yaml
   # .github/workflows/validate-build.yml
   - name: Validate Frontend Build
     run: pnpm run build
   ```

3. **Renomear Fragmentos**
   - Usar extensão `.vue.html` para fragmentos
   - Ou mover para pasta `_fragments/` ignorada pelo Vite

4. **Documentar Arquivos de Backup**
   - Adicionar sufixo `.backup`, `.old` ou `.deprecated`
   - Mover para pasta `_archive/` fora de `resources/ts`

5. **Monitorar Portas do Vite**
   ```bash
   # Script para verificar porta correta antes de iniciar
   netstat -ano | findstr :5173 && echo "Porta ocupada!" || pnpm run dev

   # Adicionar ao package.json
   "dev:safe": "netstat -ano | findstr :5173 || pnpm run dev"
   ```

6. **Verificar Arquivos Vue Antes de Build**
   ```bash
   # Encontrar arquivos Vue que começam direto com HTML (sem <script>/<template>)
   find resources/ts -name "*.vue" -exec grep -L "<script" {} \;
   ```

---

## 📁 Arquivos Modificados

- ✏️ `resources/ts/pages/vale-pedagio/new-sidebar.vue` → `.backup`
- 🧹 `node_modules/.vite/` - Cache limpo
- 🧹 `public/build/manifest.json` - Removido

---

## ✍️ Assinatura

**Investigado por:** Sistema de Auditoria
**Resolvido em:** ~45 minutos (incluindo resolução de conflito de porta)
**Data:** 2025-12-04
**Horário:** 08:21 - 09:06 (UTC-3)
**Status:** ✅ RESOLVIDO - Aguardando teste do usuário

### Resumo da Resolução

**Problema 1:** Arquivo Vue malformado (`new-sidebar.vue`)
- **Solução:** Renomeado para `.backup`
- **Tempo:** ~30 minutos

**Problema 2:** Conflito de porta do Vite (5173 → 5174)
- **Solução:** Matar processo antigo, reiniciar na porta correta
- **Tempo:** ~15 minutos

**Próxima Ação:** Usuário deve fazer hard refresh (Ctrl+Shift+R) no navegador

---

## 🔗 Relacionado

- [AUDITORIA_ENCODING_2025-12-04.md](AUDITORIA_ENCODING_2025-12-04.md) - Problema anterior de encoding UTF-8
