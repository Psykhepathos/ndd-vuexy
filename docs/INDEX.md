# Indice de Documentacao - NDD Vuexy

**Ultima Atualizacao:** 2025-12-19

## Estrutura

```
docs/
├── integracoes/        # Integracoes externas (NDD Cargo, SemParar)
│   └── ndd-cargo/      # Documentacao VPO/NDD Cargo (15 arquivos)
├── audits/             # Auditorias de seguranca (9 arquivos)
├── bug-fixes/          # Correcoes documentadas (15 arquivos)
├── analysis/           # Analises tecnicas (6 arquivos)
├── summaries/          # Resumos consolidados (3 arquivos)
├── security/           # Alertas de seguranca (1 arquivo)
├── semparar-phases/    # Fases SemParar SOAP (3 arquivos)
├── migrations/         # Migracoes de sistema (3 arquivos)
├── modules/            # Modulos especificos (3 arquivos)
└── archive/            # Documentacao historica (10 arquivos)
```

**Total:** 85+ arquivos de documentacao

---

## Ambientes de Desenvolvimento

### Desenvolvimento Local

| Ambiente | URL | Comando |
|----------|-----|---------|
| **Laravel Herd** (Recomendado) | `http://valepedagio.test` | Configurar no Herd |
| **Artisan Serve** | `http://localhost:8002` | `php artisan serve --port=8002` |

### Servidor de Producao

| Ambiente | URL | Path |
|----------|-----|------|
| **Linux** | `http://192.168.19.34/ndd-vuexy/public` | `/var/www/html/ndd-vuexy` |

**Login Padrao:** admin@ndd.com / Admin@123

---

## Documentos Essenciais

### Leia Primeiro
1. **[CLAUDE.md](../CLAUDE.md)** - Guia completo de desenvolvimento
2. **[README.md](../README.md)** - Visao geral do projeto
3. **[integracoes/ndd-cargo/INDEX.md](integracoes/ndd-cargo/INDEX.md)** - Documentacao VPO

---

## Por Categoria

### Integracoes Externas (`integracoes/`)

#### NDD Cargo / VPO (15 arquivos)
- [INDEX.md](integracoes/ndd-cargo/INDEX.md) - Indice da integracao
- [README.md](integracoes/ndd-cargo/README.md) - Visao geral
- [API_REFERENCE.md](integracoes/ndd-cargo/API_REFERENCE.md) - Referencia de API
- [IMPLEMENTACAO_BACKEND.md](integracoes/ndd-cargo/IMPLEMENTACAO_BACKEND.md) - Backend
- [VPO_EMISSAO_WIZARD.md](integracoes/ndd-cargo/VPO_EMISSAO_WIZARD.md) - Wizard emissao
- [VPO_DATA_SYNC.md](integracoes/ndd-cargo/VPO_DATA_SYNC.md) - Sincronizacao
- [VPO_FRONTEND_GUIDE.md](integracoes/ndd-cargo/VPO_FRONTEND_GUIDE.md) - Frontend
- [TABELA_MAPEAMENTO_VPO.md](integracoes/ndd-cargo/TABELA_MAPEAMENTO_VPO.md) - Mapeamento campos
- [MAPEAMENTO_VPO_PROGRESS.md](integracoes/ndd-cargo/MAPEAMENTO_VPO_PROGRESS.md) - Progress
- [BUSINESS_LOGIC.md](integracoes/ndd-cargo/BUSINESS_LOGIC.md) - Logica de negocio
- [MODELO_EMISSAO_VPO.md](integracoes/ndd-cargo/MODELO_EMISSAO_VPO.md) - Modelo emissao
- [VPO_VALIDACAO_IMPLEMENTADA.md](integracoes/ndd-cargo/VPO_VALIDACAO_IMPLEMENTADA.md) - Validacoes
- [VPO_PROBLEMAS_ENCONTRADOS.md](integracoes/ndd-cargo/VPO_PROBLEMAS_ENCONTRADOS.md) - Problemas

### Auditorias de Seguranca (`audits/`)

- [AUDITORIA_NDD_CARGO_2025-12-05.md](audits/AUDITORIA_NDD_CARGO_2025-12-05.md) - NDD Cargo
- [AUDITORIA_AUTH_CONTROLLER.md](audits/AUDITORIA_AUTH_CONTROLLER.md) - Auth
- [AUDITORIA_COMPRAVIAGEM_CONTROLLER_2025-12-04.md](audits/AUDITORIA_COMPRAVIAGEM_CONTROLLER_2025-12-04.md) - CompraViagem
- [AUDITORIA_SEMPARAR_CONTROLLER_2025-12-04.md](audits/AUDITORIA_SEMPARAR_CONTROLLER_2025-12-04.md) - SemParar
- [AUDITORIA_PACOTE_CONTROLLER_2025-12-04.md](audits/AUDITORIA_PACOTE_CONTROLLER_2025-12-04.md) - Pacote
- [AUDITORIA_PROGRESS_CONTROLLER.md](audits/AUDITORIA_PROGRESS_CONTROLLER.md) - Progress
- [AUDITORIA_ENCODING_2025-12-04.md](audits/AUDITORIA_ENCODING_2025-12-04.md) - Encoding

### Correcoes de Bugs (`bug-fixes/`)

#### Criticos
- [CORRECOES_SQL_INJECTION_2025-12-04.md](bug-fixes/CORRECOES_SQL_INJECTION_2025-12-04.md)
- [CORRECOES_BUGS_CRITICOS_FINAIS_2025-12-04.md](bug-fixes/CORRECOES_BUGS_CRITICOS_FINAIS_2025-12-04.md)
- [CORRECOES_NDD_CARGO_URGENTES.md](bug-fixes/CORRECOES_NDD_CARGO_URGENTES.md)

#### Seguranca
- [CORRECOES_SEGURANCA_2025-12-04.md](bug-fixes/CORRECOES_SEGURANCA_2025-12-04.md)
- [CORRECOES_AUTH_2025-12-04.md](bug-fixes/CORRECOES_AUTH_2025-12-04.md)
- [CORRECOES_LGPD_LOGGING_2025-12-04.md](bug-fixes/CORRECOES_LGPD_LOGGING_2025-12-04.md)

#### Por Controller
- [CORRECOES_COMPRAVIAGEM_CONTROLLER_2025-12-04.md](bug-fixes/CORRECOES_COMPRAVIAGEM_CONTROLLER_2025-12-04.md)
- [CORRECOES_PACOTE_CONTROLLER_2025-12-04.md](bug-fixes/CORRECOES_PACOTE_CONTROLLER_2025-12-04.md)
- [CORRECOES_TRANSPORTE_CONTROLLER_2025-12-04.md](bug-fixes/CORRECOES_TRANSPORTE_CONTROLLER_2025-12-04.md)
- [CORRECOES_MAP_CONTROLLERS_2025-12-04.md](bug-fixes/CORRECOES_MAP_CONTROLLERS_2025-12-04.md)

### Fases SemParar (`semparar-phases/`)

- [CHECKPOINT_FASE_1A.md](semparar-phases/CHECKPOINT_FASE_1A.md) - Core SOAP
- [SEMPARAR_FASE1B_COMPLETO.md](semparar-phases/SEMPARAR_FASE1B_COMPLETO.md) - Routing
- [SEMPARAR_IMPLEMENTATION_ROADMAP.md](semparar-phases/SEMPARAR_IMPLEMENTATION_ROADMAP.md) - Roadmap

### Resumos (`summaries/`)

- [RESUMO_CONSOLIDADO_FINAL_2025-12-05.md](summaries/RESUMO_CONSOLIDADO_FINAL_2025-12-05.md) - 93 bugs corrigidos
- [RESUMO_FINAL_CORRECOES_2025-12-04.md](summaries/RESUMO_FINAL_CORRECOES_2025-12-04.md)
- [PROGRESSO_CORRECOES_BUGS_2025-12-04.md](summaries/PROGRESSO_CORRECOES_BUGS_2025-12-04.md)

### Outros

- [MAP_SERVICE_FASE1_COMPLETO.md](MAP_SERVICE_FASE1_COMPLETO.md) - MapService
- [CACHE_OPTIMIZATION_AND_BUG_FIXES.md](CACHE_OPTIMIZATION_AND_BUG_FIXES.md) - Cache
- [PROGRESS_INTEGRATIONS.md](PROGRESS_INTEGRATIONS.md) - Progress DB
- [INTEGRACAO_PRACAS_PEDAGIO.md](INTEGRACAO_PRACAS_PEDAGIO.md) - Pracas

---

## Sistema de Autenticacao (RBAC)

### Funcionalidades Implementadas

| Funcionalidade | Status | Descricao |
|---------------|--------|-----------|
| Login/Logout | OK | Sanctum + CASL |
| Usuarios CRUD | OK | Criar, editar, excluir |
| Perfis/Roles | OK | Gerenciar perfis de acesso |
| Permissoes | OK | Granular por action/subject |
| Email Boas-vindas | OK | Enviado ao criar usuario |
| Reset de Senha | OK | Admin pode resetar, envia email |
| Configurar Senha | OK | Pagina publica via token |
| Audit Logs | OK | Historico de acoes por usuario |

### Templates de Email

| Template | Arquivo | Descricao |
|----------|---------|-----------|
| Boas-vindas | `welcome.blade.php` | Novo usuario criado |
| Reset Senha | `password-reset.blade.php` | Senha resetada pelo admin |

---

## Estatisticas

| Categoria | Bugs Corrigidos |
|-----------|-----------------|
| Criticos | 25 |
| Importantes | 37 |
| Moderados | 31 |
| **Total** | **93** |

### Controllers Auditados
- AuthController
- UserController
- RoleController
- TransporteController
- PacoteController
- SemPararController
- SemPararRotaController
- CompraViagemController
- VpoController
- VpoEmissaoController
- NddCargoController
- GeocodingController
- RoutingController
- MapController
- PracaPedagioController

### Estatisticas do Projeto

| Metrica | Valor |
|---------|-------|
| Controllers | 26 |
| Services | 29 |
| API Endpoints | 70+ |
| Tabelas Progress | 21 |
| Tabelas Laravel | 15 |
| Transportadores | 6.913+ |
| Pacotes | 800.000+ |

---

## Links Rapidos

- **Quick Start:** [CLAUDE.md](../CLAUDE.md) > Ambientes
- **Arquitetura:** [CLAUDE.md](../CLAUDE.md) > Arquitetura
- **VPO/NDD Cargo:** [integracoes/ndd-cargo/INDEX.md](integracoes/ndd-cargo/INDEX.md)
- **Troubleshooting:** [CLAUDE.md](../CLAUDE.md) > Troubleshooting
- **RBAC:** [CLAUDE.md](../CLAUDE.md) > Sistema de Autenticacao e RBAC

---

**Mantido por:** Psykhepathos
