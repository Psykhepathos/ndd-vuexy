# Análise Completa de Problemas UI/UX

## Problemas Identificados nos Prints

### Rotas SemParar (Print 1)
- [ ] Códigos com chips laranjas (#206, #304)
- [ ] Badges header com cores muito fortes
- [ ] Ícones e badges inconsistentes (laranja/amarelo)
- [ ] Chips ROTA/CD com cores erradas
- [ ] Filtros soltos sem card

### Transportadores (Print 2) - CRÍTICO
- [ ] Botão "Ocultar" isolado sem contexto
- [ ] **Filtros expansíveis HORRÍVEIS:**
  - Ocupam linha inteira
  - 4 colunas mal distribuídas
  - Checkbox com label "Visualização" + checkbox dentro
  - Box cinza com borda feia
- [ ] **Checkbox DUPLICADO** (toggle button + checkbox nos filtros)
- [ ] Badges com cores erradas (verde claro, cyan, laranja forte)
- [ ] Placas com gradiente customizado fora do padrão

## Solução Correta (Padrão Vuexy)

### Estrutura de Filtros
```
Card separado ACIMA da tabela:
- VCardTitle: "Filters"
- VCardText com VRow de 3-4 colunas
- Selects com placeholder apenas
- Sem labels, sem checkboxes extras
```

### Cores Padrão Vuexy
- Primary: Azul principal
- Success: Verde (autônomos, ativos)
- Info: Azul claro (empresas, transporte)
- Secondary: Cinza (agregado, neutros)
- Warning: Laranja (avisos)
- Error: Vermelho (inativos, erros)

Todos com `variant="tonal"` para suavizar
