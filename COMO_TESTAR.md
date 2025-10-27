# Como Claude Deve Testar Implementações

## ❌ NUNCA FAÇA ISSO:
- ❌ Abrir navegador e pedir pro usuário testar
- ❌ Usar `start` ou comandos que abrem GUI
- ❌ Depender do usuário para ver resultados
- ❌ Fazer mudanças sem testar primeiro

## ✅ SEMPRE FAÇA ISSO:

### 1. Testes de API via Node.js
```bash
# Criar arquivo .cjs (CommonJS)
node test-api.cjs

# Ver resultado no terminal
# Exit code 0 = sucesso
# Exit code 1 = falha
```

### 2. Testes via curl
```bash
curl -X POST http://localhost:8002/api/endpoint \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### 3. Ver logs do Laravel
```bash
tail -f storage/logs/laravel.log
```

### 4. Ver logs do Vite/Frontend
```bash
# Usar BashOutput tool para ver output do pnpm run dev
```

### 5. Testes TypeScript
```bash
pnpm run typecheck
```

## Fluxo Correto:

1. **Escrever código**
2. **Criar teste automatizado**
3. **Executar teste via Bash**
4. **Ver resultado no terminal**
5. **Se passou: informar usuário**
6. **Se falhou: corrigir e repetir**

## Exemplo de Teste Bom:

```javascript
// test-feature.cjs
const https = require('https');

https.get('http://localhost:8002/api/test', (res) => {
  if (res.statusCode === 200) {
    console.log('✅ TESTE PASSOU');
    process.exit(0);
  } else {
    console.error('❌ TESTE FALHOU');
    process.exit(1);
  }
});
```

Executar:
```bash
node test-feature.cjs && echo "SUCCESS" || echo "FAILED"
```
