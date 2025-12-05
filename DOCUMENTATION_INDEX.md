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
- **[docs/CACHE_OPTIMIZATION_AND_BUG_FIXES.md](docs/CACHE_OPTIMIZATION_AND_BUG_FIXES.md)** (2025-11-28)
  - Sistema de cache de rotas
  - Algoritmo Douglas-Peucker
  - Otimização de performance (80-85% melhoria)
  - Correção erro 808 SemParar
  - Bug fixes compra de viagem

### MapService Unificado
- **[docs/MAP_SERVICE_FASE1_COMPLETO.md](docs/MAP_SERVICE_FASE1_COMPLETO.md)** (2025-11-19)
  - Implementação MapService unificado
  - OSRM routing gratuito
  - Cache de 30 dias
  - Fallback inteligente

- **[docs/PLANO_MAP_SERVICE_UNIFICADO.md](docs/PLANO_MAP_SERVICE_UNIFICADO.md)** (2025-11-19)
  - Arquitetura do MapService
  - CacheManager design
  - Estratégias de otimização

### Praças de Pedágio
- **[docs/PLANO_IMPORTACAO_PRACAS_PEDAGIO.md](docs/PLANO_IMPORTACAO_PRACAS_PEDAGIO.md)** (2025-11-28)
  - Sistema de importação praças ANTT
  - Geocoding e validação
  - API endpoints

- **[docs/INTEGRACAO_PRACAS_PEDAGIO.md](docs/INTEGRACAO_PRACAS_PEDAGIO.md)** (2025-11-28)
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

### 🚛 Integrações NDD Cargo
Documentação completa da integração com NDD Cargo API (Roteirizador + Vale Pedágio)

📁 **[docs/integracoes/ndd-cargo/](docs/integracoes/ndd-cargo/)**
- **[INDEX.md](docs/integracoes/ndd-cargo/INDEX.md)** - Índice completo da documentação (COMECE AQUI!)
- **[README.md](docs/integracoes/ndd-cargo/README.md)** - Visão geral da integração NDD Cargo
  - Arquitetura completa (Protocolo CrossTalk)
  - Fluxos de integração (síncrono/assíncrono)
  - Credenciais e configuração (homologação/produção)
  - Guia de implementação no ndd-vuexy
- **[ANALISE_NTESTE_PY.md](docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md)** - Análise extremamente detalhada (1.000+ linhas)
  - Análise linha a linha do script Python de envio
  - Processo completo de assinatura digital RSA-SHA1
  - Construção de XML de negócio (consultarRoteirizador)
  - Encapsulamento SOAP (CrossTalk Message)
  - Problemas de segurança identificados e soluções
- **[ANALISE_RESULTADO_PY.md](docs/integracoes/ndd-cargo/ANALISE_RESULTADO_PY.md)** - Script de consulta assíncrona
  - Diferenças entre envio e consulta
  - ExchangePattern 8 (consulta assíncrona)
  - Processamento de resposta
  - Bugs identificados e código melhorado

**⚠️ Importante:** NDD Cargo ≠ SemParar (sistemas diferentes)
- **SemParar:** Vale pedágio eletrônico (já implementado)
- **NDD Cargo:** Roteirizador completo + gestão de transporte (nova integração)

### 🛡️ Segurança & Auditorias
- **[docs/SECURITY_AUDIT_TRANSPORTES.md](docs/SECURITY_AUDIT_TRANSPORTES.md)** (2025-10-01)
  - Auditoria de segurança módulo transportes
  - Vulnerabilidades identificadas
  - Fixes implementados

- **[docs/SECURITY_FIXES_SUMMARY.md](docs/SECURITY_FIXES_SUMMARY.md)** (2025-10-01)
  - Resumo de correções de segurança

### 🗄️ Progress Database
- **[docs/PROGRESS_INTEGRATIONS.md](docs/PROGRESS_INTEGRATIONS.md)** (2025-10-02)
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

📁 **[docs/modules/](docs/modules/)**

#### Vale Pedágio
- **[docs/modules/GUIA_LOGS_VALE_PEDAGIO.md](docs/modules/GUIA_LOGS_VALE_PEDAGIO.md)** (2025-11-06)
  - Como analisar logs de vale pedágio
  - Troubleshooting comum

- **[docs/modules/SOLUCAO_IMPRESSORA_TRANSP4.md](docs/modules/SOLUCAO_IMPRESSORA_TRANSP4.md)** (2025-11-06)
  - Configuração impressora Python Flask
  - Integração com sistema

#### Compra de Viagem
- **[docs/modules/IMPLEMENTACAO_COMPLETA.md](docs/modules/IMPLEMENTACAO_COMPLETA.md)** (2025-10-24)
  - Implementação completa do módulo
  - Fluxo de compra
  - Integração SemParar

### 🎨 APIs Externas
- **[docs/NDD-SOAP-API-Documentation.md](docs/NDD-SOAP-API-Documentation.md)** (2025-09-16)
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

## 🔧 Scripts e Utilitários

📁 **[scripts/](scripts/)**

### Scripts de Teste
- `test-custo.ps1` - Teste de cálculo de custo SemParar
- `test-fase2a-completo.ps1` - Teste completo FASE 2A (roteirização + compra)
- `test-fase2a-completo.sh` - Versão bash do teste FASE 2A
- `test-fase2b-completo.ps1` - Teste completo FASE 2B (persistência Progress)
- `test-listar-rotas-semparar.php` - Teste de listagem de rotas
- `test-mapservice-completo.ps1` - Teste completo do MapService

### Scripts Utilitários
- `extract-soap-log.php` - Extrai últimas requisições/respostas SOAP dos logs
- `diagnostico_impressora.sh` - Diagnóstico de impressora transp4 (Linux/CUPS)
- `abrir-firewall-8002.bat` - Abre porta 8002 no firewall Windows

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
1. Leia `docs/CACHE_OPTIMIZATION_AND_BUG_FIXES.md`
2. Consulte `docs/MAP_SERVICE_FASE1_COMPLETO.md` para patterns de cache
3. Veja `docs/PLANO_MAP_SERVICE_UNIFICADO.md` para estratégias

**Trabalhando com SemParar API:**
1. Veja `docs/semparar-phases/` para histórico de implementação
2. Consulte `docs/modules/IMPLEMENTACAO_COMPLETA.md` para fluxo completo
3. Verifique `docs/NDD-SOAP-API-Documentation.md` para referência SOAP

**Migrando Sistema de Mapas:**
1. Leia `docs/migrations/ROUTING_MIGRATION.md`
2. Veja exemplos específicos nas outras migrações
3. Consulte `docs/MAP_SERVICE_FASE1_COMPLETO.md` para patterns

---

## 📊 Estatísticas da Documentação

- **Total de documentos:** 34+
- **Documentação ativa:** 18 arquivos (docs/ + docs/modules/ + docs/integracoes/)
- **Integrações NDD Cargo:** 3 arquivos principais (~2.300 linhas)
- **Documentação arquivada:** 11 arquivos (docs/archive/)
- **Migrações:** 3 arquivos (docs/migrations/)
- **Fases SemParar:** 3 arquivos (docs/semparar-phases/)
- **Scripts:** 9 arquivos (scripts/)
- **Última atualização:** 2025-12-05
- **Cobertura:** Backend (PHP/Python), Frontend (Vue/TS), Infraestrutura, APIs, Integrações SOAP

---

## 🤝 Contribuindo

Ao criar nova documentação:

1. **Nome do arquivo:** Use padrão `NOME_DESCRITIVO.md`
2. **Localização:**
   - Raiz: `CLAUDE.md`, `README.md` apenas
   - `docs/`: Documentação recente e ativa (implementações, segurança, APIs)
   - `docs/integracoes/`: Documentação de integrações externas (NDD Cargo, etc.)
   - `docs/migrations/`: Migrações de sistema
   - `docs/semparar-phases/`: Fases de implementação SemParar
   - `docs/modules/`: Documentação de módulos específicos
   - `docs/archive/`: Documentação histórica preservada
   - `scripts/`: Scripts de teste e utilitários

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

*Última atualização: 2025-12-05*
*Mantido por: Claude Code*

---

## 🆕 Novidades (2025-12-05)

### Integração NDD Cargo Documentada

Adicionada documentação completa da integração com **NDD Cargo API**:

✅ **3 documentos principais** (~75 páginas, ~2.300 linhas)
✅ **Análise linha a linha** do código Python de integração
✅ **Protocolo CrossTalk** completamente documentado
✅ **Assinatura digital RSA-SHA1** explicada em detalhes
✅ **Fluxos síncrono e assíncrono** com diagramas
✅ **20+ tabelas de referência** (categorias, códigos, etc.)
✅ **Problemas de segurança identificados** e soluções propostas
✅ **Código melhorado** com boas práticas Python

**Localização:** [`docs/integracoes/ndd-cargo/`](docs/integracoes/ndd-cargo/)
**Comece por:** [`INDEX.md`](docs/integracoes/ndd-cargo/INDEX.md)
