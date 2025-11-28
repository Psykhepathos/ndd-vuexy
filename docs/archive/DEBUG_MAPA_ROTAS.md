# Sistema de Debug e Testes - Mapa de Rotas SemParar

## 📋 Resumo das Melhorias Implementadas

Este documento descreve todas as melhorias implementadas no sistema de visualização do mapa de rotas SemParar (`resources/ts/pages/rotas-semparar/mapa/[id].vue`).

## 🎯 Problemas Identificados e Solucionados

### 1. **Race Conditions no Geocoding**
**Problema**: Múltiplas chamadas de geocoding simultâneas causavam resultados inconsistentes e coordenadas incorretas.

**Solução**:
- Processamento sequencial (síncrono) de geocoding, um município por vez
- Fila de controle (`geocodingQueue`) para evitar requisições duplicadas
- Lock de sincronização (`isUpdatingMap`) para evitar atualizações concorrentes do mapa

### 2. **Validação Inadequada de Coordenadas**
**Problema**: Sistema aceitava coordenadas inválidas (null, undefined, NaN, fora do range válido).

**Solução**:
- Função `isValidCoordinate()` com validação rigorosa
- Função `sanitizeCoordinate()` para limpar e converter valores
- Validação específica para coordenadas do Brasil (lat: -35 a 6, lon: -75 a -33)
- Status de geocoding por município (`pending`, `loading`, `success`, `error`)

### 3. **Ausência de Debouncing**
**Problema**: `updateMapMarkers()` era chamado múltiplas vezes seguidas durante drag & drop e edições.

**Solução**:
- Debounce de 300ms antes de atualizar o mapa
- Opção `forceImmediate` para casos que requerem atualização instantânea
- Timer controlado (`updateMapDebounceTimer`) para cancelar atualizações pendentes

### 4. **Watch Inadequado**
**Problema**: Watch monitorava apenas `municipios.value.length`, não detectava mudanças de ordem ou coordenadas.

**Solução**:
- Removido o watch genérico
- Chamadas explícitas a `updateMapMarkers()` em todas as operações:
  - `adicionarMunicipio()`
  - `removerMunicipio()`
  - `onDragEnd()`
  - `ativarEdicao()`

### 5. **Falta de Observabilidade**
**Problema**: Difícil diagnosticar problemas sem logs estruturados ou métricas.

**Solução**: Sistema completo de logging e debug (veja seção abaixo).

## 🔍 Sistema de Debug Implementado

### 1. **Logging Estruturado**

```typescript
interface DebugLog {
  timestamp: string
  level: 'info' | 'warn' | 'error' | 'success'
  category: string  // MAP_UPDATE, GEOCODING, ROUTING, EDIT, etc.
  message: string
  data?: any  // Dados adicionais para debug
}
```

**Categorias de Log**:
- `MAP_UPDATE`: Atualizações do mapa
- `GEOCODING`: Busca de coordenadas
- `GEOCODING_API`: Requisições à API de geocoding
- `ROUTING`: Cálculo de rotas
- `EDIT`: Adição/remoção/reordenação de municípios
- `VALIDATION`: Validação de coordenadas

### 2. **Métricas em Tempo Real**

```typescript
debugStats = {
  totalGeocodes: number          // Total de tentativas de geocoding
  successfulGeocodes: number     // Geocodes bem-sucedidos
  failedGeocodes: number         // Geocodes falhados
  cachedGeocodes: number         // Coordenadas obtidas do cache
  mapUpdates: number             // Número de atualizações do mapa
  lastUpdate: Date | null        // Timestamp da última atualização
}
```

### 3. **Painel de Debug Visual**

Acessível via botão "Debug" no header da página, o painel inclui:

**📊 Estatísticas**:
- Cards visuais com métricas em tempo real
- Indicadores coloridos por categoria

**🗺️ Estado dos Municípios**:
- Tabela com todos os municípios da rota
- Sequência, nome, UF, coordenadas e status de geocoding
- Coordenadas válidas em verde, inválidas em vermelho

**📋 Logs do Sistema**:
- Lista cronológica de todos os eventos (últimos 100)
- Filtrados por nível (info, warning, error, success)
- Dados detalhados expandíveis em JSON
- Botão para limpar logs

### 4. **Indicadores Visuais no Mapa**

- **Marcadores coloridos**:
  - 🔵 Azul: Modo visualização normal
  - 🟠 Laranja: Modo edição
  - 🔴 Vermelho: Erro de geocoding

- **InfoWindow aprimorado**:
  - Coordenadas com 6 casas decimais
  - Status de geocoding
  - Mensagens de erro quando aplicável

## 🧪 Testando o Sistema

### Cenários de Teste

#### 1. **Adicionar Município**
```
1. Ativar modo edição
2. Buscar município no autocomplete
3. Clicar "Adicionar"
4. Verificar no painel de debug:
   - Log "EDIT: Adicionando novo município"
   - Log "GEOCODING: Buscando coordenadas" (se necessário)
   - Log "MAP_UPDATE: Iniciando atualização do mapa"
   - Estatística de mapUpdates incrementada
```

#### 2. **Remover Município**
```
1. Ativar modo edição
2. Clicar no X de um município
3. Verificar no painel de debug:
   - Log "EDIT: Removendo município"
   - Log "EDIT: Sequências reajustadas"
   - Log "MAP_UPDATE: Iniciando atualização do mapa"
   - Verificar reordenação correta na tabela de municípios
```

#### 3. **Reordenar Municípios (Drag & Drop)**
```
1. Ativar modo edição
2. Arrastar município para nova posição
3. Soltar município
4. Verificar no painel de debug:
   - Log "EDIT: Reordenando municípios após drag & drop"
   - Log "EDIT: Sequências reajustadas" com antes/depois
   - Log "MAP_UPDATE: Iniciando atualização do mapa"
   - Rota recalculada com nova ordem
```

#### 4. **Geocoding com Cache**
```
1. Adicionar município já geocodificado anteriormente
2. Verificar no painel de debug:
   - Log "GEOCODING_API: Coordenadas obtidas em Xs (cache)"
   - Estatística cachedGeocodes incrementada
   - Tempo de resposta < 0.5s
```

#### 5. **Erro de Geocoding**
```
1. Adicionar município com dados inválidos/inexistentes
2. Verificar no painel de debug:
   - Log nível ERROR "GEOCODING_API: API retornou erro"
   - Estatística failedGeocodes incrementada
   - Marcador vermelho no mapa (se parcialmente válido)
```

## 📊 Métricas de Desempenho Esperadas

### Geocoding
- **Cache hit**: < 0.5s
- **Google API**: 1-3s
- **Taxa de sucesso**: > 95%

### Atualização do Mapa
- **Debounce delay**: 300ms
- **Renderização**: < 1s para rotas com até 10 municípios
- **Sem race conditions**: 1 atualização por operação

### Roteamento
- **Segmentos em cache**: < 0.5s
- **Novos segmentos**: 1-2s por segmento
- **Cache rate**: > 80% após primeira visualização

## 🐛 Como Usar o Painel de Debug

### 1. **Acessar o Painel**
- Clique no botão "Debug" no header
- Badge vermelha mostra número de logs acumulados

### 2. **Interpretar Logs**

**Cores**:
- 🟢 Verde (success): Operação bem-sucedida
- 🔵 Azul (info): Informação geral
- 🟡 Amarelo (warn): Aviso ou fallback
- 🔴 Vermelho (error): Erro crítico

**Categorias comuns**:
- `MAP_UPDATE`: Acompanhar atualizações do mapa
- `GEOCODING`: Monitorar busca de coordenadas
- `ROUTING`: Verificar cálculo de rotas
- `EDIT`: Rastrear operações de edição

### 3. **Analisar Problemas**

**Conexões estranhas no mapa?**
1. Abrir painel de debug
2. Verificar seção "Estado dos Municípios"
3. Procurar por coordenadas inválidas (em vermelho)
4. Verificar logs de GEOCODING para erros
5. Verificar ordem de sequência na tabela

**Geocoding falhando?**
1. Filtrar logs por categoria "GEOCODING_API"
2. Verificar estatística de falhas
3. Analisar mensagens de erro na resposta da API
4. Validar códigos IBGE na tabela de municípios

**Mapa não atualizando?**
1. Verificar logs de "MAP_UPDATE"
2. Contar número de atualizações (deve ser 1 por operação)
3. Verificar se `isUpdatingMap` está travado
4. Limpar logs e tentar novamente

### 4. **Limpar Estado**
- Botão "Limpar Logs" reseta logs e estatísticas
- Use antes de testar novo cenário
- Não afeta dados da rota ou municípios

## 📝 Notas Técnicas

### Sincronização
- Lock `isUpdatingMap` previne atualizações concorrentes
- Queue `geocodingQueue` evita requisições duplicadas
- Debounce de 300ms otimiza performance

### Validação de Coordenadas
```typescript
isValidCoordinate(lat?: number, lon?: number): boolean
// Validações:
// - Não null/undefined/NaN
// - Latitude: -90 a 90
// - Longitude: -180 a 180
// - Warning se fora do Brasil
```

### Estados de Geocoding
- `pending`: Aguardando processamento
- `loading`: Requisição em andamento
- `success`: Coordenadas válidas obtidas
- `error`: Falha ao obter coordenadas

### Cache
- **Coordenadas de municípios**: Tabela `municipio_coordenadas` (30 dias)
- **Segmentos de rota**: Tabela `route_segments` (30 dias, ~100m tolerance)

## 🚀 Melhorias Futuras Sugeridas

1. **Retry automático** para geocoding falhado
2. **Exportação de logs** em CSV/JSON
3. **Filtros avançados** no painel de debug (por categoria, nível, data)
4. **Gráficos de performance** com histórico
5. **Alertas automáticos** quando taxa de erro > 10%
6. **Modo simulação** para testar cenários sem salvar
7. **Comparação antes/depois** ao reordenar
8. **Desfazer/Refazer** operações de edição

## 📞 Suporte

Se encontrar problemas:
1. Capture screenshot do painel de debug
2. Exporte logs (copie JSON dos logs relevantes)
3. Anote passos para reproduzir
4. Reporte com todas as informações acima

---

**Desenvolvido em**: 2025-09-30
**Versão**: 1.0.0
**Arquivo**: `resources/ts/pages/rotas-semparar/mapa/[id].vue`
