# Análise UI/UX - Sistema de Compra de Viagem SemParar

**Data:** 2025-10-30
**Arquivo:** `resources/ts/pages/compra-viagem/nova.vue`

---

## 🔴 PROBLEMAS CRÍTICOS IDENTIFICADOS

### 1. **Watchers Automáticos Invasivos**
**Linhas:** 246-282

**Problema:**
```typescript
// ❌ MAU: Dispara automaticamente ao selecionar rota
watch(rotaId, async (novoRotaId) => {
  await carregarMunicipiosRota(novoRotaId)  // Requisição automática!
})

// ❌ MAU: Dispara automaticamente ao selecionar pacote
watch(codpac, async (novoCodpac) => {
  await loadPacoteEntregas(novoCodpac)  // Requisição automática!
  await updateMapMarkers()  // Mapa atualiza sozinho!
})
```

**Impacto:**
- ⚠️ Usuário perde controle do fluxo
- ⚠️ Requisições desnecessárias se usuário mudar de ideia
- ⚠️ Mapa carrega antes de confirmar pacote
- ⚠️ Confusão: "Por que está carregando se não pedi?"

**Solução:**
```typescript
// ✅ BOM: Requisições apenas após confirmação explícita
const confirmarPacote = async () => {
  // Valida
  if (!codpac.value) return

  // Carrega dados
  await loadPacoteEntregas(codpac.value)

  // Marca etapa como concluída
  verificaPacote.value = true
}
```

---

### 2. **Falta de Stepper Visual**
**Problema:** Existe `currentStep` computed mas não é usado visualmente

**Impacto:**
- ❓ Usuário não sabe em qual etapa está
- ❓ Não sabe quantas etapas faltam
- ❓ Não vê progresso do fluxo

**Solução:**
```vue
<!-- ✅ BOM: Vuetify Stepper -->
<VStepper v-model="currentStep" :items="stepperItems">
  <template #item.1>
    <!-- Etapa 1: Selecionar Pacote -->
  </template>
  <template #item.2>
    <!-- Etapa 2: Validar Placa -->
  </template>
  ...
</VStepper>
```

---

### 3. **Falta de Botões de Confirmação**
**Problema:** Não há botões claros de "Confirmar" ou "Avançar" em cada etapa

**Impacto:**
- ⚠️ Usuário não sabe quando avançar
- ⚠️ Etapas se misturam visualmente
- ⚠️ Difícil de voltar/editar

**Solução:**
```vue
<!-- ✅ BOM: Botão explícito -->
<VCardActions>
  <VSpacer />
  <VBtn
    color="primary"
    :loading="validatingPacote"
    :disabled="!codpac"
    @click="confirmarPacote"
  >
    Confirmar Pacote
  </VBtn>
</VCardActions>
```

---

### 4. **Validação de Etapas Confusa**
**Problema:** Flags booleanas (`verificaPacote`, `verificaPlaca`) não são claras

**Impacto:**
- 🤔 Código difícil de entender
- 🤔 Lógica de validação espalhada
- 🤔 Difícil debugar

**Solução:**
```typescript
// ✅ BOM: Estado de etapa explícito
interface Step {
  id: number
  title: string
  completed: boolean
  validated: boolean
  data: any
}

const steps = ref<Step[]>([
  { id: 1, title: 'Selecionar Pacote', completed: false, validated: false, data: null },
  { id: 2, title: 'Validar Placa', completed: false, validated: false, data: null },
  ...
])
```

---

### 5. **Mapa Carrega Muito Cedo**
**Problema:** Mapa aparece e tenta carregar dados antes de confirmar rota + pacote

**Impacto:**
- ⚠️ Requisições de geocoding desnecessárias
- ⚠️ Loading desnecessário
- ⚠️ Confusão visual

**Solução:**
```typescript
// ✅ BOM: Mapa só inicializa após etapa 3 confirmada
const shouldShowMap = computed(() => {
  return verificaPacote.value && verificaPlaca.value && verificaRota.value
})

onMounted(() => {
  // NÃO inicializa mapa automaticamente
})

watch(shouldShowMap, async (show) => {
  if (show && !map.value) {
    await initMap()
    await carregarDadosMapa()
  }
})
```

---

### 6. **Loading States Inadequados**
**Problema:** Um único loading global para operações diferentes

**Impacto:**
- ❓ Usuário não sabe o que está carregando
- ❓ Não sabe quanto tempo vai demorar

**Solução:**
```vue
<!-- ✅ BOM: Loading específico por operação -->
<VBtn :loading="loadingValidatePacote">Confirmar Pacote</VBtn>
<VBtn :loading="loadingValidatePlaca">Validar Placa</VBtn>
<VBtn :loading="loadingCalculatePreco">Calcular Preço</VBtn>
```

---

### 7. **Impossível Editar Etapas Anteriores**
**Problema:** Campos ficam disabled sem opção de editar

**Impacto:**
- ⚠️ Se errou, tem que recarregar página
- ⚠️ Frustração do usuário

**Solução:**
```vue
<!-- ✅ BOM: Botão de editar etapa -->
<VBtn
  v-if="verificaPacote"
  variant="text"
  @click="editarPacote"
>
  <VIcon icon="tabler-edit" />
  Alterar Pacote
</VBtn>
```

---

## 📋 FLUXO IDEAL PROPOSTO

### **Etapa 1: Selecionar Pacote**
1. Campo autocomplete de pacote
2. Botão "Confirmar Pacote" (disabled até selecionar)
3. Ao confirmar:
   - Valida pacote via API
   - Carrega dados do pacote
   - Marca etapa 1 como concluída
   - Avança para etapa 2
   - Campo fica readonly com botão "Editar"

### **Etapa 2: Validar Placa**
1. Campo de placa
2. Botão "Validar Placa"
3. Ao confirmar:
   - Valida placa via API
   - Verifica se está cadastrada no SemParar
   - Marca etapa 2 como concluída
   - Avança para etapa 3

### **Etapa 3: Selecionar Rota**
1. Campo autocomplete de rota
2. Botão "Confirmar Rota"
3. Ao confirmar:
   - Carrega municípios da rota
   - Carrega entregas do pacote
   - Inicializa mapa
   - Geocodifica pontos
   - Calcula roteamento
   - Marca etapa 3 como concluída
   - Avança para etapa 4

### **Etapa 4: Calcular Preço**
1. Campos de data (pré-preenchidos)
2. Campo de eixos
3. Botão "Calcular Preço"
4. Ao confirmar:
   - Chama API SemParar
   - Mostra diálogo com valor
   - Marca etapa 4 como concluída
   - Avança para etapa 5

### **Etapa 5: Confirmar Compra**
1. Resumo de todos os dados
2. Botão "Confirmar Compra"
3. Ao confirmar:
   - Efetiva compra
   - Mostra recibo
   - Opção de baixar PDF

---

## 🎨 COMPONENTES UI/UX NECESSÁRIOS

### 1. **Stepper Visual (Vuetify)**
```vue
<VStepper
  v-model="currentStep"
  :items="[
    { title: 'Pacote', icon: 'tabler-package' },
    { title: 'Placa', icon: 'tabler-car' },
    { title: 'Rota', icon: 'tabler-route' },
    { title: 'Preço', icon: 'tabler-receipt' },
    { title: 'Confirmar', icon: 'tabler-check' }
  ]"
  editable
  alt-labels
/>
```

### 2. **Card de Etapa com Ações**
```vue
<VCard>
  <VCardTitle>
    <VIcon :icon="step.icon" />
    {{ step.title }}
    <VChip
      v-if="step.completed"
      color="success"
      size="small"
    >
      Concluído
    </VChip>
  </VCardTitle>

  <VCardText>
    <!-- Campos da etapa -->
  </VCardText>

  <VCardActions>
    <VBtn
      v-if="step.completed"
      variant="text"
      @click="editStep(step.id)"
    >
      Editar
    </VBtn>
    <VSpacer />
    <VBtn
      color="primary"
      :loading="step.loading"
      :disabled="!canConfirmStep(step.id)"
      @click="confirmStep(step.id)"
    >
      {{ step.completed ? 'Atualizar' : 'Confirmar' }}
    </VBtn>
  </VCardActions>
</VCard>
```

### 3. **Loading Skeleton**
```vue
<VSkeletonLoader
  v-if="loadingRotaMunicipios"
  type="card"
  :loading="true"
>
  <template #default>
    <VCard>...</VCard>
  </template>
</VSkeletonLoader>
```

### 4. **Alert de Feedback**
```vue
<VAlert
  v-if="step.error"
  type="error"
  closable
  @click:close="step.error = null"
>
  {{ step.error }}
</VAlert>

<VAlert
  v-if="step.success"
  type="success"
  closable
>
  {{ step.success }}
</VAlert>
```

---

## 🚀 PLANO DE IMPLEMENTAÇÃO

### **Fase 1: Refatoração do Estado**
- [ ] Criar estrutura de `steps` array
- [ ] Remover flags booleanas dispersas
- [ ] Centralizar estado de validação

### **Fase 2: Remover Watchers Automáticos**
- [ ] Remover `watch(rotaId)`
- [ ] Remover `watch(codpac)`
- [ ] Criar funções de confirmação explícitas

### **Fase 3: Adicionar Stepper Visual**
- [ ] Implementar `VStepper` do Vuetify
- [ ] Configurar navegação entre etapas
- [ ] Adicionar indicadores visuais

### **Fase 4: Botões de Confirmação**
- [ ] Adicionar botão em cada etapa
- [ ] Implementar loading states específicos
- [ ] Adicionar validação antes de avançar

### **Fase 5: Permitir Edição de Etapas**
- [ ] Adicionar botão "Editar" em etapas concluídas
- [ ] Implementar lógica de reset de etapas subsequentes
- [ ] Adicionar confirmação de edição

### **Fase 6: Melhorar Feedback Visual**
- [ ] Adicionar skeleton loaders
- [ ] Melhorar mensagens de erro
- [ ] Adicionar animações de transição

---

## ✅ BENEFÍCIOS ESPERADOS

1. **Controle do Usuário**: Usuário decide quando avançar
2. **Clareza Visual**: Stepper mostra progresso
3. **Menos Requisições**: Apenas quando confirmar
4. **Editabilidade**: Pode voltar e editar
5. **Feedback Claro**: Sabe o que está acontecendo
6. **Performance**: Menos requisições desnecessárias
7. **Manutenibilidade**: Código mais limpo e organizado

---

## 📊 COMPARAÇÃO: ANTES vs DEPOIS

| Aspecto | ❌ Antes | ✅ Depois |
|---------|----------|-----------|
| Requisições | Automáticas (watchers) | Sob demanda (botões) |
| Progresso | Invisível | Stepper visual |
| Validação | Confusa (flags dispersas) | Clara (estado centralizado) |
| Edição | Impossível | Possível a qualquer momento |
| Feedback | Loading genérico | Loading específico por ação |
| Controle | Sistema decide | Usuário decide |
| Performance | Muitas requisições | Requisições otimizadas |

---

**Prioridade:** 🔴 ALTA - Impacta diretamente experiência do usuário
