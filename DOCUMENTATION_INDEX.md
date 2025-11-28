# 📚 Índice de Documentação - NDD Vuexy

## 🎯 Documentação Principal

### Guias Essenciais
- **[CLAUDE.md](CLAUDE.md)** - Guia completo do projeto para Claude Code
  - Arquitetura do sistema
  - Convenções de código
  - Progress Database patterns
  - APIs e services
  - Troubleshooting

- **[README.md](README.md)** - Informações gerais do projeto
  - Setup inicial
  - Tecnologias utilizadas
  - Como rodar o projeto

---

## 🚀 Implementações Recentes (2025-11)

### Cache & Performance
- **[CACHE_OPTIMIZATION_AND_BUG_FIXES.md](CACHE_OPTIMIZATION_AND_BUG_FIXES.md)** (2025-11-28)
  - Sistema de cache de rotas
  - Algoritmo Douglas-Peucker
  - Otimização de performance (80-85% melhoria)
  - Correção erro 808 SemParar
  - Bug fixes compra de viagem

### MapService Unificado
- **[MAP_SERVICE_FASE1_COMPLETO.md](MAP_SERVICE_FASE1_COMPLETO.md)** (2025-11-19)
  - Implementação MapService unificado
  - OSRM routing gratuito
  - Cache de 30 dias
  - Fallback inteligente

- **[PLANO_MAP_SERVICE_UNIFICADO.md](PLANO_MAP_SERVICE_UNIFICADO.md)** (2025-11-19)
  - Arquitetura do MapService
  - CacheManager design
  - Estratégias de otimização

### Praças de Pedágio
- **[PLANO_IMPORTACAO_PRACAS_PEDAGIO.md](PLANO_IMPORTACAO_PRACAS_PEDAGIO.md)** (2025-11-28)
  - Sistema de importação praças ANTT
  - Geocoding e validação
  - API endpoints

- **[INTEGRACAO_PRACAS_PEDAGIO.md](INTEGRACAO_PRACAS_PEDAGIO.md)** (2025-11-28)
  - Integração frontend/backend
  - Visualização em mapa
  - Filtros e paginação

---

## 📖 Documentação por Categoria

### 🗺️ Migrações de Mapas
Documentação de migrações do Google Maps → OSRM/Leaflet

📁 **[docs/migrations/](docs/migrations/)**
- `ROUTING_MIGRATION.md` - Migração geral de routing
- `MIGRACAO_ITINERARIO_MAPSERVICE.md` - Migração página itinerário
- `MIGRACAO_ROTAS_PADRAO_MAPSERVICE.md` - Migração rotas padrão

### 🔐 SemParar API (Fases de Implementação)
Documentação histórica das fases de implementação da API SemParar

📁 **[docs/semparar-phases/](docs/semparar-phases/)**
- `CHECKPOINT_FASE_1A.md` - SOAP Core (autenticação, status veículo)
- `SEMPARAR_FASE1B_COMPLETO.md` - Roteirização de praças
- `SEMPARAR_IMPLEMENTATION_ROADMAP.md` - Roadmap completo

### 🛡️ Segurança & Auditorias
- **[SECURITY_AUDIT_TRANSPORTES.md](SECURITY_AUDIT_TRANSPORTES.md)** (2025-10-01)
  - Auditoria de segurança módulo transportes
  - Vulnerabilidades identificadas
  - Fixes implementados

- **[SECURITY_FIXES_SUMMARY.md](SECURITY_FIXES_SUMMARY.md)** (2025-10-01)
  - Resumo de correções de segurança

### 🗄️ Progress Database
- **[PROGRESS_INTEGRATIONS.md](PROGRESS_INTEGRATIONS.md)** (2025-10-02)
  - Integrações com Progress OpenEdge
  - JDBC patterns
  - Query examples

- **[docs/PROGRESS_DATABASE_SCHEMA.md](docs/PROGRESS_DATABASE_SCHEMA.md)**
  - Esquema do banco Progress
  - Tabelas principais
  - Relacionamentos

- **[docs/PROGRESS_ELOQUENT_MODELS.md](docs/PROGRESS_ELOQUENT_MODELS.md)**
  - Modelos Eloquent (Laravel tables only)
  - **Importante:** Progress usa JDBC direto, não Eloquent

### 📄 Documentação de Módulos

#### Vale Pedágio
- **[GUIA_LOGS_VALE_PEDAGIO.md](GUIA_LOGS_VALE_PEDAGIO.md)** (2025-11-06)
  - Como analisar logs de vale pedágio
  - Troubleshooting comum

- **[SOLUCAO_IMPRESSORA_TRANSP4.md](SOLUCAO_IMPRESSORA_TRANSP4.md)** (2025-11-06)
  - Configuração impressora Python Flask
  - Integração com sistema

#### Compra de Viagem
- **[IMPLEMENTACAO_COMPLETA.md](IMPLEMENTACAO_COMPLETA.md)** (2025-10-24)
  - Implementação completa do módulo
  - Fluxo de compra
  - Integração SemParar

### 🎨 APIs Externas
- **[NDD-SOAP-API-Documentation.md](NDD-SOAP-API-Documentation.md)** (2025-09-16)
  - Documentação SOAP APIs
  - Endpoints e exemplos

---

## 📦 Arquivo Histórico

Documentação antiga preservada para referência histórica.

📁 **[docs/archive/](docs/archive/)**

### Análises Antigas
- `ANALISE_COMPRA_VIAGEM_PROGRESS.md` - Análise inicial compra viagem
- `COMPRA_VIAGEM_ANALISE.md` - Análise fluxo de compra
- `ANALISE_ROTAS_SEMPARAR.md` - Análise rotas SemParar
- `DEBUG_MAPA_ROTAS.md` - Debug sistema de mapas
- `UX_ANALYSIS_COMPRA_VIAGEM.md` - Análise UX compra viagem
- `ANALISE_PROBLEMAS_UI.md` - Problemas UI identificados

### Testes e Experimentos
- `GPS_CACHE_TEST_RESULTS.md` - Resultados testes cache GPS
- `COMPRA_VIAGEM_ERROS.md` - Log de erros encontrados
- `KEYSET_PAGINATION_IMPLEMENTATION.md` - Implementação paginação

### Diversos
- `email-aprovacao-mvp.md` - Email aprovação MVP
- `COMO_TESTAR.md` - Guia de testes (obsoleto)

---

## 🔍 Como Usar Este Índice

### Por Tipo de Tarefa

**Desenvolvendo Nova Feature:**
1. Leia `CLAUDE.md` para entender arquitetura
2. Verifique documentação do módulo específico
3. Consulte `PROGRESS_INTEGRATIONS.md` se precisar acessar banco Progress

**Resolvendo Bug:**
1. Verifique documentação recente (seção "Implementações Recentes")
2. Consulte `CLAUDE.md` seção "Troubleshooting"
3. Procure na pasta `docs/archive/` por análises de problemas similares

**Otimizando Performance:**
1. Leia `CACHE_OPTIMIZATION_AND_BUG_FIXES.md`
2. Consulte `MAP_SERVICE_FASE1_COMPLETO.md` para patterns de cache
3. Veja `PLANO_MAP_SERVICE_UNIFICADO.md` para estratégias

**Trabalhando com SemParar API:**
1. Veja `docs/semparar-phases/` para histórico de implementação
2. Consulte `IMPLEMENTACAO_COMPLETA.md` para fluxo completo
3. Verifique `NDD-SOAP-API-Documentation.md` para referência SOAP

**Migrando Sistema de Mapas:**
1. Leia `docs/migrations/ROUTING_MIGRATION.md`
2. Veja exemplos específicos nas outras migrações
3. Consulte `MAP_SERVICE_FASE1_COMPLETO.md` para patterns

---

## 📊 Estatísticas da Documentação

- **Total de documentos:** 30+
- **Documentação ativa:** 15 arquivos
- **Documentação arquivada:** 11 arquivos
- **Última atualização:** 2025-11-28
- **Cobertura:** Backend (PHP), Frontend (Vue/TS), Infraestrutura, APIs

---

## 🤝 Contribuindo

Ao criar nova documentação:

1. **Nome do arquivo:** Use padrão `NOME_DESCRITIVO.md`
2. **Localização:**
   - Raiz: Documentação principal/recente
   - `docs/migrations/`: Migrações de sistema
   - `docs/semparar-phases/`: Fases SemParar
   - `docs/archive/`: Documentação antiga preservada

3. **Formato:** Siga template similar aos existentes
4. **Atualização:** Atualize este índice ao adicionar novos docs

---

## 🔗 Links Úteis

- **GitHub:** https://github.com/Psykhepathos/ndd-vuexy
- **Laravel Docs:** https://laravel.com/docs/11.x
- **Vue.js Docs:** https://vuejs.org/
- **Vuexy Template:** https://demos.pixinvent.com/vuexy-vuejs-admin-template/
- **Progress OpenEdge:** https://docs.progress.com/

---

*Última atualização: 2025-11-28*
*Mantido por: Claude Code*
